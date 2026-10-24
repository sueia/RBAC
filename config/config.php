<?php
    /* ============================================================
       RBAC — Configuração Global
       ============================================================ */

    // ==================== APLICAÇÃO ====================
    define('APP_NAME',     'RBAC');
    define('APP_VERSION',  '1.0.0');
    define('APP_URL',      'http://localhost/RBAC');
    define('APP_TIMEZONE', 'Africa/Maputo');
    date_default_timezone_set(APP_TIMEZONE);

    // ==================== BANCO DE DADOS ====================
    define('DB_HOST',    '127.0.0.1');         // ← preferir 127.0.0.1 a localhost no Windows
    define('DB_PORT',    '3306');              // ← porta padrão do MySQL Workbench
    define('DB_NAME',    'RBAC');
    define('DB_USER',    'root');
    define('DB_PASS',    'SJSueia1619.');       // ← a tua senha real
    define('DB_CHARSET', 'utf8mb4');
    define('DB_COLLATE', 'utf8mb4_unicode_ci');

    // ==================== SESSÃO / SEGURANÇA ====================
    define('SESSION_NAME',      'RBAC_SESSID');
    define('SESSION_LIFETIME',  86400);
    define('PASSWORD_MIN',      6);
    define('PASSWORD_ALGO',     PASSWORD_DEFAULT);

    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        if (SESSION_LIFETIME > 0) {
            ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_httponly', '1');
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_start();
    }

    // ==================== DEBUG ====================
    define('DEBUG_MODE', true);
    if (DEBUG_MODE) {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    } else {
        error_reporting(0);
        ini_set('display_errors', '0');
    }

    // ==================== HELPERS ====================
    function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

    function initials($name) {
        $parts = preg_split('/\s+/', trim((string)$name));
        if (empty($parts) || $parts[0] === '') return '?';
        if (count($parts) === 1) return strtoupper(substr($parts[0], 0, 2));
        return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
    }

    function fmt_datetime($v) {
        if (empty($v) || $v === '—') return '—';
        $p = explode(' ', (string)$v);
        if (!str_contains($p[0], '-')) return $v;
        [$y, $m, $d] = explode('-', $p[0]);
        return "{$d}/{$m}/{$y}" . (isset($p[1]) ? ' ' . substr($p[1], 0, 5) : '');
    }

    function fmt_date($v) {
        if (empty($v) || $v === '—' || !str_contains((string)$v, '-')) return $v;
        [$y, $m, $d] = explode('-', (string)$v);
        return "{$d}/{$m}/{$y}";
    }

    function current_page() { return basename($_SERVER['PHP_SELF'] ?? ''); }

    function acronym($text, $length = 4) {
        $ignore = ['a','e','o','de','da','do','em','para','com','sem','por'];
        $words  = preg_split('/\s+/', trim((string)$text));
        $sigla  = '';
        foreach ($words as $w) {
            if ($w === '' || in_array(mb_strtolower($w), $ignore, true)) continue;
            $sigla .= mb_strtoupper(mb_substr($w, 0, 1));
            if (mb_strlen($sigla) >= $length) break;
        }
        return $sigla !== '' ? $sigla : mb_strtoupper(mb_substr(trim((string)$text), 0, $length));
    }
?>