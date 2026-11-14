<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use Gos\Domain\Entities\Produit;
use App\Infrastructure\Persistence\PdoDocumentRepository;
use App\Infrastructure\Persistence\PdoInventoryRepository;
use App\Infrastructure\Persistence\PdoProductRepository;
use App\Infrastructure\Persistence\PdoReferenceRepository;

final class DashboardController
{
    public function index(Request $request): Response
    {
        $repo = new PdoProductRepository();
        $refs = new PdoReferenceRepository();
        $docs = new PdoDocumentRepository();

        $stats = $repo->stats();
        $locations = $repo->stockByLocation();
        $products = $repo->search();

        usort($products, static fn (Produit $a, Produit $b): int => $b->stockValue() <=> $a->stockValue());
        $topProducts = array_slice($products, 0, 8);

        $alerts = array_values(array_filter($products, static fn (Produit $p): bool => $p->isLowStock()));
        usort($alerts, static fn (Produit $a, Produit $b): int => $a->quantity() <=> $b->quantity());

        $pending = $docs->pendingCounts();
        $inventoryStats = (new PdoInventoryRepository())->stats();
        $pending['inventaire'] = $inventoryStats['open'] + $inventoryStats['counting'];

        return View::render('dashboard/index', [
            'title'       => t('Tableau de bord'),
            'stats'       => $stats,
            'locations'   => $locations,
            'topProducts' => $topProducts,
            'alerts'      => array_slice($alerts, 0, 6),
            'pending'     => $pending,
            'movements'   => $docs->movements(['limit' => 7]),
            'setup'       => [
                'locations'  => count($refs->locations(true)),
                'brands'     => count($refs->brands(true)),
                'categories' => count($refs->categories(true)),
                'units'      => count($refs->units()),
                'products'   => $stats['products'],
            ],
        ]);
    }
}
