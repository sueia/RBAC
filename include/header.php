<?php
    require_once __DIR__ . '/../config/config.php';
    $pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="RBAC — Controlo de Acessos Baseado em Perfis">
    
        <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
        <link rel="stylesheet" href="asset/css/style.css">
    </head>
    <body>