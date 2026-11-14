<?php
/** @var array<int, \Gos\Domain\Entities\Marque> $brands */
/** @var int $brandsTotal */
/** @var int $brandsPage */
/** @var int $brandsPages */
/** @var array<int, \App\Domain\Entities\Category> $categories */
/** @var int $categoriesTotal */
/** @var int $categoriesPage */
/** @var int $categoriesPages */
/** @var array<int, \App\Domain\Entities\Unit> $units */
/** @var array<int, \Gos\Domain\Entities\Fournisseur> $suppliers */
/** @var int $suppliersTotal */
/** @var int $suppliersPage */
/** @var int $suppliersPages */
/** @var array<int, \App\Domain\Entities\Location> $locations */
/** @var array<string, int> $queryParams */
/** @var bool $canManage */
$refPageUrl = static fn (string $param): callable => static function (int $target) use ($param, $queryParams): string {
    $params = $queryParams;
    $params[$param] = $target;
    return url('/referentiels?' . http_build_query($params));
};
$renderPager = static function (int $page, int $pages, int $total, callable $pageUrl): void {
    include __DIR__ . '/../partials/pager.php';
};
?>
<div class="page-head">
    <div>
        <span class="eyebrow"><?= t('Données de base') ?></span>
        <h2><?= t('Référentiels') ?></h2>
        <p><?= t('Marques, catégories, unités, fournisseurs et sites. Créez d’abord ces données avant les produits et les mouvements.') ?></p>
    </div>
</div>

<div class="alert alert-premium alert-info-soft">
    <i class="bi bi-lightbulb"></i>
    <div>
        <strong><?= t('Ordre recommandé :') ?></strong> <?= t('créez au minimum un site, une marque, une catégorie et une unité, puis un produit.') ?>
        <?= t('Les éléments désactivés ne sont plus proposés dans les formulaires mais restent historisés.') ?>
    </div>
</div>

