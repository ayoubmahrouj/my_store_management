<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    header_remove('X-Powered-By');
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    exit('Diagnostic disponible uniquement en ligne de commande.' . PHP_EOL);
}

require_once __DIR__ . '/bootstrap.php';

$results = ['OK' => 0, 'WARN' => 0, 'FAIL' => 0];

$report = static function (string $status, string $message) use (&$results): void {
    $label = match ($status) {
        'OK'   => '[OK]',
        'WARN' => '[ATTENTION]',
        default => '[ERREUR]',
    };
    $results[$status]++;
    echo $label . ' ' . $message . PHP_EOL;
};

echo 'Suivi Stock — diagnostic serveur' . PHP_EOL;
echo str_repeat('=', 34) . PHP_EOL;

$report(version_compare(PHP_VERSION, '8.1.0', '>=') ? 'OK' : 'FAIL', 'PHP ' . PHP_VERSION . ' (8.1+ requis)');

foreach (['pdo_mysql', 'mbstring', 'openssl'] as $extension) {
    $loaded = extension_loaded($extension);
    $report($loaded ? 'OK' : 'FAIL', 'Extension ' . $extension . ($loaded ? '' : ' manquante'));
}

$logDir = STOCK_ROOT . '/storage/logs';
if (is_dir($logDir) && is_writable($logDir)) {
    $report('OK', 'Dossier storage/logs accessible en écriture');
} else {
    $report('FAIL', 'Dossier storage/logs absent ou non accessible en écriture');
}

$env = (string) Config::get('app.env', 'local');
$debug = (bool) Config::get('app.debug', false);
$report(
    ($env === 'production' && $debug) ? 'FAIL' : 'OK',
    'APP_ENV=' . $env . ', APP_DEBUG=' . ($debug ? '1' : '0')
);

if ($env === 'production' && !str_starts_with((string) Config::get('app.url', ''), 'https://')) {
    $report('WARN', 'APP_URL n’est pas en HTTPS');
}

if (is_file(STOCK_ROOT . '/config/config.local.php')) {
    $report(
        $env === 'production' ? 'WARN' : 'OK',
        'config/config.local.php présent' . ($env === 'production' ? ' : il est prioritaire sur les variables d’environnement' : '')
    );
}

try {
    $pdo = Database::pdo();
    $report('OK', 'Connexion MySQL OK');

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $required = [
        'roles', 'locations', 'users', 'brands', 'categories', 'units', 'suppliers',
        'products', 'stock_documents', 'stock_document_lines', 'stock_movements',
        'stock_levels', 'inventory_sessions', 'inventory_lines', 'audit_logs',
        'login_attempts', 'settings', 'document_sequences',
    ];
    $missing = array_values(array_diff($required, $tables));
    if ($missing === []) {
        $report('OK', count($required) . ' tables métier présentes');
    } else {
        $report('FAIL', 'Tables manquantes : ' . implode(', ', $missing));
    }

    $users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $admins = (int) $pdo->query(
        "SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.id = u.role_id
         WHERE r.code = 'admin' AND u.active = 1"
    )->fetchColumn();

    $report($users > 0 ? 'OK' : 'WARN', $users . ' utilisateur(s)');
    $report($admins > 0 ? 'OK' : 'FAIL', $admins . ' administrateur(s) actif(s)');
} catch (Throwable $e) {
    $report('FAIL', 'Connexion MySQL : ' . $e->getMessage());
}

echo str_repeat('-', 34) . PHP_EOL;
echo 'Résumé : ' . $results['OK'] . ' OK, ' . $results['WARN'] . ' avertissement(s), ' . $results['FAIL'] . ' erreur(s).' . PHP_EOL;

exit($results['FAIL'] > 0 ? 1 : 0);
