<?php
declare(strict_types=1);

/**
 * Diagnostic de l'application (ligne de commande uniquement).
 *
 *   php database/doctor.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Diagnostic accessible uniquement en ligne de commande.');
}

define('GOS_ROOT', dirname(__DIR__));
$GLOBALS['GOS_CONFIG'] = require GOS_ROOT . '/shared/config.php';
define('GOS_BASE', (string) ($GLOBALS['GOS_CONFIG']['app']['base'] ?? ''));
define('GOS_APP_NAME', (string) ($GLOBALS['GOS_CONFIG']['app']['name'] ?? 'Gestion Our Stock'));
define('GOS_VERSION', (string) ($GLOBALS['GOS_CONFIG']['app']['version'] ?? '1.0'));

require GOS_ROOT . '/shared/helpers.php';
require GOS_ROOT . '/shared/db.php';

$ok = true;
$line = static function (string $label, bool $state, string $detail = ''): void {
    global $ok;
    $ok = $ok && $state;
    echo ($state ? '[ OK ] ' : '[ !! ] ') . $label . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
};

echo '=== ' . GOS_APP_NAME . ' — diagnostic ===' . PHP_EOL;
echo 'PHP ' . PHP_VERSION . ' · ' . PHP_SAPI . PHP_EOL;
echo 'Base : ' . sh_config('db.name', 'gestion_our_stock') . PHP_EOL . PHP_EOL;

$line('PHP >= 8.1', PHP_VERSION_ID >= 80100, PHP_VERSION);

foreach (['pdo', 'pdo_mysql', 'mbstring', 'json', 'openssl', 'fileinfo'] as $extension) {
    $line('Extension ' . $extension, extension_loaded($extension));
}

$line('Dossier storage accessible en écriture', is_dir(GOS_ROOT . '/storage') && is_writable(GOS_ROOT . '/storage'), GOS_ROOT . '/storage');

try {
    $pdo = core_db();
    $line('Connexion MySQL', true, (string) core_scalar('SELECT VERSION()'));
    $tables = (int) core_scalar(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE'",
        [(string) sh_config('db.name', 'gestion_our_stock')]
    );
    $views = (int) core_scalar(
        "SELECT COUNT(*) FROM information_schema.views WHERE table_schema = ?",
        [(string) sh_config('db.name', 'gestion_our_stock')]
    );
    $line('Tables installées', $tables >= 25, $tables . ' table(s)');
    $line('Vues de compatibilité', $views >= 6, $views . ' vue(s)');

    $admins = (int) core_scalar("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'admin' AND u.active = 1");
    $line('Compte administrateur actif', $admins > 0, $admins . ' compte(s)');

    $requiredViews = ['credits_products', 'document_paiement', 'carnet', 'alerte_config', 'alerte_log', 'audit_log'];
    foreach ($requiredViews as $view) {
        $exists = (int) core_scalar(
            'SELECT COUNT(*) FROM information_schema.views WHERE table_schema = ? AND table_name = ?',
            [(string) sh_config('db.name', 'gestion_our_stock'), $view]
        );
        $line('Vue ' . $view, $exists === 1);
    }

    // Chaque population de tiers doit avoir sa propre table physique.
    $requiredTables = ['clients', 'beneficiaire', 'personne', 'suppliers'];
    foreach ($requiredTables as $table) {
        $exists = (int) core_scalar(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ? AND table_type = 'BASE TABLE'",
            [(string) sh_config('db.name', 'gestion_our_stock'), $table]
        );
        $line('Table tiers ' . $table, $exists === 1);
    }
} catch (Throwable $e) {
    $line('Connexion MySQL', false, $e->getMessage());
}

echo PHP_EOL . ($ok ? 'Diagnostic terminé : tout est opérationnel.' : 'Diagnostic terminé : corrigez les points signalés.') . PHP_EOL;
exit($ok ? 0 : 1);
