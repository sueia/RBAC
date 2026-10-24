<?php
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/class/PermissionChecker.php';

    PermissionChecker::require('LIST', 'LOG');

    require_once __DIR__ . '/class/Database.php';
    require_once __DIR__ . '/class/Log.php';
    require_once __DIR__ . '/class/Sanitize.php';

    $acoesPossiveis = ['INSERT','UPDATE','DELETE','LOGIN','LOGOUT','ACCESS_DENIED','ERROR'];

    try {
        $filtroAcao   = Sanitize::enum($_GET['f_action'] ?? '', $acoesPossiveis);
        $filtroTabela = Sanitize::string($_GET['f_table'] ?? '', 100);
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;

        $db = new Database();

        $sql = "SELECT L.*, U.USER_USERNAME
                FROM LOGS L
                LEFT JOIN USERS U ON L.LOG_USER_ID = U.USER_ID
                WHERE 1=1";
        $params = [];
        if ($filtroAcao) { $sql .= " AND L.LOG_ACTION = ?"; $params[] = $filtroAcao; }
        if ($filtroTabela !== '') { $sql .= " AND L.LOG_TABLE_NAME LIKE ?"; $params[] = "%{$filtroTabela}%"; }

        $sqlCount    = "SELECT COUNT(*) AS c FROM LOGS L WHERE 1=1";
        $paramsCount = [];
        if ($filtroAcao) { $sqlCount .= " AND L.LOG_ACTION = ?"; $paramsCount[] = $filtroAcao; }
        if ($filtroTabela !== '') { $sqlCount .= " AND L.LOG_TABLE_NAME LIKE ?"; $paramsCount[] = "%{$filtroTabela}%"; }

        $total = (int)(($db->query($sqlCount, $paramsCount))[0]['c'] ?? 0);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * $perPage;

        $sql .= " ORDER BY L.LOG_CREATED_AT DESC LIMIT {$perPage} OFFSET {$offset}";
        $pagina = $db->query($sql, $params);

        $statsTotal  = (int)(($db->query("SELECT COUNT(*) AS c FROM LOGS"))[0]['c'] ?? 0);
        $statsHoje   = (int)(($db->query("SELECT COUNT(*) AS c FROM LOGS WHERE DATE(LOG_CREATED_AT) = CURDATE()"))[0]['c'] ?? 0);
        $statsErros  = (int)(($db->query("SELECT COUNT(*) AS c FROM LOGS WHERE LOG_ACTION = 'ERROR'"))[0]['c'] ?? 0);
        $statsDenied = (int)(($db->query("SELECT COUNT(*) AS c FROM LOGS WHERE LOG_ACTION = 'ACCESS_DENIED'"))[0]['c'] ?? 0);
    } catch (Throwable $e) {
        Log::error('log.php', 'LOGS', $e, null, $_SESSION['user_id'] ?? null);
        $pagina = []; $total = 0; $totalPages = 1; $page = 1; $offset = 0;
        $statsTotal = $statsHoje = $statsErros = $statsDenied = 0;
        $filtroAcao = null; $filtroTabela = '';
    }

    function build_url(array $overrides = []): string {
        $q = array_merge($_GET, $overrides);
        $q = array_filter($q, fn($v) => $v !== '' && $v !== null);
        return 'log.php' . (empty($q) ? '' : '?' . http_build_query($q));
    }

    $pageTitle = 'Logs';
    include __DIR__ . '/include/navbar.php';
