<?php
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/class/PermissionChecker.php';
    require_once __DIR__ . '/class/Profile.php';
    require_once __DIR__ . '/class/Module.php';
    require_once __DIR__ . '/class/Action.php';
    require_once __DIR__ . '/class/Permission.php';
    require_once __DIR__ . '/class/Sanitize.php';
    require_once __DIR__ . '/class/Log.php';

    $action = $_GET['action'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
        PermissionChecker::require('UPDATE', 'PERM');
    } else {
        PermissionChecker::require('LIST', 'PERM');
    }

    $canSave = PermissionChecker::can('UPDATE', 'PERM');

    $profileRepo    = new Profile();
    $moduleRepo     = new Module();
    $actionRepo     = new Action();
    $permissionRepo = new Permission();

    $profiles = $profileRepo->all();
    $modules  = $moduleRepo->all();
    $actions  = $actionRepo->all();

    $selectedProfileId = (int)($_GET['profile'] ?? ($profiles[0]['PROFILE_ID'] ?? 0));

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
        try {
            $selected = $_POST['perm'] ?? [];
            if (!is_array($selected)) $selected = [];

            $prof = $profileRepo->findById($selectedProfileId);
            if (!$prof) throw new RuntimeException("Perfil não encontrado.");

            $actionsMap = [];
            foreach ($actions as $a) $actionsMap[(int)$a['ACTION_ID']] = $a['ACTION_CODE'];
            $modulesMap = [];
            foreach ($modules as $m) $modulesMap[(int)$m['MODULE_ID']] = $m['MODULE_CODE'];

            $count = $permissionRepo->saveMatrix(
                $selectedProfileId, $selected,
                $prof['PROFILE_CODE'], $actionsMap, $modulesMap
            );

            Log::write('UPDATE', 'PERMISSIONS', null, null, [
                'profile_id' => $selectedProfileId, 'total' => $count,
            ]);

            PermissionChecker::flush();

            header('Location: permission.php?profile=' . $selectedProfileId . '&success=' . urlencode("Permissões guardadas ({$count})."));
            exit;
        } catch (Throwable $e) {
            Log::error('permission.php save', 'PERMISSIONS', $e, ['profile_id' => $selectedProfileId], $_SESSION['user_id'] ?? null);
            header('Location: permission.php?profile=' . $selectedProfileId . '&error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    try {
        $matrix = $selectedProfileId ? $permissionRepo->matrixForProfile($selectedProfileId) : [];
    } catch (Throwable $e) {
        $matrix = [];
        Log::error('permission.php matrix', 'PERMISSIONS', $e, null, $_SESSION['user_id'] ?? null);
    }

    $pageTitle = 'Permissões';
    include __DIR__ . '/include/navbar.php';
?>

        <main class="main-content">
            <div class="container-fluid">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                    <div><h1 class="page-title mb-0">Matriz de Permissões</h1><p class="text-muted mt-1 mb-0">Perfil × Ação × Módulo</p></div>
                </div>

                <?php if (!empty($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= e($_GET['success']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if (!empty($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= e($_GET['error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <div class="filtros-container mb-4">
                    <h6 class="filtros-titulo"><i class="bi bi-person-badge"></i> Selecione um Perfil</h6>
                    <form method="GET" action="permission.php">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label-sm">Perfil</label>
                                <select name="profile" class="form-select" onchange="this.form.submit()">
                                    <?php foreach ($profiles as $p): ?>
                                        <option value="<?= (int)$p['PROFILE_ID'] ?>" <?= (int)$p['PROFILE_ID'] === $selectedProfileId ? 'selected' : '' ?>>
                                            <?= e($p['PROFILE_CODE']) ?> — <?= e($p['PROFILE_NAME']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-verde w-100"><i class="bi bi-arrow-clockwise me-1"></i> Carregar</button>
                            </div>
                        </div>
                    </form>
                </div>

                <form method="POST" action="permission.php?action=save&profile=<?= $selectedProfileId ?>">
                    <div class="card card-principal">
                        <div class="card-header-custom">
                            <h5 class="card-header-titulo"><i class="bi bi-shield-lock"></i> Matriz</h5>
                            <?php if ($canSave): ?>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="marcarTudo(true)">Marcar Todas</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="marcarTudo(false)">Desmarcar</button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height:70vh;overflow:auto;">
                                <table class="table table-users mb-0" style="font-size:0.85rem;">
                                    <thead>
                                        <tr>
                                            <th>Módulo</th>
                                            <?php foreach ($actions as $a): ?>
                                                <th class="text-center"><?= e($a['ACTION_CODE']) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($modules as $m):
                                            $mid = (int)$m['MODULE_ID'];
                                        ?>
                                        <tr>
                                            <td><strong><?= e($m['MODULE_NAME']) ?></strong><br><small class="text-muted"><?= e($m['MODULE_CODE']) ?></small></td>
                                            <?php foreach ($actions as $a):
                                                $aid = (int)$a['ACTION_ID'];
                                                $checked = !empty($matrix[$mid][$aid]);
                                            ?>
                                            <td class="text-center">
                                                <input type="checkbox"
                                                    class="form-check-input"
                                                    name="perm[<?= $mid ?>][<?= $aid ?>]"
                                                    value="1"
                                                    <?= $checked ? 'checked' : '' ?>
                                                    <?= !$canSave ? 'disabled' : '' ?>>
                                            </td>
                                            <?php endforeach; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer-custom">
                            <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>Cada marcação gera uma permissão <code>PERFIL_AÇÃO_MÓDULO</code>.</span>
                            <?php if ($canSave): ?>
                            <button type="submit" class="btn btn-verde"><i class="bi bi-save me-1"></i>Guardar Permissões</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </main>

        <script>
            <?php if ($canSave): ?>
            function marcarTudo(v) {
                document.querySelectorAll('input[type=checkbox]:not(:disabled)').forEach(cb => cb.checked = v);
            }
            <?php endif; ?>
        </script>

<?php include __DIR__ . '/include/footer.php'; ?>