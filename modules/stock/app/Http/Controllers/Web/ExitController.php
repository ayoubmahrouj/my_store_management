<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

final class ExitController extends DocumentController
{
    protected string $type = 'sortie';
    protected string $label = 'Sorties de stock';
    protected string $route = '/sorties';

    protected array $motifs = [
        'vente'              => 'Vente au comptoir',
        'perte'              => 'Perte',
        'casse'              => 'Casse',
        'retour_fournisseur' => 'Retour fournisseur',
        'consommation'       => 'Consommation interne',
        'ajustement'         => 'Ajustement négatif',
    ];
}