?>

        <main class="main-content">
            <div class="container-fluid">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                    <div><h1 class="page-title mb-0">Logs de Auditoria</h1><p class="text-muted mt-1 mb-0">Histórico de operações do sistema</p></div>
                    <span class="badge bg-light text-dark p-2 shadow-sm"><i class="bi bi-shield-check me-1"></i> Somente leitura</span>
                </div>

                <?php if (!empty($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= e($_GET['success']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if (!empty($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= e($_GET['error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <div class="row g-4 mb-4">
                    <div class="col-xl-3 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Total</h6><h2 class="card-value"><?= number_format($statsTotal, 0, ',', '.') ?></h2></div><div class="card-icon total"><i class="bi bi-journal-text"></i></div></div></div></div>
                    <div class="col-xl-3 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Hoje</h6><h2 class="card-value text-info"><?= $statsHoje ?></h2></div><div class="card-icon extra"><i class="bi bi-calendar-check"></i></div></div></div></div>
                    <div class="col-xl-3 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Erros</h6><h2 class="card-value text-danger"><?= $statsErros ?></h2></div><div class="card-icon bloq"><i class="bi bi-exclamation-triangle"></i></div></div></div></div>
                    <div class="col-xl-3 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Access Denied</h6><h2 class="card-value text-warning"><?= $statsDenied ?></h2></div><div class="card-icon inativos"><i class="bi bi-shield-x"></i></div></div></div></div>
                </div>

                <div class="filtros-container mb-4">
                    <h6 class="filtros-titulo"><i class="bi bi-funnel"></i> Filtrar Logs</h6>
                    <form method="GET" action="log.php">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label-sm">Ação</label>
                                <select name="f_action" class="form-select">
                                    <option value="">Todas</option>
                                    <?php foreach ($acoesPossiveis as $a): ?>
                                        <option value="<?= e($a) ?>" <?= $filtroAcao === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-sm">Tabela</label>
                                <div class="input-group">
                                    <span class="input-group-text" style="background:var(--bg-soft);border-color:var(--border);color:var(--text-muted);"><i class="bi bi-table"></i></span>
                                    <input type="text" name="f_table" class="form-control" placeholder="Ex.: USERS, PROFILES..." value="<?= e($filtroTabela) ?>">
                                </div>
                            </div>
                            <div class="col-md-4 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-verde flex-fill"><i class="bi bi-search me-1"></i> Filtrar</button>
                                <a href="log.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="card card-principal">
                    <div class="card-header-custom">
                        <h5 class="card-header-titulo"><i class="bi bi-journal-text"></i> Registos</h5>
                        <span class="badge bg-secondary"><?= $total ?> registo(s)</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-users mb-0">
                                <thead>
                                    <tr><th>Data</th><th>Utilizador</th><th>Ação</th><th>Tabela</th><th>Registo</th><th>IP</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pagina)): ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">Nenhum registo encontrado.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($pagina as $l):
                                            $a = $l['LOG_ACTION'];
                                            $cls = match($a) {
                                                'INSERT' => 'success', 'UPDATE' => 'warning',
                                                'DELETE' => 'danger', 'LOGIN' => 'info',
                                                'LOGOUT' => 'secondary', 'ACCESS_DENIED' => 'dark',
                                                'ERROR' => 'danger', default => 'secondary',
                                            };
                                        ?>
                                        <tr>
                                            <td><small><?= e(fmt_datetime($l['LOG_CREATED_AT'])) ?></small></td>
                                            <td>
                                                <?php if (!empty($l['USER_USERNAME'])): ?>
                                                    <i class="bi bi-person-circle me-1"></i><?= e($l['USER_USERNAME']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Sistema</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-<?= $cls ?>"><?= e($a) ?></span></td>
                                            <td><code><?= e($l['LOG_TABLE_NAME']) ?></code></td>
                                            <td><small class="text-muted"><?= $l['LOG_RECORD_ID'] !== null ? '#' . (int)$l['LOG_RECORD_ID'] : '—' ?></small></td>
                                            <td><small class="text-muted"><?= e($l['LOG_IPADDRESS'] ?: '—') ?></small></td>
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
                                <?php if ($i === 1 || $i === $totalPages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(build_url(['page' => $i])) ?>"><?= $i ?></a></li>
                                <?php elseif ($i === $page - 2 || $i === $page + 2): ?>
                                    <li class="page-item disabled"><span class="page-link">…</span></li>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(build_url(['page' => $page + 1])) ?>"><i class="bi bi-chevron-right"></i></a></li>
                        </ul></nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>

<?php include __DIR__ . '/include/footer.php'; ?>