<?php
declare(strict_types=1);

/**
 * URL d'un asset avec version (force le navigateur à charger la dernière
 * version après une mise à jour de l'application).
 */
function asset_url(string $file): string
{
    $path = APP_ROOT . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $file;
    $version = is_file($path) ? (string) filemtime($path) : VERSION;

    return BASE . '/assets/' . $file . '?v=' . $version;
}

function nav_items(): array
{
    $items = [
        ['pos', t('nav_pos'), 'cart'],
        ['dashboard', t('nav_dashboard'), 'grid'],
        ['search', t('nav_search'), 'search'],
        ['clients', t('nav_clients'), 'users'],
        ['products', t('nav_products'), 'box'],
        ['sales', t('nav_sales'), 'receipt'],
        ['credits', t('nav_credits'), 'clock'],
        ['payments', t('nav_payments'), 'coins'],
        ['bons', t('nav_bons'), 'file'],
        ['reports', t('nav_reports'), 'chart'],
        ['stats', t('nav_stats'), 'activity'],
        ['settings', t('nav_settings'), 'settings'],
    ];

    if (is_admin()) {
        $items[] = ['users', t('nav_users'), 'shield'];
    }

    return $items;
}

function render_icon(string $name): string
{
    $icons = [
        'grid' => '<path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'box' => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/>',
        'receipt' => '<path d="M4 2h16v20l-4-2-4 2-4-2-4 2z"/><path d="M8 7h8M8 11h8M8 15h5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'coins' => '<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4M16.71 13.88l.7.71-2.82 2.82"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="M7 15l4-6 4 3 4-7"/>',
        'activity' => '<path d="M3 12h4l3 8 4-16 3 8h4"/>',
        'cart' => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6 1.65 1.65 0 0 0 10 3.09V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.14.34.4.6.74.74H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'print' => '<path d="M6 9V2h12v7"/><rect x="4" y="9" width="16" height="9" rx="2"/><path d="M6 18h12v4H6z"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h6"/>',
        'back' => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
    ];

    $path = $icons[$name] ?? $icons['grid'];

    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

function layout_start(string $title, string $page = ''): void
{
    $nav = [];
    foreach (nav_items() as [$key, $label, $icon]) {
        $nav['Navigation'][] = [
            'key'   => $key,
            'label' => $label,
            'href'  => url($key),
            'icon'  => $icon === 'activity' ? 'chart2' : $icon,
        ];
    }

    // Les messages locaux sont vidés : la coquille affiche ceux du socle.
    unset($_SESSION['flash']);

    sh_layout_start([
        'title'      => $title,
        'module'     => 'credits',
        'active'     => $page,
        'nav'        => $nav,
        'extra_css'  => [asset_url('app.css')],
        'extra_js'   => [asset_url('app.js')],
        'body_class' => 'module-credits',
        'subtitle'   => sh_setting('shop_name', APP_NAME),
    ]);
}

function layout_end(): void
{
    sh_layout_end();
}

/**
 * En-tête visible uniquement à l'impression (nom du commerce + titre + date).
 */
function print_header(string $title, string $meta = ''): void
{
    ?>
    <div class="print-head">
        <h2><?= e(setting('shop_name', APP_NAME)) ?></h2>
        <div class="meta">
            <?= e($title) ?><?= $meta !== '' ? ' — ' . e($meta) : '' ?>
            · <?= e(date('d/m/Y H:i')) ?>
        </div>
    </div>
    <?php
}

/**
 * Bouton d'impression (masqué à l'impression).
 */
function print_button(string $label = ''): void
{
    $label = $label !== '' ? $label : t('print');
    ?>
    <button class="btn btn-ghost no-print" type="button" onclick="window.print()">
        <?= render_icon('print') ?><?= e($label) ?>
    </button>
    <?php
}

function auth_layout_start(string $title): void
{
    sh_auth_layout_start($title);
}

function auth_layout_end(): void
{
    sh_auth_layout_end();
}
