<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

$societe = societe();
$user = current_user();

$title = 'Paramètres';
$active = 'parametres';

require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title"><?= h(t('Paramètres')) ?></h1>
        <p class="page-sub"><?= h(t('Informations de la société et sécurité du compte.')) ?></p>
    </div>
</div>

<div class="parametres-grid">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title"><?= h(t('Informations de la société')) ?></div>
                <div class="card-sub"><?= h(t('Utilisées dans les PDF et les notifications.')) ?></div>
            </div>
        </div>
        <div class="card-body">
            <form method="post" action="<?= h(url('parametres/save_societe.php')) ?>" autocomplete="off">
                <?= csrf_field() ?>
                <div class="form-grid">
                    <div class="field span-2">
                        <label for="socNom"><?= h(t('Nom de la société')) ?> <span class="req">*</span></label>
                        <input id="socNom" name="nom" type="text" class="input" required maxlength="150"
                               value="<?= h($societe['nom'] ?? '') ?>">
                    </div>
                    <div class="field span-2">
                        <label for="socAdresse"><?= h(t('Adresse')) ?></label>
                        <input id="socAdresse" name="adresse" type="text" class="input" maxlength="255"
                               value="<?= h($societe['adresse'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label for="socTelephone"><?= h(t('Téléphone')) ?></label>
                        <input id="socTelephone" name="telephone" type="text" class="input" maxlength="30"
                               value="<?= h($societe['telephone'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label for="socEmail"><?= h(t('Email')) ?></label>
                        <input id="socEmail" name="email" type="email" class="input" maxlength="150"
                               value="<?= h($societe['email'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= h(t('Enregistrer')) ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title"><?= h(t('Mot de passe')) ?></div>
                <div class="card-sub"><?= h(t('Compte :')) ?> <?= h($user['username'] ?? '') ?></div>
            </div>
        </div>
        <div class="card-body">
            <form method="post" action="<?= h(url('parametres/save_password.php')) ?>" autocomplete="off">
                <?= csrf_field() ?>
                <div class="form-grid">
                    <div class="field span-2">
                        <label for="currentPassword"><?= h(t('Mot de passe actuel')) ?> <span class="req">*</span></label>
                        <div class="password-wrap">
                            <input id="currentPassword" name="current_password" type="password" class="input"
                                   autocomplete="current-password" required>
                            <button type="button" class="password-toggle" data-password-toggle="currentPassword"
                                    aria-label="<?= h(t('Afficher / masquer')) ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"/>
                                    <circle cx="12" cy="12" r="2.6"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="field">
                        <label for="newPassword"><?= h(t('Nouveau mot de passe')) ?> <span class="req">*</span></label>
                        <div class="password-wrap">
                            <input id="newPassword" name="new_password" type="password" class="input"
                                   autocomplete="new-password" required minlength="8">
                            <button type="button" class="password-toggle" data-password-toggle="newPassword"
                                    aria-label="<?= h(t('Afficher / masquer')) ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"/>
                                    <circle cx="12" cy="12" r="2.6"/>
                                </svg>
                            </button>
                        </div>
                        <span class="hint"><?= h(t('8 caractères minimum, avec une lettre et un chiffre.')) ?></span>
                    </div>
                    <div class="field">
                        <label for="confirmPassword"><?= h(t('Confirmer le mot de passe')) ?> <span class="req">*</span></label>
                        <div class="password-wrap">
                            <input id="confirmPassword" name="confirm_password" type="password" class="input"
                                   autocomplete="new-password" required minlength="8">
                            <button type="button" class="password-toggle" data-password-toggle="confirmPassword"
                                    aria-label="<?= h(t('Afficher / masquer')) ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"/>
                                    <circle cx="12" cy="12" r="2.6"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= h(t('Modifier le mot de passe')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card mt-16">
    <div class="card-header">
        <div>
            <div class="card-title"><?= h(t('Informations du compte')) ?></div>
            <div class="card-sub"><?= h(t('Session ouverte sur cette application.')) ?></div>
        </div>
    </div>
    <div class="card-body">
        <div class="summary-list">
            <div class="summary-row">
                <span><?= h(t('Nom')) ?></span>
                <span class="mono"><?= h($user['nom'] ?? '—') ?></span>
            </div>
            <div class="summary-row">
                <span><?= h(t('Identifiant')) ?></span>
                <span class="mono"><?= h($user['username'] ?? '—') ?></span>
            </div>
            <div class="summary-row">
                <span><?= h(t('Rôle')) ?></span>
                <span class="mono"><?= h(($user['role'] ?? '') === 'ADMIN' ? t('Administrateur') : ($user['role'] ?? '—')) ?></span>
            </div>
            <div class="summary-row">
                <span><?= h(t('Langue')) ?></span>
                <span class="mono"><?= lang() === 'ar' ? 'العربية' : 'Français' ?></span>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
