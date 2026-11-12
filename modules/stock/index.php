<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\DocumentApiController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MovementApiController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\StockApiController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\ConsultationController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\EntryController;
use App\Http\Controllers\Web\ExitController;
use App\Http\Controllers\Web\InventoryController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\ReferenceController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\StatisticsController;
use App\Http\Controllers\Web\TransferController;
use App\Http\Controllers\Web\UserController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\CsrfMiddleware;
use App\Http\Middleware\RoleMiddleware;

require __DIR__ . '/../../shared/bootstrap.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/app/Core/helpers.php';

$config = require __DIR__ . '/config/config.php';
Config::load($config);

$currentRequestPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (core_user() !== null && !core_can_access('stock') && !str_contains($currentRequestPath, '/api/')) {
    http_response_code(403);
    echo '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Accès refusé</title>'
        . '<body style="font-family:system-ui;background:#080b10;color:#e7eef8;padding:48px">'
        . '<h1>403 — Accès refusé</h1><p>Votre compte n\'a pas accès au module Stock.</p>'
        . '<p><a style="color:#5b8cff" href="' . sh_e(sh_url('/index.php')) . '">Retour au tableau de bord</a></p></body></html>';
    exit;
}


date_default_timezone_set((string) Config::get('app.timezone', 'Africa/Casablanca'));

$logDir = __DIR__ . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

