<?php
declare(strict_types=1);

/**
 * Coquille visuelle commune : sidebar, topbar, contenu.
 * Les classes sont préfixées gos- pour ne jamais entrer en conflit avec le
 * CSS propre à chaque module.
 */

function sh_nav_icon(string $name): string
{
    $icons = [
        'grid'      => '<rect x="3" y="3" width="7" height="7" rx="1.6"/><rect x="14" y="3" width="7" height="7" rx="1.6"/><rect x="3" y="14" width="7" height="7" rx="1.6"/><rect x="14" y="14" width="7" height="7" rx="1.6"/>',
        'box'       => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
        'cart'      => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>',
        'receipt'   => '<path d="M4 2h16v20l-4-2-4 2-4-2-4 2z"/><path d="M8 7h8M8 11h8M8 15h5"/>',
        'coins'     => '<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4M16.71 13.88l.7.71-2.82 2.82"/>',
        'chart'     => '<path d="M3 3v18h18"/><path d="M7 15l4-6 4 3 4-7"/>',
        'chart2'    => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
        'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6 1.65 1.65 0 0 0 10 3.09V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.14.34.4.6.74.74H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'shield'    => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'file'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h6"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h2M14 14h2M8 17h2"/>',
        'bell'      => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        'book'      => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v18H6.5A2.5 2.5 0 0 1 4 18.5z"/><path d="M8 7h8M8 11h8M8 15h5"/>',
        'wallet'    => '<path d="M20 7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h15V7"/><path d="M16 13h3"/>',
        'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'back'      => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon'      => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
    ];
    $path = $icons[$name] ?? $icons['grid'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

function sh_module_label(string $module): string
{
    return match ($module) {
        'stock'   => sh_t('stock'),
        'credits' => sh_t('credits'),
        'cheques' => sh_t('cheques'),
        default   => sh_t('home'),
    };
}

function sh_resolve_link(string $module, string $href): string
{
    if ($href === '' || $href === '#') {
        return '#';
    }
    if (preg_match('#^(https?:)?//#i', $href)) {
        return $href;
    }
    if (str_starts_with($href, sh_base() . '/')) {
        return $href;
    }
    if (str_starts_with($href, '/')) {
        return sh_url($href);
    }
    if ($module === 'home') {
        return sh_url('/' . ltrim($href, '/'));
    }
    return sh_module_url($module, $href);
}

/** @param array<string, array<int, array{key:string,label:string,href:string,icon?:string}>> $nav */
function sh_render_nav(array $nav, string $module, string $active): void
{
    foreach ($nav as $section => $items) {
        if (!is_array($items) || $items === []) {
            continue;
        }
        echo '<div class="gos-nav-section">' . sh_e($section) . '</div>';
        foreach ($items as $item) {
            $key = (string) ($item['key'] ?? '');
            $href = (string) ($item['href'] ?? '#');
            $label = (string) ($item['label'] ?? $key);
            $icon = (string) ($item['icon'] ?? 'grid');
            $isActive = $active !== '' && $active === $key;
            echo '<a class="gos-nav-link' . ($isActive ? ' is-active' : '') . '" href="' . sh_e(sh_resolve_link($module, $href)) . '">'
                . sh_nav_icon($icon)
                . '<span>' . sh_e($label) . '</span>'
                . '</a>';
        }
    }
}

/** URL de bascule de langue, en conservant la page courante. */
function sh_lang_switch_url(string $locale): string
{
    $locale = $locale === 'ar' ? 'ar' : 'fr';
    $next = (string) ($_SERVER['REQUEST_URI'] ?? sh_url('/index.php'));
    return sh_url('/lang.php') . '?to=' . $locale . '&next=' . rawurlencode($next);
}

/** Sélecteur FR / AR affiché dans la barre supérieure. */
function sh_lang_switcher(): void
{
    $current = sh_locale();
    echo '<div class="gos-lang-switch" role="group" aria-label="' . sh_e(sh_t('language')) . '">';
    foreach (['fr', 'ar'] as $code) {
        $isActive = $current === $code;
        echo '<a class="gos-lang-btn' . ($isActive ? ' is-active' : '') . '"'
            . ' href="' . sh_e(sh_lang_switch_url($code)) . '"'
            . ' hreflang="' . sh_e($code) . '"'
            . ' title="' . sh_e(sh_locale_name($code)) . '"'
            . ($isActive ? ' aria-current="true"' : '') . '>'
            . sh_e(strtoupper($code)) . '</a>';
    }
    echo '</div>';
}

function sh_module_switcher(string $current): void
{
    $modules = [
        ['key' => 'home', 'label' => sh_t('home'), 'icon' => 'grid', 'url' => sh_url('/index.php')],
        ['key' => 'stock', 'label' => sh_t('stock'), 'icon' => 'box', 'url' => sh_module_url('stock', '/dashboard')],
        ['key' => 'credits', 'label' => sh_t('credits'), 'icon' => 'cart', 'url' => sh_module_url('credits', '/index.php?p=dashboard')],
        ['key' => 'cheques', 'label' => sh_t('cheques'), 'icon' => 'wallet', 'url' => sh_module_url('cheques', '/index.php')],
    ];

    echo '<div class="gos-switcher" role="navigation" aria-label="Modules">';
    foreach ($modules as $module) {
        if ($module['key'] !== 'home' && !core_can_access($module['key'])) {
            continue;
        }
        $isActive = $current === $module['key'];
        echo '<a class="gos-switch' . ($isActive ? ' is-active' : '') . '" href="' . sh_e($module['url']) . '" title="' . sh_e($module['label']) . '">'
            . sh_nav_icon($module['icon'])
            . '<span>' . sh_e($module['label']) . '</span>'
            . '</a>';
    }
    echo '</div>';
}

/**
 * @param array{title?:string,module?:string,active?:string,nav?:array,extra_css?:array<int,string>,before_css?:array<int,string>,extra_js?:array<int,string>,body_class?:string,subtitle?:string} $opts
 */
function sh_layout_start(array $opts = []): void
{
    $opts += [
        'title' => 'Tableau de bord',
        'module' => 'home',
        'active' => '',
        'nav' => [],
        'extra_css' => [],
        'before_css' => [],
        'extra_js' => [],
        'body_class' => '',
        'subtitle' => '',
    ];
    $GLOBALS['GOS_LAYOUT_OPTS'] = $opts;

    $user = core_user();
    $module = (string) $opts['module'];
    $title = (string) $opts['title'];
    $theme = $_COOKIE['gos_theme'] ?? 'dark';
    $theme = in_array($theme, ['dark', 'light'], true) ? $theme : 'dark';

    echo '<!doctype html>' . "\n";
    echo '<html lang="' . sh_e(sh_locale()) . '" dir="' . (sh_is_rtl() ? 'rtl' : 'ltr') . '" data-theme="' . sh_e($theme) . '">' . "\n<head>\n";
    echo '<meta charset="utf-8">' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
    echo '<meta name="theme-color" content="#080b10">' . "\n";
    echo '<meta name="gos-csrf" content="' . sh_e(core_csrf()) . '">' . "\n";
    echo '<title>' . sh_e($title) . ' · ' . sh_e(GOS_APP_NAME) . '</title>' . "\n";
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet">' . "\n";
    foreach ($opts['before_css'] as $css) {
        echo '<link rel="stylesheet" href="' . sh_e($css) . '">' . "\n";
    }
    echo '<link rel="stylesheet" href="' . sh_e(sh_asset('css/shell.css')) . '">' . "\n";
    foreach ($opts['extra_css'] as $css) {
        echo '<link rel="stylesheet" href="' . sh_e($css) . '">' . "\n";
    }
    echo '<link rel="stylesheet" href="' . sh_e(sh_asset('css/premium.css')) . '">' . "\n";
    echo '</head>' . "\n";
    echo '<body class="gos-body ' . sh_e($opts['body_class']) . '" data-theme="' . sh_e($theme) . '" data-module="' . sh_e($module) . '" data-base="' . sh_e(sh_base()) . '" data-quick-url="' . sh_e(sh_url('/api/quick_create.php')) . '">' . "\n";

    echo '<div class="gos-shell">';

    /* ---------------- Sidebar ---------------- */
    echo '<aside class="gos-sidebar" id="gosSidebar">';
    echo '<a class="gos-brand" href="' . sh_e(sh_url('/index.php')) . '">'
        . '<span class="gos-brand-mark">G</span>'
        . '<span class="gos-brand-text"><strong>' . sh_e(GOS_APP_NAME) . '</strong><small>' . sh_e(sh_t(sh_config('app.tagline', 'Stock · Crédits · Chèques'))) . '</small></span>'
        . '</a>';

    sh_module_switcher($module);

    echo '<nav class="gos-nav">';
    if ($opts['nav'] !== []) {
        sh_render_nav($opts['nav'], $module, (string) $opts['active']);
    }
    echo '</nav>';

    echo '<div class="gos-sidebar-foot">';
    if ($user !== null) {
        echo '<div class="gos-user-mini">'
            . '<span class="gos-avatar">' . sh_e(sh_initials((string) $user['name'])) . '</span>'
            . '<span class="gos-user-mini-text"><strong>' . sh_e((string) $user['name']) . '</strong><small>' . sh_e(sh_t((string) ($user['role_label'] ?? $user['role_code'] ?? ''))) . '</small></span>'
            . '</div>';
        echo '<form method="post" action="' . sh_e(sh_url('/logout.php')) . '" class="gos-logout-form">'
            . core_csrf_field()
            . '<button type="submit" class="gos-icon-btn" title="' . sh_e(sh_t('logout')) . '" aria-label="' . sh_e(sh_t('logout')) . '">' . sh_nav_icon('logout') . '</button>'
            . '</form>';
    }
    echo '</div>';
    echo '</aside>';

    /* ---------------- Main ---------------- */
    echo '<div class="gos-main">';
    echo '<header class="gos-topbar">';
    echo '<button class="gos-icon-btn gos-menu-btn" type="button" id="gosMenuBtn" aria-label="' . sh_e(sh_t('menu')) . '">'
        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>'
        . '</button>';
    echo '<div class="gos-page-head"><h1>' . sh_e($title) . '</h1><p>' . sh_e($opts['subtitle'] !== '' ? $opts['subtitle'] : sh_date_long()) . '</p></div>';
    echo '<div class="gos-topbar-actions">';
    if ($module !== 'home') {
        echo '<span class="gos-module-pill">' . sh_nav_icon($module === 'stock' ? 'box' : ($module === 'credits' ? 'cart' : 'wallet')) . sh_e(sh_module_label($module)) . '</span>';
    }
    sh_lang_switcher();
    echo '<button class="gos-icon-btn" type="button" id="gosThemeBtn" title="' . sh_e(sh_t('theme')) . '" aria-label="' . sh_e(sh_t('theme')) . '">'
        . '<span class="gos-theme-dark">' . sh_nav_icon('sun') . '</span><span class="gos-theme-light">' . sh_nav_icon('moon') . '</span>'
        . '</button>';
    if ($user !== null) {
        echo '<span class="gos-user-chip"><span class="gos-avatar gos-avatar-sm">' . sh_e(sh_initials((string) $user['name'])) . '</span>'
            . '<span class="gos-user-chip-text"><strong>' . sh_e((string) $user['name']) . '</strong><small>' . sh_e(sh_t((string) ($user['role_label'] ?? ''))) . '</small></span></span>';
    }
    echo '</div>';
    echo '</header>';

    echo '<div class="gos-overlay" id="gosOverlay"></div>';
    echo '<main class="gos-content">';
    sh_render_flashes();
}

function sh_layout_end(): void
{
    echo '</main>';
    echo '<footer class="gos-footer"><span>' . sh_e(GOS_APP_NAME) . ' · v' . sh_e(GOS_VERSION) . '</span><span>PHP ' . sh_e(PHP_VERSION) . ' · MySQL</span></footer>';
    echo '</div>'; // gos-main
    echo '</div>'; // gos-shell

    /* Modale de création rapide (+) */
    echo '<div class="gos-modal" id="gosQuickModal" hidden aria-hidden="true" role="dialog" aria-modal="true">'
        . '<div class="gos-modal-backdrop" data-quick-close></div>'
        . '<div class="gos-modal-card">'
        . '<header class="gos-modal-head"><div><span class="gos-eyebrow">' . sh_e(sh_t('quick_create')) . '</span><h2 id="gosQuickTitle">' . sh_e(sh_t('new')) . '</h2></div>'
        . '<button type="button" class="gos-icon-btn" data-quick-close aria-label="' . sh_e(sh_t('close')) . '">&times;</button></header>'
        . '<form id="gosQuickForm" class="gos-modal-body">'
        . '<div id="gosQuickFields"></div>'
        . '<p class="gos-form-error" id="gosQuickError" hidden></p>'
        . '<footer class="gos-modal-foot"><button type="button" class="gos-btn gos-btn-ghost" data-quick-close>' . sh_e(sh_t('cancel')) . '</button>'
        . '<button type="submit" class="gos-btn gos-btn-primary" id="gosQuickSubmit">' . sh_e(sh_t('save')) . '</button></footer>'
        . '</form></div></div>';

    echo '<script src="' . sh_e(sh_asset('js/shell.js')) . '"></script>';
    echo '<script src="' . sh_e(sh_asset('js/quick-create.js')) . '"></script>';
    $opts = $GLOBALS['GOS_LAYOUT_OPTS'] ?? [];
    foreach (($opts['extra_js'] ?? []) as $js) {
        echo '<script src="' . sh_e($js) . '"></script>';
    }
    echo '</body></html>';
}

function sh_auth_layout_start(string $title): void
{
    $theme = $_COOKIE['gos_theme'] ?? 'dark';
    $theme = in_array($theme, ['dark', 'light'], true) ? $theme : 'dark';
    echo '<!doctype html><html lang="' . sh_e(sh_locale()) . '" dir="' . (sh_is_rtl() ? 'rtl' : 'ltr') . '" data-theme="' . sh_e($theme) . '"><head>'
        . '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="theme-color" content="#080b10">'
        . '<title>' . sh_e($title) . ' · ' . sh_e(GOS_APP_NAME) . '</title>'
        . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="' . sh_e(sh_asset('css/shell.css')) . '">'
        . '<link rel="stylesheet" href="' . sh_e(sh_asset('css/premium.css')) . '">'
        . '</head><body class="gos-auth-body" data-theme="' . sh_e($theme) . '">'
        . '<div class="gos-auth-glow"></div>';
}

function sh_auth_layout_end(): void
{
    echo '<script src="' . sh_e(sh_asset('js/shell.js')) . '"></script></body></html>';
}
