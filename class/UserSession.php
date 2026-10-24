<?php
    require_once __DIR__ . '/Database.php';
    require_once __DIR__ . '/Sanitize.php';
    require_once __DIR__ . '/Log.php';

    class UserSession {

        private Database $db;
        private string $table = 'USER_SESSIONS';
        private ?int $actingUserId;

        public function __construct(?int $actingUserId = null) {
            $this->actingUserId = $actingUserId
                ?? (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
            $this->db = new Database($this->actingUserId);
        }

        /**
         * Lista sessões (com JOIN a USERS e PROFILES) com filtros opcionais.
         */
        public function search(?string $status = null, ?string $username = null): array {
            try {
                $sql = "SELECT S.*, U.USER_USERNAME, U.USER_FULLNAME,
                               P.PROFILE_CODE, P.PROFILE_NAME,
                               CASE WHEN S.SESSION_EXPIRESAT >= NOW() THEN 'ACTIVE' ELSE 'EXPIRED' END AS SESSION_STATE
                        FROM {$this->table} S
                        INNER JOIN USERS U    ON S.SESSION_USERID  = U.USER_ID
                        INNER JOIN PROFILES P ON U.USER_PROFILEID  = P.PROFILE_ID
                        WHERE 1=1";
                $params = [];

                if ($status) {
                    $status = Sanitize::enum($status, ['ACTIVE','EXPIRED']);
                    if ($status === 'ACTIVE') {
                        $sql .= " AND S.SESSION_EXPIRESAT >= NOW()";
                    } elseif ($status === 'EXPIRED') {
                        $sql .= " AND S.SESSION_EXPIRESAT < NOW()";
                    }
                }
                if ($username) {
                    $username = Sanitize::string($username, 50);
                    if ($username !== '') {
                        $sql .= " AND U.USER_USERNAME LIKE ?";
                        $params[] = "%{$username}%";
                    }
                }
                $sql .= " ORDER BY S.SESSION_CREATEAT DESC";

                return $this->db->query($sql, $params);
            } catch (Throwable $e) {
                $this->rethrow($e, 'UserSession::search', null, ['status' => $status, 'username' => $username]);
            }
        }

        public function destroy(string $sessionId): bool {
            try {
                if ($sessionId === '') throw new InvalidArgumentException("ID de sessão vazio.");
                return $this->db->delete($this->table, ['SESSION_ID' => $sessionId]);
            } catch (Throwable $e) {
                $this->rethrow($e, 'UserSession::destroy', null, ['session_id' => $sessionId]);
            }
        }

        public function destroyAllForUser(int $userId): bool {
            try {
                return $this->db->delete($this->table, ['SESSION_USERID' => $userId]);
            } catch (Throwable $e) {
                $this->rethrow($e, 'UserSession::destroyAllForUser', $userId);
            }
        }

        public function cleanExpired(): int {
            try {
                $stmt = $this->db->query(
                    "DELETE FROM {$this->table} WHERE SESSION_EXPIRESAT < NOW()"
                );
                return (int)$stmt;
            } catch (Throwable $e) {
                $this->rethrow($e, 'UserSession::cleanExpired');
            }
        }

        public function countAll(): int      { return $this->db->count($this->table); }
        public function countActive(): int {
            try {
                $row = $this->db->query("SELECT COUNT(*) AS c FROM {$this->table} WHERE SESSION_EXPIRESAT >= NOW()");
                return (int)($row[0]['c'] ?? 0);
            } catch (Throwable $e) {
                $this->rethrow($e, 'UserSession::countActive');
            }
        }
        public function countExpired(): int {
            try {
                $row = $this->db->query("SELECT COUNT(*) AS c FROM {$this->table} WHERE SESSION_EXPIRESAT < NOW()");
                return (int)($row[0]['c'] ?? 0);
            } catch (Throwable $e) {
                $this->rethrow($e, 'UserSession::countExpired');
            }
        }
        public function countUniqueUsers(): int {
            try {
                $row = $this->db->query("SELECT COUNT(DISTINCT SESSION_USERID) AS c FROM {$this->table}");
                return (int)($row[0]['c'] ?? 0);
            } catch (Throwable $e) {
                $this->rethrow($e, 'UserSession::countUniqueUsers');
            }
        }

        private function rethrow(Throwable $e, string $method, ?int $recordId = null, array $extra = []): void {
            Log::error("{$method} falhou", 'USER_SESSIONS', $e, array_merge($extra, [
                'file' => $e->getFile(), 'line' => $e->getLine(),
            ]), $this->actingUserId);
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                throw new RuntimeException(
                    "{$method} falhou\n  Mensagem: " . $e->getMessage()
                    . "\n  Ficheiro: " . $e->getFile() . ':' . $e->getLine(), 0, $e
                );
            }
            throw new RuntimeException("Erro ao processar a sessão.", 0, $e);
        }
    }
?>