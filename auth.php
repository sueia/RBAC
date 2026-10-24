<?php
    require_once __DIR__ . '/config/config.php';

    if (empty($_SESSION['user_id'])) {
        header('Location: login.php?error=' . urlencode('É necessário iniciar sessão.'));
        exit;
    }

    $maxIdle = 7200;
    if (!empty($_SESSION['logged_at']) && (time() - $_SESSION['logged_at']) > $maxIdle) {
        require_once __DIR__ . '/class/Auth.php';
        (new Auth())->logout();
        header('Location: login.php?error=' . urlencode('Sessão expirada.'));
        exit;
    }
    $_SESSION['logged_at'] = time();

    require_once __DIR__ . '/class/PermissionChecker.php';
    PermissionChecker::load();
?>