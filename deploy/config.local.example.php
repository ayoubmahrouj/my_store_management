<?php
declare(strict_types=1);

/**
 * Modèle de configuration locale / production.
 *
 * Copiez ce fichier vers : shared/config.local.php
 * Il est fusionné avec shared/config.php et n'est jamais versionné.
 *
 * Les valeurs non renseignées ici gardent leur valeur par défaut
 * (ou celle des variables d'environnement DB_HOST, DB_NAME, etc.).
 */

return [
    'app' => [
        'env'      => 'production',            // local | production
        'debug'    => false,                   // true uniquement en développement
        'url'      => 'https://exemple.com',   // URL publique de l'application
        // 'base'  => '/gestion',              // laisser vide : détection automatique
        'timezone' => 'Africa/Casablanca',
    ],

    'db' => [
        'host'    => 'localhost',
        'port'    => '3306',
        'name'    => 'moncompte_gestion',      // nom complet cPanel (préfixé)
        'user'    => 'moncompte_gos',
        'pass'    => 'mot-de-passe-fort',
        'charset' => 'utf8mb4',
    ],

    'session' => [
        'name'     => 'gos_sid',
        'lifetime' => 7200,                    // secondes d'inactivité
    ],

    'security' => [
        'max_login_attempts' => 5,
        'lock_minutes'       => 15,
    ],
];
