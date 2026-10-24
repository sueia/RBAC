<?php
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/class/PermissionChecker.php';
    require_once __DIR__ . '/class/UserSession.php';
    require_once __DIR__ . '/class/Sanitize.php';
    require_once __DIR__ . '/class/Log.php';

    $action = $_GET['action'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (in_array($action, ['destroy','destroy_user','clean_expired'], true)) {
            PermissionChecker::require('DELETE', 'SESS');
        }
    } else {
        PermissionChecker::require('LIST', 'SESS');
    }

    $canDelete = PermissionChecker::can('DELETE', 'SESS');

    $sessionRepo = new UserSession();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if ($action === 'destroy' && !empty($_POST['session_id'])) {
                $sessionRepo->destroy($_POST['session_id']);
                Log::write('DELETE', 'USER_SESSIONS', null, null, ['session_id' => $_POST['session_id']]);
                header('Location: session.php?success=' . urlencode('Sessão terminada.'));
                exit;
            }
            if ($action === 'destroy_user' && !empty($_POST['user_id'])) {
                $sessionRepo->destroyAllForUser((int)$_POST['user_id']);
                Log::write('DELETE', 'USER_SESSIONS', null, null, ['user_id' => (int)$_POST['user_id']]);
                header('Location: session.php?success=' . urlencode('Sessões do utilizador terminadas.'));
                exit;
            }
            if ($action === 'clean_expired') {
                $count = $sessionRepo->cleanExpired();
                Log::write('DELETE', 'USER_SESSIONS', null, null, ['event' => 'clean_expired', 'total' => $count]);
                header('Location: session.php?success=' . urlencode("Sessões expiradas removidas: {$count}."));
                exit;
            }
        } catch (Throwable $e) {
            Log::error('session.php POST', 'USER_SESSIONS', $e, ['action' => $action], $_SESSION['user_id'] ?? null);
            header('Location: session.php?error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    try {
        $filtroStatus = Sanitize::enum($_GET['f_status'] ?? '', ['ACTIVE','EXPIRED']);
        $filtroUser   = Sanitize::string($_GET['f_user'] ?? '', 50);
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;

        $lista      = $sessionRepo->search($filtroStatus ?: null, $filtroUser ?: null);
        $total      = count($lista);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * $perPage;
        $pagina     = array_slice($lista, $offset, $perPage);

        $statsTotal   = $sessionRepo->countAll();
        $statsActive  = $sessionRepo->countActive();
        $statsExpired = $sessionRepo->countExpired();
        $statsUsers   = $sessionRepo->countUniqueUsers();
    } catch (Throwable $e) {
        Log::error('session.php listagem', 'USER_SESSIONS', $e, null, $_SESSION['user_id'] ?? null);
        $lista = $pagina = [];
        $total = 0; $totalPages = 1; $page = 1; $offset = 0;
        $statsTotal = $statsActive = $statsExpired = $statsUsers = 0;
        $filtroStatus = null; $filtroUser = '';
    }

    function build_url(array $overrides = []): string {
        $q = array_merge($_GET, $overrides);
        $q = array_filter($q, fn($v) => $v !== '' && $v !== null);
        return 'session.php' . (empty($q) ? '' : '?' . http_build_query($q));
    }

    $pageTitle = 'Sessões';
    include __DIR__ . '/include/navbar.php';
?>

        <main class="main-content">
            <div class="container-fluid">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                    <div><h1 class="page-title mb-0">Gestão de Sessões</h1><p class="text-muted mt-1 mb-0">Controlo de sessões ativas</p></div>
                    <?php if ($canDelete): ?>
                    <form method="POST" action="session.php?action=clean_expired" onsubmit="return confirm('Remover todas as sessões expiradas?')">
                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-eraser me-1"></i> Limpar Expiradas</button>
                    </form>
                    <?php endif; ?>
                </div>

                <?php if (!empty($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= e($_GET['success']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if (!empty($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= e($_GET['error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <div class="row g-4 mb-4">
                    <div class="col-xl-3 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Total</h6><h2 class="card-value"><?= $statsTotal ?></h2></div><div class="card-icon total"><i class="bi bi-shield-check"></i></div></div></div></div>
                    <div class="col-xl-3 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Ativas</h6><h2 class="card-value text-success"><?= $statsActive ?></h2></div><div class="card-icon ativos"><i class="bi bi-check-circle"></i></div></div></div></div>
                    <div class="col-xl-3 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Expiradas</h6><h2 class="card-value"><?= $statsExpired ?></h2></div><div class="card-icon inativos"><i class="bi bi-clock-history"></i></div></div></div></div>
                    <div class="col-xl-3 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Utilizadores</h6><h2 class="card-value"><?= $statsUsers ?></h2></div><div class="card-icon extra"><i class="bi bi-people"></i></div></div></div></div>
                </div>

                <div class="filtros-container mb-4">
                    <h6 class="filtros-titulo"><i class="bi bi-funnel"></i> Filtrar Sessões</h6>
                    <form method="GET" action="session.php">
                        <div class="row g-3">
                            <div class="col-lg-4 col-md-6">
                                <label class="form-label-sm">Utilizador</label>
                                <div class="input-group">
                                    <span class="input-group-text" style="background:var(--bg-soft);border-color:var(--border);color:var(--text-muted);"><i class="bi bi-person"></i></span>
                                    <input type="text" name="f_user" class="form-control" placeholder="Username..." value="<?= e($filtroUser) ?>">
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <label class="form-label-sm">Estado</label>
                                <select name="f_status" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="ACTIVE"  <?= $filtroStatus === 'ACTIVE'  ? 'selected' : '' ?>>Ativas</option>
                                    <option value="EXPIRED" <?= $filtroStatus === 'EXPIRED' ? 'selected' : '' ?>>Expiradas</option>
                                </select>
                            </div>
                            <div class="col-lg-5 col-md-12 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-verde flex-fill"><i class="bi bi-funnel me-1"></i> Filtrar</button>
                                <a href="session.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i> Limpar</a>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="card card-principal">
                    <div class="card-header-custom">
                        <h5 class="card-header-titulo"><i class="bi bi-shield-check"></i> Sessões</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-users mb-0">
                                <thead>
                                    <tr><th>Utilizador</th><th>Perfil</th><th>IP</th><th>Criada</th><th>Expira</th><th>Estado</th><th class="text-end" style="width:180px;">Ações</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pagina)): ?>
                                        <tr><td colspan="7" class="text-center py-4 text-muted">Nenhuma sessão encontrada.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($pagina as $s):
                                            $isActive = $s['SESSION_STATE'] === 'ACTIVE';
                                            $sid = $s['SESSION_ID'];
                                            $uid = (int)$s['SESSION_USERID'];
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="user-avatar-sm <?= $isActive ? '' : 'inactive' ?>"><?= e(initials($s['USER_FULLNAME'] ?: $s['USER_USERNAME'])) ?></div>
                                                    <div class="user-cell-info">
                                                        <strong><?= e($s['USER_FULLNAME'] ?: $s['USER_USERNAME']) ?></strong>
                                                        <small>@<?= e($s['USER_USERNAME']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge-perfil"><i class="bi bi-shield-lock"></i> <?= e($s['PROFILE_CODE']) ?></span></td>
                                            <td><small class="text-muted"><?= e($s['SESSION_IPADDRESS'] ?: '—') ?></small></td>
                                            <td><small><?= e(fmt_datetime($s['SESSION_CREATEAT'])) ?></small></td>
                                            <td><small><?= e(fmt_datetime($s['SESSION_EXPIRESAT'])) ?></small></td>
                                            <td><span class="status-badge <?= $isActive ? 'ativo' : 'inativo' ?>"><?= $isActive ? 'Ativa' : 'Expirada' ?></span></td>
                                            <td>
                                                <div class="action-group">
                                                    <?php if ($canDelete): ?>
                                                    <form method="POST" action="session.php?action=destroy" class="d-inline" onsubmit="return confirm('Terminar esta sessão?')">
                                                        <input type="hidden" name="session_id" value="<?= e($sid) ?>">
                                                        <button type="submit" class="btn-action-tbl apagar" title="Terminar"><i class="bi bi-x-circle"></i></button>
                                                    </form>
                                                    <form method="POST" action="session.php?action=destroy_user" class="d-inline" onsubmit="return confirm('Terminar todas as sessões deste utilizador?')">
                                                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                                                        <button type="submit" class="btn-action-tbl senha" title="Todas do utilizador"><i class="bi bi-person-x"></i></button>
                                                    </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer-custom">
                        <div class="paginacao-info"><?= $total === 0 ? 'Sem registos' : "A mostrar " . ($offset + 1) . "–" . min($offset + $perPage, $total) . " de {$total}" ?></div>
                        <?php if ($totalPages > 1): ?>
                        <nav><ul class="pagination mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(build_url(['page' => $page - 1])) ?>"><i class="bi bi-chevron-left"></i></a></li>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(build_url(['page' => $i])) ?>"><?= $i ?></a></li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(build_url(['page' => $page + 1])) ?>"><i class="bi bi-chevron-right"></i></a></li>
                        </ul></nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>

<?php include __DIR__ . '/include/footer.php'; ?>