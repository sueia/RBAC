<?php
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/class/PermissionChecker.php';
    require_once __DIR__ . '/class/User.php';
    require_once __DIR__ . '/class/Profile.php';
    require_once __DIR__ . '/class/Sanitize.php';
    require_once __DIR__ . '/class/Log.php';

    $action = $_GET['action'] ?? '';
    $id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'create')         PermissionChecker::require('CREATE', 'USR');
        if ($action === 'update')         PermissionChecker::require('UPDATE', 'USR');
        if ($action === 'reset_password') PermissionChecker::require('UPDATE', 'USR');
        if ($action === 'toggle_status')  PermissionChecker::require('UPDATE', 'USR');
        if ($action === 'delete')         PermissionChecker::require('DELETE', 'USR');
    } else {
        PermissionChecker::require('LIST', 'USR');
    }

    $canCreate = PermissionChecker::can('CREATE', 'USR');
    $canUpdate = PermissionChecker::can('UPDATE', 'USR');
    $canDelete = PermissionChecker::can('DELETE', 'USR');

    $userRepo    = new User();
    $profileRepo = new Profile();
    $profiles    = $profileRepo->all();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if ($action === 'create') {
                $newId = $userRepo->create([
                    'username'   => $_POST['username']   ?? '',
                    'fullname'   => $_POST['fullname']   ?? '',
                    'email'      => $_POST['email']      ?? '',
                    'profile_id' => $_POST['profile_id'] ?? 0,
                    'password'   => $_POST['password']   ?? '',
                    'status'     => $_POST['status']     ?? 'ACTIVE',
                ], $_SESSION['user_id'] ?? null);
                Log::write('INSERT', 'USERS', (int)$newId, null, ['username' => $_POST['username'] ?? '']);
                header('Location: user.php?success=' . urlencode('Utilizador criado.'));
                exit;
            }
            if ($action === 'update' && $id) {
                $userRepo->update($id, [
                    'fullname'   => $_POST['fullname']   ?? '',
                    'email'      => $_POST['email']      ?? '',
                    'profile_id' => $_POST['profile_id'] ?? 0,
                    'status'     => $_POST['status']     ?? 'ACTIVE',
                ], $_SESSION['user_id'] ?? null);
                Log::write('UPDATE', 'USERS', $id, null, ['username' => $_POST['username'] ?? '']);
                header('Location: user.php?success=' . urlencode('Utilizador atualizado.'));
                exit;
            }
            if ($action === 'reset_password' && $id) {
                $userRepo->updatePassword($id, $_POST['new_password'] ?? '', $_SESSION['user_id'] ?? null);
                Log::write('UPDATE', 'USERS', $id, null, ['event' => 'password_reset']);
                header('Location: user.php?success=' . urlencode('Senha redefinida.'));
                exit;
            }
            if ($action === 'toggle_status' && $id) {
                $row = $userRepo->findById($id);
                if (!$row) throw new RuntimeException("Utilizador não encontrado.");
                $new = $row['USER_STATUS'] === 'ACTIVE' ? 'BLOCKED' : 'ACTIVE';
                $userRepo->setStatus($id, $new, $_SESSION['user_id'] ?? null);
                Log::write('UPDATE', 'USERS', $id, null, ['status' => $new]);
                header('Location: user.php?success=' . urlencode('Estado alterado.'));
                exit;
            }
            if ($action === 'delete' && $id) {
                $userRepo->delete($id);
                Log::write('DELETE', 'USERS', $id);
                header('Location: user.php?success=' . urlencode('Utilizador eliminado.'));
                exit;
            }
        } catch (Throwable $e) {
            Log::error('user.php POST', 'USERS', $e, ['action' => $action, 'id' => $id], $_SESSION['user_id'] ?? null);
            header('Location: user.php?error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    try {
        $filtroTexto  = Sanitize::string($_GET['f_name'] ?? '', 100);
        $filtroPerfil = Sanitize::code($_GET['f_profile'] ?? '', 10);
        $filtroStatus = Sanitize::enum($_GET['f_status'] ?? '', ['ACTIVE','BLOCKED','INACTIVE']);
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 8;

        $lista      = $userRepo->search($filtroTexto, $filtroPerfil ?: null, $filtroStatus ?: null);
        $total      = count($lista);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * $perPage;
        $pagina     = array_slice($lista, $offset, $perPage);

        $statsTotal    = $userRepo->countAll();
        $statsActive   = $userRepo->countActive();
        $statsBlocked  = $userRepo->countBlocked();
        $statsInactive = $userRepo->countInactive();
    } catch (Throwable $e) {
        Log::error('user.php listagem', 'USERS', $e, null, $_SESSION['user_id'] ?? null);
        $lista = $pagina = [];
        $total = 0; $totalPages = 1; $page = 1; $offset = 0;
        $statsTotal = $statsActive = $statsBlocked = $statsInactive = 0;
        $filtroTexto = ''; $filtroPerfil = ''; $filtroStatus = null;
    }

    function build_url(array $overrides = []): string {
        $q = array_merge($_GET, $overrides);
        $q = array_filter($q, fn($v) => $v !== '' && $v !== null);
        return 'user.php' . (empty($q) ? '' : '?' . http_build_query($q));
    }

    $pageTitle = 'Utilizadores';
    include __DIR__ . '/include/navbar.php';
?>

        <main class="main-content">
            <div class="container-fluid">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                    <div><h1 class="page-title mb-0">Gestão de Utilizadores</h1><p class="text-muted mt-1 mb-0">Administração das contas de acesso</p></div>
                </div>

                <?php if (!empty($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= e($_GET['success']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if (!empty($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= e($_GET['error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <div class="row g-4 mb-4">
                    <div class="col-xl-3 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Total</h6><h2 class="card-value"><?= $statsTotal ?></h2></div><div class="card-icon total"><i class="bi bi-people"></i></div></div></div></div>
                    <div class="col-xl-3 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Ativos</h6><h2 class="card-value text-success"><?= $statsActive ?></h2></div><div class="card-icon ativos"><i class="bi bi-check-circle"></i></div></div></div></div>
                    <div class="col-xl-3 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Bloqueados</h6><h2 class="card-value text-danger"><?= $statsBlocked ?></h2></div><div class="card-icon bloq"><i class="bi bi-lock"></i></div></div></div></div>
                    <div class="col-xl-3 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Inativos</h6><h2 class="card-value"><?= $statsInactive ?></h2></div><div class="card-icon inativos"><i class="bi bi-pause-circle"></i></div></div></div></div>
                </div>

                <div class="filtros-container mb-4">
                    <h6 class="filtros-titulo"><i class="bi bi-funnel"></i> Filtrar Utilizadores</h6>
                    <form method="GET" action="user.php">
                        <div class="row g-3">
                            <div class="col-lg-4 col-md-6">
                                <label class="form-label-sm">Pesquisar</label>
                                <div class="input-group">
                                    <span class="input-group-text" style="background:var(--bg-soft);border-color:var(--border);color:var(--text-muted);"><i class="bi bi-search"></i></span>
                                    <input type="text" name="f_name" class="form-control" placeholder="Username, nome ou email..." value="<?= e($filtroTexto) ?>">
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <label class="form-label-sm">Perfil</label>
                                <select name="f_profile" class="form-select">
                                    <option value="">Todos</option>
                                    <?php foreach ($profiles as $p): ?>
                                        <option value="<?= e($p['PROFILE_CODE']) ?>" <?= $filtroPerfil === $p['PROFILE_CODE'] ? 'selected' : '' ?>>
                                            <?= e($p['PROFILE_CODE']) ?> — <?= e($p['PROFILE_NAME']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-6">
                                <label class="form-label-sm">Status</label>
                                <select name="f_status" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="ACTIVE"   <?= $filtroStatus === 'ACTIVE'   ? 'selected' : '' ?>>Ativos</option>
                                    <option value="BLOCKED"  <?= $filtroStatus === 'BLOCKED'  ? 'selected' : '' ?>>Bloqueados</option>
                                    <option value="INACTIVE" <?= $filtroStatus === 'INACTIVE' ? 'selected' : '' ?>>Inativos</option>
                                </select>
                            </div>
                            <div class="col-lg-3 col-md-6 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-verde flex-fill"><i class="bi bi-funnel me-1"></i> Filtrar</button>
                                <a href="user.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i> Limpar</a>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="card card-principal">
                    <div class="card-header-custom">
                        <h5 class="card-header-titulo"><i class="bi bi-person-circle"></i> Lista de Utilizadores</h5>
                        <?php if ($canCreate): ?>
                        <button class="btn btn-verde btn-sm" data-bs-toggle="modal" data-bs-target="#novoUserModal"><i class="bi bi-plus-circle me-1"></i> Novo Utilizador</button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-users mb-0">
                                <thead>
                                    <tr><th>Utilizador</th><th>Username</th><th>Perfil</th><th>Status</th><th>Último Login</th><th class="text-end" style="width:180px;">Ações</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pagina)): ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">Nenhum utilizador encontrado.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($pagina as $u):
                                            $uid  = (int)$u['USER_ID'];
                                            $ini  = initials($u['USER_FULLNAME'] ?: $u['USER_USERNAME']);
                                            $st   = $u['USER_STATUS'];
                                            $stCls = $st === 'ACTIVE' ? 'ativo' : ($st === 'BLOCKED' ? 'bloqueado' : 'inativo');
                                            $stLbl = $st === 'ACTIVE' ? 'Ativo' : ($st === 'BLOCKED' ? 'Bloqueado' : 'Inativo');
                                            $avCls = $st === 'BLOCKED' ? 'blocked' : ($st === 'INACTIVE' ? 'inactive' : '');
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="user-avatar-sm <?= $avCls ?>"><?= e($ini) ?></div>
                                                    <div class="user-cell-info">
                                                        <strong><?= e($u['USER_FULLNAME'] ?: $u['USER_USERNAME']) ?></strong>
                                                        <small><?= e($u['USER_EMAIL'] ?: '—') ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><code>@<?= e($u['USER_USERNAME']) ?></code></td>
                                            <td><span class="badge-perfil"><i class="bi bi-shield-lock"></i> <?= e($u['PROFILE_CODE']) ?></span></td>
                                            <td><span class="status-badge <?= $stCls ?>"><?= $stLbl ?></span></td>
                                            <td><small><?= e(fmt_datetime($u['USER_LASTLOGIN'])) ?></small></td>
                                            <td>
                                                <div class="action-group">
                                                    <button class="btn-action-tbl ver" title="Ver"
                                                            data-bs-toggle="modal" data-bs-target="#verUserModal"
                                                            data-id="<?= $uid ?>"
                                                            data-nome="<?= e($u['USER_FULLNAME'] ?: $u['USER_USERNAME']) ?>"
                                                            data-username="<?= e($u['USER_USERNAME']) ?>"
                                                            data-email="<?= e($u['USER_EMAIL']) ?>"
                                                            data-perfil="<?= e($u['PROFILE_CODE']) ?>"
                                                            data-status="<?= e($st) ?>"
                                                            data-ultimo="<?= e(fmt_datetime($u['USER_LASTLOGIN'])) ?>"
                                                            data-criado="<?= e(fmt_date($u['USER_CREATEAT'])) ?>">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <?php if ($canUpdate): ?>
                                                    <button class="btn-action-tbl editar" title="Editar"
                                                            data-bs-toggle="modal" data-bs-target="#editarUserModal"
                                                            data-id="<?= $uid ?>"
                                                            data-nome="<?= e($u['USER_FULLNAME']) ?>"
                                                            data-username="<?= e($u['USER_USERNAME']) ?>"
                                                            data-email="<?= e($u['USER_EMAIL']) ?>"
                                                            data-profileid="<?= (int)$u['USER_PROFILEID'] ?>"
                                                            data-status="<?= e($st) ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn-action-tbl senha" title="Senha"
                                                            data-bs-toggle="modal" data-bs-target="#resetSenhaModal"
                                                            data-id="<?= $uid ?>"
                                                            data-nome="<?= e($u['USER_FULLNAME'] ?: $u['USER_USERNAME']) ?>">
                                                        <i class="bi bi-key"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <?php if ($canDelete): ?>
                                                    <form method="POST" action="user.php?action=toggle_status&id=<?= $uid ?>" class="d-inline" onsubmit="return confirm('<?= $st === 'ACTIVE' ? 'Bloquear' : 'Ativar' ?> utilizador?')">
                                                        <button type="submit" class="btn-action-tbl <?= $st === 'ACTIVE' ? 'apagar' : 'editar' ?>">
                                                            <i class="bi bi-<?= $st === 'ACTIVE' ? 'lock' : 'unlock' ?>"></i>
                                                        </button>
                                                    </form>
                                                    <button class="btn-action-tbl apagar" title="Eliminar"
                                                            data-bs-toggle="modal" data-bs-target="#eliminarUserModal"
                                                            data-id="<?= $uid ?>"
                                                            data-nome="<?= e($u['USER_FULLNAME'] ?: $u['USER_USERNAME']) ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
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

        <?php if ($canCreate): ?>
        <div class="modal fade" id="novoUserModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <form method="POST" action="user.php?action=create">
                    <div class="modal-header modal-header-verde"><h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Novo Utilizador</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Nome <span class="text-danger">*</span></label><input type="text" name="fullname" class="form-control" required maxlength="150"></div>
                            <div class="col-md-6"><label class="form-label">Username <span class="text-danger">*</span></label><input type="text" name="username" class="form-control" required maxlength="50"></div>
                            <div class="col-md-6"><label class="form-label">Email <span class="text-danger">*</span></label><input type="email" name="email" class="form-control" required maxlength="100"></div>
                            <div class="col-md-6"><label class="form-label">Perfil <span class="text-danger">*</span></label>
                                <select name="profile_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($profiles as $p): ?>
                                        <option value="<?= (int)$p['PROFILE_ID'] ?>"><?= e($p['PROFILE_CODE']) ?> — <?= e($p['PROFILE_NAME']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6"><label class="form-label">Senha <span class="text-danger">*</span></label><input type="password" name="password" class="form-control" required minlength="<?= PASSWORD_MIN ?>"></div>
                            <div class="col-md-6"><label class="form-label">Status</label>
                                <select name="status" class="form-select"><option value="ACTIVE">Ativo</option><option value="BLOCKED">Bloqueado</option><option value="INACTIVE">Inativo</option></select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-verde">Criar</button></div>
                </form>
            </div></div>
        </div>
        <?php endif; ?>

        <div class="modal fade" id="verUserModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <div class="modal-header modal-header-verde"><h5 class="modal-title"><i class="bi bi-eye me-2"></i>Detalhes</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                        <div class="user-avatar-sm" id="verAvatar" style="width:56px;height:56px;font-size:1.1rem;">?</div>
                        <div><h5 class="mb-1" id="verNome">—</h5><small class="text-muted" id="verUsername">@—</small></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12"><small class="text-muted text-uppercase d-block">Email</small><div id="verEmail">—</div></div>
                        <div class="col-6"><small class="text-muted text-uppercase d-block">Perfil</small><div id="verPerfil">—</div></div>
                        <div class="col-6"><small class="text-muted text-uppercase d-block">Status</small><div id="verStatus">—</div></div>
                        <div class="col-6"><small class="text-muted text-uppercase d-block">Último Login</small><div id="verUltimo">—</div></div>
                        <div class="col-6"><small class="text-muted text-uppercase d-block">Criado</small><div id="verCriado">—</div></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button></div>
            </div></div>
        </div>

        <?php if ($canUpdate): ?>
        <div class="modal fade" id="editarUserModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <form method="POST" id="formEditarUser">
                    <div class="modal-header modal-header-verde"><h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Utilizador</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Nome</label><input type="text" name="fullname" id="editNome" class="form-control" required maxlength="150"></div>
                            <div class="col-md-6"><label class="form-label">Username</label><input type="text" id="editUsername" class="form-control" readonly></div>
                            <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" id="editEmail" class="form-control" required maxlength="100"></div>
                            <div class="col-md-6"><label class="form-label">Perfil</label>
                                <select name="profile_id" id="editPerfil" class="form-select">
                                    <?php foreach ($profiles as $p): ?>
                                        <option value="<?= (int)$p['PROFILE_ID'] ?>"><?= e($p['PROFILE_CODE']) ?> — <?= e($p['PROFILE_NAME']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12"><label class="form-label">Status</label>
                                <select name="status" id="editStatus" class="form-select"><option value="ACTIVE">Ativo</option><option value="BLOCKED">Bloqueado</option><option value="INACTIVE">Inativo</option></select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-verde">Atualizar</button></div>
                </form>
            </div></div>
        </div>
        <?php endif; ?>

        <?php if ($canUpdate): ?>
        <div class="modal fade" id="resetSenhaModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <form method="POST" id="formResetSenha">
                    <div class="modal-header modal-header-verde"><h5 class="modal-title"><i class="bi bi-key me-2"></i>Redefinir Senha</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p class="text-muted mb-3">Redefinir senha de <strong id="rsNome" class="text-body">—</strong></p>
                        <div class="mb-3"><label class="form-label">Nova Senha</label><input type="password" name="new_password" id="rsNova" class="form-control" required minlength="<?= PASSWORD_MIN ?>"></div>
                        <div class="mb-3"><label class="form-label">Confirmar</label><input type="password" id="rsConfirmar" class="form-control" required minlength="<?= PASSWORD_MIN ?>"></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-verde">Redefinir</button></div>
                </form>
            </div></div>
        </div>
        <?php endif; ?>

        <?php if ($canDelete): ?>
        <div class="modal fade" id="eliminarUserModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <form method="POST" id="formEliminarUser">
                    <div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Eliminar</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p>Eliminar o utilizador <strong id="delNome">—</strong>?</p>
                        <label class="form-label">Digite <strong>ELIMINAR</strong>:</label>
                        <input type="text" id="delConfirmacao" class="form-control" autocomplete="off">
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger" id="btnConfirmarEliminar" disabled>Eliminar</button></div>
                </form>
            </div></div>
        </div>
        <?php endif; ?>

        <script>
            document.getElementById('verUserModal').addEventListener('show.bs.modal', function (e) {
                const b = e.relatedTarget;
                const av = document.getElementById('verAvatar');
                av.textContent = (b.dataset.nome || '?').substring(0, 2).toUpperCase();
                av.className = 'user-avatar-sm' + (b.dataset.status === 'BLOCKED' ? ' blocked' : b.dataset.status === 'INACTIVE' ? ' inactive' : '');
                av.style.width = '56px'; av.style.height = '56px'; av.style.fontSize = '1.1rem';
                document.getElementById('verNome').textContent = b.dataset.nome;
                document.getElementById('verUsername').textContent = '@' + b.dataset.username;
                document.getElementById('verEmail').textContent = b.dataset.email || '—';
                document.getElementById('verPerfil').innerHTML = `<span class="badge-perfil"><i class="bi bi-shield-lock"></i> ${b.dataset.perfil}</span>`;
                const st = b.dataset.status;
                const cls = st === 'ACTIVE' ? 'ativo' : st === 'BLOCKED' ? 'bloqueado' : 'inativo';
                const lbl = st === 'ACTIVE' ? 'Ativo' : st === 'BLOCKED' ? 'Bloqueado' : 'Inativo';
                document.getElementById('verStatus').innerHTML = `<span class="status-badge ${cls}">${lbl}</span>`;
                document.getElementById('verUltimo').textContent = b.dataset.ultimo || '—';
                document.getElementById('verCriado').textContent = b.dataset.criado || '—';
            });

            <?php if ($canUpdate): ?>
            document.getElementById('editarUserModal').addEventListener('show.bs.modal', function (e) {
                const b = e.relatedTarget;
                document.getElementById('formEditarUser').action = 'user.php?action=update&id=' + b.dataset.id;
                document.getElementById('editNome').value = b.dataset.nome;
                document.getElementById('editUsername').value = b.dataset.username;
                document.getElementById('editEmail').value = b.dataset.email;
                document.getElementById('editPerfil').value = b.dataset.profileid;
                document.getElementById('editStatus').value = b.dataset.status;
            });
            document.getElementById('resetSenhaModal').addEventListener('show.bs.modal', function (e) {
                const b = e.relatedTarget;
                document.getElementById('formResetSenha').action = 'user.php?action=reset_password&id=' + b.dataset.id;
                document.getElementById('rsNome').textContent = b.dataset.nome;
                document.getElementById('rsNova').value = '';
                document.getElementById('rsConfirmar').value = '';
            });
            document.getElementById('formResetSenha').addEventListener('submit', function (e) {
                if (document.getElementById('rsNova').value !== document.getElementById('rsConfirmar').value) {
                    e.preventDefault();
                    alert('As senhas não coincidem.');
                }
            });
            <?php endif; ?>

            <?php if ($canDelete): ?>
            document.getElementById('eliminarUserModal').addEventListener('show.bs.modal', function (e) {
                const b = e.relatedTarget;
                document.getElementById('formEliminarUser').action = 'user.php?action=delete&id=' + b.dataset.id;
                document.getElementById('delNome').textContent = b.dataset.nome;
                document.getElementById('delConfirmacao').value = '';
                document.getElementById('btnConfirmarEliminar').disabled = true;
            });
            document.getElementById('delConfirmacao').addEventListener('input', function () {
                document.getElementById('btnConfirmarEliminar').disabled = this.value.trim().toUpperCase() !== 'ELIMINAR';
            });
            <?php endif; ?>
        </script>

<?php include __DIR__ . '/include/footer.php'; ?>