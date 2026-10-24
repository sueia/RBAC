<?php
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/class/Database.php';
    require_once __DIR__ . '/class/Sanitize.php';
    require_once __DIR__ . '/class/Log.php';

    $db = new Database();

    try {
        $totalProfiles    = (int)$db->count('PROFILES');
        $activeProfiles   = (int)$db->count('PROFILES', ['PROFILE_STATUS' => 'ACTIVE']);
        $inactiveProfiles = $totalProfiles - $activeProfiles;

        $totalActions     = (int)$db->count('ACTIONS');
        $activeActions    = (int)$db->count('ACTIONS', ['ACTION_STATUS' => 'ACTIVE']);

        $totalModules     = (int)$db->count('MODULES');
        $activeModules    = (int)$db->count('MODULES', ['MODULE_STATUS' => 'ACTIVE']);

        $totalPermissions = (int)$db->count('PERMISSIONS');

        $totalUsers       = (int)$db->count('USERS');
        $activeUsers      = (int)$db->count('USERS', ['USER_STATUS' => 'ACTIVE']);
        $blockedUsers     = (int)$db->count('USERS', ['USER_STATUS' => 'BLOCKED']);
        $inactiveUsers    = (int)$db->count('USERS', ['USER_STATUS' => 'INACTIVE']);

        $totalSessions    = (int)$db->count('USER_SESSIONS');
        $activeSessions   = (int)(($db->query("SELECT COUNT(*) AS c FROM USER_SESSIONS WHERE SESSION_EXPIRESAT >= NOW()"))[0]['c'] ?? 0);

        $totalLogs        = (int)$db->count('LOGS');
        $logsToday        = (int)(($db->query("SELECT COUNT(*) AS c FROM LOGS WHERE DATE(LOG_CREATED_AT) = CURDATE()"))[0]['c'] ?? 0);

        $possivel  = max(1, $totalActions * $totalModules);
        $cobertura = round(($totalPermissions / $possivel) * 100, 1);

        $usersStatusData = [
            'labels' => ['Ativos', 'Bloqueados', 'Inativos'],
            'data'   => [$activeUsers, $blockedUsers, $inactiveUsers],
        ];

        $permsByProfile = $db->query(
            "SELECT P.PROFILE_CODE AS code, P.PROFILE_NAME AS name, COUNT(PERM.PERMISSION_ID) AS total
             FROM PROFILES P
             LEFT JOIN PERMISSIONS PERM ON P.PROFILE_ID = PERM.PERMISSION_PROFILEID
             GROUP BY P.PROFILE_ID, P.PROFILE_CODE, P.PROFILE_NAME
             ORDER BY total DESC"
        );

        $permsByModule = $db->query(
            "SELECT M.MODULE_NAME AS name, COUNT(PERM.PERMISSION_ID) AS total
             FROM MODULES M
             LEFT JOIN PERMISSIONS PERM ON M.MODULE_ID = PERM.PERMISSION_MODULEID
             GROUP BY M.MODULE_ID, M.MODULE_NAME
             ORDER BY total DESC"
        );

        $logsByAction = $db->query(
            "SELECT LOG_ACTION AS action, COUNT(*) AS total
             FROM LOGS
             WHERE LOG_CREATED_AT >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY LOG_ACTION
             ORDER BY total DESC"
        );

        $sessionsByDay = $db->query(
            "SELECT DATE(SESSION_CREATEAT) AS dia, COUNT(*) AS total
             FROM USER_SESSIONS
             WHERE SESSION_CREATEAT >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
             GROUP BY DATE(SESSION_CREATEAT)
             ORDER BY dia ASC"
        );
        $sessionsMap = [];
        foreach ($sessionsByDay as $r) $sessionsMap[$r['dia']] = (int)$r['total'];
        $sessionsChart = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $sessionsChart[] = ['label' => date('d/m', strtotime($d)), 'total' => $sessionsMap[$d] ?? 0];
        }

        $recentLogs = $db->query(
            "SELECT L.*, U.USER_USERNAME
             FROM LOGS L
             LEFT JOIN USERS U ON L.LOG_USER_ID = U.USER_ID
             ORDER BY L.LOG_CREATED_AT DESC LIMIT 10"
        );

        $recentSessions = $db->query(
            "SELECT S.*, U.USER_USERNAME, U.USER_FULLNAME, P.PROFILE_CODE
             FROM USER_SESSIONS S
             INNER JOIN USERS U    ON S.SESSION_USERID = U.USER_ID
             INNER JOIN PROFILES P ON U.USER_PROFILEID = P.PROFILE_ID
             WHERE S.SESSION_EXPIRESAT >= NOW()
             ORDER BY S.SESSION_CREATEAT DESC LIMIT 6"
        );

        $usersByProfile = $db->query(
            "SELECT P.PROFILE_CODE AS code, P.PROFILE_NAME AS name, COUNT(U.USER_ID) AS total
             FROM PROFILES P
             LEFT JOIN USERS U ON P.PROFILE_ID = U.USER_PROFILEID
             GROUP BY P.PROFILE_ID, P.PROFILE_CODE, P.PROFILE_NAME
             ORDER BY total DESC"
        );

        $adminMatrix = $db->query(
            "SELECT M.MODULE_NAME AS module, COUNT(PERM.PERMISSION_ID) AS total
             FROM PERMISSIONS PERM
             INNER JOIN MODULES M  ON PERM.PERMISSION_MODULEID = M.MODULE_ID
             INNER JOIN PROFILES P ON PERM.PERMISSION_PROFILEID = P.PROFILE_ID
             WHERE P.PROFILE_CODE = 'ADM'
             GROUP BY M.MODULE_ID, M.MODULE_NAME
             ORDER BY M.MODULE_NAME"
        );
    } catch (Throwable $e) {
        Log::error('dashboard.php falhou', 'DASHBOARD', $e, null, $_SESSION['user_id'] ?? null);

        $totalProfiles = $activeProfiles = $inactiveProfiles = 0;
        $totalActions = $activeActions = 0;
        $totalModules = $activeModules = 0;
        $totalPermissions = 0;
        $totalUsers = $activeUsers = $blockedUsers = $inactiveUsers = 0;
        $totalSessions = $activeSessions = 0;
        $totalLogs = $logsToday = 0;
        $cobertura = 0;
        $usersStatusData = ['labels' => [], 'data' => []];
        $permsByProfile = $permsByModule = $logsByAction = [];
        $sessionsChart = [];
        $recentLogs = $recentSessions = $usersByProfile = $adminMatrix = [];
    }

    $pageTitle = 'Dashboard';
    include __DIR__ . '/include/navbar.php';
