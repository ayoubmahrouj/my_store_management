<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

final class EntryController extends DocumentController
{
    protected string $type = 'entree';
    protected string $label = 'Entrées de stock';
    protected string $route = '/entrees';

    protected array $motifs = [
        'achat'         => 'Achat fournisseur',
        'stock_initial' => 'Stock initial / ouverture',
        'retour'        => 'Retour client',
        'ajustement'    => 'Ajustement positif',
    ];
}
