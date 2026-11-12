<?php
declare(strict_types=1);

/**
 * Configuration centrale de l'application unifiée.
 *
 * Toutes les valeurs peuvent être surchargées par variables d'environnement
 * (utile en production cPanel) ou par shared/config.local.php (local).
 */

$appRoot = dirname(__DIR__);

/** Détecte le chemin URL de l'application (racine ou sous-dossier). */
function gos_detect_base(string $appRoot): string
{
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
    $realRoot = realpath($appRoot);

    if ($docRoot !== false && $realRoot !== false) {
        $doc = str_replace('\\', '/', $docRoot);
        $root = str_replace('\\', '/', $realRoot);
        if ($root === $doc) {
            return '';
        }
        if (str_starts_with($root, rtrim($doc, '/') . '/')) {
            return rtrim(substr($root, strlen($doc)), '/');
        }
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    foreach (['/modules/stock', '/modules/credits', '/modules/cheques', '/api'] as $segment) {
        $pos = strpos($script, $segment);
        if ($pos !== false) {
            return substr($script, 0, $pos);
        }
    }

    $dir = rtrim(dirname($script), '/');
    return $dir === '.' ? '' : $dir;
}

$envBase = getenv('APP_BASE');
$base = $envBase !== false ? rtrim((string) $envBase, '/') : gos_detect_base($appRoot);
$env = getenv('APP_ENV') ?: 'production';

$config = [
    'app' => [
        'name'     => 'Gestion Our Stock',
        'tagline'  => 'Stock · Crédits · Chèques',
        'env'      => $env,
        'debug'    => getenv('APP_DEBUG') !== false ? getenv('APP_DEBUG') === '1' : $env === 'local',
        'url'      => getenv('APP_URL') ?: 'http://127.0.0.1:8080',
        'base'     => $base,
        'timezone' => getenv('APP_TZ') ?: 'Africa/Casablanca',
        'version'  => '2.0.0',
    ],
    'db' => [
        'host'    => getenv('DB_HOST') ?: '127.0.0.1',
        'port'    => getenv('DB_PORT') ?: '3306',
        'name'    => getenv('DB_NAME') ?: 'gestion_our_stock',
        'user'    => getenv('DB_USER') ?: 'root',
        'pass'    => getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '',
        'charset' => 'utf8mb4',
    ],
    'session' => [
        'name'     => getenv('SESSION_NAME') ?: 'gos_sid',
        'lifetime' => (int) (getenv('SESSION_LIFETIME') ?: 7200),
    ],
    'security' => [
        'max_login_attempts' => (int) (getenv('LOGIN_MAX_ATTEMPTS') ?: 5),
        'lock_minutes'       => (int) (getenv('LOGIN_LOCK_MINUTES') ?: 15),
    ],
];

$localFile = __DIR__ . '/config.local.php';
if (is_file($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        $config = array_replace_recursive($config, $local);
    }
}

return $config;
