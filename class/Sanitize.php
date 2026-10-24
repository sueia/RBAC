<?php
    /**
     * Sanitize.php — Funções de limpeza/validação.
     * Todos os métodos são estáticos.
     */
    class Sanitize {

        /* ============================================================
           SAÍDA / HTML
           ============================================================ */

        public static function html($v) {
            return htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        /* ============================================================
           STRINGS
           ============================================================ */

        public static function string($v, $maxLen = null) {
            $v = strip_tags((string)$v);
            $v = preg_replace('/[\x00-\x1F\x7F]/u', '', $v);
            $v = trim($v);
            if ($maxLen !== null && mb_strlen($v) > $maxLen) {
                $v = mb_substr($v, 0, $maxLen);
            }
            return $v;
        }

        public static function alnum($v, $maxLen = null) {
            $v = preg_replace('/[^\p{L}\p{N}\s._-]/u', '', (string)$v);
            $v = trim($v);
            if ($maxLen !== null) $v = mb_substr($v, 0, $maxLen);
            return $v;
        }

        public static function email($v) {
            $v = trim((string)$v);
            return filter_var($v, FILTER_VALIDATE_EMAIL) ?: null;
        }

        public static function int($v) {
            $v = filter_var($v, FILTER_VALIDATE_INT);
            return $v === false ? null : (int)$v;
        }

        public static function bool($v) {
            return filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        public static function code($v, $maxLen = 10) {
            $v = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', (string)$v));
            return mb_substr($v, 0, $maxLen);
        }

        public static function slug($v) {
            $v = iconv('UTF-8', 'ASCII//TRANSLIT', (string)$v);
            $v = strtolower((string)$v);
            $v = preg_replace('/[^a-z0-9]+/', '-', $v);
            return trim($v, '-');
        }

        public static function enum($v, array $allowed, $default = null) {
            $v = (string)$v;
            return in_array($v, $allowed, true) ? $v : $default;
        }

        /* ============================================================
           PASSWORD — método que faltava
           ============================================================ */

        /**
         * Valida uma senha. Devolve null se válida, ou string com o erro.
         *
         * @param string $password
         * @param int    $min  Comprimento mínimo (default 6)
         * @return string|null
         */
        public static function password($password, $min = 6) {
            $password = (string)$password;

            if (mb_strlen($password) < $min) {
                return "A senha deve ter pelo menos {$min} caracteres.";
            }
            if (!preg_match('/[A-Za-z]/', $password)) {
                return "A senha deve conter pelo menos uma letra.";
            }
            if (!preg_match('/[0-9]/', $password)) {
                return "A senha deve conter pelo menos um número.";
            }
            return null;
        }

        /* ============================================================
           SQL — identificadores
           ============================================================ */

        public static function identifier($name, array $allowed = null) {
            $name = (string)$name;
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                throw new InvalidArgumentException("Identificador SQL inválido: {$name}");
            }
            if ($allowed !== null && !in_array($name, $allowed, true)) {
                throw new InvalidArgumentException("Identificador não permitido: {$name}");
            }
            return $name;
        }

        /* ============================================================
           JSON
           ============================================================ */

        public static function json($v) {
            json_decode((string)$v);
            return json_last_error() === JSON_ERROR_NONE;
        }
    }
?>