<?php
/** @var string $type */
/** @var string $label */
/** @var string $route */
/** @var array<string, string> $motifs */
/** @var \App\Domain\Entities\StockDocument $document */
$isTransfer = $document->type() === 'transfert';
$canAct = $type === 'sortie'
    ? \App\Core\Auth::isRole('admin', 'responsable', 'magasinier', 'vendeur')
    : \App\Core\Auth::isRole('admin', 'responsable', 'magasinier');
$showSale = !$isTransfer;
$motifCode = $document->motif();
$motifLabel = $motifCode !== null && $motifCode !== ''
    ? ($motifs[$motifCode] ?? ucfirst($motifCode))
    : '—';
?>
<div class="page-head no-print">
    <div>
        <span class="eyebrow"><?= e($label) ?></span>
        <h2 class="doc-title">
            <?= e($document->numero()) ?>
            <span class="pill <?= e(status_class($document->status())) ?>"><?= e(t($document->statusLabel())) ?></span>
        </h2>
        <p><?= e(t($document->typeLabel())) ?> <?= t('du') ?> <?= e(date('d/m/Y', strtotime($document->docDate()))) ?> · <?= e($document->locationName()) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-soft" href="<?= url($route) ?>"><i class="bi bi-arrow-left"></i> <?= t('Registre') ?></a>
        <a class="btn btn-soft" href="<?= url($route . '/' . $document->id() . '/export?format=xls') ?>" title="<?= t('Exporter le détail en Excel') ?>">
            <i class="bi bi-file-earmark-excel"></i> Excel
        </a>
        <a class="btn btn-soft" href="<?= url($route . '/' . $document->id() . '/export?format=pdf') ?>" title="<?= t('Exporter le détail en PDF') ?>">
            <i class="bi bi-file-earmark-pdf"></i> PDF
        </a>
        <button class="btn btn-soft" type="button" data-print><i class="bi bi-printer"></i> <?= t('Imprimer') ?></button>

        <?php if ($document->isDraft() && $canAct): ?>
            <?php if ($isTransfer): ?>
                <form method="post" action="<?= url($route . '/' . $document->id() . '/expedier') ?>" class="inline-form"
                      data-confirm="<?= t('Expédier ce transfert ? Le stock du site source sera diminué.') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-accent" type="submit"><i class="bi bi-truck"></i> <?= t('Expédier') ?></button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= url($route . '/' . $document->id() . '/valider') ?>" class="inline-form"
                      data-confirm="<?= t('Valider ce document ? Le stock sera immédiatement impacté.') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-accent" type="submit"><i class="bi bi-check2-circle"></i> <?= t('Valider') ?></button>
                </form>
            <?php endif; ?>
            <form method="post" action="<?= url($route . '/' . $document->id() . '/annuler') ?>" class="inline-form"
                  data-confirm="<?= t('Annuler ce brouillon ? Cette action est définitive.') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-danger-soft" type="submit"><i class="bi bi-x-circle"></i> <?= t('Annuler') ?></button>
            </form>
        <?php endif; ?>

        <?php if ($isTransfer && $document->isShipped() && $canAct): ?>
            <form method="post" action="<?= url($route . '/' . $document->id() . '/recevoir') ?>" class="inline-form"
                  data-confirm="<?= t('Réceptionner ce transfert ? Le stock du site destination sera augmenté.') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-accent" type="submit"><i class="bi bi-box-arrow-in-down"></i> <?= t('Réceptionner') ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="print-head">
    <div>
        <strong><?= t('Suivi Stock') ?></strong>
        <span><?= e(t($document->typeLabel())) ?> <?= e($document->numero()) ?></span>
    </div>
    <div><?= t('Édité le') ?> <?= e(date('d/m/Y H:i')) ?></div>
</div>

<?php if ($document->isDraft()): ?>
    <div class="alert alert-premium alert-warning-soft no-print">
        <i class="bi bi-info-circle"></i>
        <div>
            <strong><?= t('Brouillon :') ?></strong> <?= t('le stock n’est pas encore impacté.') ?>
            <?= $isTransfer
                ? t('Expédiez le transfert pour diminuer le stock source, puis réceptionnez-le à destination.')
                : t('Validez le document pour générer les mouvements de stock.') ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($document->isCancelled()): ?>
    <div class="alert alert-premium alert-danger-soft no-print">
        <i class="bi bi-x-octagon"></i> <?= t('Ce document a été annulé et n’a aucun impact sur le stock.') ?>
    </div>
<?php endif; ?>

