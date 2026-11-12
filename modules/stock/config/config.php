<?php
declare(strict_types=1);

/**
 * Configuration du module Stock.
 * Toutes les valeurs viennent du socle unifié : une seule base, une seule session.
 */

$shared = $GLOBALS['GOS_CONFIG'] ?? require_once __DIR__ . '/../../../shared/config.php';

$appBase = rtrim((string) ($shared['app']['base'] ?? ''), '/');
$moduleBase = $appBase . '/modules/stock';

return [
    'app' => [
        'name'     => 'Gestion Our Stock',
        'tagline'  => 'Cockpit de stock multi-sites',
        'env'      => (string) ($shared['app']['env'] ?? 'production'),
        'debug'    => (bool) ($shared['app']['debug'] ?? false),
        'url'      => (string) ($shared['app']['url'] ?? 'http://127.0.0.1:8080'),
        'base'     => $moduleBase,
        'timezone' => (string) ($shared['app']['timezone'] ?? 'Africa/Casablanca'),
    ],
    'db' => $shared['db'],
    'session' => [
        'name'     => (string) ($shared['session']['name'] ?? 'gos_sid'),
        'lifetime' => (int) ($shared['session']['lifetime'] ?? 7200),
    ],
    'security' => [
        'max_login_attempts' => (int) ($shared['security']['max_login_attempts'] ?? 5),
        'lock_minutes'       => (int) ($shared['security']['lock_minutes'] ?? 15),
    ],
];
