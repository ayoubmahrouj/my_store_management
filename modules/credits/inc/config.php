<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Configuration générale — Gestion Crédits (PHP natif)
|--------------------------------------------------------------------------
| Le module s'appuie entièrement sur le socle unifié : configuration,
| session, base de données et authentification sont partagées.
*/

$gosRoot = dirname(__DIR__, 3);
require_once $gosRoot . '/shared/bootstrap.php';

define('APP_ROOT', dirname(__DIR__));
define('DATA_DIR', APP_ROOT . DIRECTORY_SEPARATOR . 'data');
define('BACKUP_DIR', DATA_DIR . DIRECTORY_SEPARATOR . 'backups');
define('EXPORT_DIR', DATA_DIR . DIRECTORY_SEPARATOR . 'exports');

define('APP_NAME', 'Gestion Crédits');
define('CURRENCY', sh_setting('currency', 'MAD'));
define('VERSION', '2.0');

// URL de base du module (ex. /modules/credits)
define('BASE', sh_module_base('credits'));
define('DEBUG', (bool) sh_config('app.debug', false));

foreach ([DATA_DIR, BACKUP_DIR, EXPORT_DIR] as $dir) {
    if (! is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

if (DEBUG) {
    ini_set('display_errors', '1');
}
error_reporting(E_ALL);
