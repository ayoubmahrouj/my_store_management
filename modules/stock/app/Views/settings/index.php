<?php
/** @var array<string, string> $settings */
/** @var array{total_value: float, total_quantity: float, products: int, low_stock: int, locations: int} $stats */
$allowNegative = ($settings['allow_negative_stock'] ?? '0') === '1';
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Administration</span>
        <h2>Paramètres</h2>
        <p>Configuration générale de l’application et règles de gestion du stock.</p>
    </div>
</div>

<form method="post" action="<?= url('/parametres') ?>" autocomplete="off">
    <?= csrf_field() ?>
    <section class="panel">
        <div class="panel-head">
            <div><h3>Identité de l’entreprise</h3><p>Affichée dans l’interface et les exports</p></div>
        </div>
        <div class="form-grid">
            <div class="field">
                <label for="companyName">Nom de l’entreprise <span class="req">*</span></label>
                <input class="form-control" type="text" id="companyName" name="company_name" maxlength="120" required
                       value="<?= e($settings['company_name'] ?? 'Suivi Stock') ?>">
            </div>
            <div class="field">
                <label for="currency">Devise</label>
                <input class="form-control" type="text" id="currency" name="currency" maxlength="8"
                       value="<?= e($settings['currency'] ?? 'MAD') ?>">
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div><h3>Règles de stock</h3><p>Comportement des validations</p></div>
        </div>
        <div class="form-grid">
            <div class="field">
                <label>Méthode de valorisation</label>
                <input class="form-control" type="text" value="CUMP global par produit" disabled>
                <small class="field-hint">Le CUMP est recalculé à chaque entrée validée et utilisé pour les sorties.</small>
            </div>
            <div class="field">
                <label class="check-line">
                    <input class="form-check-input" type="checkbox" name="allow_negative_stock" value="1" <?= $allowNegative ? 'checked' : '' ?>>
                    Autoriser les sorties en stock négatif
                </label>
                <small class="field-hint">Désactivé par défaut : une sortie supérieure au stock disponible est bloquée.</small>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-accent" type="submit"><i class="bi bi-save"></i> Enregistrer les paramètres</button>
        </div>
    </section>
</form>

<section class="panel">
    <div class="panel-head"><div><h3>Informations système</h3><p>Diagnostic et API</p></div></div>
    <div class="detail-grid">
        <div class="detail-item"><span>Application</span><strong>Suivi Stock</strong><small>PHP <?= e(PHP_VERSION) ?></small></div>
        <div class="detail-item"><span>Base de données</span><strong><?= e((string) \App\Core\Config::get('db.name')) ?></strong><small>MySQL / MariaDB</small></div>
        <div class="detail-item"><span>Produits actifs</span><strong><?= e((string) $stats['products']) ?></strong></div>
        <div class="detail-item"><span>Sites actifs</span><strong><?= e((string) $stats['locations']) ?></strong></div>
        <div class="detail-item"><span>Valeur du stock</span><strong><?= e(money($stats['total_value'])) ?></strong></div>
        <div class="detail-item"><span>API REST</span><strong><code>/api/v1</code></strong><small>health · auth · products · stock · documents · movements</small></div>
    </div>
</section>

<section class="panel">
    <div class="panel-head"><div><h3>Rôles &amp; permissions</h3><p>Vue synthétique</p></div></div>
    <div class="table-responsive">
        <table class="table table-premium align-middle">
            <thead><tr><th>Rôle</th><th>Produits</th><th>Entrées / transferts</th><th>Sorties</th><th>Inventaire</th><th>Administration</th></tr></thead>
            <tbody>
            <tr><td><strong>Administrateur</strong></td><td>Tout</td><td>Tout</td><td>Tout</td><td>Tout</td><td>Oui</td></tr>
            <tr><td><strong>Responsable stock</strong></td><td>Tout</td><td>Tout</td><td>Tout</td><td>Tout</td><td>Non</td></tr>
            <tr><td><strong>Magasinier</strong></td><td>Lecture</td><td>Tout</td><td>Tout</td><td>Tout</td><td>Non</td></tr>
            <tr><td><strong>Vendeur</strong></td><td>Lecture</td><td>Non</td><td>Tout</td><td>Lecture</td><td>Non</td></tr>
            <tr><td><strong>Auditeur</strong></td><td>Lecture</td><td>Lecture</td><td>Lecture</td><td>Lecture</td><td>Non</td></tr>
            </tbody>
        </table>
    </div>
</section>
