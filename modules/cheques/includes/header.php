<?php
/**
 * En-tête commun du module Chèques : coquille visuelle unifiée.
 *
 * Variables attendues :
 *   $title  : titre de la page
 *   $active : clé du lien actif (dashboard, documents, …)
 */
$title = $title ?? 'Tableau de bord';
$active = $active ?? '';
$user = current_user();

$nav = [
    'Pilotage' => [
        ['key' => 'dashboard', 'label' => t('Tableau de bord'), 'href' => 'index.php', 'icon' => 'grid'],
        ['key' => 'statistiques', 'label' => t('Statistiques'), 'href' => 'statistiques/index.php', 'icon' => 'chart2'],
        ['key' => 'calendrier', 'label' => t('Calendrier de distribution'), 'href' => 'calendrier/index.php', 'icon' => 'calendar'],
        ['key' => 'rapports', 'label' => t('Rapports PDF'), 'href' => 'rapports/index.php', 'icon' => 'file'],
        ['key' => 'documents', 'label' => t('Chèques & effets'), 'href' => 'documents/index.php', 'icon' => 'wallet'],
        ['key' => 'beneficiaires', 'label' => t('Bénéficiaires'), 'href' => 'beneficiaires/index.php', 'icon' => 'users'],
        ['key' => 'personnes', 'label' => t('Personnes responsables'), 'href' => 'personnes/index.php', 'icon' => 'user'],
    ],
    'Ressources' => [
        ['key' => 'carnets', 'label' => t('Carnets & registres'), 'href' => 'carnets/index.php', 'icon' => 'book'],
        ['key' => 'alertes', 'label' => t('Alertes & rappels'), 'href' => 'alertes/index.php', 'icon' => 'bell'],
        ['key' => 'aide', 'label' => t('Guide & aide'), 'href' => 'aide.php', 'icon' => 'file'],
        ['key' => 'historique', 'label' => t('Historique'), 'href' => 'historique/index.php', 'icon' => 'clock'],
        ['key' => 'parametres', 'label' => t('Paramètres'), 'href' => 'parametres/index.php', 'icon' => 'settings'],
    ],
];

sh_layout_start([
    'title'      => t($title),
    'module'     => 'cheques',
    'active'     => $active,
    'nav'        => $nav,
    'extra_css'  => [
        asset('css/app.css'),
        asset('css/php.css'),
    ],
    'body_class' => 'module-cheques',
    'subtitle'   => t('Pilotage des paiements'),
]);
