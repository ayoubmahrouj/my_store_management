<?php
/** @var \App\Domain\Entities\InventorySession $session */
$editable = $session->isEditable();
$gapLines = $session->gapLines();
$gapValue = $session->gapValue();
$canAct = \App\Core\Auth::isRole('admin', 'responsable', 'magasinier');
?>
<div class="page-head no-print">
    <div>
        <span class="eyebrow"><?= t('Inventaire') ?></span>
        <h2 class="doc-title">
            <?= e($session->numero()) ?>
            <span class="pill <?= e(status_class($session->status())) ?>"><?= e($session->statusLabel()) ?></span>
        </h2>
        <p><?= e(t(':location · démarré le :date par :user', ['location' => $session->locationName(), 'date' => date('d/m/Y H:i', strtotime($session->startedAt())), 'user' => $session->startedByName()])) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-soft" href="<?= url('/inventaire') ?>"><i class="bi bi-arrow-left"></i> <?= t('Registre') ?></a>
        <a class="btn btn-soft" href="<?= url('/inventaire/' . $session->id() . '/export?format=xls') ?>" title="<?= e(t('Exporter en Excel')) ?>">
            <i class="bi bi-file-earmark-excel"></i> Excel
        </a>
        <a class="btn btn-soft" href="<?= url('/inventaire/' . $session->id() . '/export?format=pdf') ?>" title="<?= e(t('Exporter en PDF')) ?>">
            <i class="bi bi-file-earmark-pdf"></i> PDF
        </a>
        <button class="btn btn-soft" type="button" data-print><i class="bi bi-printer"></i> <?= t('Imprimer') ?></button>

        <?php if ($editable && $canAct): ?>
            <form method="post" action="<?= url('/inventaire/' . $session->id() . '/valider') ?>" class="inline-form"
                  data-confirm="<?= e(t('Valider l’inventaire ? Les écarts seront appliqués au stock réel.')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-accent" type="submit" <?= $session->countedLineCount() === 0 ? 'disabled' : '' ?>>
                    <i class="bi bi-check2-circle"></i> <?= t('Valider l’inventaire') ?>
                </button>
            </form>
            <form method="post" action="<?= url('/inventaire/' . $session->id() . '/annuler') ?>" class="inline-form"
                  data-confirm="<?= e(t('Annuler cette session d’inventaire ? Le comptage sera perdu.')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-danger-soft" type="submit"><i class="bi bi-x-circle"></i> <?= t('Annuler') ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="print-head">
    <div><strong>Suivi Stock</strong><span><?= t('Inventaire') ?> <?= e($session->numero()) ?></span></div>
    <div><?= e(t('Édité le :date', ['date' => date('d/m/Y H:i')])) ?></div>
</div>

<?php if ($session->isValidated()): ?>
    <div class="alert alert-premium alert-success-soft no-print">
        <i class="bi bi-check-circle"></i>
        <?= t('Inventaire validé') ?><?= $session->validatedAt() !== null ? ' ' . e(t('le :date', ['date' => date('d/m/Y H:i', strtotime($session->validatedAt()))])) : '' ?>.
        <?= t('Les écarts ont été appliqués au stock.') ?>
    </div>
<?php elseif ($session->isCancelled()): ?>
    <div class="alert alert-premium alert-danger-soft no-print">
        <i class="bi bi-x-octagon"></i> <?= t('Cette session a été annulée : aucun mouvement de stock.') ?>
    </div>
<?php elseif ($editable): ?>
    <div class="alert alert-premium alert-warning-soft no-print">
        <i class="bi bi-info-circle"></i>
        <?= t('Saisissez les quantités réellement comptées, enregistrez le comptage, puis validez pour appliquer les écarts.') ?>
    </div>
<?php endif; ?>

<div class="mini-stats cols-4">
    <div><span><?= t('Lignes') ?></span><strong><?= e((string) $session->lineCount()) ?></strong></div>
    <div><span><?= t('Comptées') ?></span><strong><?= e((string) $session->countedLineCount()) ?></strong></div>
    <div><span><?= t('Écarts') ?></span><strong class="<?= $gapLines > 0 ? 'text-warning' : '' ?>"><?= e((string) $gapLines) ?></strong></div>
    <div><span><?= t('Écart valorisé') ?></span><strong class="<?= $gapValue < 0 ? 'text-danger' : ($gapValue > 0 ? 'text-success' : '') ?>"><?= e(money($gapValue)) ?></strong></div>