<section class="panel">
    <div class="panel-head">
        <div><h3><?= t('Informations') ?></h3><p><?= t('Traçabilité du document') ?></p></div>
        <span class="pill <?= e(status_class($document->status())) ?>"><?= e(t($document->statusLabel())) ?></span>
    </div>

    <div class="detail-grid">
        <div class="detail-item"><span><?= t('Date') ?></span><strong><?= e(date('d/m/Y', strtotime($document->docDate()))) ?></strong></div>
        <div class="detail-item"><span><?= $isTransfer ? t('Site source') : t('Site') ?></span><strong><?= e($document->locationName()) ?></strong></div>
        <?php if ($isTransfer): ?>
            <div class="detail-item"><span><?= t('Site destination') ?></span><strong><?= e($document->destLocationName() ?? '—') ?></strong></div>
        <?php endif; ?>
        <?php if ($type === 'entree'): ?>
            <div class="detail-item"><span><?= t('Fournisseur') ?></span><strong><?= e($document->supplierName() ?? '—') ?></strong></div>
        <?php endif; ?>
        <div class="detail-item"><span><?= t('Motif') ?></span><strong><?= e($motifLabel) ?></strong></div>
        <div class="detail-item"><span><?= t('Référence') ?></span><strong><?= e($document->reference() ?? '—') ?></strong></div>
        <div class="detail-item"><span><?= t('Créé par') ?></span><strong><?= e($document->createdByName()) ?></strong><small><?= e(date('d/m/Y H:i', strtotime($document->createdAt()))) ?></small></div>
        <?php if ($document->validatedAt() !== null): ?>
            <div class="detail-item"><span><?= t('Validé le') ?></span><strong><?= e(date('d/m/Y H:i', strtotime($document->validatedAt()))) ?></strong></div>
        <?php endif; ?>
        <?php if ($document->shippedAt() !== null): ?>
            <div class="detail-item"><span><?= t('Expédié le') ?></span><strong><?= e(date('d/m/Y H:i', strtotime($document->shippedAt()))) ?></strong></div>
        <?php endif; ?>
        <?php if ($document->receivedAt() !== null): ?>
            <div class="detail-item"><span><?= t('Réceptionné le') ?></span><strong><?= e(date('d/m/Y H:i', strtotime($document->receivedAt()))) ?></strong></div>
        <?php endif; ?>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <div><h3><?= t('Lignes') ?></h3><p><?= t(':count ligne(s)', ['count' => $document->lineCount()]) ?></p></div>
    </div>
    <div class="table-responsive">
        <table class="table table-premium align-middle">
            <thead>
            <tr>
                <th><?= t('Produit') ?></th>
                <th class="text-end"><?= t('Quantité') ?></th>
                <th class="text-end"><?= t('Coût unitaire') ?></th>
                <?php if ($showSale): ?><th class="text-end"><?= t('Prix de vente') ?></th><?php endif; ?>
                <th class="text-end"><?= t('Total coût') ?></th>
                <?php if ($showSale): ?><th class="text-end"><?= t('Total vente') ?></th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($document->lines() as $line): ?>
                <tr>
                    <td>
                        <?php
                        $lineBrand = $line->brand();
                        $lineMeta = array_filter([$line->sku(), $line->unit()], static fn (string $v): bool => $v !== '');
                        ?>
                        <strong><?= e($line->designation()) ?><?= $lineBrand !== '' ? '&nbsp;&nbsp;&nbsp;' . e($lineBrand) : '' ?></strong>
                        <small class="d-block muted"><?= e(implode(' · ', $lineMeta)) ?></small>
                    </td>
                    <td class="text-end"><?= e(qty($line->qty())) ?></td>
                    <td class="text-end"><?= e(money($line->unitCost())) ?></td>
                    <?php if ($showSale): ?><td class="text-end"><?= e(money($line->unitPrice())) ?></td><?php endif; ?>
                    <td class="text-end"><strong><?= e(money($line->totalCost())) ?></strong></td>
                    <?php if ($showSale): ?><td class="text-end"><?= e(money($line->totalSale())) ?></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($document->lines() === []): ?>
                <tr><td colspan="<?= $showSale ? '6' : '4' ?>" class="text-center muted"><?= t('Aucune ligne.') ?></td></tr>
            <?php endif; ?>
            </tbody>
            <tfoot>
            <tr>
                <th colspan="<?= $showSale ? '4' : '3' ?>" class="text-end"><?= t('Totaux') ?></th>
                <th class="text-end"><?= e(money($document->totalCost())) ?></th>
                <?php if ($showSale): ?><th class="text-end"><?= e(money($document->totalSale())) ?></th><?php endif; ?>
            </tr>
            </tfoot>
        </table>
    </div>
</section>

<?php if ($document->notes() !== null): ?>
    <section class="panel">
        <div class="panel-head"><div><h3><?= t('Notes') ?></h3></div></div>
        <p class="notes-block"><?= nl2br(e($document->notes())) ?></p>
    </section>
<?php endif; ?>

<section class="panel no-print">
    <div class="panel-head"><div><h3><?= t('Cycle de vie') ?></h3><p><?= t('Journal du document') ?></p></div></div>
    <div class="timeline">
        <div class="timeline-step done">
            <span class="timeline-dot"></span>
            <div><strong><?= t('Brouillon créé') ?></strong><small><?= e($document->createdByName()) ?> · <?= e(date('d/m/Y H:i', strtotime($document->createdAt()))) ?></small></div>
        </div>
        <?php if ($isTransfer): ?>
            <div class="timeline-step <?= $document->shippedAt() !== null ? 'done' : '' ?>">
                <span class="timeline-dot"></span>
                <div><strong><?= t('Expédition') ?></strong><small><?= $document->shippedAt() !== null ? e(date('d/m/Y H:i', strtotime($document->shippedAt()))) : t('En attente') ?></small></div>
            </div>
            <div class="timeline-step <?= $document->receivedAt() !== null ? 'done' : '' ?>">
                <span class="timeline-dot"></span>
                <div><strong><?= t('Réception destination') ?></strong><small><?= $document->receivedAt() !== null ? e(date('d/m/Y H:i', strtotime($document->receivedAt()))) : t('En attente') ?></small></div>
            </div>
        <?php else: ?>
            <div class="timeline-step <?= $document->validatedAt() !== null ? 'done' : '' ?>">
                <span class="timeline-dot"></span>
                <div><strong><?= t('Validation &amp; mouvement de stock') ?></strong><small><?= $document->validatedAt() !== null ? e(date('d/m/Y H:i', strtotime($document->validatedAt()))) : t('En attente') ?></small></div>
            </div>
        <?php endif; ?>
    </div>
</section>
