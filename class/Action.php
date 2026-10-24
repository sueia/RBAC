<?php
    require_once __DIR__ . '/Database.php';
    require_once __DIR__ . '/Sanitize.php';
    require_once __DIR__ . '/Log.php';

    class Action {

        private Database $db;
        private string $table = 'ACTIONS';
        private ?int $actingUserId;

        public function __construct(?int $actingUserId = null) {
            $this->actingUserId = $actingUserId
                ?? (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
            $this->db = new Database($this->actingUserId);
        }

        public function create(array $input, ?int $createdBy = null): int {
            try {
                $data = $this->prepare($input, true);
                if (empty($data['ACTION_CODE'])) {
                    $data['ACTION_CODE'] = strtoupper(substr($data['ACTION_NAME'], 0, 10));
                }
                if ($this->exists(['ACTION_CODE' => $data['ACTION_CODE']])) {
                    throw new InvalidArgumentException("Já existe uma ação com o código {$data['ACTION_CODE']}.");
                }
                if ($this->exists(['ACTION_NAME' => $data['ACTION_NAME']])) {
                    throw new InvalidArgumentException("Já existe uma ação com o nome {$data['ACTION_NAME']}.");
                }
                $data['ACTION_CREATEBY'] = $createdBy ?? $this->actingUserId;
                return (int)$this->db->create($this->table, $data);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Action::create', null, ['input_keys' => array_keys($input)]);
            }
        }

        public function read(array $conditions = [], array $additionalParams = []): array {
            try {
                return $this->db->read($this->table, $conditions, $additionalParams);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Action::read', null, ['conditions' => $conditions]);
            }
        }

        public function all(): array {
            return $this->read([], ['order_by' => 'ACTION_NAME ASC']);
        }

        public function search(string $term, ?string $status = null): array {
            try {
                $term = Sanitize::string($term, 100);
                $cond = [];
                if ($status !== null) {
                    $status = Sanitize::enum($status, ['ACTIVE','INACTIVE']);
                    if ($status) $cond['ACTION_STATUS'] = $status;
                }
                if ($term === '') return $this->read($cond, ['order_by' => 'ACTION_NAME ASC']);
                $sql = "SELECT * FROM {$this->table}
                        WHERE (ACTION_NAME LIKE ? OR ACTION_CODE LIKE ? OR ACTION_DESCRIPTION LIKE ?)";
                $params = ["%{$term}%", "%{$term}%", "%{$term}%"];
                if (!empty($cond)) {
                    foreach ($cond as $col => $val) {
                        $sql .= " AND {$col} = ?";
                        $params[] = $val;
                    }
                }
                $sql .= " ORDER BY ACTION_NAME ASC";
                return $this->db->query($sql, $params);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Action::search', null, ['term' => $term]);
            }
        }

        public function update(int $id, array $input, ?int $updatedBy = null): bool {
            try {
                if (!$this->exists(['ACTION_ID' => $id])) {
                    throw new RuntimeException("Ação #{$id} não encontrada.");
                }
                $data = $this->prepare($input, false);
                if (empty($data)) return true;

                if (!empty($data['ACTION_NAME'])) {
                    $check = $this->db->query(
                        "SELECT 1 FROM {$this->table} WHERE ACTION_NAME = ? AND ACTION_ID <> ?",
                        [$data['ACTION_NAME'], $id]
                    );
                    if (!empty($check)) throw new InvalidArgumentException("Já existe uma ação com este nome.");
                }
                $data['ACTION_UPDATEBY'] = $updatedBy ?? $this->actingUserId;
                return $this->db->update($this->table, $data, ['ACTION_ID' => $id]);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Action::update', $id);
            }
        }

        public function delete(int $id): bool {
            try {
                $perms = $this->db->count('PERMISSIONS', ['PERMISSION_ACTIONID' => $id]);
                if ($perms > 0) {
                    throw new RuntimeException(
                        "Não é possível eliminar: existem {$perms} permissão(ões) a usar esta ação."
                    );
                }
                return $this->db->delete($this->table, ['ACTION_ID' => $id]);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Action::delete', $id);
            }
        }

        public function countAll(): int      { return $this->db->count($this->table); }
        public function countActive(): int   { return $this->db->count($this->table, ['ACTION_STATUS' => 'ACTIVE']); }

        public function exists(array $conditions): bool {
            return $this->db->exists($this->table, $conditions);
        }

        private function prepare(array $input, bool $requireName): array {
            $data = [];
            if (!empty($input['name']) || !empty($input['ACTION_NAME'])) {
                $name = Sanitize::string($input['name'] ?? $input['ACTION_NAME'], 50);
                if ($name === '' && $requireName) throw new InvalidArgumentException("O nome é obrigatório.");
                if ($name !== '') $data['ACTION_NAME'] = $name;
            } elseif ($requireName) {
                throw new InvalidArgumentException("O nome é obrigatório.");
            }
            if (isset($input['code']) || isset($input['ACTION_CODE'])) {
                $code = Sanitize::code($input['code'] ?? $input['ACTION_CODE'], 10);
                if ($code !== '') $data['ACTION_CODE'] = $code;
            }
            if (array_key_exists('description', $input) || array_key_exists('ACTION_DESCRIPTION', $input)) {
                $data['ACTION_DESCRIPTION'] = Sanitize::string(
                    $input['description'] ?? $input['ACTION_DESCRIPTION'], 1000
                ) ?: null;
            }
            if (isset($input['status']) || isset($input['ACTION_STATUS'])) {
                $data['ACTION_STATUS'] = Sanitize::enum(
                    $input['status'] ?? $input['ACTION_STATUS'],
                    ['ACTIVE','INACTIVE'], 'ACTIVE'
                );
            } elseif ($requireName) {
                $data['ACTION_STATUS'] = 'ACTIVE';
            }
            return $data;
        }

        private function rethrow(Throwable $e, string $method, ?int $recordId = null, array $extra = []): void {
            Log::error("{$method} falhou", 'ACTIONS', $e, array_merge($extra, [
                'file' => $e->getFile(), 'line' => $e->getLine(),
            ]), $this->actingUserId);
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                throw new RuntimeException(
                    "{$method} falhou\n  Mensagem: " . $e->getMessage()
                    . "\n  Ficheiro: " . $e->getFile() . ':' . $e->getLine(), 0, $e
                );
            }
            throw new RuntimeException("Erro ao processar a ação.", 0, $e);
        }
    }
?>