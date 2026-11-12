<?php
/** @var string $type */
/** @var string $label */
/** @var string $route */
/** @var \App\Domain\Entities\StockDocument $document */
$isTransfer = $document->type() === 'transfert';
$canAct = $type === 'sortie'
    ? \App\Core\Auth::isRole('admin', 'responsable', 'magasinier', 'vendeur')
    : \App\Core\Auth::isRole('admin', 'responsable', 'magasinier');
$showSale = !$isTransfer;
?>
<div class="page-head no-print">
    <div>
        <span class="eyebrow"><?= e($label) ?></span>
        <h2 class="doc-title">
            <?= e($document->numero()) ?>
            <span class="pill <?= e(status_class($document->status())) ?>"><?= e($document->statusLabel()) ?></span>
        </h2>
        <p><?= e($document->typeLabel()) ?> du <?= e(date('d/m/Y', strtotime($document->docDate()))) ?> · <?= e($document->locationName()) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-soft" href="<?= url($route) ?>"><i class="bi bi-arrow-left"></i> Registre</a>
        <a class="btn btn-soft" href="<?= url($route . '/' . $document->id() . '/export?format=xls') ?>" title="Exporter le détail en Excel">
            <i class="bi bi-file-earmark-excel"></i> Excel
        </a>
        <a class="btn btn-soft" href="<?= url($route . '/' . $document->id() . '/export?format=pdf') ?>" title="Exporter le détail en PDF">
            <i class="bi bi-file-earmark-pdf"></i> PDF
        </a>
        <button class="btn btn-soft" type="button" data-print><i class="bi bi-printer"></i> Imprimer</button>

        <?php if ($document->isDraft() && $canAct): ?>
            <?php if ($isTransfer): ?>
                <form method="post" action="<?= url($route . '/' . $document->id() . '/expedier') ?>" class="inline-form"
                      data-confirm="Expédier ce transfert ? Le stock du site source sera diminué.">
                    <?= csrf_field() ?>
                    <button class="btn btn-accent" type="submit"><i class="bi bi-truck"></i> Expédier</button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= url($route . '/' . $document->id() . '/valider') ?>" class="inline-form"
                      data-confirm="Valider ce document ? Le stock sera immédiatement impacté.">
                    <?= csrf_field() ?>
                    <button class="btn btn-accent" type="submit"><i class="bi bi-check2-circle"></i> Valider</button>
                </form>
            <?php endif; ?>
            <form method="post" action="<?= url($route . '/' . $document->id() . '/annuler') ?>" class="inline-form"
                  data-confirm="Annuler ce brouillon ? Cette action est définitive.">
                <?= csrf_field() ?>
                <button class="btn btn-danger-soft" type="submit"><i class="bi bi-x-circle"></i> Annuler</button>
            </form>
        <?php endif; ?>

        <?php if ($isTransfer && $document->isShipped() && $canAct): ?>
            <form method="post" action="<?= url($route . '/' . $document->id() . '/recevoir') ?>" class="inline-form"
                  data-confirm="Réceptionner ce transfert ? Le stock du site destination sera augmenté.">
                <?= csrf_field() ?>
                <button class="btn btn-accent" type="submit"><i class="bi bi-box-arrow-in-down"></i> Réceptionner</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="print-head">
    <div>
        <strong>Suivi Stock</strong>
        <span><?= e($document->typeLabel()) ?> <?= e($document->numero()) ?></span>
    </div>
    <div>Édité le <?= e(date('d/m/Y H:i')) ?></div>
</div>

<?php if ($document->isDraft()): ?>
    <div class="alert alert-premium alert-warning-soft no-print">
        <i class="bi bi-info-circle"></i>
        <div>
            <strong>Brouillon :</strong> le stock n’est pas encore impacté.
            <?= $isTransfer ? 'Expédiez le transfert pour diminuer le stock source, puis réceptionnez-le à destination.' : 'Validez le document pour générer les mouvements de stock.' ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($document->isCancelled()): ?>
    <div class="alert alert-premium alert-danger-soft no-print">
        <i class="bi bi-x-octagon"></i> Ce document a été annulé et n’a aucun impact sur le stock.
    </div>
<?php endif; ?>

