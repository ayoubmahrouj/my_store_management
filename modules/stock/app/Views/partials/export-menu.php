<?php
/** @var array<int, array{label: string, url: string, format: string, description?: string}> $exportLinks */
?>
<div class="dropdown export-dropdown">
    <button class="btn btn-soft dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-download"></i> Exporter
    </button>
    <ul class="dropdown-menu dropdown-menu-end export-menu">
        <?php foreach ($exportLinks as $link): ?>
            <?php
            $format = strtolower((string) ($link['format'] ?? 'xls'));
            $formatLabel = match ($format) {
                'pdf' => 'PDF',
                'csv' => 'CSV',
                default => 'XLS',
            };
            $formatIcon = match ($format) {
                'pdf' => 'bi-file-earmark-pdf',
                'csv' => 'bi-filetype-csv',
                default => 'bi-file-earmark-excel',
            };
            ?>
            <li>
                <a class="dropdown-item export-item" href="<?= e((string) $link['url']) ?>">
                    <span class="export-format <?= e($format) ?>"><i class="bi <?= e($formatIcon) ?>"></i> <?= e($formatLabel) ?></span>
                    <span class="export-copy">
                        <strong><?= e((string) $link['label']) ?></strong>
                        <?php if (!empty($link['description'])): ?>
                            <small><?= e((string) $link['description']) ?></small>
                        <?php endif; ?>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
