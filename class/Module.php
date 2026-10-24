<?php
    require_once __DIR__ . '/Database.php';
    require_once __DIR__ . '/Sanitize.php';
    require_once __DIR__ . '/Log.php';

    class Module {

        private Database $db;
        private string $table = 'MODULES';
        private ?int $actingUserId;

        public function __construct(?int $actingUserId = null) {
            $this->actingUserId = $actingUserId
                ?? (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
            $this->db = new Database($this->actingUserId);
        }

        public function create(array $input, ?int $createdBy = null): int {
            try {
                $data = $this->prepare($input, true);

                if (empty($data['MODULE_CODE'])) {
                    $data['MODULE_CODE'] = $this->generateUniqueCode($data['MODULE_NAME']);
                }
                if ($this->exists(['MODULE_CODE' => $data['MODULE_CODE']])) {
                    throw new InvalidArgumentException("Já existe um módulo com o código {$data['MODULE_CODE']}.");
                }
                if ($this->exists(['MODULE_NAME' => $data['MODULE_NAME']])) {
                    throw new InvalidArgumentException("Já existe um módulo com o nome {$data['MODULE_NAME']}.");
                }
                $data['MODULE_CREATEBY'] = $createdBy ?? $this->actingUserId;

                return (int)$this->db->create($this->table, $data);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Module::create', null, ['input_keys' => array_keys($input)]);
            }
        }

        public function read(array $conditions = [], array $additionalParams = []): array {
            try {
                return $this->db->read($this->table, $conditions, $additionalParams);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Module::read', null, ['conditions' => $conditions]);
            }
        }

        public function findById(int $id): ?array {
            try {
                return $this->db->readOne($this->table, ['MODULE_ID' => $id]);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Module::findById', $id);
            }
        }

        public function all(): array {
            return $this->read([], ['order_by' => 'MODULE_NAME ASC']);
        }

        public function search(string $term, ?string $status = null): array {
            try {
                $term = Sanitize::string($term, 100);
                $cond = [];
                if ($status !== null) {
                    $status = Sanitize::enum($status, ['ACTIVE','INACTIVE']);
                    if ($status) $cond['MODULE_STATUS'] = $status;
                }
                if ($term === '') {
                    return $this->read($cond, ['order_by' => 'MODULE_NAME ASC']);
                }
                $sql = "SELECT * FROM {$this->table}
                        WHERE (MODULE_NAME LIKE ? OR MODULE_CODE LIKE ? OR MODULE_DESCRIPTION LIKE ?)";
                $params = ["%{$term}%", "%{$term}%", "%{$term}%"];
                if (!empty($cond)) {
                    foreach ($cond as $col => $val) {
                        $sql .= " AND {$col} = ?";
                        $params[] = $val;
                    }
                }
                $sql .= " ORDER BY MODULE_NAME ASC";
                return $this->db->query($sql, $params);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Module::search', null, ['term' => $term]);
            }
        }

        public function update(int $id, array $input, ?int $updatedBy = null): bool {
            try {
                if (!$this->exists(['MODULE_ID' => $id])) {
                    throw new RuntimeException("Módulo #{$id} não encontrado.");
                }
                $data = $this->prepare($input, false);
                if (empty($data)) return true;

                if (!empty($data['MODULE_NAME'])) {
                    $check = $this->db->query(
                        "SELECT 1 FROM {$this->table} WHERE MODULE_NAME = ? AND MODULE_ID <> ?",
                        [$data['MODULE_NAME'], $id]
                    );
                    if (!empty($check)) {
                        throw new InvalidArgumentException("Já existe um módulo com este nome.");
                    }
                }
                $data['MODULE_UPDATEBY'] = $updatedBy ?? $this->actingUserId;
                return $this->db->update($this->table, $data, ['MODULE_ID' => $id]);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Module::update', $id);
            }
        }

        public function delete(int $id): bool {
            try {
                $perms = $this->db->count('PERMISSIONS', ['PERMISSION_MODULEID' => $id]);
                if ($perms > 0) {
                    throw new RuntimeException(
                        "Não é possível eliminar: existem {$perms} permissão(ões) a usar este módulo."
                    );
                }
                return $this->db->delete($this->table, ['MODULE_ID' => $id]);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Module::delete', $id);
            }
        }

        public function countAll(): int       { return $this->db->count($this->table); }
        public function countActive(): int    { return $this->db->count($this->table, ['MODULE_STATUS' => 'ACTIVE']); }
        public function countInactive(): int  { return $this->db->count($this->table, ['MODULE_STATUS' => 'INACTIVE']); }

        public function exists(array $conditions): bool {
            return $this->db->exists($this->table, $conditions);
        }

        public function generateUniqueCode(string $name): string {
            $base = acronym($name, 4);
            if ($base === '') $base = 'MOD';
            $candidate = $base;
            $i = 1;
            while ($this->exists(['MODULE_CODE' => $candidate])) {
                $candidate = $base . $i;
                $i++;
                if ($i > 999) throw new RuntimeException("Não foi possível gerar código único.");
            }
            return $candidate;
        }

        private function prepare(array $input, bool $requireName): array {
            $data = [];
            if (!empty($input['name']) || !empty($input['MODULE_NAME'])) {
                $name = Sanitize::string($input['name'] ?? $input['MODULE_NAME'], 50);
                if ($name === '' && $requireName) throw new InvalidArgumentException("O nome é obrigatório.");
                if ($name !== '') $data['MODULE_NAME'] = $name;
            } elseif ($requireName) {
                throw new InvalidArgumentException("O nome é obrigatório.");
            }
            if (isset($input['code']) || isset($input['MODULE_CODE'])) {
                $code = Sanitize::code($input['code'] ?? $input['MODULE_CODE'], 10);
                if ($code !== '') $data['MODULE_CODE'] = $code;
            }
            if (array_key_exists('description', $input) || array_key_exists('MODULE_DESCRIPTION', $input)) {
                $data['MODULE_DESCRIPTION'] = Sanitize::string(
                    $input['description'] ?? $input['MODULE_DESCRIPTION'], 1000
                ) ?: null;
            }
            if (isset($input['status']) || isset($input['MODULE_STATUS'])) {
                $data['MODULE_STATUS'] = Sanitize::enum(
                    $input['status'] ?? $input['MODULE_STATUS'],
                    ['ACTIVE','INACTIVE'], 'INACTIVE'
                );
            } elseif ($requireName) {
                $data['MODULE_STATUS'] = 'INACTIVE';
            }
            return $data;
        }

        private function rethrow(Throwable $e, string $method, ?int $recordId = null, array $extra = []): void {
            Log::error("{$method} falhou", 'MODULES', $e, array_merge($extra, [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]), $this->actingUserId);

            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                throw new RuntimeException(
                    "{$method} falhou\n  Mensagem: " . $e->getMessage()
                    . "\n  Ficheiro: " . $e->getFile() . ':' . $e->getLine(), 0, $e
                );
            }
            throw new RuntimeException("Erro ao processar o módulo.", 0, $e);
        }
    }
?>