<div class="ref-grid">
    <section class="panel">
        <div class="panel-head">
            <div><h3><?= t('Marques') ?></h3><p><?= t(':count marque(s)', ['count' => (int) $brandsTotal]) ?></p></div>
            <span class="pill pill-muted"><i class="bi bi-tags"></i></span>
        </div>
        <?php if ($canManage): ?>
            <form class="ref-add-form" method="post" action="<?= url('/referentiels/brand') ?>">
                <?= csrf_field() ?>
                <input class="form-control" type="text" name="name" maxlength="80" placeholder="<?= e(t('Nom de la marque')) ?>" required>
                <button class="btn btn-accent" type="submit"><i class="bi bi-plus-lg"></i></button>
            </form>
        <?php endif; ?>
        <div class="ref-list">
            <?php if ($brands === []): ?><p class="muted"><?= t('Aucune marque.') ?></p><?php endif; ?>
            <?php foreach ($brands as $brand): ?>
                <div class="ref-item">
                    <span><?= e($brand->name()) ?></span>
                    <div class="ref-item-actions">
                        <span class="pill <?= $brand->isActive() ? 'pill-success' : 'pill-muted' ?>"><?= $brand->isActive() ? t('Active') : t('Inactive') ?></span>
                        <?php if ($canManage): ?>
                            <form method="post" action="<?= url('/referentiels/brand/' . $brand->id() . '/basculer') ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-icon btn-sm" type="submit" title="<?= e($brand->isActive() ? t('Désactiver') : t('Réactiver')) ?>">
                                    <i class="bi <?= $brand->isActive() ? 'bi-toggle-on text-success' : 'bi-toggle-off' ?>"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php $renderPager($brandsPage, $brandsPages, $brandsTotal, $refPageUrl('pb')); ?>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div><h3><?= t('Catégories') ?></h3><p><?= t(':count catégorie(s)', ['count' => (int) $categoriesTotal]) ?></p></div>
            <span class="pill pill-muted"><i class="bi bi-diagram-3"></i></span>
        </div>
        <?php if ($canManage): ?>
            <form class="ref-add-form" method="post" action="<?= url('/referentiels/category') ?>">
                <?= csrf_field() ?>
                <input class="form-control" type="text" name="name" maxlength="80" placeholder="<?= e(t('Nom de la catégorie')) ?>" required>
                <button class="btn btn-accent" type="submit"><i class="bi bi-plus-lg"></i></button>
            </form>
        <?php endif; ?>
        <div class="ref-list">
            <?php if ($categories === []): ?><p class="muted"><?= t('Aucune catégorie.') ?></p><?php endif; ?>
            <?php foreach ($categories as $category): ?>
                <div class="ref-item">
                    <span><?= e($category->name()) ?></span>
                    <div class="ref-item-actions">
                        <span class="pill <?= $category->isActive() ? 'pill-success' : 'pill-muted' ?>"><?= $category->isActive() ? t('Active') : t('Inactive') ?></span>
                        <?php if ($canManage): ?>
                            <form method="post" action="<?= url('/referentiels/category/' . $category->id() . '/basculer') ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-icon btn-sm" type="submit" title="<?= e($category->isActive() ? t('Désactiver') : t('Réactiver')) ?>">
                                    <i class="bi <?= $category->isActive() ? 'bi-toggle-on text-success' : 'bi-toggle-off' ?>"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php $renderPager($categoriesPage, $categoriesPages, $categoriesTotal, $refPageUrl('pc')); ?>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div><h3><?= t('Unités') ?></h3><p><?= t(':count unité(s)', ['count' => count($units)]) ?></p></div>
            <span class="pill pill-muted"><i class="bi bi-rulers"></i></span>
        </div>
        <?php if ($canManage): ?>
            <form class="ref-add-form" method="post" action="<?= url('/referentiels/unit') ?>">
                <?= csrf_field() ?>
                <input class="form-control" type="text" name="code" maxlength="20" placeholder="<?= e(t('Code (ex. PCS)')) ?>" required>
                <input class="form-control" type="text" name="label" maxlength="60" placeholder="<?= e(t('Libellé (ex. Pièce)')) ?>" required>
                <button class="btn btn-accent" type="submit"><i class="bi bi-plus-lg"></i></button>
            </form>
        <?php endif; ?>
        <div class="ref-list">
            <?php if ($units === []): ?><p class="muted"><?= t('Aucune unité.') ?></p><?php endif; ?>
            <?php foreach ($units as $unit): ?>
                <div class="ref-item">
                    <span><span class="code-chip"><?= e($unit->code()) ?></span> <?= e($unit->label()) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div><h3><?= t('Sites') ?></h3><p><?= t(':count site(s)', ['count' => count($locations)]) ?></p></div>
            <span class="pill pill-muted"><i class="bi bi-geo-alt"></i></span>
        </div>
        <?php if ($canManage): ?>
            <details class="ref-add">
                <summary><i class="bi bi-plus-lg"></i> <?= t('Ajouter un site') ?></summary>
                <form class="ref-form-grid" method="post" action="<?= url('/referentiels/location') ?>">
                    <?= csrf_field() ?>
                    <div class="field"><label><?= t('Code') ?></label><input class="form-control" type="text" name="code" maxlength="20" placeholder="DEP-01" required></div>
                    <div class="field"><label><?= t('Nom') ?></label><input class="form-control" type="text" name="name" maxlength="120" placeholder="<?= e(t('Dépôt principal')) ?>" required></div>
                    <div class="field"><label><?= t('Type') ?></label>
                        <select class="form-select" name="type">
                            <option value="depot"><?= t('Dépôt') ?></option>
                            <option value="point_vente"><?= t('Point de vente') ?></option>
                        </select>
                    </div>
                    <div class="field"><label><?= t('Téléphone') ?></label><input class="form-control" type="text" name="phone" maxlength="30"></div>
                    <div class="field field-wide"><label><?= t('Adresse') ?></label><input class="form-control" type="text" name="address" maxlength="255"></div>
                    <div class="field field-wide"><button class="btn btn-accent" type="submit"><i class="bi bi-save"></i> <?= t('Enregistrer le site') ?></button></div>
                </form>
            </details>
        <?php endif; ?>
        <div class="ref-list">
            <?php if ($locations === []): ?><p class="muted"><?= t('Aucun site. Créez un dépôt ou un point de vente.') ?></p><?php endif; ?>
            <?php foreach ($locations as $location): ?>
                <div class="ref-item">
                    <div>
                        <span class="code-chip"><?= e($location->code()) ?></span>
                        <strong><?= e($location->name()) ?></strong>
                        <small class="d-block muted"><?= e($location->typeLabel()) ?><?= $location->address() !== null ? ' · ' . e($location->address()) : '' ?></small>
                    </div>
                    <div class="ref-item-actions">
                        <span class="pill <?= $location->isActive() ? 'pill-success' : 'pill-muted' ?>"><?= $location->isActive() ? 'Actif' : 'Inactif' ?></span>
                        <?php if ($canManage): ?>
                            <form method="post" action="<?= url('/referentiels/location/' . $location->id() . '/basculer') ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-icon btn-sm" type="submit" title="<?= $location->isActive() ? 'Désactiver' : 'Réactiver' ?>">
                                    <i class="bi <?= $location->isActive() ? 'bi-toggle-on text-success' : 'bi-toggle-off' ?>"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel ref-span">
        <div class="panel-head">
            <div><h3><?= t('Fournisseurs') ?></h3><p><?= t(':count fournisseur(s)', ['count' => (int) $suppliersTotal]) ?></p></div>
            <span class="pill pill-muted"><i class="bi bi-truck"></i></span>
        </div>
        <?php if ($canManage): ?>
            <details class="ref-add">
                <summary><i class="bi bi-plus-lg"></i> <?= t('Ajouter un fournisseur') ?></summary>
                <form class="ref-form-grid" method="post" action="<?= url('/referentiels/supplier') ?>">
                    <?= csrf_field() ?>
                    <div class="field"><label><?= t('Nom') ?> <span class="req">*</span></label><input class="form-control" type="text" name="name" maxlength="120" required></div>
                    <div class="field"><label>ICE</label><input class="form-control" type="text" name="ice" maxlength="30"></div>
                    <div class="field"><label><?= t('Téléphone') ?></label><input class="form-control" type="text" name="phone" maxlength="30"></div>
                    <div class="field"><label><?= t('E-mail') ?></label><input class="form-control" type="email" name="email" maxlength="160"></div>
                    <div class="field field-wide"><label><?= t('Adresse') ?></label><input class="form-control" type="text" name="address" maxlength="255"></div>
                    <div class="field field-wide"><button class="btn btn-accent" type="submit"><i class="bi bi-save"></i> <?= t('Enregistrer le fournisseur') ?></button></div>
                </form>
            </details>
        <?php endif; ?>
        <div class="ref-list">
            <?php if ($suppliers === []): ?><p class="muted"><?= t('Aucun fournisseur. Il est optionnel pour les entrées de stock initial.') ?></p><?php endif; ?>
            <?php foreach ($suppliers as $supplier): ?>
                <div class="ref-item">
                    <div>
                        <strong><?= e($supplier->name()) ?></strong>
                        <small class="d-block muted">
                            <?= e($supplier->ice() ?? 'ICE —') ?>
                            <?= $supplier->phone() !== null ? ' · ' . e($supplier->phone()) : '' ?>
                            <?= $supplier->email() !== null ? ' · ' . e($supplier->email()) : '' ?>
                        </small>
                    </div>
                    <div class="ref-item-actions">
                        <span class="pill <?= $supplier->isActive() ? 'pill-success' : 'pill-muted' ?>"><?= $supplier->isActive() ? 'Actif' : 'Inactif' ?></span>
                        <?php if ($canManage): ?>
                            <form method="post" action="<?= url('/referentiels/supplier/' . $supplier->id() . '/basculer') ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-icon btn-sm" type="submit" title="<?= $supplier->isActive() ? 'Désactiver' : 'Réactiver' ?>">
                                    <i class="bi <?= $supplier->isActive() ? 'bi-toggle-on text-success' : 'bi-toggle-off' ?>"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php $renderPager($suppliersPage, $suppliersPages, $suppliersTotal, $refPageUrl('pf')); ?>
    </section>
</div>
