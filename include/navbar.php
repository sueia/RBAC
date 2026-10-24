<?php
    include __DIR__ . '/sidebar.php';
    require_once __DIR__ . '/../class/PermissionChecker.php';

    $nomeUser  = $_SESSION['user_name']    ?? 'Utilizador';
    $perfilTxt = ($_SESSION['profile_code'] ?? '') . ' — ' . ($_SESSION['profile_name'] ?? '');
    $iniciais  = initials($nomeUser);
?>
        <nav class="navbar-custom">
            <div class="container-fluid d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <button class="sidebar-toggle" id="sidebarToggle" title="Alternar menu">
                        <i class="bi bi-list"></i>
                    </button>
                    <h6 class="mb-0 text-muted d-none d-md-block">
                        <i class="bi bi-calendar3 me-1"></i><span id="navbarDate"><?= date('d/m/Y') ?></span>
                    </h6>
                </div>
                <div class="user-dropdown">
                    <div class="dropdown">
                        <button class="dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <div class="user-avatar"><?= e($iniciais) ?></div>
                            <span class="d-none d-md-inline"><?= e($nomeUser) ?></span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header"><i class="bi bi-person-circle me-1"></i><?= e($nomeUser) ?></h6></li>
                            <li><small class="dropdown-item-text text-muted small"><?= e($perfilTxt) ?></small></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#perfilModal"><i class="bi bi-person me-2"></i>Perfil</a></li>
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#senhaModal"><i class="bi bi-key me-2"></i>Mudar Senha</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#sairModal"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>