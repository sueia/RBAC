<?php
    /**
     * forbidden.php — Página de acesso negado (403).
     *
     * É para aqui que o PermissionChecker redireciona quando o utilizador
     * tenta aceder a um recurso para o qual o seu perfil não tem permissão.
     */

    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/auth.php';   // exige sessão iniciada

    $msg = $_GET['msg'] ?? 'Sem permissão para aceder a esta página.';
    $pageTitle = 'Acesso Negado';
    include __DIR__ . '/include/navbar.php';
?>

<main class="main-content">
    <div class="container-fluid">

        <div class="row justify-content-center">
            <div class="col-12 col-lg-8 col-xl-6">

                <div class="card card-principal">
                    <div class="card-body text-center py-5 px-4">

                        <div style="
                            width: 96px; height: 96px;
                            margin: 0 auto 20px;
                            border-radius: 24px;
                            background: rgba(239, 68, 68, 0.12);
                            display: flex; align-items: center; justify-content: center;
                            font-size: 3rem;
                            color: #ef4444;
                        ">
                            <i class="bi bi-shield-x"></i>
                        </div>

                        <h1 class="fw-bold mb-2" style="font-size:1.8rem; color: var(--text-strong);">
                            Acesso Negado
                        </h1>

                        <p class="text-muted mb-4" style="font-size:1rem;">
                            <?= e($msg) ?>
                        </p>

                        <?php if (!empty($_SESSION['profile_code'])): ?>
                        <p class="text-muted small mb-4">
                            <i class="bi bi-person-badge me-1"></i>
                            O seu perfil é
                            <strong><?= e($_SESSION['profile_code']) ?> — <?= e($_SESSION['profile_name'] ?? '') ?></strong>.
                            Contacte o administrador se acredita que isto é um erro.
                        </p>
                        <?php endif; ?>

                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <a href="dashboard.php" class="btn btn-verde">
                                <i class="bi bi-house-door me-1"></i> Voltar ao Dashboard
                            </a>
                            <a href="javascript:history.back()" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Voltar
                            </a>
                        </div>

                    </div>
                </div>

                <p class="text-center text-muted small mt-3 mb-0">
                    <i class="bi bi-shield-lock me-1"></i>
                    Este evento foi registado no histórico de auditoria.
                </p>

            </div>
        </div>

    </div>
</main>

<?php include __DIR__ . '/include/footer.php'; ?>