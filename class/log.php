<?php
    require_once __DIR__ . '/../config/config.php';

    /**
     * Log.php — Auditoria e erros.
     *
     * Grava em LOGS:
     *  - INSERT / UPDATE / DELETE / LOGIN / LOGOUT  (ações normais)
     *  - ACCESS_DENIED                              (tentativas de acesso não autorizado)
     *  - ERROR                                      (erros técnicos / exceções)
     *
     * Métodos estáticos — não precisa de instância.
     * É a ÚNICA classe que escreve na tabela LOGS.
     */
    class Log {

        /**
         * Registo genérico.
         */
        public static function write(
            string $action,
            string $table,
            ?int $recordId = null,
            ?array $oldData = null,
            ?array $newData = null,
            ?int $userId = null
        ): void {
            try {
                $allowed = ['INSERT','UPDATE','DELETE','LOGIN','LOGOUT','ACCESS_DENIED','ERROR'];
                if (!in_array($action, $allowed, true)) {
                    $action = 'ERROR';
                }

                $userId = $userId ?? (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);

                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);

                $sql = "INSERT INTO LOGS
                            (LOG_USER_ID, LOG_ACTION, LOG_TABLE_NAME, LOG_RECORD_ID,
                             LOG_OLD_DATA, LOG_NEW_DATA, LOG_IPADDRESS)
                        VALUES (:user, :action, :tbl, :rec, :old, :new, :ip)";

                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':user',   $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmt->bindValue(':action', $action, PDO::PARAM_STR);
                $stmt->bindValue(':tbl',    mb_substr($table, 0, 100), PDO::PARAM_STR);
                $stmt->bindValue(':rec',    $recordId, $recordId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmt->bindValue(':old',    $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
                                $oldData ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindValue(':new',    $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
                                $newData ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindValue(':ip',     $_SERVER['REMOTE_ADDR'] ?? null,
                                isset($_SERVER['REMOTE_ADDR']) ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->execute();
            } catch (Throwable $e) {
                // Se falhar, apenas no error_log do PHP — nunca propaga
                error_log('[RBAC][Log::write] Falha: ' . $e->getMessage()
                    . ' | action=' . $action . ' table=' . $table);
            }
        }

        /**
         * Atalho para erros. Grava com LOG_ACTION = 'ERROR'.
         *
         * @param string         $message   Mensagem descritiva
         * @param string         $context   Contexto (tabela, camada, etc.)
         * @param Throwable|null $e         Exceção original (opcional)
         * @param array|null     $extra     Dados extra
         * @param int|null       $userId    Utilizador (default: sessão)
         */
        public static function error(
            string $message,
            string $context = 'GENERIC',
            ?Throwable $e = null,
            ?array $extra = null,
            ?int $userId = null
        ): void {
            $payload = [
                'message' => $message,
                'context' => $context,
                'time'    => date('c'),
            ];

            if ($e !== null) {
                $payload['exception']     = get_class($e);
                $payload['exception_msg'] = $e->getMessage();
                $payload['file']          = $e->getFile();
                $payload['line']          = $e->getLine();

                $trace = [];
                foreach (array_slice($e->getTrace(), 0, 5) as $t) {
                    $trace[] = ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? '')
                             . (isset($t['file']) ? ' @ ' . basename($t['file']) . ':' . ($t['line'] ?? '?') : '');
                }
                $payload['trace'] = $trace;
            }

            if (!empty($extra)) {
                $payload['extra'] = $extra;
            }

            self::write('ERROR', 'ERROR', null, null, $payload, $userId);

            error_log('[RBAC][' . $context . '] ' . $message
                . ($e ? ' | ' . $e->getMessage() : ''));
        }

        // ==================== ATALHOS ====================
        public static function login(?int $userId = null): void {
            self::write('LOGIN', 'USERS', $userId, null, ['event' => 'login']);
        }

        public static function logout(?int $userId = null): void {
            self::write('LOGOUT', 'USERS', $userId, null, ['event' => 'logout']);
        }

        public static function accessDenied(string $context, ?array $extra = null, ?int $userId = null): void {
            self::write('ACCESS_DENIED', $context, null, null, $extra, $userId);
        }

        // ==================== CONSULTAS ====================
        public static function recent(int $limit = 50): array {
            try {
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
                $limit = max(1, min(500, $limit));
                $stmt = $pdo->prepare("SELECT * FROM LOGS ORDER BY LOG_CREATED_AT DESC LIMIT {$limit}");
                $stmt->execute();
                return $stmt->fetchAll();
            } catch (Throwable $e) {
                error_log('[RBAC][Log::recent] ' . $e->getMessage());
                return [];
            }
        }

        public static function errors(int $limit = 50): array {
            try {
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
                $limit = max(1, min(500, $limit));
                $stmt = $pdo->prepare("SELECT * FROM LOGS WHERE LOG_ACTION = 'ERROR' ORDER BY LOG_CREATED_AT DESC LIMIT {$limit}");
                $stmt->execute();
                return $stmt->fetchAll();
            } catch (Throwable $e) {
                error_log('[RBAC][Log::errors] ' . $e->getMessage());
                return [];
            }
        }
    }
?>