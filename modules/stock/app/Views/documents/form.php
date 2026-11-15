<?php
/** @var string $type */
/** @var string $label */
/** @var string $route */
/** @var array<string, string> $motifs */
/** @var array<int, \App\Domain\Entities\Location> $locations */
/** @var array<int, \Gos\Domain\Entities\Fournisseur> $suppliers */
/** @var array<int, array<string, mixed>> $products */
/** @var array<string, mixed> $old */
/** @var array<int, string> $errors */
$isEntry = $type === 'entree';
$isExit = $type === 'sortie';
$isTransfer = $type === 'transfert';
$errors = is_array($errors) ? $errors : [];
$old = is_array($old) ? $old : [];

$docDate = (string) ($old['doc_date'] ?? date('Y-m-d'));
$locationId = (int) ($old['location_id'] ?? 0);
$destId = (int) ($old['dest_location_id'] ?? 0);
$supplierId = (int) ($old['supplier_id'] ?? 0);
$motif = (string) ($old['motif'] ?? array_key_first($motifs));
$reference = (string) ($old['reference'] ?? '');
$notes = (string) ($old['notes'] ?? '');

$oldItems = $old['items'] ?? [];
if (!is_array($oldItems)) {
    $oldItems = [];
}
$oldItems = array_values(array_filter($oldItems, 'is_array'));
if ($oldItems === []) {
    $oldItems = [['product_id' => '', 'qty' => '1', 'unit_cost' => '', 'unit_price' => '']];
}

$renderOptions = static function (int $selected = 0) use ($products): void {
    foreach ($products as $product) {
        $brand = trim((string) ($product['brand'] ?? ''));
        ?>
        <option value="<?= (int) $product['id'] ?>"
                data-cost="<?= e((string) $product['purchase_cost']) ?>"
                data-price="<?= e((string) $product['sale_price']) ?>"
                data-unit="<?= e((string) $product['unit']) ?>"
                data-brand="<?= e($brand) ?>"
                <?= $selected === (int) $product['id'] ? 'selected' : '' ?>>
            <?= e((string) $product['sku']) ?> — <?= e((string) $product['designation']) ?><?= $brand !== '' ? ' · ' . e($brand) : '' ?>
        </option>
        <?php
    }
};
?>
<div class="page-head">
    <div>
        <span class="eyebrow"><?= e($label) ?></span>
        <h2><?= e($title ?? t('Nouveau document')) ?></h2>
        <p><?= t('Enregistrez un brouillon, vérifiez les lignes, puis validez pour impacter le stock.') ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-soft" href="<?= url($route) ?>"><i class="bi bi-arrow-left"></i> <?= t('Retour') ?></a>
    </div>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-premium alert-danger-soft">
        <i class="bi bi-exclamation-octagon"></i>
        <div>
            <strong><?= t('Le document n’a pas pu être enregistré.') ?></strong>
            <ul class="mb-0 mt-1">
                <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<?php if ($locations === []): ?>
    <div class="alert alert-premium alert-danger-soft">
        <i class="bi bi-geo-alt"></i>
        <?= t('Aucun site actif.') ?> <a href="<?= url('/referentiels') ?>"><?= t('Créez un dépôt ou un point de vente') ?></a> <?= t('avant de continuer.') ?>
    </div>
<?php endif; ?>

