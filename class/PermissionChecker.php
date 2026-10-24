<?php
    require_once __DIR__ . '/Database.php';
    require_once __DIR__ . '/Log.php';

    class PermissionChecker {

        public static function load(): array {
            if (isset($_SESSION['perms']) && is_array($_SESSION['perms'])) {
                return $_SESSION['perms'];
            }
            $userId    = $_SESSION['user_id']    ?? 0;
            $profileId = $_SESSION['profile_id'] ?? 0;
            if (!$userId || !$profileId) return $_SESSION['perms'] = [];

            try {
                $db = new Database();
                $rows = $db->query(
                    "SELECT PERMISSION_CODE FROM PERMISSIONS WHERE PERMISSION_PROFILEID = ?",
                    [$profileId]
                );
                $perms = array_column($rows, 'PERMISSION_CODE');
                $_SESSION['perms'] = $perms;
                return $perms;
            } catch (Throwable $e) {
                Log::error('PermissionChecker::load falhou', 'PERMISSIONS', $e, null, $userId);
                return $_SESSION['perms'] = [];
            }
        }

        public static function flush(): void { unset($_SESSION['perms']); }

        public static function can(string $actionCode, string $moduleCode): bool {
            if (empty($_SESSION['user_id'])) return false;
            if (($_SESSION['profile_code'] ?? '') === 'ADM') return true;

            $code = strtoupper(($_SESSION['profile_code'] ?? '') . '_' . $actionCode . '_' . $moduleCode);
            if (strpos($code, '__') !== false) return false;

            return in_array($code, self::load(), true);
        }

        public static function any(array $pairs): bool {
            foreach ($pairs as $p) if (self::can($p[0], $p[1])) return true;
            return false;
        }

        public static function all(array $pairs): bool {
            foreach ($pairs as $p) if (!self::can($p[0], $p[1])) return false;
            return true;
        }

        public static function require(string $actionCode, string $moduleCode): void {
            if (!self::can($actionCode, $moduleCode)) {
                Log::accessDenied($moduleCode, [
                    'event'  => 'rbac_denied',
                    'action' => $actionCode,
                    'page'   => $_SERVER['REQUEST_URI'] ?? '',
                ], $_SESSION['user_id'] ?? null);
                header('Location: forbidden.php?msg=' . urlencode("Sem permissão para {$actionCode} em {$moduleCode}."));
                exit;
            }
        }

        public static function requireAny(array $pairs): void {
            if (!self::any($pairs)) {
                Log::accessDenied('ANY', ['event' => 'rbac_denied', 'pairs' => $pairs], $_SESSION['user_id'] ?? null);
                header('Location: forbidden.php');
                exit;
            }
        }
    }
?>