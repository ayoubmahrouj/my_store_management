<?php /** @var string|null $error */ /** @var string $login */ ?>
<div class="auth-shell">
    <section class="auth-brand">
        <div class="auth-brand-top">
            <span class="brand-mark">S</span>
            <span class="brand-text">
                <strong><?= t('Suivi Stock') ?></strong>
                <small><?= t('Cockpit multi-sites') ?></small>
            </span>
        </div>

        <div class="auth-hero">
            <span class="eyebrow"><i class="bi bi-stars"></i> <?= t('Gestion de stock nouvelle génération') ?></span>
            <h1><?= t('Le stock,') ?><br><span><?= t('enfin') ?></span> <?= t('sous contrôle.') ?></h1>
            <p><?= t('Un seul cockpit pour vos dépôts et points de vente. Quantités, valeur, mouvements et responsabilités — tout est traçable.') ?></p>

            <ul class="auth-features">
                <li><i class="bi bi-check2-circle"></i> <?= t('Stock réel par produit et par site') ?></li>
                <li><i class="bi bi-check2-circle"></i> <?= t('Valorisation CUMP et capital stock') ?></li>
                <li><i class="bi bi-check2-circle"></i> <?= t('Transferts, inventaires et audit utilisateur') ?></li>
            </ul>
        </div>

        <div class="auth-stats">
            <div>
                <strong>100 %</strong>
                <span><?= t('traçable') ?></span>
            </div>
            <div>
                <strong><?= e(currency()) ?></strong>
                <span><?= t('valorisation') ?></span>
            </div>
            <div>
                <strong>API</strong>
                <span>REST v1</span>
            </div>
        </div>
    </section>

    <section class="auth-panel">
        <div class="auth-card">
            <div class="auth-card-head">
                <span class="mobile-brand"><span class="brand-mark">S</span> <?= t('Suivi Stock') ?></span>
                <span class="eyebrow"><?= t('Espace sécurisé') ?></span>
                <h2><?= t('Connexion') ?></h2>
                <p><?= t('Accédez à votre cockpit de stock.') ?></p>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-premium alert-success-soft" role="alert">
                    <i class="bi bi-check-circle"></i>
                    <span><?= e($success) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-premium alert-danger-soft" role="alert">
                    <i class="bi bi-shield-exclamation"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= url('/login') ?>" class="auth-form" autocomplete="on">
                <?= csrf_field() ?>

                <label class="form-label" for="login"><?= t('Identifiant') ?></label>
                <div class="input-icon">
                    <i class="bi bi-person"></i>
                    <input class="form-control" type="text" id="login" name="login" value="<?= e($login ?? '') ?>" placeholder="<?= e(t('ex. admin')) ?>" required autofocus>
                </div>

                <label class="form-label" for="password"><?= t('Mot de passe') ?></label>
                <div class="input-icon">
                    <i class="bi bi-lock"></i>
                    <input class="form-control" type="password" id="password" name="password" placeholder="••••••••" required>
                    <button class="btn-reveal" type="button" data-toggle-password="#password" aria-label="<?= e(t('Afficher le mot de passe')) ?>">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>

                <button class="btn btn-accent btn-lg w-100" type="submit">
                    <?= t('Se connecter') ?> <i class="bi bi-arrow-right"></i>
                </button>
            </form>

            <p class="auth-legal"><?= t('Accès réservé aux utilisateurs autorisés. Toutes les connexions sont journalisées.') ?></p>
        </div>
    </section>
</div>
