<?php
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/class/Database.php';
    require_once __DIR__ . '/class/Sanitize.php';
    require_once __DIR__ . '/class/Log.php';

    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
        exit;
    }

    $email = Sanitize::email($_POST['email'] ?? '');

    if ($email === null) {
        echo json_encode(['success' => false, 'message' => 'Email inválido.']);
        exit;
    }

    try {
        $db = new Database();

        $rows = $db->query(
            "SELECT USER_ID, USER_USERNAME, USER_EMAIL, USER_FULLNAME
             FROM USERS
             WHERE USER_EMAIL = ?
             LIMIT 1",
            [$email]
        );

        // Regista sempre (não revela se o email existe)
        Log::accessDenied('USERS', [
            'event' => 'password_reset_request',
            'email' => $email,
            'found' => !empty($rows),
            'ip'    => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Se o email existir na nossa base, receberá instruções para redefinir a senha.',
        ]);
    } catch (Throwable $e) {
        Log::error('forgot_password.php falhou', 'USERS', $e, ['email' => $email]);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao processar. Tente novamente.',
        ]);
    }
?>