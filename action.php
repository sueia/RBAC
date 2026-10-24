<?php
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/class/Action.php';
    require_once __DIR__ . '/class/Sanitize.php';
    require_once __DIR__ . '/class/Log.php';

    $actionRepo = new Action();
    $action = $_GET['action'] ?? '';
    $id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if ($action === 'create') {
                $newId = $actionRepo->create([
                    'name'        => $_POST['name']        ?? '',
                    'code'        => $_POST['code']        ?? '',
                    'description' => $_POST['description'] ?? '',
                    'status'      => $_POST['status']      ?? 'ACTIVE',
                ], $_SESSION['user_id'] ?? null);
                Log::write('INSERT', 'ACTIONS', (int)$newId, null, ['name' => $_POST['name'] ?? '']);
                header('Location: action.php?success=' . urlencode('Ação criada.'));
                exit;
            }
            if ($action === 'update' && $id) {
                $actionRepo->update($id, [
                    'name'        => $_POST['name']        ?? '',
                    'description' => $_POST['description'] ?? '',
                    'status'      => $_POST['status']      ?? 'ACTIVE',
                ], $_SESSION['user_id'] ?? null);
                Log::write('UPDATE', 'ACTIONS', $id, null, ['name' => $_POST['name'] ?? '']);
                header('Location: action.php?success=' . urlencode('Ação atualizada.'));
                exit;
            }
            if ($action === 'delete' && $id) {
                $actionRepo->delete($id);
                Log::write('DELETE', 'ACTIONS', $id);
                header('Location: action.php?success=' . urlencode('Ação eliminada.'));
                exit;
            }
        } catch (Throwable $e) {
            Log::error('Tela action.php: erro na ação POST', 'ACTIONS', $e, [
                'action' => $action, 'id' => $id,
            ], $_SESSION['user_id'] ?? null);
            header('Location: action.php?error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    try {
        $filtroTexto  = Sanitize::string($_GET['f_name'] ?? '', 100);
        $filtroStatus = Sanitize::enum($_GET['f_status'] ?? '', ['ACTIVE','INACTIVE']);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;

        $lista      = $actionRepo->search($filtroTexto, $filtroStatus ?: null);
        $total      = count($lista);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * $perPage;
        $pagina     = array_slice($lista, $offset, $perPage);

        $statsTotal    = $actionRepo->countAll();
        $statsActive   = $actionRepo->countActive();
        $statsInactive = $statsTotal - $statsActive;

        $db = new Database();
        $meta = [];
        foreach ($pagina as $a) {
            $aid = (int)$a['ACTION_ID'];
            $meta[$aid] = ['perms' => (int)$db->count('PERMISSIONS', ['PERMISSION_ACTIONID' => $aid])];
        }
    } catch (Throwable $e) {
        Log::error('Tela action.php: erro ao carregar listagem', 'ACTIONS', $e, null, $_SESSION['user_id'] ?? null);
        $lista = $pagina = $meta = [];
        $total = 0; $totalPages = 1; $page = 1; $offset = 0;
        $statsTotal = $statsActive = $statsInactive = 0;
        $filtroTexto = ''; $filtroStatus = null;
    }

    function build_url(array $overrides = []): string {
        $q = array_merge($_GET, $overrides);
        $q = array_filter($q, fn($v) => $v !== '' && $v !== null);
        return 'action.php' . (empty($q) ? '' : '?' . http_build_query($q));
    }

    $pageTitle = 'Ações';
    include __DIR__ . '/include/navbar.php';
?>

        <main class="main-content">
            <div class="container-fluid">

                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                    <div><h1 class="page-title mb-0">Gestão de Ações</h1><p class="text-muted mt-1 mb-0">Verbos RBAC</p></div>
                </div>

                <?php if (!empty($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= e($_GET['success']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if (!empty($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= e($_GET['error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <div class="row g-4 mb-4">
                    <div class="col-xl-4 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Total</h6><h2 class="card-value"><?= $statsTotal ?></h2></div><div class="card-icon total"><i class="bi bi-list-check"></i></div></div></div></div>
                    <div class="col-xl-4 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Ativas</h6><h2 class="card-value text-success"><?= $statsActive ?></h2></div><div class="card-icon ativos"><i class="bi bi-check-circle"></i></div></div></div></div>
                    <div class="col-xl-4 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Inativas</h6><h2 class="card-value"><?= $statsInactive ?></h2></div><div class="card-icon inativos"><i class="bi bi-pause-circle"></i></div></div></div></div>
                </div>

                <div class="filtros-container mb-4">
                    <h6 class="filtros-titulo"><i class="bi bi-funnel"></i> Filtrar Ações</h6>
                    <form method="GET" action="action.php">
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
                                    <option value="ACTIVE"   <?= $filtroStatus === 'ACTIVE'   ? 'selected' : '' ?>>Ativas</option>
                                    <option value="INACTIVE" <?= $filtroStatus === 'INACTIVE' ? 'selected' : '' ?>>Inativas</option>
                                </select>
                            </div>
                            <div class="col-lg-4 col-md-12 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-verde flex-fill"><i class="bi bi-funnel me-1"></i> Filtrar</button>
                                <a href="action.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i> Limpar</a>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="card card-principal">
                    <div class="card-header-custom">
                        <h5 class="card-header-titulo"><i class="bi bi-list-check"></i> Ações</h5>
                        <button class="btn btn-verde btn-sm" data-bs-toggle="modal" data-bs-target="#novaAcaoModal"><i class="bi bi-plus-circle me-1"></i> Nova Ação</button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-users mb-0">
                                <thead><tr><th>Código</th><th>Nome</th><th>Descrição</th><th>Perms</th><th>Status</th><th class="text-end">Ações</th></tr></thead>
                                <tbody>
                                    <?php if (empty($pagina)): ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">Nenhuma ação encontrada.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($pagina as $a):
                                            $aid = (int)$a['ACTION_ID'];
                                            $isA = $a['ACTION_STATUS'] === 'ACTIVE';
                                        ?>
                                        <tr>
                                            <td><span class="badge bg-secondary"><?= e($a['ACTION_CODE']) ?></span></td>
                                            <td><strong><?= e($a['ACTION_NAME']) ?></strong></td>
                                            <td><small class="text-muted"><?= e($a['ACTION_DESCRIPTION'] ?: '—') ?></small></td>
                                            <td><span class="badge bg-primary"><?= $meta[$aid]['perms'] ?? 0 ?></span></td>
                                            <td><span class="status-badge <?= $isA ? 'ativo' : 'inativo' ?>"><?= $isA ? 'Ativa' : 'Inativa' ?></span></td>
                                            <td>
                                                <div class="action-group">
                                                    <button class="btn-action-tbl editar" data-bs-toggle="modal" data-bs-target="#editarAcaoModal"
                                                            data-id="<?= $aid ?>"
                                                            data-nome="<?= e($a['ACTION_NAME']) ?>"
                                                            data-codigo="<?= e($a['ACTION_CODE']) ?>"
                                                            data-descricao="<?= e($a['ACTION_DESCRIPTION']) ?>"
                                                            data-status="<?= e($a['ACTION_STATUS']) ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn-action-tbl apagar" data-bs-toggle="modal" data-bs-target="#eliminarAcaoModal"
                                                            data-id="<?= $aid ?>"
                                                            data-nome="<?= e($a['ACTION_NAME']) ?>"
                                                            data-perms="<?= $meta[$aid]['perms'] ?? 0 ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
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

        <!-- NOVA -->
        <div class="modal fade" id="novaAcaoModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <form method="POST" action="action.php?action=create">
                    <div class="modal-header modal-header-verde"><h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nova Ação</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8"><label class="form-label">Nome <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required maxlength="50"></div>
                            <div class="col-md-4"><label class="form-label">Código</label><input type="text" name="code" class="form-control" maxlength="10" placeholder="Auto"></div>
                            <div class="col-12"><label class="form-label">Descrição</label><textarea name="description" class="form-control" rows="2" maxlength="1000"></textarea></div>
                            <div class="col-12"><label class="form-label">Status</label><select name="status" class="form-select"><option value="ACTIVE">Ativa</option><option value="INACTIVE">Inativa</option></select></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-verde">Guardar</button></div>
                </form>
            </div></div>
        </div>

        <!-- EDITAR -->
        <div class="modal fade" id="editarAcaoModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <form method="POST" id="formEditarAcao">
                    <div class="modal-header modal-header-verde"><h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Ação</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8"><label class="form-label">Nome</label><input type="text" name="name" id="editNome" class="form-control" required maxlength="50"></div>
                            <div class="col-md-4"><label class="form-label">Código</label><input type="text" id="editCodigo" class="form-control" readonly></div>
                            <div class="col-12"><label class="form-label">Descrição</label><textarea name="description" id="editDescricao" class="form-control" rows="2" maxlength="1000"></textarea></div>
                            <div class="col-12"><label class="form-label">Status</label><select name="status" id="editStatus" class="form-select"><option value="ACTIVE">Ativa</option><option value="INACTIVE">Inativa</option></select></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-verde">Atualizar</button></div>
                </form>
            </div></div>
        </div>

        <!-- ELIMINAR -->
        <div class="modal fade" id="eliminarAcaoModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <form method="POST" id="formEliminarAcao">
                    <div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Eliminar</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p>Eliminar a ação <strong id="delNome">—</strong>?</p>
                        <p class="text-danger small d-none" id="delAviso"><i class="bi bi-exclamation-circle me-1"></i>Esta ação tem <strong id="delPerms">0</strong> permissão(ões) associada(s) e não pode ser eliminada.</p>
                        <label class="form-label">Digite <strong>ELIMINAR</strong>:</label>
                        <input type="text" id="delConfirmacao" class="form-control" autocomplete="off">
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger" id="btnConfirmarEliminar" disabled>Eliminar</button></div>
                </form>
            </div></div>
        </div>

        <script>
            document.getElementById('editarAcaoModal').addEventListener('show.bs.modal', function (e) {
                const b = e.relatedTarget;
                document.getElementById('formEditarAcao').action = 'action.php?action=update&id=' + b.dataset.id;
                document.getElementById('editNome').value = b.dataset.nome;
                document.getElementById('editCodigo').value = b.dataset.codigo;
                document.getElementById('editDescricao').value = b.dataset.descricao;
                document.getElementById('editStatus').value = b.dataset.status;
            });
            document.getElementById('eliminarAcaoModal').addEventListener('show.bs.modal', function (e) {
                const b = e.relatedTarget;
                document.getElementById('formEliminarAcao').action = 'action.php?action=delete&id=' + b.dataset.id;
                document.getElementById('delNome').textContent = b.dataset.nome;
                const input = document.getElementById('delConfirmacao');
                const btn   = document.getElementById('btnConfirmarEliminar');
                const aviso = document.getElementById('delAviso');
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
        </script>

<?php include __DIR__ . '/include/footer.php'; ?>