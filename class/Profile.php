<?php
    require_once __DIR__ . '/Database.php';
    require_once __DIR__ . '/Sanitize.php';
    require_once __DIR__ . '/Log.php';

    class Profile {

        private Database $db;
        private string $table = 'PROFILES';
        private ?int $actingUserId;

        public function __construct(?int $actingUserId = null) {
            $this->actingUserId = $actingUserId
                ?? (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
            $this->db = new Database($this->actingUserId);
        }

        // ==================== CREATE ====================
        public function create(array $input, ?int $createdBy = null): int {
            try {
                $data = $this->prepareData($input, true);

                if (empty($data['PROFILE_CODE'])) {
                    $data['PROFILE_CODE'] = $this->generateUniqueCode($data['PROFILE_NAME']);
                }
                if ($this->exists(['PROFILE_CODE' => $data['PROFILE_CODE']])) {
                    throw new InvalidArgumentException("Já existe um perfil com o código {$data['PROFILE_CODE']}.");
                }
                if ($this->exists(['PROFILE_NAME' => $data['PROFILE_NAME']])) {
                    throw new InvalidArgumentException("Já existe um perfil com o nome {$data['PROFILE_NAME']}.");
                }

                $data['PROFILE_CREATEBY'] = $createdBy ?? $this->actingUserId;

                return (int)$this->db->create($this->table, $data);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Profile::create', null, [
                    'input_keys' => array_keys($input),
                ]);
            }
        }

        // ==================== READ ====================
        public function read(array $conditions = [], array $additionalParams = []): array {
            try {
                return $this->db->read($this->table, $conditions, $additionalParams);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Profile::read', null, ['conditions' => $conditions]);
            }
        }

        public function findById(int $id): ?array {
            try {
                return $this->db->readOne($this->table, ['PROFILE_ID' => $id]);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Profile::findById', $id);
            }
        }

        public function findByCode(string $code): ?array {
            try {
                $code = Sanitize::code($code, 10);
                return $code ? $this->db->readOne($this->table, ['PROFILE_CODE' => $code]) : null;
            } catch (Throwable $e) {
                $this->rethrow($e, 'Profile::findByCode', null, ['code' => $code]);
            }
        }

        public function all(): array {
            return $this->read([], ['order_by' => 'PROFILE_NAME ASC']);
        }

        public function search(string $term, ?string $status = null): array {
            try {
                $term = Sanitize::string($term, 100);
                $cond = [];
                if ($status !== null) {
                    $status = Sanitize::enum($status, ['ACTIVE','INACTIVE']);
                    if ($status) $cond['PROFILE_STATUS'] = $status;
                }
                if ($term === '') {
                    return $this->read($cond, ['order_by' => 'PROFILE_NAME ASC']);
                }
                $sql = "SELECT * FROM {$this->table}
                        WHERE (PROFILE_NAME LIKE ? OR PROFILE_CODE LIKE ? OR PROFILE_DESCRIPTION LIKE ?)";
                $params = ["%{$term}%", "%{$term}%", "%{$term}%"];
                if (!empty($cond)) {
                    foreach ($cond as $col => $val) {
                        $sql .= " AND {$col} = ?";
                        $params[] = $val;
                    }
                }
                $sql .= " ORDER BY PROFILE_NAME ASC";
                return $this->db->query($sql, $params);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Profile::search', null, ['term' => $term]);
            }
        }

        // ==================== UPDATE ====================
        public function update(int $id, array $input, ?int $updatedBy = null): bool {
            try {
                if (!$this->exists(['PROFILE_ID' => $id])) {
                    throw new RuntimeException("Perfil #{$id} não encontrado.");
                }
                $data = $this->prepareData($input, false);
                if (empty($data)) return true;

                if (!empty($data['PROFILE_NAME'])) {
                    $check = $this->db->query(
                        "SELECT 1 FROM {$this->table} WHERE PROFILE_NAME = ? AND PROFILE_ID <> ?",
                        [$data['PROFILE_NAME'], $id]
                    );
                    if (!empty($check)) {
                        throw new InvalidArgumentException("Já existe um perfil com este nome.");
                    }
                }

                $data['PROFILE_UPDATEBY'] = $updatedBy ?? $this->actingUserId;
                return $this->db->update($this->table, $data, ['PROFILE_ID' => $id]);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Profile::update', $id);
            }
        }

        public function setStatus(int $id, string $status): bool {
            try {
                $status = Sanitize::enum($status, ['ACTIVE','INACTIVE']);
                if (!$status) throw new InvalidArgumentException("Status inválido.");
                return $this->db->update($this->table, ['PROFILE_STATUS' => $status], ['PROFILE_ID' => $id]);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Profile::setStatus', $id, ['status' => $status]);
            }
        }

        // ==================== DELETE ====================
        public function delete(int $id): bool {
            try {
                $users = $this->db->count('USERS', ['USER_PROFILEID' => $id]);
                if ($users > 0) {
                    throw new RuntimeException(
                        "Não é possível eliminar: existem {$users} utilizador(es) associado(s) a este perfil."
                    );
                }
                return $this->db->delete($this->table, ['PROFILE_ID' => $id]);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Profile::delete', $id);
            }
        }

        // ==================== ESTATÍSTICAS ====================
        public function countAll(): int       { return $this->db->count($this->table); }
        public function countActive(): int    { return $this->db->count($this->table, ['PROFILE_STATUS' => 'ACTIVE']); }
        public function countInactive(): int  { return $this->db->count($this->table, ['PROFILE_STATUS' => 'INACTIVE']); }

        public function countWithPermissions(): int {
            try {
                $row = $this->db->query("SELECT COUNT(DISTINCT PERMISSION_PROFILEID) AS c FROM PERMISSIONS");
                return (int)($row[0]['c'] ?? 0);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Profile::countWithPermissions', null);
            }
        }

        // ==================== HELPERS ====================
        public function exists(array $conditions): bool {
            return $this->db->exists($this->table, $conditions);
        }

        public function generateUniqueCode(string $name): string {
            $base = acronym($name, 4);
            if ($base === '') $base = 'PRF';
            $candidate = $base;
            $i = 1;
            while ($this->exists(['PROFILE_CODE' => $candidate])) {
                $candidate = $base . $i;
                $i++;
                if ($i > 999) throw new RuntimeException("Não foi possível gerar código único.");
            }
            return $candidate;
        }

        private function prepareData(array $input, bool $requireName): array {
            $data = [];
            if (!empty($input['name']) || !empty($input['PROFILE_NAME'])) {
                $name = Sanitize::string($input['name'] ?? $input['PROFILE_NAME'], 50);
                if ($name === '' && $requireName) throw new InvalidArgumentException("O nome do perfil é obrigatório.");
                if ($name !== '') $data['PROFILE_NAME'] = $name;
            } elseif ($requireName) {
                throw new InvalidArgumentException("O nome do perfil é obrigatório.");
            }
            if (isset($input['code']) || isset($input['PROFILE_CODE'])) {
                $code = Sanitize::code($input['code'] ?? $input['PROFILE_CODE'], 10);
                if ($code !== '') $data['PROFILE_CODE'] = $code;
            }
            if (array_key_exists('description', $input) || array_key_exists('PROFILE_DESCRIPTION', $input)) {
                $data['PROFILE_DESCRIPTION'] = Sanitize::string(
                    $input['description'] ?? $input['PROFILE_DESCRIPTION'], 1000
                ) ?: null;
            }
            if (isset($input['status']) || isset($input['PROFILE_STATUS'])) {
                $data['PROFILE_STATUS'] = Sanitize::enum(
                    $input['status'] ?? $input['PROFILE_STATUS'],
                    ['ACTIVE','INACTIVE'], 'INACTIVE'
                );
            } elseif ($requireName) {
                $data['PROFILE_STATUS'] = 'INACTIVE';
            }
            return $data;
        }

        /**
         * Loga o erro e relança com o detalhe real em DEBUG_MODE.
         */
        private function rethrow(Throwable $e, string $method, ?int $recordId = null, array $extra = []): void {
            Log::error("{$method} falhou", 'PROFILES', $e, array_merge($extra, [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]), $this->actingUserId);

            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                throw new RuntimeException(
                    "{$method} falhou\n"
                    . "  Mensagem: " . $e->getMessage() . "\n"
                    . "  Ficheiro: " . $e->getFile() . ':' . $e->getLine(),
                    0,
                    $e
                );
            }

            throw new RuntimeException("Erro ao processar o perfil.", 0, $e);
        }
    }
?>