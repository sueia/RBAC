<?php
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/class/Auth.php';

    (new Auth())->logout();

    header('Location: login.php?success=' . urlencode('Sessão terminada.'));
    exit;
?>