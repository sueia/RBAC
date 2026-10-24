<?php
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/Sanitize.php';
    require_once __DIR__ . '/Log.php';

    /**
     * Database.php — Acesso a dados.
     *
     * Em DEBUG_MODE = true → o erro real do PDO é lançado na exceção
     * (mensagem, código, DSN) para diagnóstico imediato.
     * Em produção → mensagem genérica ao utilizador; detalhe vai para LOGS.
     */
    class Database {

        private PDO $conn;
        private ?int $actingUserId;

        public function __construct(?int $actingUserId = null) {
            $this->actingUserId = $actingUserId
                ?? (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);

            try {
                $dsn = 'mysql:host=' . DB_HOST
                     . ';port='     . (defined('DB_PORT') ? DB_PORT : '3306')
                     . ';dbname='   . DB_NAME
                     . ';charset='  . DB_CHARSET;

                $this->conn = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
                $this->conn->exec("SET NAMES " . DB_CHARSET . " COLLATE " . DB_COLLATE);

            } catch (PDOException $e) {
                // 1) Grava o erro real em LOGS
                Log::error('Database::__construct — falha na conexão', 'DATABASE', $e, [
                    'dsn'  => $dsn ?? null,
                    'host' => DB_HOST,
                    'port' => defined('DB_PORT') ? DB_PORT : null,
                    'db'   => DB_NAME,
                    'user' => DB_USER,
                    'code' => $e->getCode(),
                ], $this->actingUserId);

                // 2) Em debug → lança a exceção com o erro REAL
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    throw new RuntimeException(
                        "Erro ao conectar ao banco de dados.\n"
                        . "  Mensagem: " . $e->getMessage() . "\n"
                        . "  Código:   " . $e->getCode() . "\n"
                        . "  DSN:      " . ($dsn ?? '') . "\n"
                        . "  User:     " . DB_USER . "\n"
                        . "  Ficheiro: " . $e->getFile() . ':' . $e->getLine(),
                        0,
                        $e
                    );
                }

                // 3) Em produção → mensagem genérica
                throw new RuntimeException("Erro ao conectar ao banco de dados.", 0, $e);
            }
        }

        public function getConnection(): PDO { return $this->conn; }
        public function getLastInsertId()      { return $this->conn->lastInsertId(); }

        // ==================== CREATE ====================
        public function create(string $table, array $data) {
            try {
                $table = Sanitize::identifier($table);
                if (empty($data)) throw new InvalidArgumentException("Sem dados para inserir.");

                $cols = array_map([Sanitize::class, 'identifier'], array_keys($data));
                $ph   = array_map(fn($c) => ':' . $c, $cols);
                $sql  = "INSERT INTO {$table} (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $ph) . ")";

                $stmt = $this->conn->prepare($sql);
                foreach ($data as $k => $v) $this->bind($stmt, ':' . $k, $v);
                $stmt->execute();
                return $this->conn->lastInsertId();
            } catch (Throwable $e) {
                $this->rethrow($e, 'Database::create', $table, [
                    'columns'  => array_keys($data),
                    'sqlstate' => $e instanceof PDOException ? $e->getCode() : null,
                ]);
            }
        }

        // ==================== READ ====================
        public function read(string $table, array $conditions = [], array $additionalParams = []) {
            try {
                $table = Sanitize::identifier($table);
                $sql    = "SELECT * FROM {$table}";
                $params = [];

                if (!empty($conditions)) {
                    $sql .= ' WHERE ' . $this->buildWhere($conditions, $params);
                }
                if (!empty($additionalParams['group_by'])) {
                    $sql .= ' GROUP BY ' . Sanitize::identifier($additionalParams['group_by']);
                }
                if (!empty($additionalParams['order_by'])) {
                    $sql .= ' ORDER BY ' . $this->buildOrderBy($additionalParams['order_by']);
                }
                if (isset($additionalParams['limit'])) {
                    $sql .= ' LIMIT ' . (int)$additionalParams['limit'];
                    if (isset($additionalParams['offset'])) {
                        $sql .= ' OFFSET ' . (int)$additionalParams['offset'];
                    }
                }

                $stmt = $this->conn->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll();
            } catch (Throwable $e) {
                $this->rethrow($e, 'Database::read', $table, [
                    'conditions' => $conditions,
                ]);
            }
        }

        public function readOne(string $table, array $conditions = []): ?array {
            $rows = $this->read($table, $conditions, ['limit' => 1]);
            return $rows[0] ?? null;
        }

        // ==================== UPDATE ====================
        public function update(string $table, array $data, array $conditions) {
            try {
                $table = Sanitize::identifier($table);
                if (empty($data))       throw new InvalidArgumentException("Sem dados para atualizar.");
                if (empty($conditions)) throw new InvalidArgumentException("Sem condições para atualizar.");

                $setParts = [];
                $params   = [];
                foreach ($data as $k => $v) {
                    $col = Sanitize::identifier($k);
                    $setParts[] = "{$col} = ?";
                    $params[]   = $v;
                }
                $whereSql = $this->buildWhere($conditions, $params);
                $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$whereSql}";

                $stmt = $this->conn->prepare($sql);
                return $stmt->execute($params);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Database::update', $table, [
                    'columns'    => array_keys($data),
                    'conditions' => $conditions,
                ]);
            }
        }

        // ==================== DELETE ====================
        public function delete(string $table, array $conditions) {
            try {
                $table = Sanitize::identifier($table);
                if (empty($conditions)) throw new InvalidArgumentException("Sem condições para eliminar.");

                $params = [];
                $sql = "DELETE FROM {$table} WHERE " . $this->buildWhere($conditions, $params);

                $stmt = $this->conn->prepare($sql);
                return $stmt->execute($params);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Database::delete', $table, [
                    'conditions' => $conditions,
                ]);
            }
        }

        // ==================== COUNT ====================
        public function count(string $table, array $conditions = []): int {
            try {
                $table = Sanitize::identifier($table);
                $params = [];
                $sql = "SELECT COUNT(*) FROM {$table}";
                if (!empty($conditions)) {
                    $sql .= ' WHERE ' . $this->buildWhere($conditions, $params);
                }
                $stmt = $this->conn->prepare($sql);
                $stmt->execute($params);
                return (int)$stmt->fetchColumn();
            } catch (Throwable $e) {
                $this->rethrow($e, 'Database::count', $table, [
                    'conditions' => $conditions,
                ]);
            }
        }

        public function exists(string $table, array $conditions): bool {
            return $this->count($table, $conditions) > 0;
        }

        // ==================== QUERY ====================
        public function query(string $sql, array $params = []) {
            try {
                $stmt = $this->conn->prepare($sql);
                $stmt->execute($params);
                $verb = strtoupper(strtok(ltrim($sql), " \t\n\r("));
                if (in_array($verb, ['SELECT','SHOW','DESCRIBE','DESC','EXPLAIN','WITH'], true)) {
                    return $stmt->fetchAll();
                }
                return $stmt->rowCount();
            } catch (Throwable $e) {
                $this->rethrow($e, 'Database::query', 'QUERY', [
                    'sql' => mb_substr($sql, 0, 500),
                ]);
            }
        }

        // ==================== TRANSAÇÕES ====================
        public function beginTransaction(): bool { return $this->conn->beginTransaction(); }
        public function commit(): bool           { return $this->conn->commit(); }
        public function rollback(): bool         { return $this->conn->rollBack(); }
        public function inTransaction(): bool    { return $this->conn->inTransaction(); }

        // ==================== HASH ====================
        public static function hashPassword(string $password): string {
            return password_hash($password, PASSWORD_ALGO);
        }
        public static function verifyPassword(string $password, string $hash): bool {
            return password_verify($password, $hash);
        }

        // ==================== INTERNOS ====================
        private function buildWhere(array $conditions, array &$params): string {
            $parts = [];
            foreach ($conditions as $col => $value) {
                $col = Sanitize::identifier($col);
                if (is_array($value)) {
                    $op  = strtoupper(trim($value[0] ?? '='));
                    $val = $value[1] ?? null;
                    switch ($op) {
                        case 'IN':
                        case 'NOT IN':
                            if (!preg_match('/^\([\d,\'\s]*\)$/', (string)$val)) {
                                throw new InvalidArgumentException("IN inválido.");
                            }
                            $parts[] = "{$col} {$op} {$val}";
                            break;
                        case 'IS NULL':     $parts[] = "{$col} IS NULL"; break;
                        case 'IS NOT NULL': $parts[] = "{$col} IS NOT NULL"; break;
                        case 'LIKE':
                        case 'NOT LIKE':
                        case '=':
                        case '!=':
                        case '<>':
                        case '>':
                        case '>=':
                        case '<':
                        case '<=':
                            $parts[] = "{$col} {$op} ?";
                            $params[] = $val;
                            break;
                        default:
                            throw new InvalidArgumentException("Operador inválido: {$op}");
                    }
                } else {
                    $parts[] = "{$col} = ?";
                    $params[] = $value;
                }
            }
            return implode(' AND ', $parts);
        }

        private function buildOrderBy(string $orderBy): string {
            $pieces = array_map('trim', explode(',', $orderBy));
            $out = [];
            foreach ($pieces as $piece) {
                if ($piece === '') continue;
                $bits = preg_split('/\s+/', $piece);
                $col  = Sanitize::identifier($bits[0]);
                $dir  = isset($bits[1]) ? strtoupper($bits[1]) : 'ASC';
                if (!in_array($dir, ['ASC','DESC'], true)) $dir = 'ASC';
                $out[] = "{$col} {$dir}";
            }
            return implode(', ', $out);
        }

        private function bind(PDOStatement $stmt, string $placeholder, $value): void {
            if (is_int($value))      $stmt->bindValue($placeholder, $value, PDO::PARAM_INT);
            elseif (is_bool($value)) $stmt->bindValue($placeholder, $value, PDO::PARAM_BOOL);
            elseif (is_null($value)) $stmt->bindValue($placeholder, null, PDO::PARAM_NULL);
            else                     $stmt->bindValue($placeholder, (string)$value, PDO::PARAM_STR);
        }

        /**
         * Loga o erro e relança:
         *  - Em DEBUG_MODE → relança com mensagem REAL do erro
         *  - Em produção    → relança com mensagem genérica + código
         */
        private function rethrow(Throwable $e, string $method, string $table, array $extra = []): void {
            Log::error("{$method} falhou", $table, $e, array_merge($extra, [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
            ]), $this->actingUserId);

            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                $msg = "{$method} falhou\n"
                     . "  Mensagem: " . $e->getMessage() . "\n"
                     . "  Ficheiro: " . $e->getFile() . ':' . $e->getLine();
                if ($e instanceof PDOException) {
                    $msg .= "\n  SQLSTATE: " . $e->getCode();
                }
                throw new RuntimeException($msg, 0, $e);
            }

            throw new RuntimeException("Erro ao processar a operação.", 0, $e);
        }
    }
?>