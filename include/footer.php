        <!-- ==================== RODAPÉ ==================== -->
        <footer class="footer">
            <p>&copy; <?= date('Y') ?> <?= APP_NAME ?> — Controlo de Acessos Baseado em Perfis. Todos os direitos reservados.</p>
            <p class="text-muted small mb-0">Última atualização: <span id="footerUpdated"><?= date('d/m/Y H:i') ?></span></p>
        </footer>

        <!-- ==================== MODAIS GLOBAIS ==================== -->

        <!-- Sair -->
        <div class="modal fade" id="sairModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="bi bi-box-arrow-right me-2"></i>Terminar Sessão</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body"><p>Tem certeza que deseja sair do sistema?</p></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <a href="logout.php" class="btn btn-danger"><i class="bi bi-box-arrow-right me-1"></i>Sair</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Perfil -->
        <div class="modal fade" id="perfilModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header modal-header-verde">
                        <h5 class="modal-title"><i class="bi bi-person-circle me-2"></i>Perfil</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Utilizador</label><input type="text" class="form-control" value="<?= e($_SESSION['username'] ?? 'admin') ?>" readonly></div>
                        <div class="mb-3"><label class="form-label">Nome</label><input type="text" class="form-control" value="<?= e($_SESSION['user_name'] ?? 'Administrador') ?>" readonly></div>
                        <div class="mb-3"><label class="form-label">Perfil</label><input type="text" class="form-control" value="<?= e(($_SESSION['profile_code'] ?? '') . ' — ' . ($_SESSION['profile_name'] ?? '')) ?>" readonly></div>
                        <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" value="<?= e($_SESSION['user_email'] ?? '') ?>" readonly></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button></div>
                </div>
            </div>
        </div>

        <!-- Mudar Senha -->
        <div class="modal fade" id="senhaModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header modal-header-verde">
                        <h5 class="modal-title"><i class="bi bi-key me-2"></i>Alterar Senha</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="formMudarSenha">
                            <div class="mb-3"><label class="form-label">Senha Atual</label><input type="password" class="form-control" required></div>
                            <div class="mb-3"><label class="form-label">Nova Senha</label><input type="password" class="form-control" required minlength="<?= PASSWORD_MIN ?>"></div>
                            <div class="mb-3"><label class="form-label">Confirmar Nova Senha</label><input type="password" class="form-control" required minlength="<?= PASSWORD_MIN ?>"></div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" form="formMudarSenha" class="btn btn-verde"><i class="bi bi-check-circle me-1"></i>Alterar Senha</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== TOAST ==================== -->
        <div id="toastCenterContainer" class="toast-center-container"></div>

        <!-- ==================== ACESSIBILIDADE ==================== -->
        <button class="a11y-fab" id="a11yFab" aria-label="Abrir painel de acessibilidade" aria-expanded="false">
            <i class="bi bi-universal-access"></i>
        </button>

        <div class="a11y-panel" id="a11yPanel" role="dialog" aria-label="Painel de acessibilidade">
            <div class="a11y-body">
                <div class="a11y-row">
                    <span class="a11y-row-label"><i class="bi bi-fonts"></i> Tamanho da fonte</span>
                    <div class="a11y-font-group">
                        <button class="a11y-font-btn" id="fontMinus" aria-label="Diminuir fonte">A-</button>
                        <span class="a11y-font-value" id="fontValue">100%</span>
                        <button class="a11y-font-btn" id="fontPlus" aria-label="Aumentar fonte">A+</button>
                    </div>
                </div>
                <div class="a11y-row">
                    <span class="a11y-row-label"><i class="bi bi-moon-stars"></i> Modo escuro</span>
                    <button class="a11y-switch" id="themeSwitch" aria-label="Alternar modo escuro" aria-pressed="false"></button>
                </div>
                <button class="a11y-reset" id="resetAll">
                    <i class="bi bi-arrow-counterclockwise"></i> Restaurar padrão
                </button>
            </div>
        </div>

        <!-- ==================== SCRIPTS ==================== -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="asset/js/script.js"></script>
        <?php if (!empty($pageScript)): ?>
        <script src="<?= e($pageScript) ?>"></script>
        <?php endif; ?>
    </body>
</html>