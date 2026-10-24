<?php
    require_once __DIR__ . '/Database.php';
    require_once __DIR__ . '/Sanitize.php';
    require_once __DIR__ . '/Log.php';

    class User {

        private Database $db;
        private string $table = 'USERS';
        private ?int $actingUserId;

        public function __construct(?int $actingUserId = null) {
            $this->actingUserId = $actingUserId
                ?? (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
            $this->db = new Database($this->actingUserId);
        }

        // ==================== CREATE ====================
        public function create(array $input, ?int $createdBy = null): int {
            try {
                $data = $this->prepare($input, true);

                // Unicidade de username e email
                if ($this->exists(['USER_USERNAME' => $data['USER_USERNAME']])) {
                    throw new InvalidArgumentException("Já existe um utilizador com o username '{$data['USER_USERNAME']}'.");
                }
                if (!empty($data['USER_EMAIL']) && $this->exists(['USER_EMAIL' => $data['USER_EMAIL']])) {
                    throw new InvalidArgumentException("Já existe um utilizador com o email '{$data['USER_EMAIL']}'.");
                }

                // Perfil tem de existir
                if (!$this->db->exists('PROFILES', ['PROFILE_ID' => $data['USER_PROFILEID']])) {
                    throw new InvalidArgumentException("O perfil indicado não existe.");
                }

                // Hash da senha
                $password = $input['password'] ?? $input['USER_PASSWORDHASH'] ?? '';
                $err = Sanitize::password($password, PASSWORD_MIN);
                if ($err !== null) throw new InvalidArgumentException($err);
                $data['USER_PASSWORDHASH'] = Database::hashPassword($password);

                $data['USER_CREATEBY'] = $createdBy ?? $this->actingUserId;

                return (int)$this->db->create($this->table, $data);
            } catch (Throwable $e) {
                $this->rethrow($e, 'User::create', null, ['input_keys' => array_keys($input)]);
            }
        }

        // ==================== READ ====================
        public function read(array $conditions = [], array $additionalParams = []): array {
            try {
                return $this->db->read($this->table, $conditions, $additionalParams);
            } catch (Throwable $e) {
                $this->rethrow($e, 'User::read', null, ['conditions' => $conditions]);
            }
        }

        public function findById(int $id): ?array {
            try {
                return $this->db->readOne($this->table, ['USER_ID' => $id]);
            } catch (Throwable $e) {
                $this->rethrow($e, 'User::findById', $id);
            }
        }

        public function findByUsername(string $username): ?array {
            try {
                $username = Sanitize::string($username, 50);
                return $username ? $this->db->readOne($this->table, ['USER_USERNAME' => $username]) : null;
            } catch (Throwable $e) {
                $this->rethrow($e, 'User::findByUsername', null, ['username' => $username]);
            }
        }

        /**
         * Lista com JOIN a PROFILES para trazer PROFILE_CODE/PROFILE_NAME.
         */
        public function search(string $term, ?string $profileCode = null, ?string $status = null): array {
            try {
                $term = Sanitize::string($term, 100);
                $sql = "SELECT U.*, P.PROFILE_CODE, P.PROFILE_NAME
                        FROM {$this->table} U
                        INNER JOIN PROFILES P ON U.USER_PROFILEID = P.PROFILE_ID
                        WHERE 1=1";
                $params = [];

                if ($term !== '') {
                    $sql .= " AND (U.USER_USERNAME LIKE ? OR U.USER_FULLNAME LIKE ? OR U.USER_EMAIL LIKE ?)";
                    $params[] = "%{$term}%";
                    $params[] = "%{$term}%";
                    $params[] = "%{$term}%";
                }
                if ($profileCode) {
                    $profileCode = Sanitize::code($profileCode, 10);
                    if ($profileCode) {
                        $sql .= " AND P.PROFILE_CODE = ?";
                        $params[] = $profileCode;
                    }
                }
                if ($status) {
                    $status = Sanitize::enum($status, ['ACTIVE','BLOCKED','INACTIVE']);
                    if ($status) {
                        $sql .= " AND U.USER_STATUS = ?";
                        $params[] = $status;
                    }
                }
                $sql .= " ORDER BY U.USER_USERNAME ASC";

                return $this->db->query($sql, $params);
            } catch (Throwable $e) {
                $this->rethrow($e, 'User::search', null, ['term' => $term]);
            }
        }

        // ==================== UPDATE ====================
        public function update(int $id, array $input, ?int $updatedBy = null): bool {
            try {
                if (!$this->exists(['USER_ID' => $id])) {
                    throw new RuntimeException("Utilizador #{$id} não encontrado.");
                }
                $data = $this->prepare($input, false);
                if (empty($data)) return true;

                // Unicidade de email
                if (!empty($data['USER_EMAIL'])) {
                    $check = $this->db->query(
                        "SELECT 1 FROM {$this->table} WHERE USER_EMAIL = ? AND USER_ID <> ?",
                        [$data['USER_EMAIL'], $id]
                    );
                    if (!empty($check)) {
                        throw new InvalidArgumentException("Já existe um utilizador com este email.");
                    }
                }

                $data['USER_UPDATEBY'] = $updatedBy ?? $this->actingUserId;
                return $this->db->update($this->table, $data, ['USER_ID' => $id]);
            } catch (Throwable $e) {
                $this->rethrow($e, 'User::update', $id, ['input_keys' => array_keys($input)]);
            }
        }

        public function updatePassword(int $id, string $newPassword, ?int $updatedBy = null): bool {
            try {
                $err = Sanitize::password($newPassword, PASSWORD_MIN);
                if ($err !== null) throw new InvalidArgumentException($err);

                return $this->db->update($this->table, [
                    'USER_PASSWORDHASH' => Database::hashPassword($newPassword),
                    'USER_UPDATEBY'     => $updatedBy ?? $this->actingUserId,
                ], ['USER_ID' => $id]);
            } catch (Throwable $e) {
                $this->rethrow($e, 'User::updatePassword', $id);
            }
        }

        public function setStatus(int $id, string $status, ?int $updatedBy = null): bool {
            try {
                $status = Sanitize::enum($status, ['ACTIVE','BLOCKED','INACTIVE']);
                if (!$status) throw new InvalidArgumentException("Status inválido.");

                return $this->db->update($this->table, [
                    'USER_STATUS'   => $status,
                    'USER_UPDATEBY' => $updatedBy ?? $this->actingUserId,
                ], ['USER_ID' => $id]);
            } catch (Throwable $e) {
                $this->rethrow($e, 'User::setStatus', $id, ['status' => $status]);
            }
        }

        // ==================== DELETE ====================
        public function delete(int $id): bool {
            try {
                if ($id === $this->actingUserId) {
                    throw new RuntimeException("Não é possível eliminar a própria conta.");
                }
                return $this->db->delete($this->table, ['USER_ID' => $id]);
            } catch (Throwable $e) {
                $this->rethrow($e, 'User::delete', $id);
            }
        }

        // ==================== ESTATÍSTICAS ====================
        public function countAll(): int      { return $this->db->count($this->table); }
        public function countActive(): int   { return $this->db->count($this->table, ['USER_STATUS' => 'ACTIVE']); }
        public function countBlocked(): int  { return $this->db->count($this->table, ['USER_STATUS' => 'BLOCKED']); }
        public function countInactive(): int { return $this->db->count($this->table, ['USER_STATUS' => 'INACTIVE']); }

        // ==================== HELPERS ====================
        public function exists(array $conditions): bool {
            return $this->db->exists($this->table, $conditions);
        }

        private function prepare(array $input, bool $requireAll): array {
            $data = [];

            if (isset($input['username']) || isset($input['USER_USERNAME'])) {
                $username = Sanitize::string($input['username'] ?? $input['USER_USERNAME'], 50);
                if ($username === '' && $requireAll) throw new InvalidArgumentException("O username é obrigatório.");
                if ($username !== '') $data['USER_USERNAME'] = $username;
            } elseif ($requireAll) {
                throw new InvalidArgumentException("O username é obrigatório.");
            }

            if (isset($input['fullname']) || isset($input['USER_FULLNAME'])) {
                $fullname = Sanitize::string($input['fullname'] ?? $input['USER_FULLNAME'], 150);
                if ($fullname !== '') $data['USER_FULLNAME'] = $fullname;
            }

            if (isset($input['email']) || isset($input['USER_EMAIL'])) {
                $email = Sanitize::email($input['email'] ?? $input['USER_EMAIL']);
                if ($email === null && !empty($input['email'] ?? $input['USER_EMAIL'] ?? '')) {
                    throw new InvalidArgumentException("Email inválido.");
                }
                $data['USER_EMAIL'] = $email;
            }

            if (isset($input['profile_id']) || isset($input['USER_PROFILEID'])) {
                $pid = Sanitize::int($input['profile_id'] ?? $input['USER_PROFILEID']);
                if ($pid === null && $requireAll) throw new InvalidArgumentException("O perfil é obrigatório.");
                if ($pid !== null) $data['USER_PROFILEID'] = $pid;
            } elseif ($requireAll) {
                throw new InvalidArgumentException("O perfil é obrigatório.");
            }

            if (isset($input['status']) || isset($input['USER_STATUS'])) {
                $st = Sanitize::enum($input['status'] ?? $input['USER_STATUS'], ['ACTIVE','BLOCKED','INACTIVE'], 'ACTIVE');
                $data['USER_STATUS'] = $st;
            } elseif ($requireAll) {
                $data['USER_STATUS'] = 'ACTIVE';
            }

            return $data;
        }

        private function rethrow(Throwable $e, string $method, ?int $recordId = null, array $extra = []): void {
            Log::error("{$method} falhou", 'USERS', $e, array_merge($extra, [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]), $this->actingUserId);

            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                throw new RuntimeException(
                    "{$method} falhou\n  Mensagem: " . $e->getMessage()
                    . "\n  Ficheiro: " . $e->getFile() . ':' . $e->getLine(), 0, $e
                );
            }
            throw new RuntimeException("Erro ao processar o utilizador.", 0, $e);
        }
    }
?>