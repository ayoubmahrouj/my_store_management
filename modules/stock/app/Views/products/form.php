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
        <span class="eyebrow">Référentiel produits</span>
        <h2><?= $isEdit ? 'Modifier le produit' : 'Nouveau produit' ?></h2>
        <p>Le CUMP est calculé automatiquement à chaque entrée validée. Le stock réel est suivi par site.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-soft" href="<?= url('/produits') ?>"><i class="bi bi-arrow-left"></i> Retour</a>
    </div>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-premium alert-danger-soft">
        <i class="bi bi-exclamation-octagon"></i>
        <div>
            <strong>Le produit n’a pas pu être enregistré.</strong>
            <ul class="mb-0 mt-1"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    </div>
<?php endif; ?>

<?php if ($missingRefs): ?>
    <div class="alert alert-premium alert-warning-soft">
        <i class="bi bi-diagram-3"></i>
        <div>
            <strong>Référentiels incomplets.</strong>
            Créez au moins une marque, une catégorie et une unité dans
            <a href="<?= url('/referentiels') ?>">Référentiels</a> avant d’enregistrer un produit.
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= $isEdit ? url('/produits/' . (int) $product['id']) : url('/produits') ?>" autocomplete="off">
    <?= csrf_field() ?>

    <section class="panel">
        <div class="panel-head">
            <div><h3>Identification</h3><p>Références internes et commerciales</p></div>
            <span class="pill pill-muted"><?= $isEdit ? 'Édition' : 'Création' ?></span>
        </div>

        <div class="form-grid form-grid-3">
            <div class="field">
                <label for="sku">SKU <span class="req">*</span></label>
                <input class="form-control" type="text" id="sku" name="sku" maxlength="50" required
                       value="<?= e((string) $value('sku')) ?>" placeholder="Ex. MED-HUI-01">
            </div>
            <div class="field">
                <label for="barcode">Code-barres</label>
                <input class="form-control" type="text" id="barcode" name="barcode" maxlength="60"
                       value="<?= e((string) $value('barcode')) ?>" placeholder="Optionnel">
            </div>
            <div class="field field-wide">
                <label for="designation">Désignation <span class="req">*</span></label>
                <input class="form-control" type="text" id="designation" name="designation" maxlength="160" required
                       value="<?= e((string) $value('designation')) ?>" placeholder="Nom du produit">
            </div>

            <div class="field">
                <label for="brandId">Marque <span class="req">*</span></label>
                <select class="form-select" id="brandId" name="brand_id" data-quick="brand" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= $brand->id() ?>" <?= (int) $value('brand_id') === $brand->id() ? 'selected' : '' ?>><?= e($brand->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="categoryId">Catégorie <span class="req">*</span></label>
                <select class="form-select" id="categoryId" name="category_id" data-quick="category" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category->id() ?>" <?= (int) $value('category_id') === $category->id() ? 'selected' : '' ?>><?= e($category->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="unitId">Unité <span class="req">*</span></label>
                <select class="form-select" id="unitId" name="unit_id" data-quick="unit" required>
                    <option value="">— Sélectionner —</option>
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
            <div><h3>Valorisation &amp; seuil</h3><p>Le coût d’achat initial sert de CUMP de départ</p></div>
        </div>
        <div class="form-grid form-grid-3">
            <div class="field">
                <label for="purchaseCost">Coût d’achat / CUMP initial</label>
                <div class="input-suffix">
                    <input class="form-control" type="number" id="purchaseCost" name="purchase_cost" min="0" step="0.01"
                           value="<?= e(money_input($value('purchase_cost', 0))) ?>">
                    <span><?= e(currency()) ?></span>
                </div>
                <small class="field-hint">Recalculé automatiquement par les entrées validées.</small>
            </div>
            <div class="field">
                <label for="salePrice">Prix de vente</label>
                <div class="input-suffix">
                    <input class="form-control" type="number" id="salePrice" name="sale_price" min="0" step="0.01"
                           value="<?= e(money_input($value('sale_price', 0))) ?>">
                    <span><?= e(currency()) ?></span>
                </div>
            </div>
            <div class="field">
                <label for="minStock">Seuil d’alerte</label>
                <input class="form-control" type="number" id="minStock" name="min_stock" min="0" step="0.001"
                       value="<?= e(num_input($value('min_stock', 0))) ?>">
                <small class="field-hint">Alerte lorsque la quantité totale est inférieure ou égale à ce seuil.</small>
            </div>

            <div class="field field-wide">
                <label class="check-line">
                    <input class="form-check-input" type="checkbox" name="active" value="1" <?= $isActive ? 'checked' : '' ?>>
                    Produit actif
                </label>
                <small class="field-hint">Un produit inactif reste historisé mais n’apparaît plus dans les formulaires de mouvement.</small>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= url('/produits') ?>">Annuler</a>
        <button class="btn btn-accent" type="submit">
            <i class="bi bi-save"></i> <?= $isEdit ? 'Enregistrer les modifications' : 'Créer le produit' ?>
        </button>
    </div>
</form>
