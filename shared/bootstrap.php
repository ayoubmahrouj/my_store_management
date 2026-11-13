<?php
declare(strict_types=1);

/**
 * Bootstrap commun : configuration, session, en-têtes de sécurité, base.
 * Chaque module (stock, crédits, chèques) inclut ce fichier au lieu de gérer
 * sa propre session et sa propre connexion.
 */

if (defined('GOS_BOOTSTRAPPED')) {
    return;
}
define('GOS_BOOTSTRAPPED', true);

define('GOS_ROOT', dirname(__DIR__));

$GOS_CONFIG = require __DIR__ . '/config.php';
$GLOBALS['GOS_CONFIG'] = $GOS_CONFIG;

define('GOS_BASE', (string) ($GOS_CONFIG['app']['base'] ?? ''));
define('GOS_APP_NAME', (string) ($GOS_CONFIG['app']['name'] ?? 'Gestion Our Stock'));
define('GOS_VERSION', (string) ($GOS_CONFIG['app']['version'] ?? '1.0'));

date_default_timezone_set((string) ($GOS_CONFIG['app']['timezone'] ?? 'Africa/Casablanca'));

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/uploads.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/i18n.php';

/* -------------------------------------------------------------------------
| Autoload du domaine partagé (namespace Gos\)
|-------------------------------------------------------------------------*/

spl_autoload_register(static function (string $class): void {
    $prefix = 'Gos\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

/* -------------------------------------------------------------------------
| Erreurs
|-------------------------------------------------------------------------*/

$logDir = GOS_ROOT . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

error_reporting(E_ALL);
if (!empty($GOS_CONFIG['app']['debug'])) {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', $logDir . '/php-error.log');
}

/* -------------------------------------------------------------------------
| Session unique (partagée par tous les modules)
|-------------------------------------------------------------------------*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    $cookiePath = GOS_BASE === '' ? '/' : rtrim(GOS_BASE, '/') . '/';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', (string) max(1800, (int) ($GOS_CONFIG['session']['lifetime'] ?? 7200)));

    session_name((string) ($GOS_CONFIG['session']['name'] ?? 'gos_sid'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $cookiePath,
        'secure'   => sh_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Expiration par inactivité.
$lifetime = (int) ($GOS_CONFIG['session']['lifetime'] ?? 7200);
$last = (int) ($_SESSION['last_activity'] ?? 0);
if (!empty($_SESSION['user_id']) && $last > 0 && (time() - $last) > $lifetime) {
    core_logout();
    sh_redirect(sh_url('/login.php?expired=1'));
}
if (!empty($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
}

/* -------------------------------------------------------------------------
| En-têtes de sécurité
|-------------------------------------------------------------------------*/

header_remove('X-Powered-By');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('X-Permitted-Cross-Domain-Policies: none');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header(
    "Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdn.jsdelivr.net; "
    . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
    . "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net data:; "
    . "img-src 'self' data:; connect-src 'self'; "
    . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'"
);

if (sh_is_https()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

/* -------------------------------------------------------------------------
| Dossiers d'écriture
|-------------------------------------------------------------------------*/

foreach ([
    GOS_ROOT . '/storage/logs',
    GOS_ROOT . '/storage/exports',
    GOS_ROOT . '/storage/uploads',
    GOS_ROOT . '/storage/cache',
] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}