?>

        <main class="main-content">
            <div class="container-fluid">

                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                    <div>
                        <h1 class="page-title mb-0">Dashboard RBAC</h1>
                        <p class="text-muted mt-1 mb-0">Visão geral do controlo de acessos baseado em perfis</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="badge bg-light text-dark p-2 shadow-sm">
                            <i class="bi bi-calendar3 me-1"></i> <span id="currentDate"><?= date('d/m/Y') ?></span>
                        </span>
                        <span class="badge bg-success p-2 shadow-sm">
                            <i class="bi bi-circle-fill me-1" style="font-size:8px;"></i> Sistema Online
                        </span>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card dashboard-card h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted text-uppercase fw-semibold mb-2">Perfis</h6>
                                    <h2 class="card-value"><?= $totalProfiles ?></h2>
                                    <small class="text-success"><i class="bi bi-check-circle"></i> <?= $activeProfiles ?> ativos</small>
                                </div>
                                <div class="card-icon total"><i class="bi bi-person-badge"></i></div>
                            </div>
                            <div class="card-footer bg-transparent border-0 d-flex justify-content-between">
                                <small class="text-muted">Inativos: <?= $inactiveProfiles ?></small>
                                <a href="profile.php" class="text-decoration-none small">Ver <i class="bi bi-chevron-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card dashboard-card h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted text-uppercase fw-semibold mb-2">Ações</h6>
                                    <h2 class="card-value"><?= $totalActions ?></h2>
                                    <small class="text-success"><i class="bi bi-check-circle"></i> <?= $activeActions ?> ativas</small>
                                </div>
                                <div class="card-icon ativos"><i class="bi bi-list-check"></i></div>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <a href="action.php" class="text-decoration-none small">Gerir <i class="bi bi-chevron-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card dashboard-card h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted text-uppercase fw-semibold mb-2">Módulos</h6>
                                    <h2 class="card-value"><?= $totalModules ?></h2>
                                    <small class="text-success"><i class="bi bi-check-circle"></i> <?= $activeModules ?> ativos</small>
                                </div>
                                <div class="card-icon" style="background:linear-gradient(135deg,#ff9800,#f57c00);"><i class="bi bi-grid-3x3-gap-fill"></i></div>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <a href="module.php" class="text-decoration-none small">Gerir <i class="bi bi-chevron-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card dashboard-card h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted text-uppercase fw-semibold mb-2">Permissões</h6>
                                    <h2 class="card-value"><?= $totalPermissions ?></h2>
                                    <small class="text-muted"><?= $totalActions ?>×<?= $totalModules ?> possíveis</small>
                                </div>
                                <div class="card-icon" style="background:linear-gradient(135deg,#9c27b0,#7b1fa2);"><i class="bi bi-shield-lock"></i></div>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <a href="permission.php" class="text-decoration-none small">Ver matriz <i class="bi bi-chevron-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card dashboard-card h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted text-uppercase fw-semibold mb-2">Utilizadores</h6>
                                    <h2 class="card-value"><?= $totalUsers ?></h2>
                                    <small class="text-success"><i class="bi bi-check-circle"></i> <?= $activeUsers ?> ativos</small>
                                </div>
                                <div class="card-icon" style="background:linear-gradient(135deg,#17a2b8,#138496);"><i class="bi bi-people"></i></div>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <a href="user.php" class="text-decoration-none small">Ver <i class="bi bi-chevron-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card dashboard-card h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted text-uppercase fw-semibold mb-2">Sessões Ativas</h6>
                                    <h2 class="card-value"><?= $activeSessions ?></h2>
                                    <small class="text-muted">Total histórico: <?= $totalSessions ?></small>
                                </div>
                                <div class="card-icon" style="background:linear-gradient(135deg,#4d96ff,#2563eb);"><i class="bi bi-shield-check"></i></div>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <a href="session.php" class="text-decoration-none small">Gerir <i class="bi bi-chevron-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card dashboard-card h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted text-uppercase fw-semibold mb-2">Logs</h6>
                                    <h2 class="card-value"><?= number_format($totalLogs, 0, ',', '.') ?></h2>
                                    <small class="text-info"><i class="bi bi-clock-history"></i> <?= $logsToday ?> hoje</small>
                                </div>
                                <div class="card-icon inativos"><i class="bi bi-journal-text"></i></div>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <a href="log.php" class="text-decoration-none small">Consultar <i class="bi bi-chevron-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card dashboard-card h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted text-uppercase fw-semibold mb-2">Cobertura RBAC</h6>
                                    <h2 class="card-value"><?= $cobertura ?>%</h2>
                                    <small class="text-muted"><?= $totalPermissions ?> / <?= $possivel ?></small>
                                </div>
                                <div class="card-icon" style="background:linear-gradient(135deg,#689F38,#4e7a29);"><i class="bi bi-pie-chart"></i></div>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <div class="progress" style="height:6px;">
                                    <div class="progress-bar bg-success" style="width:<?= $cobertura ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-xl-4 col-lg-6">
                        <div class="card card-principal h-100">
                            <div class="card-header-custom">
                                <h5 class="card-header-titulo"><i class="bi bi-pie-chart text-primary"></i> Status dos Utilizadores</h5>
                                <span class="badge bg-soft-primary">Total: <?= $totalUsers ?></span>
                            </div>
                            <div class="card-body">
                                <div style="position:relative;height:220px;"><canvas id="userStatusChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-6">
                        <div class="card card-principal h-100">
                            <div class="card-header-custom">
                                <h5 class="card-header-titulo"><i class="bi bi-bar-chart text-success"></i> Permissões por Perfil</h5>
                            </div>
                            <div class="card-body">
                                <div style="position:relative;height:220px;"><canvas id="permsByProfileChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-12">
                        <div class="card card-principal h-100">
                            <div class="card-header-custom">
                                <h5 class="card-header-titulo"><i class="bi bi-bar-chart text-warning"></i> Permissões por Módulo</h5>
                            </div>
                            <div class="card-body">
                                <div style="position:relative;height:220px;"><canvas id="permsByModuleChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-xl-6">
                        <div class="card card-principal h-100">
                            <div class="card-header-custom">
                                <h5 class="card-header-titulo"><i class="bi bi-activity text-info"></i> Logs por Ação (últimos 30 dias)</h5>
                            </div>
                            <div class="card-body">
                                <div style="position:relative;height:240px;"><canvas id="logsByActionChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="card card-principal h-100">
                            <div class="card-header-custom">
                                <h5 class="card-header-titulo"><i class="bi bi-graph-up text-success"></i> Sessões Criadas (últimos 7 dias)</h5>
                            </div>
                            <div class="card-body">
                                <div style="position:relative;height:240px;"><canvas id="sessionsChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-xl-7">
                        <div class="card card-principal h-100">
                            <div class="card-header-custom">
                                <h5 class="card-header-titulo"><i class="bi bi-journal-text text-secondary"></i> Últimos Logs</h5>
                                <a href="log.php" class="btn btn-sm btn-outline-secondary">Ver todos <i class="bi bi-arrow-right"></i></a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height:360px;overflow-y:auto;">
                                    <table class="table table-hover table-sm align-middle mb-0" style="font-size:0.85rem;">
                                        <thead class="table-light">
                                            <tr><th>Data</th><th>Utilizador</th><th>Ação</th><th>Tabela</th><th>IP</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recentLogs)): ?>
                                                <tr><td colspan="5" class="text-center py-4 text-muted">Sem registos.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($recentLogs as $l):
                                                    $cls = match($l['LOG_ACTION']) {
                                                        'INSERT' => 'success', 'UPDATE' => 'warning',
                                                        'DELETE' => 'danger', 'LOGIN' => 'info',
                                                        'LOGOUT' => 'secondary', 'ACCESS_DENIED' => 'dark',
                                                        'ERROR' => 'danger', default => 'secondary',
                                                    };
                                                ?>
                                                <tr>
                                                    <td><small><?= date('d/m H:i', strtotime($l['LOG_CREATED_AT'])) ?></small></td>
                                                    <td>
                                                        <?php if (!empty($l['USER_USERNAME'])): ?>
                                                            <i class="bi bi-person-circle me-1"></i><?= e($l['USER_USERNAME']) ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Sistema</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><span class="badge bg-<?= $cls ?>"><?= e($l['LOG_ACTION']) ?></span></td>
                                                    <td><code><?= e($l['LOG_TABLE_NAME']) ?></code></td>
                                                    <td><small class="text-muted"><?= e($l['LOG_IPADDRESS'] ?: '—') ?></small></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-5">
                        <div class="card card-principal h-100">
                            <div class="card-header-custom">
                                <h5 class="card-header-titulo"><i class="bi bi-shield-check text-primary"></i> Sessões Ativas</h5>
                                <a href="session.php" class="btn btn-sm btn-outline-secondary">Gerir <i class="bi bi-arrow-right"></i></a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentSessions)): ?>
                                    <p class="text-muted text-center py-4 mb-0">Nenhuma sessão ativa.</p>
                                <?php else: ?>
                                    <?php foreach ($recentSessions as $s): ?>
                                    <div class="d-flex align-items-start py-2 border-bottom">
                                        <div class="me-2">
                                            <span class="badge bg-light text-secondary rounded-circle p-2"><i class="bi bi-person-circle"></i></span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between">
                                                <strong><?= e($s['USER_FULLNAME'] ?: $s['USER_USERNAME']) ?></strong>
                                                <small class="text-muted"><?= date('H:i', strtotime($s['SESSION_CREATEAT'])) ?></small>
                                            </div>
                                            <small class="text-muted d-block">
                                                <i class="bi bi-shield-lock me-1"></i><?= e($s['PROFILE_CODE']) ?>
                                                <?php if (!empty($s['SESSION_IPADDRESS'])): ?>
                                                    &nbsp;|&nbsp;<i class="bi bi-geo-alt me-1"></i><?= e($s['SESSION_IPADDRESS']) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-xl-6">
                        <div class="card card-principal h-100">
                            <div class="card-header-custom">
                                <h5 class="card-header-titulo"><i class="bi bi-people text-info"></i> Utilizadores por Perfil</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr><th>Código</th><th>Perfil</th><th class="text-end">Utilizadores</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($usersByProfile)): ?>
                                                <tr><td colspan="3" class="text-center py-4 text-muted">Sem perfis.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($usersByProfile as $p): ?>
                                                <tr>
                                                    <td><span class="badge bg-secondary"><?= e($p['code']) ?></span></td>
                                                    <td><?= e($p['name']) ?></td>
                                                    <td class="text-end"><span class="badge bg-primary"><?= (int)$p['total'] ?></span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="card card-principal h-100">
                            <div class="card-header-custom">
                                <h5 class="card-header-titulo"><i class="bi bi-shield-lock text-success"></i> Permissões do Administrador</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($adminMatrix)): ?>
                                    <p class="text-muted text-center py-4 mb-0">Sem dados.</p>
                                <?php else: ?>
                                    <?php foreach ($adminMatrix as $row): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                        <span><i class="bi bi-grid-3x3-gap-fill text-success me-2"></i><?= e($row['module']) ?></span>
                                        <span class="badge bg-success"><?= (int)$row['total'] ?> ações</span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

        <script>
            (function () {
                'use strict';
                const usersStatus  = <?= json_encode($usersStatusData) ?>;
                const permsProfile = <?= json_encode($permsByProfile) ?>;
                const permsModule  = <?= json_encode($permsByModule) ?>;
                const logsAction   = <?= json_encode($logsByAction) ?>;
                const sessionsData = <?= json_encode($sessionsChart) ?>;

                Chart.defaults.font.family = "'Inter', 'Segoe UI', Tahoma, sans-serif";
                Chart.defaults.font.size   = 11;
                Chart.defaults.color       = '#64748b';

                const charts = [];

                charts.push(new Chart(document.getElementById('userStatusChart'), {
                    type: 'doughnut',
                    data: {
                        labels: usersStatus.labels,
                        datasets: [{ data: usersStatus.data, backgroundColor: ['#10b981', '#ef4444', '#94a3b8'], borderWidth: 2, borderColor: '#fff' }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { display: false } } }
                }));

                charts.push(new Chart(document.getElementById('permsByProfileChart'), {
                    type: 'bar',
                    data: {
                        labels: permsProfile.map(p => p.code),
                        datasets: [{ label: 'Permissões', data: permsProfile.map(p => +p.total), backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#9c27b0', '#06b6d4', '#ec4899'], borderRadius: 6 }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(100,116,139,0.1)' } } } }
                }));

                charts.push(new Chart(document.getElementById('permsByModuleChart'), {
                    type: 'bar',
                    data: {
                        labels: permsModule.map(m => m.name),
                        datasets: [{ label: 'Permissões', data: permsModule.map(m => +m.total), backgroundColor: '#f59e0b', borderRadius: 6 }]
                    },
                    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(100,116,139,0.1)' } }, y: { grid: { display: false } } } }
                }));

                const logColors = { INSERT:'#10b981', UPDATE:'#f59e0b', DELETE:'#ef4444', LOGIN:'#06b6d4', LOGOUT:'#94a3b8', ACCESS_DENIED:'#ec4899', ERROR:'#dc2626' };
                charts.push(new Chart(document.getElementById('logsByActionChart'), {
                    type: 'doughnut',
                    data: {
                        labels: logsAction.map(l => l.action),
                        datasets: [{ data: logsAction.map(l => +l.total), backgroundColor: logsAction.map(l => logColors[l.action] || '#94a3b8'), borderWidth: 2, borderColor: '#fff' }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '55%', plugins: { legend: { position: 'right', labels: { boxWidth: 12, padding: 10 } } } }
                }));

                charts.push(new Chart(document.getElementById('sessionsChart'), {
                    type: 'line',
                    data: {
                        labels: sessionsData.map(s => s.label),
                        datasets: [{ label: 'Sessões', data: sessionsData.map(s => +s.total), borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.15)', fill: true, tension: 0.4, pointRadius: 4, pointBackgroundColor: '#3b82f6' }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(100,116,139,0.1)' } } } }
                }));

                function refreshChartColors(isDark) {
                    const textColor = isDark ? '#8fa0b8' : '#64748b';
                    const gridColor = isDark ? 'rgba(143,160,184,0.15)' : 'rgba(100,116,139,0.1)';
                    Chart.defaults.color = textColor;
                    charts.forEach(c => {
                        if (c.options.scales) Object.values(c.options.scales).forEach(scale => {
                            if (scale.grid && scale.grid.color) scale.grid.color = gridColor;
                            if (scale.ticks) scale.ticks.color = textColor;
                        });
                        if (c.options.plugins && c.options.plugins.legend && c.options.plugins.legend.labels) {
                            c.options.plugins.legend.labels.color = textColor;
                        }
                        c.update('none');
                    });
                }
                window.__refreshChartColors = refreshChartColors;
                refreshChartColors(document.documentElement.getAttribute('data-theme') === 'dark');
            })();
        </script>

<?php include __DIR__ . '/include/footer.php'; ?>