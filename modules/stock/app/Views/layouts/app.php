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
    t('Pilotage') => [
        ['key' => 'dashboard', 'label' => t('Tableau de bord'), 'href' => url('/dashboard'), 'icon' => 'grid'],
        ['key' => 'consultation', 'label' => t('Consultation'), 'href' => url('/consultation'), 'icon' => 'search'],
        ['key' => 'statistiques', 'label' => t('Statistiques'), 'href' => url('/statistiques'), 'icon' => 'chart2'],
        ['key' => 'rapports', 'label' => t('Rapports'), 'href' => url('/rapports'), 'icon' => 'file'],
    ],
    t('Opérations') => [
        ['key' => 'produits', 'label' => t('Produits'), 'href' => url('/produits'), 'icon' => 'box'],
        ['key' => 'entrees', 'label' => t('Entrées'), 'href' => url('/entrees'), 'icon' => 'receipt'],
        ['key' => 'sorties', 'label' => t('Sorties'), 'href' => url('/sorties'), 'icon' => 'cart'],
        ['key' => 'transferts', 'label' => t('Transferts'), 'href' => url('/transferts'), 'icon' => 'arrow'],
        ['key' => 'inventaire', 'label' => t('Inventaire'), 'href' => url('/inventaire'), 'icon' => 'book'],
    ],
];

$admin = [];
$admin[] = ['key' => 'referentiels', 'label' => t('Référentiels'), 'href' => url('/referentiels'), 'icon' => 'settings'];
if ($user !== null && $user->roleCode() === 'admin') {
    $admin[] = ['key' => 'utilisateurs', 'label' => t('Utilisateurs'), 'href' => url('/utilisateurs'), 'icon' => 'users'];
    $admin[] = ['key' => 'parametres', 'label' => t('Paramètres'), 'href' => url('/parametres'), 'icon' => 'shield'];
}
$nav[t('Administration')] = $admin;

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
    'title'     => $title ?? t('Suivi Stock'),
    'module'    => 'stock',
    'active'    => $active,
    'nav'       => $nav,
    'body_class'=> 'stock-module',
    'subtitle'  => $user?->locationName() ?? t('Tous les sites'),
    'before_css'=> [
        sh_is_rtl()
            ? 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css'
            : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
    ],
    'extra_css' => [asset('css/app.css')],
    'extra_js'  => [
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
        asset('js/app.js'),
    ],
]);

/* Pont de traduction pour le JavaScript (window.GOS_I18N). */
$i18n = [
    'locale'         => sh_locale(),
    'intl'           => sh_is_rtl() ? 'ar-MA' : 'fr-FR',
    'dir'            => sh_is_rtl() ? 'rtl' : 'ltr',
    'currency'       => currency(),
    'confirmDefault' => t('Confirmer cette action ?'),
    'showPassword'   => t('Afficher le mot de passe'),
    'hidePassword'   => t('Masquer le mot de passe'),
    'quickCreate'    => t('Création rapide'),
    'newLabel'       => t('Nouveau'),
    'loadingForm'    => t('Chargement du formulaire…'),
    'saving'         => t('Enregistrement…'),
    'formLoadError'  => t('Le formulaire n’a pas pu être chargé.'),
    'formHttpError'  => t('Impossible de charger le formulaire (HTTP :code).'),
    'networkError'   => t('Connexion impossible. Vérifiez votre réseau puis réessayez.'),
    'saveError'      => t('L’enregistrement a échoué. Vérifiez votre connexion puis réessayez.'),
    'chartsOffline'  => t('Graphiques indisponibles hors connexion. Les données restent consultables ci-dessous.'),
    'noCategoryStock'=> t('Aucun stock valorisé par catégorie pour le moment.'),
    'capital'        => t('Capital'),
    'capitalLabel'   => t('Capital : '),
    'entries'        => t('Entrées'),
    'exits'          => t('Sorties'),
    'newWords'       => [t('Nouveau'), t('Nouvelle'), t('Nouvel'), 'Nouveau', 'Nouvelle', 'Nouvel'],
];
echo '<script>window.GOS_I18N = '
    . json_encode($i18n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
    . ';</script>' . "\n";

echo $content;

sh_layout_end();