if ((bool) Config::get('app.debug', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', $logDir . '/php-error.log');
}

$router = new Router();
$request = new Request();

// --- Web (MVC) ---
$router->get('/', [AuthController::class, 'showLogin']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login'], [CsrfMiddleware::web()]);
$router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::web(), CsrfMiddleware::web()]);
$router->get('/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::web()]);

// Produits
$router->get('/produits', [ProductController::class, 'index'], [AuthMiddleware::web()]);
$router->get('/produits/export', [ProductController::class, 'export'], [AuthMiddleware::web()]);
$router->get('/produits/nouveau', [ProductController::class, 'create'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable')]);
$router->post('/produits', [ProductController::class, 'store'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable'), CsrfMiddleware::web()]);
$router->get('/produits/{id}/modifier', [ProductController::class, 'edit'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable')]);
$router->post('/produits/{id}/basculer', [ProductController::class, 'toggle'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable'), CsrfMiddleware::web()]);
$router->post('/produits/{id}', [ProductController::class, 'update'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable'), CsrfMiddleware::web()]);

// Référentiels
$router->get('/referentiels', [ReferenceController::class, 'index'], [AuthMiddleware::web()]);
$router->post('/referentiels/{type}', [ReferenceController::class, 'store'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable'), CsrfMiddleware::web()]);
$router->post('/referentiels/{type}/{id}/basculer', [ReferenceController::class, 'toggle'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable'), CsrfMiddleware::web()]);

// Entrées
$router->get('/entrees', [EntryController::class, 'index'], [AuthMiddleware::web()]);
$router->get('/entrees/export', [EntryController::class, 'export'], [AuthMiddleware::web()]);
$router->get('/entrees/nouveau', [EntryController::class, 'create'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable', 'magasinier')]);
$router->post('/entrees', [EntryController::class, 'store'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable', 'magasinier'), CsrfMiddleware::web()]);
$router->get('/entrees/{id}/export', [EntryController::class, 'exportDocument'], [AuthMiddleware::web()]);
$router->get('/entrees/{id}', [EntryController::class, 'show'], [AuthMiddleware::web()]);
$router->post('/entrees/{id}/valider', [EntryController::class, 'validateDocument'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable', 'magasinier'), CsrfMiddleware::web()]);
$router->post('/entrees/{id}/annuler', [EntryController::class, 'cancel'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable', 'magasinier'), CsrfMiddleware::web()]);

// Sorties
$router->get('/sorties', [ExitController::class, 'index'], [AuthMiddleware::web()]);
$router->get('/sorties/export', [ExitController::class, 'export'], [AuthMiddleware::web()]);
$router->get('/sorties/nouveau', [ExitController::class, 'create'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable', 'magasinier', 'vendeur')]);
$router->post('/sorties', [ExitController::class, 'store'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable', 'magasinier', 'vendeur'), CsrfMiddleware::web()]);
$router->get('/sorties/{id}/export', [ExitController::class, 'exportDocument'], [AuthMiddleware::web()]);
$router->get('/sorties/{id}', [ExitController::class, 'show'], [AuthMiddleware::web()]);
$router->post('/sorties/{id}/valider', [ExitController::class, 'validateDocument'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable', 'magasinier', 'vendeur'), CsrfMiddleware::web()]);
$router->post('/sorties/{id}/annuler', [ExitController::class, 'cancel'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable', 'magasinier', 'vendeur'), CsrfMiddleware::web()]);

// Transferts
$router->get('/transferts', [TransferController::class, 'index'], [AuthMiddleware::web()]);
$router->get('/transferts/export', [TransferController::class, 'export'], [AuthMiddleware::web()]);
$router->get('/transferts/nouveau', [TransferController::class, 'create'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable', 'magasinier')]);
$router->post('/transferts', [TransferController::class, 'store'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable', 'magasinier'), CsrfMiddleware::web()]);
$router->get('/transferts/{id}/export', [TransferController::class, 'exportDocument'], [AuthMiddleware::web()]);
$router->get('/transferts/{id}', [TransferController::class, 'show'], [AuthMiddleware::web()]);
$router->post('/transferts/{id}/expedier', [TransferController::class, 'ship'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable', 'magasinier'), CsrfMiddleware::web()]);
$router->post('/transferts/{id}/recevoir', [TransferController::class, 'receive'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable', 'magasinier'), CsrfMiddleware::web()]);
$router->post('/transferts/{id}/annuler', [TransferController::class, 'cancel'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable', 'magasinier'), CsrfMiddleware::web()]);

// Inventaire
$router->get('/inventaire', [InventoryController::class, 'index'], [AuthMiddleware::web()]);
$router->get('/inventaire/export', [InventoryController::class, 'export'], [AuthMiddleware::web()]);
$router->get('/inventaire/nouveau', [InventoryController::class, 'create'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable', 'magasinier')]);
$router->post('/inventaire', [InventoryController::class, 'store'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable', 'magasinier'), CsrfMiddleware::web()]);
$router->get('/inventaire/{id}/export', [InventoryController::class, 'exportDocument'], [AuthMiddleware::web()]);
$router->get('/inventaire/{id}', [InventoryController::class, 'show'], [AuthMiddleware::web()]);
$router->post('/inventaire/{id}/enregistrer', [InventoryController::class, 'save'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable', 'magasinier'), CsrfMiddleware::web()]);
$router->post('/inventaire/{id}/valider', [InventoryController::class, 'validateInventory'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable', 'magasinier'), CsrfMiddleware::web()]);
$router->post('/inventaire/{id}/annuler', [InventoryController::class, 'cancel'], [AuthMiddleware::web(), RoleMiddleware::web('admin', 'responsable', 'magasinier'), CsrfMiddleware::web()]);

// Statistiques, consultation et rapports
$router->get('/statistiques', [StatisticsController::class, 'index'], [AuthMiddleware::web()]);
$router->get('/statistiques/export', [StatisticsController::class, 'export'], [AuthMiddleware::web()]);
$router->get('/consultation', [ConsultationController::class, 'index'], [AuthMiddleware::web()]);
$router->get('/consultation/export', [ConsultationController::class, 'export'], [AuthMiddleware::web()]);
$router->get('/rapports', [ReportController::class, 'index'], [AuthMiddleware::web()]);
$router->get('/rapports/export', [ReportController::class, 'export'], [AuthMiddleware::web()]);

// Administration
$router->get('/utilisateurs', [UserController::class, 'index'], [AuthMiddleware::web(), RoleMiddleware::web('admin')]);
$router->get('/utilisateurs/nouveau', [UserController::class, 'create'], [AuthMiddleware::web(), RoleMiddleware::web('admin')]);
$router->post('/utilisateurs', [UserController::class, 'store'], [AuthMiddleware::web(), RoleMiddleware::web('admin'), CsrfMiddleware::web()]);
$router->get('/utilisateurs/{id}/modifier', [UserController::class, 'edit'], [AuthMiddleware::web(), RoleMiddleware::web('admin')]);
$router->post('/utilisateurs/{id}/basculer', [UserController::class, 'toggle'], [AuthMiddleware::web(), RoleMiddleware::web('admin'), CsrfMiddleware::web()]);
$router->post('/utilisateurs/{id}', [UserController::class, 'update'], [AuthMiddleware::web(), RoleMiddleware::web('admin'), CsrfMiddleware::web()]);
$router->get('/parametres', [SettingsController::class, 'index'], [AuthMiddleware::web(), RoleMiddleware::web('admin')]);
$router->post('/parametres', [SettingsController::class, 'update'], [AuthMiddleware::web(), RoleMiddleware::web('admin'), CsrfMiddleware::web()]);

// --- REST API v1 ---
$router->get('/api/v1/health', [HealthController::class, 'index']);
$router->post('/api/v1/auth/login', [AuthApiController::class, 'login']);
$router->get('/api/v1/auth/me', [AuthApiController::class, 'me'], [AuthMiddleware::api()]);
$router->post('/api/v1/auth/logout', [AuthApiController::class, 'logout'], [AuthMiddleware::api()]);
$router->get('/api/v1/products', [ProductApiController::class, 'index'], [AuthMiddleware::api()]);
$router->get('/api/v1/products/{id}', [ProductApiController::class, 'show'], [AuthMiddleware::api()]);
$router->get('/api/v1/stock', [StockApiController::class, 'index'], [AuthMiddleware::api()]);
$router->get('/api/v1/documents', [DocumentApiController::class, 'index'], [AuthMiddleware::api()]);
$router->get('/api/v1/documents/{id}', [DocumentApiController::class, 'show'], [AuthMiddleware::api()]);
$router->get('/api/v1/movements', [MovementApiController::class, 'index'], [AuthMiddleware::api()]);

try {
    $router->dispatch($request)->send();
} catch (Throwable $e) {
    error_log('[suivi-stock] ' . (string) $e);

    if ((bool) Config::get('app.debug', false)) {
        $message = $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine();
    } else {
        $message = 'Une erreur interne est survenue.';
    }

    if ($request->isApi()) {
        Response::json([
            'success' => false,
            'message' => $message,
            'errors'  => [],
        ], 500)->send();
        return;
    }

    View::render('errors/500', ['title' => 'Erreur', 'message' => $message], 'auth')->withStatus(500)->send();
}