</div>

<section class="panel">
    <div class="panel-head panel-head-wrap">
        <div>
            <h3><?= t('Feuille de comptage') ?></h3>
            <p><?= t('Stock théorique figé au démarrage de la session') ?></p>
        </div>
        <div class="input-icon compact no-print">
            <i class="bi bi-search"></i>
            <input class="form-control" type="search" id="inventoryFilter" placeholder="<?= e(t('Filtrer un produit…')) ?>">
        </div>
    </div>

    <?php if ($session->lines() === []): ?>
        <div class="empty-state">
            <i class="bi bi-box"></i>
            <h4><?= t('Aucun produit actif') ?></h4>
            <p><?= t('Créez des produits puis relancez un inventaire.') ?></p>
            <a class="btn btn-accent btn-add mt-3" href="<?= url('/produits/nouveau') ?>" data-modal
               title="<?= e(t('Nouveau produit')) ?>" aria-label="<?= e(t('Nouveau produit')) ?>">
                <i class="bi bi-plus-lg"></i>
            </a>
        </div>
    <?php else: ?>
        <form method="post" action="<?= url('/inventaire/' . $session->id() . '/enregistrer') ?>" data-inventory-table>
            <?= csrf_field() ?>
            <div class="table-responsive">
                <table class="table table-premium align-middle inventory-table">
                    <thead>
                    <tr>
                        <th><?= t('Produit') ?></th>
                        <th class="text-end"><?= t('Théorique') ?></th>
                        <th style="width: 160px" class="text-end"><?= t('Compté') ?></th>
                        <th class="text-end"><?= t('Écart') ?></th>
                        <th class="text-end">CUMP</th>
                        <th class="text-end"><?= t('Écart valorisé') ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($session->lines() as $line): ?>
                        <?php
                        $gap = $line->gap();
                        $gapClass = $gap === null ? 'muted' : ($gap < 0 ? 'text-danger' : ($gap > 0 ? 'text-success' : 'muted'));
                        ?>
                        <tr data-inventory-row>
                            <td>
                                <?php
                                $lineBrand = $line->brand();
                                $lineMeta = array_filter([$line->sku(), $line->unit()], static fn (string $v): bool => $v !== '');
                                ?>
                                <strong><?= e($line->designation()) ?><?= $lineBrand !== '' ? '&nbsp;&nbsp;&nbsp;' . e($lineBrand) : '' ?></strong>
                                <small class="d-block muted"><?= e(implode(' · ', $lineMeta)) ?></small>
                            </td>
                            <td class="text-end"><?= e(qty($line->theoretical())) ?></td>
                            <td>
                                <input class="form-control form-control-sm count-input text-end" type="number" min="0" step="0.001"
                                       name="counts[<?= $line->id() ?>]"
                                       value="<?= e(num_input($line->counted())) ?>"
                                       data-theoretical="<?= e(num_input($line->theoretical())) ?>"
                                       data-cost="<?= e(num_input($line->unitCost(), 2)) ?>"
                                       <?= $editable && $canAct ? '' : 'disabled' ?>>
                            </td>
                            <td class="text-end"><span class="gap-label <?= $gapClass ?>"><?= $gap === null ? '—' : e(($gap > 0 ? '+' : '') . qty($gap)) ?></span></td>
                            <td class="text-end"><?= e(money($line->unitCost())) ?></td>
                            <td class="text-end"><span class="gap-value <?= $gapClass ?>"><?= $gap === null ? '—' : e(money($line->gapValue())) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($editable && $canAct): ?>
                <div class="form-actions">
                    <button class="btn btn-accent" type="submit" name="action" value="save">
                        <i class="bi bi-save"></i> <?= t('Enregistrer le comptage') ?>
                    </button>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</section>

<?php if ($session->notes() !== null): ?>
    <section class="panel">
        <div class="panel-head"><div><h3><?= t('Notes') ?></h3></div></div>
        <p class="notes-block"><?= nl2br(e($session->notes())) ?></p>
    </section>
<?php endif; ?>
