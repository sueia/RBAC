<?php
    include __DIR__ . '/header.php';
    require_once __DIR__ . '/../class/PermissionChecker.php';

    $cp = current_page();

    // Cada item é mostrado se o utilizador tiver, pelo menos, LIST no módulo
    $canProfiles = PermissionChecker::can('LIST', 'PROF');
    $canActions  = PermissionChecker::can('LIST', 'ACT');
    $canModules  = PermissionChecker::can('LIST', 'MOD');
    $canPerms    = PermissionChecker::can('LIST', 'PERM');
    $canUsers    = PermissionChecker::can('LIST', 'USR');
    $canSess     = PermissionChecker::can('LIST', 'SESS');
    $canLogs     = PermissionChecker::can('LIST', 'LOG');

    $hasAcl = $canProfiles || $canActions || $canModules || $canPerms;
    $hasUsr = $canUsers || $canSess;
?>
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="logo">
                    <div class="logo-icon"><i class="bi bi-shield-lock"></i></div>
                    <span class="logo-text sidebar-text"><?= APP_NAME ?></span>
                </a>
            </div>
            <div class="sidebar-divider"></div>
            <ul class="sidebar-menu">
                <li class="<?= $cp === 'dashboard.php' ? 'active' : '' ?>">
                    <a href="dashboard.php"><i class="bi bi-house-door"></i><span class="sidebar-text">Dashboard</span></a>
                </li>

                <?php if ($hasAcl): ?>
                <li class="menu-item has-submenu <?= in_array($cp, ['profile.php','action.php','module.php','permission.php']) ? 'active' : '' ?>">
                    <a href="#" class="submenu-toggle">
                        <i class="bi bi-shield-lock"></i>
                        <span class="sidebar-text">Controlo de Acesso</span>
                        <i class="bi bi-chevron-down ms-auto sidebar-text"></i>
                    </a>
                    <ul class="sidebar-submenu <?= in_array($cp, ['profile.php','action.php','module.php','permission.php']) ? 'show' : '' ?>">
                        <?php if ($canProfiles): ?>
                        <li class="<?= $cp === 'profile.php' ? 'active' : '' ?>"><a href="profile.php"><i class="bi bi-person-badge"></i> <span class="sidebar-text">Perfis</span></a></li>
                        <?php endif; ?>
                        <?php if ($canActions): ?>
                        <li class="<?= $cp === 'action.php' ? 'active' : '' ?>"><a href="action.php"><i class="bi bi-list-check"></i> <span class="sidebar-text">Ações</span></a></li>
                        <?php endif; ?>
                        <?php if ($canModules): ?>
                        <li class="<?= $cp === 'module.php' ? 'active' : '' ?>"><a href="module.php"><i class="bi bi-grid-3x3-gap-fill"></i> <span class="sidebar-text">Módulos</span></a></li>
                        <?php endif; ?>
                        <?php if ($canPerms): ?>
                        <li class="<?= $cp === 'permission.php' ? 'active' : '' ?>"><a href="permission.php"><i class="bi bi-shield-check"></i> <span class="sidebar-text">Permissões</span></a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($hasUsr): ?>
                <li class="menu-item has-submenu <?= in_array($cp, ['user.php','session.php']) ? 'active' : '' ?>">
                    <a href="#" class="submenu-toggle">
                        <i class="bi bi-person-circle"></i>
                        <span class="sidebar-text">Utilizadores</span>
                        <i class="bi bi-chevron-down ms-auto sidebar-text"></i>
                    </a>
                    <ul class="sidebar-submenu <?= in_array($cp, ['user.php','session.php']) ? 'show' : '' ?>">
                        <?php if ($canUsers): ?>
                        <li class="<?= $cp === 'user.php' ? 'active' : '' ?>"><a href="user.php"><i class="bi bi-people"></i> <span class="sidebar-text">Lista de Utilizadores</span></a></li>
                        <?php endif; ?>
                        <?php if ($canSess): ?>
                        <li class="<?= $cp === 'session.php' ? 'active' : '' ?>"><a href="session.php"><i class="bi bi-shield-check"></i> <span class="sidebar-text">Sessões</span></a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($canLogs): ?>
                <li class="<?= $cp === 'log.php' ? 'active' : '' ?>">
                    <a href="log.php"><i class="bi bi-journal-text"></i><span class="sidebar-text">Logs</span></a>
                </li>
                <?php endif; ?>

                <div class="sidebar-divider"></div>

                <li>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#sairModal">
                        <i class="bi bi-box-arrow-right"></i><span class="sidebar-text">Sair</span>
                    </a>
                </li>
            </ul>
        </aside>