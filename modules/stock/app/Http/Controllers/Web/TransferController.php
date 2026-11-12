<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Services\StockService;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use Throwable;

final class TransferController extends DocumentController
{
    protected string $type = 'transfert';
    protected string $label = 'Transferts';
    protected string $route = '/transferts';

    protected array $motifs = [
        'reapprovisionnement' => 'Réapprovisionnement d’un point de vente',
        'transfert_interne'   => 'Transfert interne',
        'retour_depot'        => 'Retour vers le dépôt',
    ];

    public function ship(Request $request, string $id): Response
    {
        try {
            (new StockService())->shipTransfer((int) $id, Auth::id());
            flash('success', 'Transfert expédié : le stock source a été diminué.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        return Response::redirect(url($this->route . '/' . (int) $id));
    }

    public function receive(Request $request, string $id): Response
    {
        try {
            (new StockService())->receiveTransfer((int) $id, Auth::id());
            flash('success', 'Transfert réceptionné : le stock destination a été augmenté.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        return Response::redirect(url($this->route . '/' . (int) $id));
    }
}
