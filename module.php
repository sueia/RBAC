<?php
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/class/PermissionChecker.php';
    require_once __DIR__ . '/class/Module.php';
    require_once __DIR__ . '/class/Sanitize.php';
    require_once __DIR__ . '/class/Log.php';

    $action = $_GET['action'] ?? '';
    $id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'create') PermissionChecker::require('CREATE', 'MOD');
        if ($action === 'update') PermissionChecker::require('UPDATE', 'MOD');
        if ($action === 'delete') PermissionChecker::require('DELETE', 'MOD');
    } else {
        PermissionChecker::require('LIST', 'MOD');
    }

    $canCreate = PermissionChecker::can('CREATE', 'MOD');
    $canUpdate = PermissionChecker::can('UPDATE', 'MOD');
    $canDelete = PermissionChecker::can('DELETE', 'MOD');

    $moduleRepo = new Module();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if ($action === 'create') {
                $newId = $moduleRepo->create([
                    'name'        => $_POST['name']        ?? '',
                    'code'        => $_POST['code']        ?? '',
                    'description' => $_POST['description'] ?? '',
                    'status'      => $_POST['status']      ?? 'INACTIVE',
                ], $_SESSION['user_id'] ?? null);
                Log::write('INSERT', 'MODULES', (int)$newId, null, ['name' => $_POST['name'] ?? '']);
                header('Location: module.php?success=' . urlencode('Módulo criado.'));
                exit;
            }
            if ($action === 'update' && $id) {
                $moduleRepo->update($id, [
                    'name'        => $_POST['name']        ?? '',
                    'description' => $_POST['description'] ?? '',
                    'status'      => $_POST['status']      ?? 'INACTIVE',
                ], $_SESSION['user_id'] ?? null);
                Log::write('UPDATE', 'MODULES', $id, null, ['name' => $_POST['name'] ?? '']);
                header('Location: module.php?success=' . urlencode('Módulo atualizado.'));
                exit;
            }
            if ($action === 'delete' && $id) {
                $moduleRepo->delete($id);
                Log::write('DELETE', 'MODULES', $id);
                header('Location: module.php?success=' . urlencode('Módulo eliminado.'));
                exit;
            }
        } catch (Throwable $e) {
            Log::error('module.php POST', 'MODULES', $e, ['action' => $action, 'id' => $id], $_SESSION['user_id'] ?? null);
            header('Location: module.php?error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    try {
        $filtroTexto  = Sanitize::string($_GET['f_name'] ?? '', 100);
        $filtroStatus = Sanitize::enum($_GET['f_status'] ?? '', ['ACTIVE','INACTIVE']);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;

        $lista      = $moduleRepo->search($filtroTexto, $filtroStatus ?: null);
        $total      = count($lista);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * $perPage;
        $pagina     = array_slice($lista, $offset, $perPage);

        $statsTotal    = $moduleRepo->countAll();
        $statsActive   = $moduleRepo->countActive();
        $statsInactive = $moduleRepo->countInactive();

        $db = new Database();
        $meta = [];
        foreach ($pagina as $m) {
            $mid = (int)$m['MODULE_ID'];
            $meta[$mid] = ['perms' => (int)$db->count('PERMISSIONS', ['PERMISSION_MODULEID' => $mid])];
        }
    } catch (Throwable $e) {
        Log::error('module.php listagem', 'MODULES', $e, null, $_SESSION['user_id'] ?? null);
        $lista = $pagina = $meta = [];
        $total = 0; $totalPages = 1; $page = 1; $offset = 0;
        $statsTotal = $statsActive = $statsInactive = 0;
        $filtroTexto = ''; $filtroStatus = null;
    }

    function build_url(array $overrides = []): string {
        $q = array_merge($_GET, $overrides);
        $q = array_filter($q, fn($v) => $v !== '' && $v !== null);
        return 'module.php' . (empty($q) ? '' : '?' . http_build_query($q));
    }

    $pageTitle = 'Módulos';
    include __DIR__ . '/include/navbar.php';
?>

        <main class="main-content">
            <div class="container-fluid">

                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                    <div>
                        <h1 class="page-title mb-0">Módulos do Sistema</h1>
                        <p class="text-muted mt-1 mb-0">Recursos protegidos pelo RBAC</p>
                    </div>
                </div>

                <?php if (!empty($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= e($_GET['success']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if (!empty($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= e($_GET['error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <div class="row g-4 mb-4">
                    <div class="col-xl-4 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Total</h6><h2 class="card-value"><?= $statsTotal ?></h2></div><div class="card-icon total"><i class="bi bi-grid-3x3-gap-fill"></i></div></div></div></div>
                    <div class="col-xl-4 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Ativos</h6><h2 class="card-value text-success"><?= $statsActive ?></h2></div><div class="card-icon ativos"><i class="bi bi-check-circle"></i></div></div></div></div>
                    <div class="col-xl-4 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Inativos</h6><h2 class="card-value"><?= $statsInactive ?></h2></div><div class="card-icon inativos"><i class="bi bi-pause-circle"></i></div></div></div></div>
                </div>

                <div class="filtros-container mb-4">
                    <h6 class="filtros-titulo"><i class="bi bi-funnel"></i> Filtrar Módulos</h6>
                    <form method="GET" action="module.php">
                        <div class="row g-3">
                            <div class="col-lg-5 col-md-6">
                                <label class="form-label-sm">Pesquisar</label>
                                <div class="input-group">
                                    <span class="input-group-text" style="background:var(--bg-soft);border-color:var(--border);color:var(--text-muted);"><i class="bi bi-search"></i></span>
                                    <input type="text" name="f_name" class="form-control" placeholder="Nome ou código..." value="<?= e($filtroTexto) ?>">
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <label class="form-label-sm">Status</label>
                                <select name="f_status" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="ACTIVE"   <?= $filtroStatus === 'ACTIVE'   ? 'selected' : '' ?>>Ativos</option>
                                    <option value="INACTIVE" <?= $filtroStatus === 'INACTIVE' ? 'selected' : '' ?>>Inativos</option>
                                </select>
                            </div>
                            <div class="col-lg-4 col-md-12 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-verde flex-fill"><i class="bi bi-funnel me-1"></i> Filtrar</button>
                                <a href="module.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i> Limpar</a>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="card card-principal">
                    <div class="card-header-custom">
                        <h5 class="card-header-titulo"><i class="bi bi-grid-3x3-gap-fill"></i> Módulos</h5>
                        <?php if ($canCreate): ?>
                        <button class="btn btn-verde btn-sm" data-bs-toggle="modal" data-bs-target="#novoModuloModal">
                            <i class="bi bi-plus-circle me-1"></i> Novo Módulo
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-users mb-0">
                                <thead><tr><th>Código</th><th>Nome</th><th>Descrição</th><th>Perms</th><th>Status</th><th class="text-end">Ações</th></tr></thead>
                                <tbody>
                                    <?php if (empty($pagina)): ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">Nenhum módulo encontrado.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($pagina as $m):
                                            $mid = (int)$m['MODULE_ID'];
                                            $isA = $m['MODULE_STATUS'] === 'ACTIVE';
                                        ?>
                                        <tr>
                                            <td><span class="badge bg-secondary"><?= e($m['MODULE_CODE']) ?></span></td>
                                            <td><strong><?= e($m['MODULE_NAME']) ?></strong></td>
                                            <td><small class="text-muted"><?= e($m['MODULE_DESCRIPTION'] ?: '—') ?></small></td>
                                            <td><span class="badge bg-primary"><?= $meta[$mid]['perms'] ?? 0 ?></span></td>
                                            <td><span class="status-badge <?= $isA ? 'ativo' : 'inativo' ?>"><?= $isA ? 'Ativo' : 'Inativo' ?></span></td>
                                            <td>
                                                <div class="action-group">
                                                    <?php if ($canUpdate): ?>
                                                    <button class="btn-action-tbl editar" data-bs-toggle="modal" data-bs-target="#editarModuloModal"
                                                            data-id="<?= $mid ?>"
                                                            data-nome="<?= e($m['MODULE_NAME']) ?>"
                                                            data-codigo="<?= e($m['MODULE_CODE']) ?>"
                                                            data-descricao="<?= e($m['MODULE_DESCRIPTION']) ?>"
                                                            data-status="<?= e($m['MODULE_STATUS']) ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <?php if ($canDelete): ?>
                                                    <button class="btn-action-tbl apagar" data-bs-toggle="modal" data-bs-target="#eliminarModuloModal"
                                                            data-id="<?= $mid ?>"
                                                            data-nome="<?= e($m['MODULE_NAME']) ?>"
                                                            data-perms="<?= $meta[$mid]['perms'] ?? 0 ?>">
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
        <div class="modal fade" id="novoModuloModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <form method="POST" action="module.php?action=create">
                    <div class="modal-header modal-header-verde">
                        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Novo Módulo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8"><label class="form-label">Nome <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required maxlength="50"></div>
                            <div class="col-md-4"><label class="form-label">Código</label><input type="text" name="code" class="form-control" maxlength="10" placeholder="Auto"></div>
                            <div class="col-12"><label class="form-label">Descrição</label><textarea name="description" class="form-control" rows="2" maxlength="1000"></textarea></div>
                            <div class="col-12"><label class="form-label">Status</label>
                                <select name="status" class="form-select"><option value="ACTIVE">Ativo</option><option value="INACTIVE" selected>Inativo</option></select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-verde">Guardar</button></div>
                </form>
            </div></div>
        </div>
        <?php endif; ?>

        <?php if ($canUpdate): ?>
        <div class="modal fade" id="editarModuloModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <form method="POST" id="formEditarModulo">
                    <div class="modal-header modal-header-verde">
                        <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Módulo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8"><label class="form-label">Nome</label><input type="text" name="name" id="editNome" class="form-control" required maxlength="50"></div>
                            <div class="col-md-4"><label class="form-label">Código</label><input type="text" id="editCodigo" class="form-control" readonly></div>
                            <div class="col-12"><label class="form-label">Descrição</label><textarea name="description" id="editDescricao" class="form-control" rows="2" maxlength="1000"></textarea></div>
                            <div class="col-12"><label class="form-label">Status</label>
                                <select name="status" id="editStatus" class="form-select"><option value="ACTIVE">Ativo</option><option value="INACTIVE">Inativo</option></select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-verde">Atualizar</button></div>
                </form>
            </div></div>
        </div>
        <?php endif; ?>

        <?php if ($canDelete): ?>
        <div class="modal fade" id="eliminarModuloModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <form method="POST" id="formEliminarModulo">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Eliminar</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Eliminar o módulo <strong id="delNome">—</strong>?</p>
                        <p class="text-danger small d-none" id="delAviso"><i class="bi bi-exclamation-circle me-1"></i>Este módulo tem <strong id="delPerms">0</strong> permissão(ões) associada(s) e não pode ser eliminado.</p>
                        <label class="form-label">Digite <strong>ELIMINAR</strong>:</label>
                        <input type="text" id="delConfirmacao" class="form-control" autocomplete="off">
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger" id="btnConfirmarEliminar" disabled>Eliminar</button></div>
                </form>
            </div></div>
        </div>
        <?php endif; ?>

        <script>
            <?php if ($canUpdate): ?>
            document.getElementById('editarModuloModal').addEventListener('show.bs.modal', function (e) {
                const b = e.relatedTarget;
                document.getElementById('formEditarModulo').action = 'module.php?action=update&id=' + b.dataset.id;
                document.getElementById('editNome').value = b.dataset.nome;
                document.getElementById('editCodigo').value = b.dataset.codigo;
                document.getElementById('editDescricao').value = b.dataset.descricao;
                document.getElementById('editStatus').value = b.dataset.status;
            });
            <?php endif; ?>
            <?php if ($canDelete): ?>
            document.getElementById('eliminarModuloModal').addEventListener('show.bs.modal', function (e) {
                const b = e.relatedTarget;
                const form = document.getElementById('formEliminarModulo');
                const btn  = document.getElementById('btnConfirmarEliminar');
                const aviso = document.getElementById('delAviso');
                const input = document.getElementById('delConfirmacao');
                form.action = 'module.php?action=delete&id=' + b.dataset.id;
                document.getElementById('delNome').textContent = b.dataset.nome;
                input.value = ''; btn.disabled = true;
                const perms = parseInt(b.dataset.perms || '0', 10);
                if (perms > 0) {
                    aviso.classList.remove('d-none');
                    document.getElementById('delPerms').textContent = perms;
                    btn.dataset.blocked = 'true';
                } else {
                    aviso.classList.add('d-none');
                    btn.dataset.blocked = 'false';
                }
            });
            document.getElementById('delConfirmacao').addEventListener('input', function () {
                const btn = document.getElementById('btnConfirmarEliminar');
                if (btn.dataset.blocked === 'true') return;
                btn.disabled = this.value.trim().toUpperCase() !== 'ELIMINAR';
            });
            <?php endif; ?>
        </script>

<?php include __DIR__ . '/include/footer.php'; ?>