<?php
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/class/Auth.php';
    require_once __DIR__ . '/class/Sanitize.php';
    require_once __DIR__ . '/class/Log.php';

    if (!empty($_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit;
    }

    $erro = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $username = Sanitize::string($_POST['username'] ?? '', 50);
            $password = $_POST['password'] ?? '';

            if ($username === '' || $password === '') {
                $erro = 'Preencha o utilizador e a senha.';
            } else {
                $auth = new Auth();
                $user = $auth->attempt($username, $password);

                if ($user === null) {
                    $erro = 'Utilizador ou senha inválidos. Tente novamente.';
                    Log::accessDenied('USERS', [
                        'username' => $username,
                        'event'    => 'login_failed',
                        'ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
                    ]);
                } else {
                    $auth->login($user);
                    header('Location: dashboard.php');
                    exit;
                }
            }
        } catch (Throwable $e) {
            $erro = (defined('DEBUG_MODE') && DEBUG_MODE)
                ? 'Erro: ' . $e->getMessage()
                : 'Erro ao processar o pedido.';
        }
    }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= APP_NAME ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">

    <style>
        /* ============================================================
           VARIÁVEIS — TEMA CLARO
           ============================================================ */
        :root {
            --verde:        #10b981;
            --verde-escuro: #059669;
            --verde-claro:  #d1fae5;

            --bg-body:      #f5f7fb;
            --bg-surface:   #ffffff;
            --bg-soft:      #f1f5f9;
            --bg-hover:     #e2e8f0;

            --text-strong:  #1e293b;
            --text-muted:   #64748b;

            --border:       #e2e8f0;
            --sombra:       rgba(0, 0, 0, 0.08);
            --radius:       12px;

            --font-scale: 1;

            --bs-body-bg:      var(--bg-body);
            --bs-body-color:   var(--text-strong);
            --bs-border-color: var(--border);
        }

        /* TEMA ESCURO */
        [data-theme="dark"] {
            --bg-body:      #0b1220;
            --bg-surface:   #111a2e;
            --bg-soft:      #1a2540;
            --bg-hover:     #223050;
            --text-strong:  #e6edf7;
            --text-muted:   #8fa0b8;
            --border:       #223050;
            --sombra:       rgba(0, 0, 0, 0.5);
            --verde-claro:  rgba(16, 185, 129, 0.18);
            --bs-body-bg:      var(--bg-body);
            --bs-body-color:   var(--text-strong);
            --bs-border-color: var(--border);
            color-scheme: dark;
        }

        /* BASE */
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        html { font-size: calc(16px * var(--font-scale)); }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-body);
            color: var(--text-strong);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            transition: background-color 0.25s ease, color 0.25s ease;
        }
        a { text-decoration: none; }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-soft); }
        ::-webkit-scrollbar-thumb { background: var(--verde); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--verde-escuro); }

        [data-theme="dark"] .form-control,
        [data-theme="dark"] .input-group-text {
            background-color: var(--bg-surface);
            color: var(--text-strong);
            border-color: var(--border);
        }
        [data-theme="dark"] .form-control::placeholder { color: var(--text-muted); }
        [data-theme="dark"] .form-control:focus {
            background-color: var(--bg-surface);
            color: var(--text-strong);
            border-color: var(--verde);
            box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.25);
        }

        /* LAYOUT */
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow: hidden;
        }

        .login-wrapper::before,
        .login-wrapper::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(90px);
            opacity: 0.35;
            pointer-events: none;
            z-index: 0;
        }
        .login-wrapper::before {
            width: 480px; height: 480px;
            background: var(--verde);
            top: -160px; left: -120px;
        }
        .login-wrapper::after {
            width: 420px; height: 420px;
            background: #3b82f6;
            bottom: -150px; right: -100px;
            opacity: 0.22;
        }
        [data-theme="dark"] .login-wrapper::before { opacity: 0.18; }
        [data-theme="dark"] .login-wrapper::after  { opacity: 0.12; }

        /* CARD */
        .login-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 430px;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 24px 60px var(--sombra);
            overflow: hidden;
            animation: cardIn 0.45s ease;
        }
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(18px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .login-header {
            background: linear-gradient(135deg, var(--verde) 0%, var(--verde-escuro) 100%);
            padding: 34px 28px 28px;
            text-align: center;
            color: #fff;
        }
        .login-logo {
            width: 64px; height: 64px;
            margin: 0 auto 14px;
            background: rgba(255,255,255,0.2);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 30px;
            backdrop-filter: blur(4px);
        }
        .login-header h1 {
            font-size: 1.45rem;
            font-weight: 700;
            margin: 0 0 4px;
            letter-spacing: 0.3px;
        }
        .login-header p {
            margin: 0;
            font-size: 0.86rem;
            opacity: 0.9;
        }

        .login-body { padding: 28px; }

        .login-body .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-strong);
            margin-bottom: 6px;
        }

        .input-group-text {
            background: var(--bg-soft);
            border-color: var(--border);
            color: var(--text-muted);
        }
        .form-control {
            background: var(--bg-surface);
            color: var(--text-strong);
            border-color: var(--border);
            padding: 10px 12px;
            font-size: 0.92rem;
        }
        .form-control:focus {
            border-color: var(--verde);
            box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.2);
        }

        .toggle-senha {
            background: var(--bg-soft);
            border: 1px solid var(--border);
            border-left: none;
            color: var(--text-muted);
            padding: 0 12px;
            cursor: pointer;
            transition: color 0.15s, background 0.15s;
        }
        .toggle-senha:hover { color: var(--verde-escuro); background: var(--bg-hover); }

        .login-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            font-size: 0.85rem;
            margin: 4px 0 18px;
        }
        .form-check-label { color: var(--text-muted); font-size: 0.85rem; cursor: pointer; }
        .form-check-input:checked {
            background-color: var(--verde);
            border-color: var(--verde);
        }
        .form-check-input:focus {
            border-color: var(--verde);
            box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.2);
        }
        .link-verde {
            color: var(--verde-escuro);
            font-weight: 600;
        }
        .link-verde:hover { color: var(--verde); text-decoration: underline; }
        [data-theme="dark"] .link-verde { color: #6ee7b7; }
        [data-theme="dark"] .link-verde:hover { color: #a7f3d0; }

        .btn-verde {
            background: var(--verde);
            border-color: var(--verde);
            color: #fff;
            font-weight: 600;
            padding: 11px 16px;
            font-size: 0.95rem;
            border-radius: 10px;
            transition: background 0.2s, border-color 0.2s, transform 0.1s;
        }
        .btn-verde:hover {
            background: var(--verde-escuro);
            border-color: var(--verde-escuro);
            color: #fff;
        }
        .btn-verde:active { transform: scale(0.99); }
        .btn-verde:disabled { opacity: 0.7; }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
            color: var(--text-muted);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .login-footer {
            text-align: center;
            font-size: 0.83rem;
            color: var(--text-muted);
            padding: 0 0 4px;
        }

        .login-page-footer {
            position: relative;
            z-index: 1;
            text-align: center;
            margin-top: 18px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .login-alert {
            display: none;
            align-items: flex-start;
            gap: 10px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #b91c1c;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.85rem;
            margin-bottom: 16px;
            animation: slideDown 0.25s ease;
        }
        .login-alert.show { display: flex; }
        [data-theme="dark"] .login-alert {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.4);
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* TOAST */
        .toast-center-container {
            position: fixed; top: 25%; left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            min-width: 320px; max-width: 500px;
            pointer-events: none;
        }
        .toast-center {
            pointer-events: auto;
            background: var(--bg-surface);
            color: var(--text-strong);
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
            overflow: hidden;
            margin-bottom: 12px;
            animation: slideDown 0.3s ease;
            border-left: 5px solid;
            display: flex; flex-direction: column;
        }
        .toast-success { border-left-color: #10b981; }
        .toast-danger  { border-left-color: #ef4444; }
        .toast-warning { border-left-color: #f59e0b; }
        .toast-info    { border-left-color: #06b6d4; }
        .toast-body-custom { padding: 14px 16px; display: flex; align-items: flex-start; gap: 12px; }
        .toast-icon { font-size: 1.6rem; flex-shrink: 0; }
        .toast-message { flex: 1; font-size: 0.92rem; color: var(--text-strong); line-height: 1.4; }
        .toast-close { background: transparent; border: none; font-size: 1.3rem; color: var(--text-muted); cursor: pointer; padding: 0; line-height: 1; }
        .toast-progress { height: 3px; background: var(--bg-soft); }
        .toast-progress-bar { height: 100%; background: var(--text-muted); transition: width linear; }
        .toast-success .toast-progress-bar { background: #10b981; }
        .toast-danger  .toast-progress-bar { background: #ef4444; }
        .toast-warning .toast-progress-bar { background: #f59e0b; }
        .toast-info    .toast-progress-bar { background: #06b6d4; }
        .toast-fade-out { animation: fadeUp 0.3s ease forwards; }
        @keyframes fadeUp {
            from { opacity: 1; transform: translateY(0); }
            to   { opacity: 0; transform: translateY(-20px); }
        }

        /* ACESSIBILIDADE */
        .a11y-fab {
            position: fixed;
            right: 20px; bottom: 20px;
            width: 56px; height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--verde), var(--verde-escuro));
            color: #fff;
            border: none;
            cursor: pointer;
            font-size: 1.5rem;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 6px 18px rgba(16, 185, 129, 0.45);
            z-index: 1050;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .a11y-fab:hover { transform: scale(1.08); box-shadow: 0 8px 24px rgba(16, 185, 129, 0.65); }
        .a11y-fab:focus-visible { outline: 3px solid #fbbf24; outline-offset: 3px; }

        .a11y-panel {
            position: fixed;
            right: 20px; bottom: 90px;
            width: 280px;
            max-width: calc(100vw - 40px);
            background: var(--bg-surface);
            color: var(--text-strong);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 24px 48px rgba(0,0,0,0.25);
            z-index: 1050;
            opacity: 0;
            transform: translateY(14px) scale(0.96);
            pointer-events: none;
            transition: opacity 0.22s ease, transform 0.22s ease;
            overflow: hidden;
        }
        .a11y-panel.open { opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; }
        .a11y-body { padding: 16px; }
        .a11y-row {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }
        .a11y-row:last-child { border-bottom: none; }
        .a11y-row-label { display: flex; align-items: center; gap: 10px; font-size: 0.9rem; font-weight: 500; color: var(--text-strong); }
        .a11y-row-label i { font-size: 1.05rem; color: var(--verde); width: 22px; text-align: center; }
        .a11y-font-group {
            display: flex; align-items: center; gap: 6px;
            background: var(--bg-soft);
            border-radius: 10px; padding: 4px;
        }
        .a11y-font-btn {
            width: 32px; height: 32px;
            border-radius: 8px; border: none;
            background: transparent; color: var(--text-strong);
            font-weight: 700; font-size: 0.95rem; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.15s, color 0.15s;
        }
        .a11y-font-btn:hover { background: var(--verde); color: #fff; }
        .a11y-font-btn:disabled { opacity: 0.35; cursor: not-allowed; }
        .a11y-font-btn:disabled:hover { background: transparent; color: var(--text-strong); }
        .a11y-font-value {
            min-width: 44px; text-align: center;
            font-size: 0.78rem; font-weight: 700;
            color: var(--verde-escuro); background: var(--verde-claro);
            border-radius: 6px; padding: 5px 6px;
        }
        .a11y-switch {
            position: relative;
            width: 42px; height: 24px;
            background: var(--bg-hover); border-radius: 12px;
            cursor: pointer; transition: background 0.2s;
            flex-shrink: 0; border: none;
        }
        .a11y-switch::after {
            content: ''; position: absolute;
            top: 3px; left: 3px;
            width: 18px; height: 18px;
            background: #fff; border-radius: 50%;
            transition: transform 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .a11y-switch.on { background: var(--verde); }
        .a11y-switch.on::after { transform: translateX(18px); }
        .a11y-switch:focus-visible { outline: 2px solid var(--verde); outline-offset: 2px; }
        .a11y-reset {
            width: 100%; margin-top: 12px; padding: 10px;
            background: var(--bg-soft); border: 1px solid var(--border);
            color: var(--text-strong); border-radius: 10px;
            font-size: 0.85rem; font-weight: 600; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: background 0.15s, color 0.15s;
        }
        .a11y-reset:hover { background: var(--verde); color: #fff; border-color: var(--verde); }

        /* ============================================================
           MODAL ESQUECEU A SENHA
           ============================================================ */
        .modal-content {
            background: var(--bg-surface);
            color: var(--text-strong);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
        }
        .modal-header-verde {
            background: linear-gradient(135deg, var(--verde) 0%, var(--verde-escuro) 100%);
            color: #fff;
            border: none;
            padding: 16px 20px;
        }
        .modal-header-verde .btn-close { filter: brightness(0) invert(1); opacity: 0.9; }
        .modal-header-verde .modal-title { font-weight: 600; }
        .modal-body .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-strong);
        }
        .modal-body .form-control {
            background: var(--bg-soft);
            color: var(--text-strong);
            border-color: var(--border);
            padding: 10px 12px;
        }
        .modal-body .form-control:focus {
            background: var(--bg-surface);
            border-color: var(--verde);
            box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.2);
            color: var(--text-strong);
        }
        [data-theme="dark"] .modal-body .form-control {
            background: var(--bg-soft);
            color: var(--text-strong);
            border-color: var(--border);
        }
        [data-theme="dark"] .modal-body .form-control:focus {
            background: var(--bg-surface);
        }
        .modal-footer { border-color: var(--border); }

        /* RESPONSIVO */
        @media (max-width: 575.98px) {
            .login-wrapper { padding: 16px; }
            .login-body { padding: 22px 20px; }
            .login-header { padding: 28px 20px 24px; }
            .login-logo { width: 56px; height: 56px; font-size: 26px; }
            .login-header h1 { font-size: 1.25rem; }
            .a11y-panel { right: 10px; left: 10px; width: auto; bottom: 88px; }
            .a11y-fab { right: 15px; bottom: 15px; width: 52px; height: 52px; }
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div style="width:100%;max-width:430px;">
            <div class="login-card">

                <!-- Cabeçalho -->
                <div class="login-header">
                    <div class="login-logo"><i class="bi bi-shield-lock"></i></div>
                    <h1><?= APP_NAME ?></h1>
                    <p>Controlo de Acessos Baseado em Perfis</p>
                </div>

                <!-- Corpo -->
                <div class="login-body">

                    <div class="login-alert <?= !empty($erro) ? 'show' : '' ?>" id="loginAlert">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size:1.1rem;margin-top:1px;"></i>
                        <span id="loginAlertMsg"><?= e($erro ?: 'Credenciais inválidas.') ?></span>
                    </div>

                    <?php if (!empty($_GET['success'])): ?>
                        <div class="alert alert-success d-flex align-items-center py-2 mb-3"
                             style="background:rgba(16,185,129,0.12);color:var(--verde-escuro);border:none;border-radius:10px;font-size:0.85rem;">
                            <i class="bi bi-check-circle me-2"></i>
                            <div><?= e($_GET['success']) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($_GET['error'])): ?>
                        <div class="alert alert-danger d-flex align-items-center py-2 mb-3"
                             style="background:rgba(239,68,68,0.12);color:#b91c1c;border:none;border-radius:10px;font-size:0.85rem;">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <div><?= e($_GET['error']) ?></div>
                        </div>
                    <?php endif; ?>

                    <form id="formLogin" method="POST" action="login.php" novalidate>
                        <div class="mb-3">
                            <label for="loginUser" class="form-label">Utilizador</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="loginUser" name="username"
                                       placeholder="O seu username" autocomplete="username"
                                       value="<?= e($_POST['username'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="loginPass" class="form-label">Senha</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="loginPass" name="password"
                                       placeholder="A sua senha" autocomplete="current-password"
                                       minlength="<?= PASSWORD_MIN ?>" required>
                                <button type="button" class="toggle-senha" id="toggleSenha" title="Mostrar senha" aria-label="Mostrar senha">
                                    <i class="bi bi-eye" id="toggleSenhaIcon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="login-options">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="lembrarMe">
                                <label class="form-check-label" for="lembrarMe">Lembrar-me</label>
                            </div>
                            <a href="#" class="link-verde" id="linkRecuperar"
                               data-bs-toggle="modal" data-bs-target="#forgotModal">
                                Esqueceu a senha?
                            </a>
                        </div>

                        <button type="submit" class="btn btn-verde w-100" id="btnEntrar">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Entrar
                        </button>
                    </form>

                    <div class="divider">ou</div>

                    <div class="login-footer">
                        Não tem conta? <a href="#" class="link-verde" id="linkSuporte">Contacte o administrador</a>
                    </div>
                </div>
            </div>

            <div class="login-page-footer">
                &copy; <span id="footerYear"><?= date('Y') ?></span> <?= APP_NAME ?> — Todos os direitos reservados.
            </div>
        </div>
    </div>

    <!-- ==================== MODAL ESQUECEU A SENHA ==================== -->
    <div class="modal fade" id="forgotModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-verde">
                    <h5 class="modal-title">
                        <i class="bi bi-key me-2"></i>Recuperar Senha
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <form id="formForgotPassword">
                    <div class="modal-body">

                        <p class="text-muted small mb-3">
                            Introduza o email associado à sua conta. Se existir,
                            receberá instruções para redefinir a senha.
                        </p>

                        <div class="mb-3">
                            <label class="form-label" for="forgotEmail">
                                Email <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email"
                                       id="forgotEmail"
                                       name="email"
                                       class="form-control"
                                       required
                                       maxlength="100"
                                       placeholder="exemplo@rbac.local">
                            </div>
                        </div>

                        <div id="forgotFeedback" class="d-none"></div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-verde" id="btnForgotSubmit">
                            <i class="bi bi-send me-1"></i> Enviar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="toastCenterContainer" class="toast-center-container"></div>

    <!-- ==================== ACESSIBILIDADE ==================== -->
    <button class="a11y-fab" id="a11yFab" aria-label="Abrir painel de acessibilidade" aria-expanded="false">
        <i class="bi bi-universal-access"></i>
    </button>

    <div class="a11y-panel" id="a11yPanel" role="dialog" aria-label="Painel de acessibilidade">
        <div class="a11y-body">
            <div class="a11y-row">
                <span class="a11y-row-label"><i class="bi bi-fonts"></i> Tamanho da fonte</span>
                <div class="a11y-font-group">
                    <button class="a11y-font-btn" id="fontMinus" aria-label="Diminuir">A-</button>
                    <span class="a11y-font-value" id="fontValue">100%</span>
                    <button class="a11y-font-btn" id="fontPlus" aria-label="Aumentar">A+</button>
                </div>
            </div>
            <div class="a11y-row">
                <span class="a11y-row-label"><i class="bi bi-moon-stars"></i> Modo escuro</span>
                <button class="a11y-switch" id="themeSwitch" aria-label="Alternar modo escuro" aria-pressed="false"></button>
            </div>
            <button class="a11y-reset" id="resetAll">
                <i class="bi bi-arrow-counterclockwise"></i> Restaurar padrão
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        (function () {
            'use strict';

            /* ==================== TOAST ==================== */
            function escapeHtml(s) {
                if (!s) return '';
                return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
            }
            function showToast(type, message, duration = 4500) {
                const container = document.getElementById('toastCenterContainer');
                const configs = {
                    success: { cls:'toast-success', icon:'bi-check-circle-fill',    color:'#10b981' },
                    danger:  { cls:'toast-danger',  icon:'bi-exclamation-triangle-fill', color:'#ef4444' },
                    warning: { cls:'toast-warning', icon:'bi-exclamation-circle-fill',   color:'#f59e0b' },
                    info:    { cls:'toast-info',    icon:'bi-info-circle-fill',    color:'#06b6d4' }
                };
                const cfg = configs[type] || configs.info;
                const id  = 'toast_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6);
                container.insertAdjacentHTML('beforeend', `
                    <div id="${id}" class="toast-center ${cfg.cls}" role="alert">
                        <div class="toast-body-custom">
                            <div class="toast-icon" style="color:${cfg.color};"><i class="bi ${cfg.icon}"></i></div>
                            <div class="toast-message">${escapeHtml(message)}</div>
                            <button type="button" class="toast-close" aria-label="Fechar">&times;</button>
                        </div>
                        <div class="toast-progress"><div class="toast-progress-bar" style="width:100%;"></div></div>
                    </div>`);
                const el  = document.getElementById(id);
                const bar = el.querySelector('.toast-progress-bar');
                const btn = el.querySelector('.toast-close');
                let raf = null, tId = null, dead = false;
                function destroy() {
                    if (dead) return;
                    dead = true;
                    if (raf) cancelAnimationFrame(raf);
                    if (tId) clearTimeout(tId);
                    el.classList.add('toast-fade-out');
                    setTimeout(() => el.remove(), 300);
                }
                function tick(start) {
                    const elapsed = performance.now() - start;
                    bar.style.width = Math.max(0, 100 - (elapsed / duration) * 100) + '%';
                    if (elapsed < duration) raf = requestAnimationFrame(() => tick(start));
                    else destroy();
                }
                raf = requestAnimationFrame(() => tick(performance.now()));
                tId = setTimeout(destroy, duration + 100);
                btn.addEventListener('click', destroy);
            }
            window.showToast = showToast;

            /* ==================== MOSTRAR/OCULTAR SENHA ==================== */
            const senhaInput  = document.getElementById('loginPass');
            const toggleSenha = document.getElementById('toggleSenha');
            const toggleIcon  = document.getElementById('toggleSenhaIcon');

            toggleSenha.addEventListener('click', () => {
                const visivel = senhaInput.type === 'text';
                senhaInput.type = visivel ? 'password' : 'text';
                toggleIcon.className = visivel ? 'bi bi-eye' : 'bi bi-eye-slash';
                toggleSenha.setAttribute('aria-label', visivel ? 'Mostrar senha' : 'Ocultar senha');
                toggleSenha.title = visivel ? 'Mostrar senha' : 'Ocultar senha';
                senhaInput.focus();
            });

            /* ==================== FORM LOGIN ==================== */
            const formLogin  = document.getElementById('formLogin');
            const loginUser  = document.getElementById('loginUser');
            const loginPass  = document.getElementById('loginPass');
            const btnEntrar  = document.getElementById('btnEntrar');
            const loginAlert = document.getElementById('loginAlert');
            const loginAlertMsg = document.getElementById('loginAlertMsg');

            function mostrarErro(msg) {
                loginAlertMsg.textContent = msg;
                loginAlert.classList.add('show');
            }
            function limparErro() {
                loginAlert.classList.remove('show');
            }

            formLogin.addEventListener('submit', function (e) {
                limparErro();

                const user = loginUser.value.trim();
                const pass = loginPass.value;

                if (!user) {
                    e.preventDefault();
                    mostrarErro('Informe o seu utilizador.');
                    loginUser.focus();
                    return;
                }
                if (!pass) {
                    e.preventDefault();
                    mostrarErro('Informe a sua senha.');
                    loginPass.focus();
                    return;
                }

                btnEntrar.disabled = true;
                btnEntrar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> A entrar...';
                // O form faz POST real para login.php
            });

            [loginUser, loginPass].forEach(el => el.addEventListener('input', limparErro));

            /* ==================== FORM ESQUECEU A SENHA ==================== */
            const formForgot = document.getElementById('formForgotPassword');
            const forgotEmail = document.getElementById('forgotEmail');
            const btnForgot = document.getElementById('btnForgotSubmit');
            const forgotFeedback = document.getElementById('forgotFeedback');

            formForgot.addEventListener('submit', async function (e) {
                e.preventDefault();

                forgotFeedback.className = 'd-none';
                forgotFeedback.innerHTML = '';
                btnForgot.disabled = true;
                btnForgot.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A enviar...';

                try {
                    const fd = new FormData();
                    fd.append('email', forgotEmail.value.trim());

                    const res = await fetch('forgot_password.php', {
                        method: 'POST',
                        body: fd,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    const data = await res.json();

                    forgotFeedback.className = 'alert ' + (data.success ? 'alert-success' : 'alert-danger');
                    forgotFeedback.innerHTML = `<i class="bi bi-${data.success ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${escapeHtml(data.message)}`;
                    forgotFeedback.classList.remove('d-none');

                    if (data.success) {
                        forgotEmail.value = '';
                        setTimeout(() => {
                            const modal = bootstrap.Modal.getInstance(document.getElementById('forgotModal'));
                            if (modal) modal.hide();
                        }, 2500);
                    }
                } catch (err) {
                    forgotFeedback.className = 'alert alert-danger';
                    forgotFeedback.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Erro de rede. Tente novamente.';
                    forgotFeedback.classList.remove('d-none');
                } finally {
                    btnForgot.disabled = false;
                    btnForgot.innerHTML = '<i class="bi bi-send me-1"></i> Enviar';
                }
            });

            document.getElementById('forgotModal').addEventListener('show.bs.modal', function () {
                forgotFeedback.className = 'd-none';
                forgotFeedback.innerHTML = '';
                forgotEmail.value = '';
            });

            /* ==================== LINK SUPORTE ==================== */
            document.getElementById('linkSuporte').addEventListener('click', (e) => {
                e.preventDefault();
                showToast('info', 'Contacte o administrador do sistema para obter uma conta.');
            });

            /* ==================== ACESSIBILIDADE ==================== */
            const a11yFab     = document.getElementById('a11yFab');
            const a11yPanel   = document.getElementById('a11yPanel');
            const fontMinus   = document.getElementById('fontMinus');
            const fontPlus    = document.getElementById('fontPlus');
            const fontValue   = document.getElementById('fontValue');
            const themeSwitch = document.getElementById('themeSwitch');
            const resetAll    = document.getElementById('resetAll');
            const html        = document.documentElement;

            const FONT_MIN = 0.8, FONT_MAX = 1.5, FONT_STEP = 0.1;
            const state = { fontScale: 1, theme: 'light' };

            try {
                const saved = JSON.parse(localStorage.getItem('rbac-a11y') || '{}');
                if (saved.fontScale) state.fontScale = saved.fontScale;
                if (saved.theme)     state.theme     = saved.theme;
            } catch (_) {}

            function savePrefs() { localStorage.setItem('rbac-a11y', JSON.stringify(state)); }
            function applyA11y() {
                html.style.setProperty('--font-scale', state.fontScale);
                html.setAttribute('data-theme', state.theme);
                fontValue.textContent = Math.round(state.fontScale * 100) + '%';
                fontMinus.disabled = state.fontScale <= FONT_MIN + 0.001;
                fontPlus.disabled  = state.fontScale >= FONT_MAX - 0.001;
                themeSwitch.classList.toggle('on', state.theme === 'dark');
                themeSwitch.setAttribute('aria-pressed', state.theme === 'dark');
                savePrefs();
            }
            function openPanel(open) {
                a11yPanel.classList.toggle('open', open);
                a11yFab.setAttribute('aria-expanded', open ? 'true' : 'false');
                a11yFab.innerHTML = open ? '<i class="bi bi-x-lg"></i>' : '<i class="bi bi-universal-access"></i>';
            }

            a11yFab.addEventListener('click', () => openPanel(!a11yPanel.classList.contains('open')));
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape' && a11yPanel.classList.contains('open')) openPanel(false);
            });
            document.addEventListener('click', e => {
                if (!a11yPanel.classList.contains('open')) return;
                if (a11yPanel.contains(e.target) || a11yFab.contains(e.target)) return;
                openPanel(false);
            });

            fontMinus.addEventListener('click', () => { state.fontScale = Math.max(FONT_MIN, +(state.fontScale - FONT_STEP).toFixed(2)); applyA11y(); });
            fontPlus.addEventListener('click',  () => { state.fontScale = Math.min(FONT_MAX, +(state.fontScale + FONT_STEP).toFixed(2)); applyA11y(); });
            themeSwitch.addEventListener('click',() => { state.theme = state.theme === 'dark' ? 'light' : 'dark'; applyA11y(); showToast('info', state.theme === 'dark' ? 'Modo escuro ativado.' : 'Modo claro ativado.'); });
            resetAll.addEventListener('click',  () => { state.fontScale = 1; state.theme = 'light'; applyA11y(); showToast('success', 'Preferências restauradas.'); });

            applyA11y();

            /* ==================== FOCO INICIAL ==================== */
            window.addEventListener('load', () => loginUser.focus());

        })();
    </script>
</body>
</html>