<?php
/** @var array<int, \App\Domain\Entities\Location> $locations */
/** @var array<string, mixed> $old */
/** @var array<int, string> $errors */
$old = is_array($old) ? $old : [];
$errors = is_array($errors) ? $errors : [];
$locationId = (int) ($old['location_id'] ?? 0);
$notes = (string) ($old['notes'] ?? '');
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Inventaire</span>
        <h2>Nouvel inventaire</h2>
        <p>Sélectionnez le site à compter. Tous les produits actifs seront inclus dans la session.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-soft" href="<?= url('/inventaire') ?>"><i class="bi bi-arrow-left"></i> Retour</a>
    </div>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-premium alert-danger-soft">
        <i class="bi bi-exclamation-octagon"></i>
        <div>
            <strong>Impossible de créer l’inventaire.</strong>
            <ul class="mb-0 mt-1"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    </div>
<?php endif; ?>

<?php if ($locations === []): ?>
    <div class="alert alert-premium alert-danger-soft">
        <i class="bi bi-geo-alt"></i>
        Aucun site actif. <a href="<?= url('/referentiels') ?>">Créez un dépôt ou un point de vente</a> avant de lancer un inventaire.
    </div>
<?php endif; ?>

<form method="post" action="<?= url('/inventaire') ?>" class="panel" autocomplete="off">
    <?= csrf_field() ?>
    <div class="panel-head">
        <div><h3>Paramètres de la session</h3><p>Le stock théorique est figé au démarrage.</p></div>
    </div>

    <div class="form-grid">
        <div class="field">
            <label for="locationId">Site à inventorier <span class="req">*</span></label>
            <select class="form-select" id="locationId" name="location_id" data-quick="location" required>
                <option value="">— Sélectionner —</option>
                <?php foreach ($locations as $location): ?>
                    <option value="<?= $location->id() ?>" <?= $locationId === $location->id() ? 'selected' : '' ?>>
                        <?= e($location->code()) ?> · <?= e($location->name()) ?> (<?= e($location->typeLabel()) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field field-wide">
            <label for="notes">Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Équipe, zone, commentaire…"><?= e($notes) ?></textarea>
        </div>
    </div>

    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= url('/inventaire') ?>">Annuler</a>
        <button class="btn btn-accent" type="submit" <?= $locations === [] ? 'disabled' : '' ?>>
            <i class="bi bi-play-circle"></i> Démarrer l’inventaire
        </button>
    </div>
</form>
