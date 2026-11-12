<?php
/** @var string $content */
$user = auth_user();

$currentPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$basePath = rtrim((string) \App\Core\Config::get('app.base', ''), '/');
if ($basePath !== '' && str_starts_with($currentPath, $basePath)) {
    $currentPath = substr($currentPath, strlen($basePath)) ?: '/';
}
$currentPath = '/' . trim($currentPath, '/');

$nav = [
    'Pilotage' => [
        ['key' => 'dashboard', 'label' => 'Tableau de bord', 'href' => url('/dashboard'), 'icon' => 'grid'],
        ['key' => 'consultation', 'label' => 'Consultation', 'href' => url('/consultation'), 'icon' => 'search'],
        ['key' => 'statistiques', 'label' => 'Statistiques', 'href' => url('/statistiques'), 'icon' => 'chart2'],
        ['key' => 'rapports', 'label' => 'Rapports', 'href' => url('/rapports'), 'icon' => 'file'],
    ],
    'Opérations' => [
        ['key' => 'produits', 'label' => 'Produits', 'href' => url('/produits'), 'icon' => 'box'],
        ['key' => 'entrees', 'label' => 'Entrées', 'href' => url('/entrees'), 'icon' => 'receipt'],
        ['key' => 'sorties', 'label' => 'Sorties', 'href' => url('/sorties'), 'icon' => 'cart'],
        ['key' => 'transferts', 'label' => 'Transferts', 'href' => url('/transferts'), 'icon' => 'arrow'],
        ['key' => 'inventaire', 'label' => 'Inventaire', 'href' => url('/inventaire'), 'icon' => 'book'],
    ],
];

$admin = [];
$admin[] = ['key' => 'referentiels', 'label' => 'Référentiels', 'href' => url('/referentiels'), 'icon' => 'settings'];
if ($user !== null && $user->roleCode() === 'admin') {
    $admin[] = ['key' => 'utilisateurs', 'label' => 'Utilisateurs', 'href' => url('/utilisateurs'), 'icon' => 'users'];
    $admin[] = ['key' => 'parametres', 'label' => 'Paramètres', 'href' => url('/parametres'), 'icon' => 'shield'];
}
$nav['Administration'] = $admin;

$active = '';
foreach (['dashboard', 'consultation', 'statistiques', 'rapports', 'produits', 'entrees', 'sorties', 'transferts', 'inventaire', 'referentiels', 'utilisateurs', 'parametres'] as $candidate) {
    $route = '/' . $candidate;
    if ($currentPath === $route || str_starts_with($currentPath, $route . '/')) {
        if ($active === '' || strlen($route) > strlen($active)) {
            $active = $candidate;
        }
    }
}

sh_layout_start([
    'title'     => $title ?? 'Suivi Stock',
    'module'    => 'stock',
    'active'    => $active,
    'nav'       => $nav,
    'body_class'=> 'stock-module',
    'subtitle'  => $user?->locationName() ?? 'Tous les sites',
    'before_css'=> [
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
    ],
    'extra_css' => [asset('css/app.css')],
    'extra_js'  => [
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
        asset('js/app.js'),
    ],
]);

echo $content;

sh_layout_end();
