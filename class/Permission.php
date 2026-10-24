<?php
    require_once __DIR__ . '/Database.php';
    require_once __DIR__ . '/Sanitize.php';
    require_once __DIR__ . '/Log.php';

    class Permission {

        private Database $db;
        private string $table = 'PERMISSIONS';
        private ?int $actingUserId;

        public function __construct(?int $actingUserId = null) {
            $this->actingUserId = $actingUserId
                ?? (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
            $this->db = new Database($this->actingUserId);
        }

        /** Permissões de um perfil: [moduleId][actionId] = true */
        public function matrixForProfile(int $profileId): array {
            try {
                $rows = $this->db->read($this->table, ['PERMISSION_PROFILEID' => $profileId]);
                $matrix = [];
                foreach ($rows as $r) {
                    $matrix[(int)$r['PERMISSION_MODULEID']][(int)$r['PERMISSION_ACTIONID']] = true;
                }
                return $matrix;
            } catch (Throwable $e) {
                $this->rethrow($e, 'Permission::matrixForProfile', null, ['profile_id' => $profileId]);
            }
        }

        public function countByProfile(int $profileId): int {
            try {
                return $this->db->count($this->table, ['PERMISSION_PROFILEID' => $profileId]);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Permission::countByProfile', null, ['profile_id' => $profileId]);
            }
        }

        /**
         * Guarda a matriz de um perfil.
         * Estratégia: apaga todas as do perfil e recria (transação).
         *
         * @param int   $profileId
         * @param array $selected  [moduleId => [actionId => 1]]
         * @param string $profileCode  (ex.: 'ADM') para gerar PERMISSION_CODE
         * @param array $actionsMap    [actionId => code]
         * @param array $modulesMap    [moduleId => code]
         */
        public function saveMatrix(int $profileId, array $selected, string $profileCode, array $actionsMap, array $modulesMap): int {
            try {
                $this->db->beginTransaction();

                $this->db->delete($this->table, ['PERMISSION_PROFILEID' => $profileId]);

                $count = 0;
                foreach ($selected as $moduleId => $actions) {
                    if (!is_array($actions)) continue;
                    foreach ($actions as $actionId => $v) {
                        if (!$v) continue;
                        $code = strtoupper($profileCode . '_' . ($actionsMap[(int)$actionId] ?? 'ACT') . '_' . ($modulesMap[(int)$moduleId] ?? 'MOD'));
                        $code = mb_substr($code, 0, 30);

                        $this->db->create($this->table, [
                            'PERMISSION_PROFILEID'   => (int)$profileId,
                            'PERMISSION_ACTIONID'    => (int)$actionId,
                            'PERMISSION_MODULEID'    => (int)$moduleId,
                            'PERMISSION_CODE'        => $code,
                            'PERMISSION_DESCRIPTION' => 'Permissão ' . $code,
                            'PERMISSION_CREATEBY'    => $this->actingUserId,
                        ]);
                        $count++;
                    }
                }
                $this->db->commit();
                return $count;
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) $this->db->rollback();
                $this->rethrow($e, 'Permission::saveMatrix', null, ['profile_id' => $profileId]);
            }
        }

        public function deleteByProfile(int $profileId): bool {
            try {
                return $this->db->delete($this->table, ['PERMISSION_PROFILEID' => $profileId]);
            } catch (Throwable $e) {
                $this->rethrow($e, 'Permission::deleteByProfile', null, ['profile_id' => $profileId]);
            }
        }

        private function rethrow(Throwable $e, string $method, ?int $recordId = null, array $extra = []): void {
            Log::error("{$method} falhou", 'PERMISSIONS', $e, array_merge($extra, [
                'file' => $e->getFile(), 'line' => $e->getLine(),
            ]), $this->actingUserId);
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                throw new RuntimeException(
                    "{$method} falhou\n  Mensagem: " . $e->getMessage()
                    . "\n  Ficheiro: " . $e->getFile() . ':' . $e->getLine(), 0, $e
                );
            }
            throw new RuntimeException("Erro ao processar as permissões.", 0, $e);
        }
    }
?>