<?php
/** @var array<string, string> $settings */
/** @var array{total_value: float, total_quantity: float, products: int, low_stock: int, locations: int} $stats */
$allowNegative = ($settings['allow_negative_stock'] ?? '0') === '1';
?>
<div class="page-head">
    <div>
        <span class="eyebrow"><?= t('Administration') ?></span>
        <h2><?= t('Paramètres') ?></h2>
        <p><?= t('Configuration générale de l’application et règles de gestion du stock.') ?></p>
    </div>
</div>

<form method="post" action="<?= url('/parametres') ?>" autocomplete="off">
    <?= csrf_field() ?>
    <section class="panel">
        <div class="panel-head">
            <div><h3><?= t('Identité de l’entreprise') ?></h3><p><?= t('Affichée dans l’interface et les exports') ?></p></div>
        </div>
        <div class="form-grid">
            <div class="field">
                <label for="companyName"><?= t('Nom de l’entreprise') ?> <span class="req">*</span></label>
                <input class="form-control" type="text" id="companyName" name="company_name" maxlength="120" required
                       value="<?= e($settings['company_name'] ?? 'Suivi Stock') ?>">
            </div>
            <div class="field">
                <label for="currency"><?= t('Devise') ?></label>
                <input class="form-control" type="text" id="currency" name="currency" maxlength="8"
                       value="<?= e($settings['currency'] ?? 'MAD') ?>">
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div><h3><?= t('Règles de stock') ?></h3><p><?= t('Comportement des validations') ?></p></div>
        </div>
        <div class="form-grid">
            <div class="field">
                <label><?= t('Méthode de valorisation') ?></label>
                <input class="form-control" type="text" value="<?= t('CUMP global par produit') ?>" disabled>
                <small class="field-hint"><?= t('Le CUMP est recalculé à chaque entrée validée et utilisé pour les sorties.') ?></small>
            </div>
            <div class="field">
                <label class="check-line">
                    <input class="form-check-input" type="checkbox" name="allow_negative_stock" value="1" <?= $allowNegative ? 'checked' : '' ?>>
                    <?= t('Autoriser les sorties en stock négatif') ?>
                </label>
                <small class="field-hint"><?= t('Désactivé par défaut : une sortie supérieure au stock disponible est bloquée.') ?></small>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-accent" type="submit"><i class="bi bi-save"></i> <?= t('Enregistrer les paramètres') ?></button>
        </div>
    </section>
</form>

<section class="panel">
    <div class="panel-head"><div><h3><?= t('Informations système') ?></h3><p><?= t('Diagnostic et API') ?></p></div></div>
    <div class="detail-grid">
        <div class="detail-item"><span><?= t('Application') ?></span><strong><?= t('Suivi Stock') ?></strong><small>PHP <?= e(PHP_VERSION) ?></small></div>
        <div class="detail-item"><span><?= t('Base de données') ?></span><strong><?= e((string) \App\Core\Config::get('db.name')) ?></strong><small>MySQL / MariaDB</small></div>
        <div class="detail-item"><span><?= t('Produits actifs') ?></span><strong><?= e((string) $stats['products']) ?></strong></div>
        <div class="detail-item"><span><?= t('Sites actifs') ?></span><strong><?= e((string) $stats['locations']) ?></strong></div>
        <div class="detail-item"><span><?= t('Valeur du stock') ?></span><strong><?= e(money($stats['total_value'])) ?></strong></div>
        <div class="detail-item"><span><?= t('API REST') ?></span><strong><code>/api/v1</code></strong><small>health · auth · products · stock · documents · movements</small></div>
    </div>
</section>

<section class="panel">
    <div class="panel-head"><div><h3><?= t('Rôles & permissions') ?></h3><p><?= t('Vue synthétique') ?></p></div></div>
    <div class="table-responsive">
        <table class="table table-premium align-middle">
            <thead><tr><th><?= t('Rôle') ?></th><th><?= t('Produits') ?></th><th><?= t('Entrées / transferts') ?></th><th><?= t('Sorties') ?></th><th><?= t('Inventaire') ?></th><th><?= t('Administration') ?></th></tr></thead>
            <tbody>
            <tr><td><strong><?= t('Administrateur') ?></strong></td><td><?= t('Tout') ?></td><td><?= t('Tout') ?></td><td><?= t('Tout') ?></td><td><?= t('Tout') ?></td><td><?= t('Oui') ?></td></tr>
            <tr><td><strong><?= t('Responsable stock') ?></strong></td><td><?= t('Tout') ?></td><td><?= t('Tout') ?></td><td><?= t('Tout') ?></td><td><?= t('Tout') ?></td><td><?= t('Non') ?></td></tr>
            <tr><td><strong><?= t('Magasinier') ?></strong></td><td><?= t('Lecture') ?></td><td><?= t('Tout') ?></td><td><?= t('Tout') ?></td><td><?= t('Tout') ?></td><td><?= t('Non') ?></td></tr>
            <tr><td><strong><?= t('Vendeur') ?></strong></td><td><?= t('Lecture') ?></td><td><?= t('Non') ?></td><td><?= t('Tout') ?></td><td><?= t('Lecture') ?></td><td><?= t('Non') ?></td></tr>
            <tr><td><strong><?= t('Auditeur') ?></strong></td><td><?= t('Lecture') ?></td><td><?= t('Lecture') ?></td><td><?= t('Lecture') ?></td><td><?= t('Lecture') ?></td><td><?= t('Non') ?></td></tr>
            </tbody>
        </table>
    </div>
</section>
