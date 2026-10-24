<?php
    /**
     * index.php — Ponto de entrada do sistema RBAC.
     *
     * Decide o destino com base no estado da sessão:
     *  - Utilizador autenticado  → dashboard.php
     *  - Utilizador anónimo      → login.php
     */

    require_once __DIR__ . '/config/config.php';

    // Já tem sessão ativa? → dashboard
    if (!empty($_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit;
    }

    // Caso contrário → login
    header('Location: login.php');
    exit;
?>