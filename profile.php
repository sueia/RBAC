<?php
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/class/PermissionChecker.php';
    require_once __DIR__ . '/class/Profile.php';
    require_once __DIR__ . '/class/Sanitize.php';
    require_once __DIR__ . '/class/Log.php';

    $action = $_GET['action'] ?? '';
    $id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'create') PermissionChecker::require('CREATE', 'PROF');
        if ($action === 'update') PermissionChecker::require('UPDATE', 'PROF');
        if ($action === 'delete') PermissionChecker::require('DELETE', 'PROF');
    } else {
        PermissionChecker::require('LIST', 'PROF');
    }

    $canCreate = PermissionChecker::can('CREATE', 'PROF');
    $canUpdate = PermissionChecker::can('UPDATE', 'PROF');
    $canDelete = PermissionChecker::can('DELETE', 'PROF');

    $profileRepo = new Profile();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if ($action === 'create') {
                $newId = $profileRepo->create([
                    'name'        => $_POST['name']        ?? '',
                    'code'        => $_POST['code']        ?? '',
                    'description' => $_POST['description'] ?? '',
                    'status'      => $_POST['status']      ?? 'INACTIVE',
                ], $_SESSION['user_id'] ?? null);
                Log::write('INSERT', 'PROFILES', (int)$newId, null, ['name' => $_POST['name'] ?? '']);
                header('Location: profile.php?success=' . urlencode('Perfil criado.'));
                exit;
            }
            if ($action === 'update' && $id) {
                $profileRepo->update($id, [
                    'name'        => $_POST['name']        ?? '',
                    'description' => $_POST['description'] ?? '',
                    'status'      => $_POST['status']      ?? 'INACTIVE',
                ], $_SESSION['user_id'] ?? null);
                Log::write('UPDATE', 'PROFILES', $id, null, ['name' => $_POST['name'] ?? '']);
                header('Location: profile.php?success=' . urlencode('Perfil atualizado.'));
                exit;
            }
            if ($action === 'delete' && $id) {
                $profileRepo->delete($id);
                Log::write('DELETE', 'PROFILES', $id);
                header('Location: profile.php?success=' . urlencode('Perfil eliminado.'));
                exit;
            }
        } catch (Throwable $e) {
            Log::error('profile.php POST', 'PROFILES', $e, ['action' => $action, 'id' => $id], $_SESSION['user_id'] ?? null);
            header('Location: profile.php?error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    try {
        $filtroTexto  = Sanitize::string($_GET['f_name'] ?? '', 100);
        $filtroStatus = Sanitize::enum($_GET['f_status'] ?? '', ['ACTIVE','INACTIVE']);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 6;

        $lista      = $profileRepo->search($filtroTexto, $filtroStatus ?: null);
        $total      = count($lista);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * $perPage;
        $perfisPag  = array_slice($lista, $offset, $perPage);

        $statsTotal    = $profileRepo->countAll();
        $statsActive   = $profileRepo->countActive();
        $statsInactive = $profileRepo->countInactive();
        $statsComPerms = $profileRepo->countWithPermissions();

        $db = new Database();
        $perfilMeta = [];
        foreach ($perfisPag as $p) {
            $pid = (int)$p['PROFILE_ID'];
            $perfilMeta[$pid] = [
                'perms' => (int)$db->count('PERMISSIONS', ['PERMISSION_PROFILEID' => $pid]),
                'users' => (int)$db->count('USERS', ['USER_PROFILEID' => $pid]),
            ];
        }
    } catch (Throwable $e) {
        Log::error('profile.php listagem', 'PROFILES', $e, null, $_SESSION['user_id'] ?? null);
        $lista = $perfisPag = $perfilMeta = [];
        $total = 0; $totalPages = 1; $page = 1; $offset = 0;
        $statsTotal = $statsActive = $statsInactive = $statsComPerms = 0;
        $filtroTexto = ''; $filtroStatus = null;
    }

    function build_url(array $overrides = []): string {
        $q = array_merge($_GET, $overrides);
        $q = array_filter($q, fn($v) => $v !== '' && $v !== null);
        return 'profile.php' . (empty($q) ? '' : '?' . http_build_query($q));
    }

    $pageTitle = 'Perfis';
    include __DIR__ . '/include/navbar.php';
?>

    <main class="main-content">
        <div class="container-fluid">

            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                <div>
                    <h1 class="page-title mb-0">Gestão de Perfis</h1>
                    <p class="text-muted mt-1 mb-0">Administração dos perfis de acesso</p>
                </div>
            </div>

            <?php if (!empty($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= e($_GET['success']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if (!empty($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= e($_GET['error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Total</h6><h2 class="card-value"><?= $statsTotal ?></h2></div><div class="card-icon total"><i class="bi bi-person-badge"></i></div></div></div></div>
                <div class="col-xl-3 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Ativos</h6><h2 class="card-value text-success"><?= $statsActive ?></h2></div><div class="card-icon ativos"><i class="bi bi-check-circle"></i></div></div></div></div>
                <div class="col-xl-3 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Inativos</h6><h2 class="card-value"><?= $statsInactive ?></h2></div><div class="card-icon inativos"><i class="bi bi-pause-circle"></i></div></div></div></div>
                <div class="col-xl-3 col-md-6"><div class="card dashboard-card h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted text-uppercase fw-semibold mb-2">Com Permissões</h6><h2 class="card-value"><?= $statsComPerms ?></h2></div><div class="card-icon extra"><i class="bi bi-shield-lock"></i></div></div></div></div>
            </div>

            <div class="filtros-container mb-4">
                <h6 class="filtros-titulo"><i class="bi bi-funnel"></i> Filtrar Perfis</h6>
                <form method="GET" action="profile.php">
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
                            <a href="profile.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i> Limpar</a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card card-principal">
                <div class="card-header-custom">
                    <h5 class="card-header-titulo"><i class="bi bi-person-badge"></i> Perfis de Acesso</h5>
                    <?php if ($canCreate): ?>
                    <button class="btn btn-verde btn-sm" data-bs-toggle="modal" data-bs-target="#novoPerfilModal">
                        <i class="bi bi-plus-circle me-1"></i> Novo Perfil
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <?php if (empty($perfisPag)): ?>
                            <div class="col-12 text-center py-5">
                                <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                                <p class="text-muted mb-0">Nenhum perfil encontrado.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($perfisPag as $p):
                                $pid = (int)$p['PROFILE_ID'];
                                $isAtivo = $p['PROFILE_STATUS'] === 'ACTIVE';
                                $meta = $perfilMeta[$pid] ?? ['perms' => 0, 'users' => 0];
                            ?>
                            <div class="col-xl-4 col-md-6">
                                <div class="perfil-card">
                                    <div class="perfil-card-top">
                                        <div class="perfil-icon <?= $isAtivo ? '' : 'inactive' ?>"><i class="bi bi-person-badge"></i></div>
                                        <div class="perfil-title">
                                            <h6><?= e($p['PROFILE_NAME']) ?></h6>
                                            <small><?= e($p['PROFILE_CODE']) ?></small>
                                        </div>
                                        <span class="status-badge <?= $isAtivo ? 'ativo' : 'inativo' ?>">
                                            <i class="bi bi-circle-fill" style="font-size:6px;"></i>
                                            <?= $isAtivo ? 'Ativo' : 'Inativo' ?>
                                        </span>
                                    </div>
                                    <div class="perfil-card-body">
                                        <div class="perfil-desc"><?= e($p['PROFILE_DESCRIPTION'] ?: '—') ?></div>
                                        <div class="perfil-stats">
                                            <div class="perfil-stat"><div class="val"><?= $meta['perms'] ?></div><div class="lbl">Perms</div></div>
                                            <div class="perfil-stat"><div class="val"><?= $meta['users'] ?></div><div class="lbl">Users</div></div>
                                        </div>
                                    </div>
                                    <div class="perfil-card-footer">
                                        <button class="btn-perfil-action ver"
                                                data-bs-toggle="modal" data-bs-target="#verPerfilModal"
                                                data-id="<?= $pid ?>"
                                                data-nome="<?= e($p['PROFILE_NAME']) ?>"
                                                data-codigo="<?= e($p['PROFILE_CODE']) ?>"
                                                data-descricao="<?= e($p['PROFILE_DESCRIPTION']) ?>"
                                                data-status="<?= e($p['PROFILE_STATUS']) ?>"
                                                data-perms="<?= $meta['perms'] ?>"
                                                data-users="<?= $meta['users'] ?>"
                                                data-criado="<?= e($p['PROFILE_CREATEAT']) ?>">
                                            <i class="bi bi-eye"></i> Ver
                                        </button>
                                        <?php if ($canUpdate): ?>
                                        <button class="btn-perfil-action editar"
                                                data-bs-toggle="modal" data-bs-target="#editarPerfilModal"
                                                data-id="<?= $pid ?>"
                                                data-nome="<?= e($p['PROFILE_NAME']) ?>"
                                                data-codigo="<?= e($p['PROFILE_CODE']) ?>"
                                                data-descricao="<?= e($p['PROFILE_DESCRIPTION']) ?>"
                                                data-status="<?= e($p['PROFILE_STATUS']) ?>">
                                            <i class="bi bi-pencil"></i> Editar
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($canDelete): ?>
                                        <button class="btn-perfil-action apagar"
                                                data-bs-toggle="modal" data-bs-target="#eliminarPerfilModal"
                                                data-id="<?= $pid ?>"
                                                data-nome="<?= e($p['PROFILE_NAME']) ?>"
                                                data-users="<?= $meta['users'] ?>">
                                            <i class="bi bi-trash"></i> Eliminar
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
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
    <div class="modal fade" id="novoPerfilModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form method="POST" action="profile.php?action=create">
                <div class="modal-header modal-header-verde">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Novo Perfil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8"><label class="form-label">Nome <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required maxlength="50"></div>
                        <div class="col-md-4"><label class="form-label">Código</label><input type="text" name="code" class="form-control" maxlength="10" placeholder="Auto"></div>
                        <div class="col-12"><label class="form-label">Descrição</label><textarea name="description" class="form-control" rows="3" maxlength="1000"></textarea></div>
                        <div class="col-12"><label class="form-label">Status</label>
                            <select name="status" class="form-select"><option value="ACTIVE">Ativo</option><option value="INACTIVE" selected>Inativo</option></select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-verde"><i class="bi bi-check-circle me-1"></i>Guardar</button>
                </div>
            </form>
        </div></div>
    </div>
    <?php endif; ?>

    <div class="modal fade" id="verPerfilModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header modal-header-verde">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Detalhes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                    <div class="perfil-icon" id="verIcon"><i class="bi bi-person-badge"></i></div>
                    <div><h5 class="mb-1" id="verNome">—</h5><span class="badge bg-secondary" id="verCodigo">—</span></div>
                </div>
                <p class="mb-3" id="verDescricao">—</p>
                <div class="row g-3">
                    <div class="col-6"><small class="text-muted text-uppercase d-block">Status</small><div id="verStatus">—</div></div>
                    <div class="col-6"><small class="text-muted text-uppercase d-block">Permissões</small><div id="verPerms">—</div></div>
                    <div class="col-6"><small class="text-muted text-uppercase d-block">Utilizadores</small><div id="verUsers">—</div></div>
                    <div class="col-6"><small class="text-muted text-uppercase d-block">Criado</small><div id="verCriado">—</div></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button></div>
        </div></div>
    </div>

    <?php if ($canUpdate): ?>
    <div class="modal fade" id="editarPerfilModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form method="POST" id="formEditarPerfil">
                <div class="modal-header modal-header-verde">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Perfil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8"><label class="form-label">Nome <span class="text-danger">*</span></label><input type="text" name="name" id="editNome" class="form-control" required maxlength="50"></div>
                        <div class="col-md-4"><label class="form-label">Código</label><input type="text" id="editCodigo" class="form-control" readonly></div>
                        <div class="col-12"><label class="form-label">Descrição</label><textarea name="description" id="editDescricao" class="form-control" rows="3" maxlength="1000"></textarea></div>
                        <div class="col-12"><label class="form-label">Status</label>
                            <select name="status" id="editStatus" class="form-select"><option value="ACTIVE">Ativo</option><option value="INACTIVE">Inativo</option></select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-verde"><i class="bi bi-check-circle me-1"></i>Atualizar</button>
                </div>
            </form>
        </div></div>
    </div>
    <?php endif; ?>

    <?php if ($canDelete): ?>
    <div class="modal fade" id="eliminarPerfilModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form method="POST" id="formEliminarPerfil">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Eliminar</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Eliminar o perfil <strong id="delNome">—</strong>?</p>
                    <p class="text-danger small d-none" id="delAviso">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        Este perfil tem <strong id="delUsers">0</strong> utilizador(es) associado(s) e não pode ser eliminado.
                    </p>
                    <label class="form-label">Digite <strong>ELIMINAR</strong> para confirmar:</label>
                    <input type="text" id="delConfirmacao" class="form-control" autocomplete="off">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" id="btnConfirmarEliminar" disabled>Eliminar</button>
                </div>
            </form>
        </div></div>
    </div>
    <?php endif; ?>

    <script>
        document.getElementById('verPerfilModal').addEventListener('show.bs.modal', function (e) {
            const b = e.relatedTarget;
            document.getElementById('verNome').textContent = b.dataset.nome;
            document.getElementById('verCodigo').textContent = b.dataset.codigo;
            document.getElementById('verDescricao').textContent = b.dataset.descricao || '—';
            const st = b.dataset.status;
            const cls = st === 'ACTIVE' ? 'ativo' : 'inativo';
            document.getElementById('verStatus').innerHTML = `<span class="status-badge ${cls}">${st === 'ACTIVE' ? 'Ativo' : 'Inativo'}</span>`;
            document.getElementById('verPerms').textContent = b.dataset.perms;
            document.getElementById('verUsers').textContent = b.dataset.users;
            document.getElementById('verCriado').textContent = (b.dataset.criado || '').split(' ')[0];
        });

        <?php if ($canUpdate): ?>
        document.getElementById('editarPerfilModal').addEventListener('show.bs.modal', function (e) {
            const b = e.relatedTarget;
            document.getElementById('formEditarPerfil').action = 'profile.php?action=update&id=' + b.dataset.id;
            document.getElementById('editNome').value = b.dataset.nome;
            document.getElementById('editCodigo').value = b.dataset.codigo;
            document.getElementById('editDescricao').value = b.dataset.descricao;
            document.getElementById('editStatus').value = b.dataset.status;
        });
        <?php endif; ?>

        <?php if ($canDelete): ?>
        document.getElementById('eliminarPerfilModal').addEventListener('show.bs.modal', function (e) {
            const b = e.relatedTarget;
            const form = document.getElementById('formEliminarPerfil');
            const btn  = document.getElementById('btnConfirmarEliminar');
            const aviso = document.getElementById('delAviso');
            const input = document.getElementById('delConfirmacao');

            form.action = 'profile.php?action=delete&id=' + b.dataset.id;
            document.getElementById('delNome').textContent = b.dataset.nome;
            input.value = ''; btn.disabled = true;

            const users = parseInt(b.dataset.users || '0', 10);
            if (users > 0) {
                aviso.classList.remove('d-none');
                document.getElementById('delUsers').textContent = users;
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