<?php if ($products === []): ?>
    <div class="alert alert-premium alert-danger-soft">
        <i class="bi bi-box-seam"></i>
        <?= t('Aucun produit actif.') ?> <a href="<?= url('/produits/nouveau') ?>" data-modal><?= t('Créez un produit') ?></a> <?= t('avant de continuer.') ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= url($route) ?>" class="document-form" autocomplete="off">
    <?= csrf_field() ?>

    <section class="panel">
        <div class="panel-head">
            <div><h3><?= t('Informations du document') ?></h3><p><?= t('Date, site et références') ?></p></div>
            <span class="pill pill-muted"><?= e($label) ?></span>
        </div>

        <div class="form-grid form-grid-3">
            <div class="field">
                <label for="docDate"><?= t('Date du document') ?> <span class="req">*</span></label>
                <input class="form-control" type="date" id="docDate" name="doc_date" value="<?= e($docDate) ?>" required>
            </div>

            <div class="field">
                <label for="locationId"><?= $isTransfer ? t('Site source') : t('Site') ?> <span class="req">*</span></label>
                <select class="form-select" id="locationId" name="location_id" data-quick="location" required>
                    <option value=""><?= t('— Sélectionner —') ?></option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= $location->id() ?>" <?= $locationId === $location->id() ? 'selected' : '' ?>>
                            <?= e($location->code()) ?> · <?= e($location->name()) ?> (<?= e($location->typeLabel()) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($isTransfer): ?>
                <div class="field">
                    <label for="destId"><?= t('Site destination') ?> <span class="req">*</span></label>
                    <select class="form-select" id="destId" name="dest_location_id" data-quick="location" required>
                        <option value=""><?= t('— Sélectionner —') ?></option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= $location->id() ?>" <?= $destId === $location->id() ? 'selected' : '' ?>>
                                <?= e($location->code()) ?> · <?= e($location->name()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-hint"><?= t('La destination doit être différente du site source.') ?></small>
                </div>
            <?php elseif ($isEntry): ?>
                <div class="field">
                    <label for="supplierId"><?= t('Fournisseur') ?></label>
                    <select class="form-select" id="supplierId" name="supplier_id" data-quick="supplier">
                        <option value=""><?= t('— Aucun / stock initial —') ?></option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= $supplier->id() ?>" <?= $supplierId === $supplier->id() ? 'selected' : '' ?>>
                                <?= e($supplier->name()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($suppliers === []): ?>
                        <small class="field-hint"><a href="<?= url('/referentiels') ?>"><?= t('Créez un fournisseur') ?></a> <?= t('si nécessaire.') ?></small>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="field">
                    <label for="motif"><?= t('Motif') ?> <span class="req">*</span></label>
                    <select class="form-select" id="motif" name="motif" required>
                        <?php foreach ($motifs as $value => $text): ?>
                            <option value="<?= e($value) ?>" <?= $motif === $value ? 'selected' : '' ?>><?= e($text) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($isEntry || $isTransfer): ?>
                <div class="field">
                    <label for="motif"><?= t('Motif') ?> <span class="req">*</span></label>
                    <select class="form-select" id="motif" name="motif" required>
                        <?php foreach ($motifs as $value => $text): ?>
                            <option value="<?= e($value) ?>" <?= $motif === $value ? 'selected' : '' ?>><?= e($text) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="field">
                <label for="reference"><?= t('Référence externe') ?></label>
                <input class="form-control" type="text" id="reference" name="reference" maxlength="100"
                       value="<?= e($reference) ?>" placeholder="<?= t('Bon de livraison, facture…') ?>">
            </div>

            <div class="field field-wide">
                <label for="notes"><?= t('Notes') ?></label>
                <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="<?= t('Commentaire interne (optionnel)') ?>"><?= e($notes) ?></textarea>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div><h3><?= t('Lignes du document') ?></h3><p><?= t('Produits, quantités et coûts') ?></p></div>
            <button class="btn btn-soft" type="button" data-add-line>
                <i class="bi bi-plus-lg"></i> <?= t('Ajouter une ligne') ?>
            </button>
        </div>

        <div class="lines-editor" data-lines-editor data-type="<?= e($type) ?>">
            <div class="table-responsive">
                <table class="table table-premium lines-table align-middle">
                    <thead>
                    <tr>
                        <th style="min-width: 300px"><?= t('Produit') ?></th>
                        <th style="width: 120px" class="text-end"><?= t('Quantité') ?></th>
                        <?php if ($isEntry): ?>
                            <th style="width: 150px" class="text-end"><?= t('Coût unitaire') ?></th>
                            <th style="width: 150px" class="text-end"><?= t('Prix de vente') ?></th>
                        <?php elseif ($isExit): ?>
                            <th style="width: 130px" class="text-end">CUMP</th>
                            <th style="width: 150px" class="text-end"><?= t('Prix de vente') ?></th>
                        <?php else: ?>
                            <th style="width: 130px" class="text-end">CUMP</th>
                        <?php endif; ?>
                        <th style="width: 130px" class="text-end"><?= t('Total') ?></th>
                        <th style="width: 50px"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($oldItems as $index => $item): ?>
                        <?php
                        $item = array_merge(['product_id' => '', 'qty' => '1', 'unit_cost' => '', 'unit_price' => ''], $item);
                        $selectedProduct = (int) ($item['product_id'] ?? 0);
                        ?>
                        <tr data-line>
                            <td>
                                <select class="form-select form-select-sm line-product" name="items[<?= $index ?>][product_id]" data-quick="product">
                                    <option value=""><?= t('— Choisir un produit —') ?></option>
                                    <?php $renderOptions($selectedProduct); ?>
                                </select>
                                <small class="line-unit muted"></small>
                            </td>
                            <td>
                                <input class="form-control form-control-sm line-qty text-end" type="number" min="0" step="0.001"
                                       name="items[<?= $index ?>][qty]" value="<?= e((string) $item['qty']) ?>">
                            </td>
                            <?php if ($isEntry): ?>
                                <td>
                                    <input class="form-control form-control-sm line-cost text-end" type="number" min="0" step="0.01"
                                           name="items[<?= $index ?>][unit_cost]" value="<?= e((string) $item['unit_cost']) ?>">
                                </td>
                                <td>
                                    <input class="form-control form-control-sm line-price text-end" type="number" min="0" step="0.01"
                                           name="items[<?= $index ?>][unit_price]" value="<?= e((string) $item['unit_price']) ?>">
                                </td>
                            <?php elseif ($isExit): ?>
                                <td>
                                    <input class="line-cost" type="hidden" name="items[<?= $index ?>][unit_cost]" value="<?= e((string) $item['unit_cost']) ?>">
                                    <span class="line-cost-label text-end d-block">—</span>
                                </td>
                                <td>
                                    <input class="form-control form-control-sm line-price text-end" type="number" min="0" step="0.01"
                                           name="items[<?= $index ?>][unit_price]" value="<?= e((string) $item['unit_price']) ?>">
                                </td>
                            <?php else: ?>
                                <td>
                                    <input class="line-cost" type="hidden" name="items[<?= $index ?>][unit_cost]" value="<?= e((string) $item['unit_cost']) ?>">
                                    <span class="line-cost-label text-end d-block">—</span>
                                </td>
                            <?php endif; ?>
                            <td class="text-end"><strong class="line-total">0,00</strong></td>
                            <td class="text-end">
                                <button class="btn btn-icon btn-sm" type="button" data-remove-line title="<?= t('Supprimer la ligne') ?>">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <template id="lineTemplate">
                <tr data-line>
                    <td>
                        <select class="form-select form-select-sm line-product" name="items[__INDEX__][product_id]" data-quick="product">
                            <option value=""><?= t('— Choisir un produit —') ?></option>
                            <?php $renderOptions(0); ?>
                        </select>
                        <small class="line-unit muted"></small>
                    </td>
                    <td>
                        <input class="form-control form-control-sm line-qty text-end" type="number" min="0" step="0.001"
                               name="items[__INDEX__][qty]" value="1">
                    </td>
                    <?php if ($isEntry): ?>
                        <td>
                            <input class="form-control form-control-sm line-cost text-end" type="number" min="0" step="0.01"
                                   name="items[__INDEX__][unit_cost]" value="">
                        </td>
                        <td>
                            <input class="form-control form-control-sm line-price text-end" type="number" min="0" step="0.01"
                                   name="items[__INDEX__][unit_price]" value="">
                        </td>
                    <?php elseif ($isExit): ?>
                        <td>
                            <input class="line-cost" type="hidden" name="items[__INDEX__][unit_cost]" value="">
                            <span class="line-cost-label text-end d-block">—</span>
                        </td>
                        <td>
                            <input class="form-control form-control-sm line-price text-end" type="number" min="0" step="0.01"
                                   name="items[__INDEX__][unit_price]" value="">
                        </td>
                    <?php else: ?>
                        <td>
                            <input class="line-cost" type="hidden" name="items[__INDEX__][unit_cost]" value="">
                            <span class="line-cost-label text-end d-block">—</span>
                        </td>
                    <?php endif; ?>
                    <td class="text-end"><strong class="line-total">0,00</strong></td>
                    <td class="text-end">
                        <button class="btn btn-icon btn-sm" type="button" data-remove-line title="<?= t('Supprimer la ligne') ?>">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </td>
                </tr>
            </template>
        </div>

        <div class="document-totals">
            <div><span><?= t('Total coût') ?></span><strong id="totalCost">0,00</strong></div>
            <?php if ($isEntry || $isExit): ?>
                <div><span><?= t('Total vente') ?></span><strong id="totalSale">0,00</strong></div>
            <?php endif; ?>
        </div>
    </section>

    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= url($route) ?>"><?= t('Annuler') ?></a>
        <?php if ($isTransfer): ?>
            <button class="btn btn-accent" type="submit" name="action" value="draft">
                <i class="bi bi-save"></i> <?= t('Enregistrer le transfert') ?>
            </button>
        <?php else: ?>
            <button class="btn btn-soft" type="submit" name="action" value="draft">
                <i class="bi bi-save"></i> <?= t('Enregistrer le brouillon') ?>
            </button>
            <button class="btn btn-accent" type="submit" name="action" value="validate"
                    data-confirm="<?= t('Valider ce document ? Le stock sera immédiatement impacté.') ?>">
                <i class="bi bi-check2-circle"></i> <?= t('Enregistrer et valider') ?>
            </button>
        <?php endif; ?>
    </div>
</form>
