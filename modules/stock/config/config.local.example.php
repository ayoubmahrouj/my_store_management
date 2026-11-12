<?php
declare(strict_types=1);

/**
 * Configuration locale optionnelle.
 *
 * Copiez ce fichier vers config/config.local.php puis adaptez les valeurs.
 * Ce fichier local est ignoré par Git : il ne sera jamais poussé sur GitHub.
 *
 * Attention : les valeurs définies ici sont prioritaires sur les variables
 * d'environnement. En production, ne créez pas config.local.php et utilisez
 * plutôt les variables APP_* et DB_*.
 */
return [
    'app' => [
        'env'   => 'local',
        'debug' => true,
        'url'   => 'http://127.0.0.1:8080',
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'suivi_stock',
        'user' => 'root',
        'pass' => '',
    ],
];
