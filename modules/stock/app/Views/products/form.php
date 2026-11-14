<?php
/** @var array<string, mixed>|null $product */
/** @var array<string, mixed> $old */
/** @var array<int, string> $errors */
/** @var array<int, \Gos\Domain\Entities\Marque> $brands */
/** @var array<int, \App\Domain\Entities\Category> $categories */
/** @var array<int, \App\Domain\Entities\Unit> $units */
$product = $product ?? null;
$old = is_array($old) ? $old : [];
$errors = is_array($errors) ? $errors : [];
$isEdit = $product !== null;
$value = static function (string $key, mixed $default = '') use ($old, $product): mixed {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    if ($product !== null && array_key_exists($key, $product)) {
        return $product[$key];
    }
    return $default;
};
$activeRaw = array_key_exists('active', $old) ? $old['active'] : ($product['active'] ?? 1);
$isActive = (int) $activeRaw === 1 || $activeRaw === true || $activeRaw === '1';
$missingRefs = $brands === [] || $categories === [] || $units === [];
?>
<div class="page-head">
    <div>
        <span class="eyebrow"><?= t('Référentiel produits') ?></span>
        <h2><?= $isEdit ? t('Modifier le produit') : t('Nouveau produit') ?></h2>
        <p><?= t('Le CUMP est calculé automatiquement à chaque entrée validée. Le stock réel est suivi par site.') ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-soft" href="<?= url('/produits') ?>"><i class="bi bi-arrow-left"></i> <?= t('Retour') ?></a>
    </div>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-premium alert-danger-soft">
        <i class="bi bi-exclamation-octagon"></i>
        <div>
            <strong><?= t('Le produit n’a pas pu être enregistré.') ?></strong>
            <ul class="mb-0 mt-1"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    </div>
<?php endif; ?>

<?php if ($missingRefs): ?>
    <div class="alert alert-premium alert-warning-soft">
        <i class="bi bi-diagram-3"></i>
        <div>
            <strong><?= t('Référentiels incomplets.') ?></strong>
            <?= t('Créez au moins une marque, une catégorie et une unité dans') ?>
            <a href="<?= url('/referentiels') ?>"><?= t('Référentiels') ?></a> <?= t('avant d’enregistrer un produit.') ?>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= $isEdit ? url('/produits/' . (int) $product['id']) : url('/produits') ?>" autocomplete="off">
    <?= csrf_field() ?>

    <section class="panel">
        <div class="panel-head">
            <div><h3><?= t('Identification') ?></h3><p><?= t('Références internes et commerciales') ?></p></div>
            <span class="pill pill-muted"><?= $isEdit ? t('Édition') : t('Création') ?></span>
        </div>

        <div class="form-grid form-grid-3">
            <div class="field">
                <label for="sku">SKU <span class="req">*</span></label>
                <input class="form-control" type="text" id="sku" name="sku" maxlength="50" required
                       value="<?= e((string) $value('sku')) ?>" placeholder="<?= e(t('Ex. MED-HUI-01')) ?>">
            </div>
            <div class="field">
                <label for="barcode"><?= t('Code-barres') ?></label>
                <input class="form-control" type="text" id="barcode" name="barcode" maxlength="60"
                       value="<?= e((string) $value('barcode')) ?>" placeholder="<?= e(t('Optionnel')) ?>">
            </div>
            <div class="field field-wide">
                <label for="designation"><?= t('Désignation') ?> <span class="req">*</span></label>
                <input class="form-control" type="text" id="designation" name="designation" maxlength="160" required
                       value="<?= e((string) $value('designation')) ?>" placeholder="<?= e(t('Nom du produit')) ?>">
            </div>

            <div class="field">
                <label for="brandId"><?= t('Marque') ?> <span class="req">*</span></label>
                <select class="form-select" id="brandId" name="brand_id" data-quick="brand" required>
                    <option value=""><?= t('— Sélectionner —') ?></option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= $brand->id() ?>" <?= (int) $value('brand_id') === $brand->id() ? 'selected' : '' ?>><?= e($brand->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="categoryId"><?= t('Catégorie') ?> <span class="req">*</span></label>
                <select class="form-select" id="categoryId" name="category_id" data-quick="category" required>
                    <option value=""><?= t('— Sélectionner —') ?></option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category->id() ?>" <?= (int) $value('category_id') === $category->id() ? 'selected' : '' ?>><?= e($category->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="unitId"><?= t('Unité') ?> <span class="req">*</span></label>
                <select class="form-select" id="unitId" name="unit_id" data-quick="unit" required>
                    <option value=""><?= t('— Sélectionner —') ?></option>
                    <?php foreach ($units as $unit): ?>
                        <option value="<?= $unit->id() ?>" <?= (int) $value('unit_id') === $unit->id() ? 'selected' : '' ?>>
                            <?= e($unit->code()) ?> · <?= e($unit->label()) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div><h3><?= e(t('Valorisation & seuil')) ?></h3><p><?= t('Le coût d’achat initial sert de CUMP de départ') ?></p></div>
        </div>
        <div class="form-grid form-grid-3">
            <div class="field">
                <label for="purchaseCost"><?= t('Coût d’achat / CUMP initial') ?></label>
                <div class="input-suffix">
                    <input class="form-control" type="number" id="purchaseCost" name="purchase_cost" min="0" step="0.01"
                           value="<?= e(money_input($value('purchase_cost', 0))) ?>">
                    <span><?= e(currency()) ?></span>
                </div>
                <small class="field-hint"><?= t('Recalculé automatiquement par les entrées validées.') ?></small>
            </div>
            <div class="field">
                <label for="salePrice"><?= t('Prix de vente') ?></label>
                <div class="input-suffix">
                    <input class="form-control" type="number" id="salePrice" name="sale_price" min="0" step="0.01"
                           value="<?= e(money_input($value('sale_price', 0))) ?>">
                    <span><?= e(currency()) ?></span>
                </div>
            </div>
            <div class="field">
                <label for="minStock"><?= t('Seuil d’alerte') ?></label>
                <input class="form-control" type="number" id="minStock" name="min_stock" min="0" step="0.001"
                       value="<?= e(num_input($value('min_stock', 0))) ?>">
                <small class="field-hint"><?= t('Alerte lorsque la quantité totale est inférieure ou égale à ce seuil.') ?></small>
            </div>

            <div class="field field-wide">
                <label class="check-line">
                    <input class="form-check-input" type="checkbox" name="active" value="1" <?= $isActive ? 'checked' : '' ?>>
                    <?= t('Produit actif') ?>
                </label>
                <small class="field-hint"><?= t('Un produit inactif reste historisé mais n’apparaît plus dans les formulaires de mouvement.') ?></small>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= url('/produits') ?>"><?= t('Annuler') ?></a>
        <button class="btn btn-accent" type="submit">
            <i class="bi bi-save"></i> <?= $isEdit ? t('Enregistrer les modifications') : t('Créer le produit') ?>
        </button>
    </div>
</form>
