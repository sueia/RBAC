<?php
    require_once __DIR__ . '/Database.php';
    require_once __DIR__ . '/Sanitize.php';
    require_once __DIR__ . '/Log.php';

    class Auth {

        private Database $db;

        public function __construct() {
            $this->db = new Database();
        }

        public function attempt(string $username, string $password): ?array {
            $username = Sanitize::string($username, 50);
            if ($username === '' || $password === '') return null;

            try {
                $rows = $this->db->query(
                    "SELECT U.*, P.PROFILE_CODE, P.PROFILE_NAME, P.PROFILE_STATUS
                     FROM USERS U
                     INNER JOIN PROFILES P ON U.USER_PROFILEID = P.PROFILE_ID
                     WHERE U.USER_USERNAME = ?
                     LIMIT 1",
                    [$username]
                );
                if (empty($rows)) return null;
                $user = $rows[0];

                if ($user['USER_STATUS'] !== 'ACTIVE') return null;
                if ($user['PROFILE_STATUS'] !== 'ACTIVE') return null;
                if (!Database::verifyPassword($password, $user['USER_PASSWORDHASH'])) {
                    Log::accessDenied('USERS', [
                        'username' => $username,
                        'event'    => 'login_failed_password',
                    ]);
                    return null;
                }

                $this->db->update('USERS', [
                    'USER_LASTLOGIN' => date('Y-m-d H:i:s'),
                ], ['USER_ID' => (int)$user['USER_ID']]);

                return $user;
            } catch (Throwable $e) {
                Log::error('Auth::attempt falhou', 'USERS', $e, ['username' => $username]);
                throw $e;
            }
        }

        public function login(array $user): void {
            try {
                if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);

                $_SESSION['user_id']      = (int)$user['USER_ID'];
                $_SESSION['username']     = $user['USER_USERNAME'];
                $_SESSION['user_name']    = $user['USER_FULLNAME'] ?: $user['USER_USERNAME'];
                $_SESSION['user_email']   = $user['USER_EMAIL'] ?? '';
                $_SESSION['profile_id']   = (int)$user['USER_PROFILEID'];
                $_SESSION['profile_code'] = $user['PROFILE_CODE'];
                $_SESSION['profile_name'] = $user['PROFILE_NAME'];
                $_SESSION['logged_at']    = time();

                // Carrega permissões do perfil
                require_once __DIR__ . '/PermissionChecker.php';
                unset($_SESSION['perms']);
                PermissionChecker::load();

                // Registo da sessão na BD
                $sessionId = session_id() ?: bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', time() + (defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 86400));

                try {
                    $this->db->create('USER_SESSIONS', [
                        'SESSION_ID'        => $sessionId,
                        'SESSION_USERID'    => (int)$user['USER_ID'],
                        'SESSION_IPADDRESS' => $_SERVER['REMOTE_ADDR'] ?? null,
                        'SESSION_USERAGENT' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                        'SESSION_EXPIRESAT' => $expiresAt,
                    ]);
                    $_SESSION['session_record_id'] = $sessionId;
                } catch (Throwable $e) {
                    Log::error('Auth::login — falha USER_SESSIONS', 'USER_SESSIONS', $e, null, (int)$user['USER_ID']);
                }

                Log::login((int)$user['USER_ID']);
            } catch (Throwable $e) {
                Log::error('Auth::login falhou', 'USERS', $e, null, (int)$user['USER_ID']);
                throw $e;
            }
        }

        public function logout(): void {
            $userId   = $_SESSION['user_id'] ?? null;
            $recordId = $_SESSION['session_record_id'] ?? null;
            try {
                if ($recordId) $this->db->delete('USER_SESSIONS', ['SESSION_ID' => $recordId]);
                if ($userId)   Log::logout((int)$userId);
            } catch (Throwable $e) {}

            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
        }
    }
?>