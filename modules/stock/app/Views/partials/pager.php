<?php
/**
 * Pagination réutilisable.
 *
 * Variables attendues :
 *   int    $page    : page courante (1-indexée)
 *   int    $pages   : nombre total de pages
 *   int    $total   : nombre total d'éléments filtrés
 *   callable $pageUrl : fn(int $target): string
 */
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);

if ($pages <= 1) {
    return;
}

$window = 2;
$start = max(1, $page - $window);
$end = min($pages, $page + $window);
$firstDisabled = $page <= 1;
$lastDisabled = $page >= $pages;
?>
<nav class="table-pager" aria-label="Pagination">
    <span class="pager-info">
        Page <?= $page ?> / <?= $pages ?>
        <span class="pager-sep">·</span>
        <?= e(number_format((float) $total, 0, ',', ' ')) ?> résultat<?= $total > 1 ? 's' : '' ?>
    </span>
    <div class="pager-links">
        <a class="pager-btn<?= $firstDisabled ? ' is-disabled' : '' ?>" href="<?= $firstDisabled ? '#' : e($pageUrl(1)) ?>"
           <?= $firstDisabled ? 'aria-disabled="true" tabindex="-1"' : '' ?> title="Première page" aria-label="Première page">
            <i class="bi bi-chevron-double-left"></i>
        </a>
        <a class="pager-btn<?= $firstDisabled ? ' is-disabled' : '' ?>" href="<?= $firstDisabled ? '#' : e($pageUrl($page - 1)) ?>"
           <?= $firstDisabled ? 'aria-disabled="true" tabindex="-1"' : '' ?> title="Précédent" aria-label="Précédent">
            <i class="bi bi-chevron-left"></i>
        </a>

        <?php if ($start > 1): ?>
            <a class="pager-num" href="<?= e($pageUrl(1)) ?>">1</a>
            <?php if ($start > 2): ?><span class="pager-gap">…</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
            <a class="pager-num<?= $i === $page ? ' is-active' : '' ?>" href="<?= e($pageUrl($i)) ?>"
               <?= $i === $page ? 'aria-current="page"' : '' ?>><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($end < $pages): ?>
            <?php if ($end < $pages - 1): ?><span class="pager-gap">…</span><?php endif; ?>
            <a class="pager-num" href="<?= e($pageUrl($pages)) ?>"><?= $pages ?></a>
        <?php endif; ?>

        <a class="pager-btn<?= $lastDisabled ? ' is-disabled' : '' ?>" href="<?= $lastDisabled ? '#' : e($pageUrl($page + 1)) ?>"
           <?= $lastDisabled ? 'aria-disabled="true" tabindex="-1"' : '' ?> title="Suivant" aria-label="Suivant">
            <i class="bi bi-chevron-right"></i>
        </a>
        <a class="pager-btn<?= $lastDisabled ? ' is-disabled' : '' ?>" href="<?= $lastDisabled ? '#' : e($pageUrl($pages)) ?>"
           <?= $lastDisabled ? 'aria-disabled="true" tabindex="-1"' : '' ?> title="Dernière page" aria-label="Dernière page">
            <i class="bi bi-chevron-double-right"></i>
        </a>
    </div>
</nav>