<section class="panel">
    <div class="panel-head">
        <div><h3>Informations</h3><p>Traçabilité du document</p></div>
        <span class="pill <?= e(status_class($document->status())) ?>"><?= e($document->statusLabel()) ?></span>
    </div>

    <div class="detail-grid">
        <div class="detail-item"><span>Date</span><strong><?= e(date('d/m/Y', strtotime($document->docDate()))) ?></strong></div>
        <div class="detail-item"><span><?= $isTransfer ? 'Site source' : 'Site' ?></span><strong><?= e($document->locationName()) ?></strong></div>
        <?php if ($isTransfer): ?>
            <div class="detail-item"><span>Site destination</span><strong><?= e($document->destLocationName() ?? '—') ?></strong></div>
        <?php endif; ?>
        <?php if ($type === 'entree'): ?>
            <div class="detail-item"><span>Fournisseur</span><strong><?= e($document->supplierName() ?? '—') ?></strong></div>
        <?php endif; ?>
        <div class="detail-item"><span>Motif</span><strong><?= e($document->motif() ?? '—') ?></strong></div>
        <div class="detail-item"><span>Référence</span><strong><?= e($document->reference() ?? '—') ?></strong></div>
        <div class="detail-item"><span>Créé par</span><strong><?= e($document->createdByName()) ?></strong><small><?= e(date('d/m/Y H:i', strtotime($document->createdAt()))) ?></small></div>
        <?php if ($document->validatedAt() !== null): ?>
            <div class="detail-item"><span>Validé le</span><strong><?= e(date('d/m/Y H:i', strtotime($document->validatedAt()))) ?></strong></div>
        <?php endif; ?>
        <?php if ($document->shippedAt() !== null): ?>
            <div class="detail-item"><span>Expédié le</span><strong><?= e(date('d/m/Y H:i', strtotime($document->shippedAt()))) ?></strong></div>
        <?php endif; ?>
        <?php if ($document->receivedAt() !== null): ?>
            <div class="detail-item"><span>Réceptionné le</span><strong><?= e(date('d/m/Y H:i', strtotime($document->receivedAt()))) ?></strong></div>
        <?php endif; ?>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <div><h3>Lignes</h3><p><?= $document->lineCount() ?> ligne(s)</p></div>
    </div>
    <div class="table-responsive">
        <table class="table table-premium align-middle">
            <thead>
            <tr>
                <th>Produit</th>
                <th class="text-end">Quantité</th>
                <th class="text-end">Coût unitaire</th>
                <?php if ($showSale): ?><th class="text-end">Prix de vente</th><?php endif; ?>
                <th class="text-end">Total coût</th>
                <?php if ($showSale): ?><th class="text-end">Total vente</th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($document->lines() as $line): ?>
                <tr>
                    <td>
                        <strong><?= e($line->designation()) ?></strong>
                        <small class="d-block muted"><?= e($line->sku()) ?> · <?= e($line->unit()) ?></small>
                    </td>
                    <td class="text-end"><?= e(qty($line->qty())) ?></td>
                    <td class="text-end"><?= e(money($line->unitCost())) ?></td>
                    <?php if ($showSale): ?><td class="text-end"><?= e(money($line->unitPrice())) ?></td><?php endif; ?>
                    <td class="text-end"><strong><?= e(money($line->totalCost())) ?></strong></td>
                    <?php if ($showSale): ?><td class="text-end"><?= e(money($line->totalSale())) ?></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($document->lines() === []): ?>
                <tr><td colspan="<?= $showSale ? '6' : '4' ?>" class="text-center muted">Aucune ligne.</td></tr>
            <?php endif; ?>
            </tbody>
            <tfoot>
            <tr>
                <th colspan="<?= $showSale ? '4' : '3' ?>" class="text-end">Totaux</th>
                <th class="text-end"><?= e(money($document->totalCost())) ?></th>
                <?php if ($showSale): ?><th class="text-end"><?= e(money($document->totalSale())) ?></th><?php endif; ?>
            </tr>
            </tfoot>
        </table>
    </div>
</section>

<?php if ($document->notes() !== null): ?>
    <section class="panel">
        <div class="panel-head"><div><h3>Notes</h3></div></div>
        <p class="notes-block"><?= nl2br(e($document->notes())) ?></p>
    </section>
<?php endif; ?>

<section class="panel no-print">
    <div class="panel-head"><div><h3>Cycle de vie</h3><p>Journal du document</p></div></div>
    <div class="timeline">
        <div class="timeline-step done">
            <span class="timeline-dot"></span>
            <div><strong>Brouillon créé</strong><small><?= e($document->createdByName()) ?> · <?= e(date('d/m/Y H:i', strtotime($document->createdAt()))) ?></small></div>
        </div>
        <?php if ($isTransfer): ?>
            <div class="timeline-step <?= $document->shippedAt() !== null ? 'done' : '' ?>">
                <span class="timeline-dot"></span>
                <div><strong>Expédition</strong><small><?= $document->shippedAt() !== null ? e(date('d/m/Y H:i', strtotime($document->shippedAt()))) : 'En attente' ?></small></div>
            </div>
            <div class="timeline-step <?= $document->receivedAt() !== null ? 'done' : '' ?>">
                <span class="timeline-dot"></span>
                <div><strong>Réception destination</strong><small><?= $document->receivedAt() !== null ? e(date('d/m/Y H:i', strtotime($document->receivedAt()))) : 'En attente' ?></small></div>
            </div>
        <?php else: ?>
            <div class="timeline-step <?= $document->validatedAt() !== null ? 'done' : '' ?>">
                <span class="timeline-dot"></span>
                <div><strong>Validation &amp; mouvement de stock</strong><small><?= $document->validatedAt() !== null ? e(date('d/m/Y H:i', strtotime($document->validatedAt()))) : 'En attente' ?></small></div>
            </div>
        <?php endif; ?>
    </div>
</section>
