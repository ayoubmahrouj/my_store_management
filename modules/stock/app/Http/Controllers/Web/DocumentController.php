<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Services\StockService;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Infrastructure\Export\ExportDocument;
use App\Infrastructure\Persistence\PdoDocumentRepository;
use App\Infrastructure\Persistence\PdoProductRepository;
use App\Infrastructure\Persistence\PdoReferenceRepository;
use Throwable;

abstract class DocumentController
{
    protected string $type = 'entree';
    protected string $label = 'Entrées';
    protected string $route = '/entrees';

    /** @var array<string, string> */
    protected array $motifs = [];

    private const PER_PAGE = 50;

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $repo = new PdoDocumentRepository();
        $refs = new PdoReferenceRepository();

        $total = $repo->countSearch($this->type, $filters);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, (int) $request->query('page', 1)), $pages);

        return View::render('documents/index', [
            'title'     => $this->label,
            'type'      => $this->type,
            'label'     => $this->label,
            'route'     => $this->route,
            'documents' => $repo->search($this->type, $filters + [
                'limit'  => self::PER_PAGE,
                'offset' => ($page - 1) * self::PER_PAGE,
            ]),
            'stats'     => $repo->stats($this->type),
            'page'      => $page,
            'pages'     => $pages,
            'total'     => $total,
            'locations' => $refs->locations(true),
            'suppliers' => $this->type === 'entree' ? $refs->suppliers(true) : [],
            'motifs'    => $this->motifs,
            'filters'   => $filters,
        ]);
    }

    public function export(Request $request): Response
    {
        $filters = $this->filters($request);
        $repo = new PdoDocumentRepository();
        $refs = new PdoReferenceRepository();
        $detail = $request->query('detail') === '1';
        $meta = $this->filterMeta($filters, $refs);

        if ($detail) {
            $lines = $repo->lineReport($this->type, $filters);
            $totalQty = 0.0;
            $totalCost = 0.0;
            $totalSale = 0.0;
            $rows = [];
            foreach ($lines as $line) {
                $totalQty += (float) $line['qty'];
                $totalCost += (float) $line['line_cost'];
                $totalSale += (float) $line['line_sale'];
                $rows[] = [
                    $line['numero'],
                    date('d/m/Y', strtotime($line['doc_date'])),
                    $this->statusLabel($line['status']),
                    $line['location_name'],
                    $this->counterpartyLabel($line),
                    $line['sku'],
                    $line['designation'],
                    $line['unit'],
                    (float) $line['qty'],
                    (float) $line['unit_cost'],
                    (float) $line['line_cost'],
                    (float) $line['unit_price'],
                    (float) $line['line_sale'],
                ];
            }

            $document = new ExportDocument(
                filename: $this->type . '-lignes-' . date('Ymd-Hi'),
                title: 'Détail des lignes · ' . $this->label,
                subtitle: 'Chaque produit entré ou sorti, avec sa quantité, son prix unitaire et sa valeur totale.',
                meta: $meta,
                summary: [
                    ['label' => 'Lignes', 'value' => (string) count($lines)],
                    ['label' => 'Quantité totale', 'value' => qty($totalQty)],
                    ['label' => 'Total achat', 'value' => money($totalCost)],
                    ['label' => 'Total vente', 'value' => money($totalSale)],
                ],
                columns: [
                    ['label' => 'N° document', 'width' => 1.0],
                    ['label' => 'Date', 'width' => 0.8, 'align' => 'center'],
                    ['label' => 'Statut', 'width' => 0.8, 'align' => 'center'],
                    ['label' => 'Site', 'width' => 1.2],
                    ['label' => $this->counterpartyHeader(), 'width' => 1.2],
                    ['label' => 'SKU', 'width' => 0.9],
                    ['label' => 'Produit', 'width' => 2.0],
                    ['label' => 'Unité', 'width' => 0.55, 'align' => 'center'],
                    ['label' => 'Quantité', 'width' => 0.75, 'align' => 'right', 'type' => 'qty'],
                    ['label' => 'Prix achat (' . currency() . ')', 'width' => 0.9, 'align' => 'right', 'type' => 'money'],
                    ['label' => 'Total achat (' . currency() . ')', 'width' => 1.0, 'align' => 'right', 'type' => 'money'],
                    ['label' => 'Prix vente (' . currency() . ')', 'width' => 0.9, 'align' => 'right', 'type' => 'money'],
                    ['label' => 'Total vente (' . currency() . ')', 'width' => 1.0, 'align' => 'right', 'type' => 'money'],
                ],
                rows: $rows,
                totals: [null, null, null, null, null, null, null, null, $totalQty, null, $totalCost, null, $totalSale],
                landscape: true,
                currency: currency(),
            );

            return export_response((string) $request->query('format', 'xls'), $document);
        }

        $documents = $repo->search($this->type, $filters);
        $totalCost = 0.0;
        $totalSale = 0.0;
        $totalLines = 0;
        $rows = [];
        foreach ($documents as $item) {
            $totalCost += $item->totalCost();
            $totalSale += $item->totalSale();
            $totalLines += $item->lineCount();
            $rows[] = [
                $item->numero(),
                date('d/m/Y', strtotime($item->docDate())),
                $item->statusLabel(),
                $item->locationName(),
                $this->counterpartyLabel([
                    'supplier_name' => $item->supplierName(),
                    'dest_name' => $item->destLocationName(),
                    'motif' => $item->motif(),
                ]),
                $item->reference() ?: '',
                $item->lineCount(),
                $item->totalCost(),
                $item->totalSale(),
                $item->createdByName(),
            ];
        }

        $document = new ExportDocument(
            filename: $this->type . '-registre-' . date('Ymd-Hi'),
            title: 'Registre · ' . $this->label,
            subtitle: 'Liste filtrée des documents, avec leur montant d’achat, de vente et leur nombre de lignes.',
            meta: $meta,
            summary: [
                ['label' => 'Documents', 'value' => (string) count($documents)],
                ['label' => 'Lignes', 'value' => (string) $totalLines],
                ['label' => 'Total achat', 'value' => money($totalCost)],
                ['label' => 'Total vente', 'value' => money($totalSale)],
            ],
            columns: [
                ['label' => 'N° document', 'width' => 1.0],
                ['label' => 'Date', 'width' => 0.8, 'align' => 'center'],
                ['label' => 'Statut', 'width' => 0.9, 'align' => 'center'],
                ['label' => 'Site', 'width' => 1.4],
                ['label' => $this->counterpartyHeader(), 'width' => 1.4],
                ['label' => 'Référence', 'width' => 1.0],
                ['label' => 'Lignes', 'width' => 0.6, 'align' => 'right', 'type' => 'number'],
                ['label' => 'Total achat (' . currency() . ')', 'width' => 1.1, 'align' => 'right', 'type' => 'money'],
                ['label' => 'Total vente (' . currency() . ')', 'width' => 1.1, 'align' => 'right', 'type' => 'money'],
                ['label' => 'Créé par', 'width' => 1.1],
            ],
            rows: $rows,
            totals: [null, null, null, null, null, null, $totalLines, $totalCost, $totalSale, null],
            landscape: true,
            currency: currency(),
        );

        return export_response((string) $request->query('format', 'xls'), $document);
    }

    public function exportDocument(Request $request, string $id): Response
    {
        $document = (new PdoDocumentRepository())->find((int) $id);
        if ($document === null || $document->type() !== $this->type) {
            return View::render('errors/404', ['title' => 'Document introuvable'])->withStatus(404);
        }

        $totalQty = 0.0;
        $totalCost = 0.0;
        $totalSale = 0.0;
        $rows = [];
        foreach ($document->lines() as $line) {
            $lineCost = $line->qty() * $line->unitCost();
            $lineSale = $line->qty() * $line->unitPrice();
            $totalQty += $line->qty();
            $totalCost += $lineCost;
            $totalSale += $lineSale;
            $rows[] = [
                $line->sku(),
                $line->designation(),
                $line->unit(),
                $line->qty(),
                $line->unitCost(),
                $lineCost,
                $line->unitPrice(),
                $lineSale,
            ];
        }

        $meta = [
            'Document : ' . $document->numero(),
            'Date : ' . date('d/m/Y', strtotime($document->docDate())),
            'Statut : ' . $document->statusLabel(),
            'Site : ' . $document->locationName(),
        ];
        if ($document->supplierName() !== null) {
            $meta[] = 'Fournisseur : ' . $document->supplierName();
        }
        if ($document->destLocationName() !== null) {
            $meta[] = 'Destination : ' . $document->destLocationName();
        }
        if ($document->motif() !== null) {
            $meta[] = 'Motif : ' . ($this->motifs[$document->motif()] ?? ucfirst($document->motif()));
        }
        if ($document->reference() !== null && $document->reference() !== '') {
            $meta[] = 'Référence : ' . $document->reference();
        }

        $export = new ExportDocument(
            filename: $this->type . '-' . $document->numero() . '-' . date('Ymd-Hi'),
            title: $this->documentTitle() . ' · ' . $document->numero(),
            subtitle: 'Détail complet des produits, quantités et prix de ce document.',
            meta: $meta,
            summary: [
                ['label' => 'Lignes', 'value' => (string) count($rows)],
                ['label' => 'Quantité totale', 'value' => qty($totalQty)],
                ['label' => 'Total achat', 'value' => money($totalCost)],
                ['label' => 'Total vente', 'value' => money($totalSale)],
            ],
            columns: [
                ['label' => 'SKU', 'width' => 0.9],
                ['label' => 'Produit', 'width' => 2.6],
                ['label' => 'Unité', 'width' => 0.6, 'align' => 'center'],
                ['label' => 'Quantité', 'width' => 0.8, 'align' => 'right', 'type' => 'qty'],
                ['label' => 'Prix achat (' . currency() . ')', 'width' => 1.0, 'align' => 'right', 'type' => 'money'],
                ['label' => 'Total achat (' . currency() . ')', 'width' => 1.1, 'align' => 'right', 'type' => 'money'],
                ['label' => 'Prix vente (' . currency() . ')', 'width' => 1.0, 'align' => 'right', 'type' => 'money'],
                ['label' => 'Total vente (' . currency() . ')', 'width' => 1.1, 'align' => 'right', 'type' => 'money'],
            ],
            rows: $rows,
            totals: [null, null, null, $totalQty, null, $totalCost, null, $totalSale],
            landscape: false,
            currency: currency(),
        );

        return export_response((string) $request->query('format', 'xls'), $export);
    }

    public function create(Request $request): Response
    {
        return $this->formView([], []);
    }

    public function store(Request $request): Response
    {
        $items = $request->input('items', []);
        if (!is_array($items)) {
            $items = [];
        }

        $userId = Auth::id();
        $service = new StockService();
        $createdId = 0;

        try {
            $id = $service->createDocument(
                $this->type,
                (string) $request->input('motif', ''),
                (string) $request->input('doc_date', date('Y-m-d')),
                (int) $request->input('location_id', 0),
                $request->input('dest_location_id') !== null ? (int) $request->input('dest_location_id') : null,
                $request->input('supplier_id') !== null && $request->input('supplier_id') !== '' ? (int) $request->input('supplier_id') : null,
                $request->input('reference') !== null ? (string) $request->input('reference') : null,
                $request->input('notes') !== null ? (string) $request->input('notes') : null,
                $items,
                $userId,
            );
            $createdId = $id;

            $validated = $request->input('action') === 'validate' && $this->type !== 'transfert';
            if ($validated) {
                $service->validateDocument($id, $userId);
            }

            flash('success', $validated
                ? 'Le document a été enregistré et validé. Le stock est à jour.'
                : 'Le document a été enregistré en brouillon.');

            return Response::redirect(url($this->route . '/' . $id));
        } catch (Throwable $e) {
            if ($createdId > 0 && $request->input('action') === 'validate') {
                try {
                    $service->cancelDocument($createdId, $userId, 'Validation immédiate échouée.');
                } catch (Throwable) {
                    // Le brouillon reste disponible pour correction manuelle.
                }
            }

            return $this->formView($request->all(), [$e->getMessage()]);
        }
    }

    public function show(Request $request, string $id): Response
    {
        $document = (new PdoDocumentRepository())->find((int) $id);
        if ($document === null || $document->type() !== $this->type) {
            return View::render('errors/404', ['title' => 'Document introuvable'])->withStatus(404);
        }

        return View::render('documents/show', [
            'title'    => $document->numero(),
            'type'     => $this->type,
            'label'    => $this->label,
            'route'    => $this->route,
            'document' => $document,
        ]);
    }

    public function validateDocument(Request $request, string $id): Response
    {
        try {
            (new StockService())->validateDocument((int) $id, Auth::id());
            flash('success', 'Document validé : les mouvements de stock ont été générés.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        return Response::redirect(url($this->route . '/' . (int) $id));
    }

    public function cancel(Request $request, string $id): Response
    {
        try {
            (new StockService())->cancelDocument((int) $id, Auth::id(), (string) $request->input('reason', ''));
            flash('success', 'Le document a été annulé.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        return Response::redirect(url($this->route . '/' . (int) $id));
    }

    /** @return array{q: string, status: string, location_id: int, date_from: string, date_to: string, motif: string, supplier_id: int, product_q: string, amount_from: string, amount_to: string} */
    private function filters(Request $request): array
    {
        return [
            'q'           => trim((string) $request->query('q', '')),
            'status'      => trim((string) $request->query('statut', '')),
            'location_id' => (int) $request->query('site', 0),
            'date_from'   => trim((string) $request->query('du', '')),
            'date_to'     => trim((string) $request->query('au', '')),
            'motif'       => trim((string) $request->query('motif', '')),
            'supplier_id' => (int) $request->query('fournisseur', 0),
            'product_q'   => trim((string) $request->query('produit', '')),
            'amount_from' => trim((string) $request->query('montant_min', '')),
            'amount_to'   => trim((string) $request->query('montant_max', '')),
        ];
    }

    /** @param array<string, mixed> $filters
     *  @return array<int, string>
     */
    private function filterMeta(array $filters, PdoReferenceRepository $refs): array
    {
        $meta = ['Site : ' . $this->locationLabel($refs->locations(true), (int) $filters['location_id'])];
        if ((string) $filters['status'] !== '') {
            $meta[] = 'Statut : ' . $this->statusLabel((string) $filters['status']);
        }
        if ((string) $filters['date_from'] !== '' || (string) $filters['date_to'] !== '') {
            $meta[] = 'Période : ' . $this->dateRangeLabel((string) $filters['date_from'], (string) $filters['date_to']);
        }
        if ((int) $filters['supplier_id'] > 0) {
            foreach ($refs->suppliers(true) as $supplier) {
                if ($supplier->id() === (int) $filters['supplier_id']) {
                    $meta[] = 'Fournisseur : ' . $supplier->name();
                    break;
                }
            }
        }
        if ((string) $filters['product_q'] !== '') {
            $meta[] = 'Produit : « ' . (string) $filters['product_q'] . ' »';
        }
        if ((string) $filters['motif'] !== '') {
            $meta[] = 'Motif : ' . ($this->motifs[(string) $filters['motif']] ?? ucfirst((string) $filters['motif']));
        }
        if ((string) $filters['q'] !== '') {
            $meta[] = 'Recherche : « ' . (string) $filters['q'] . ' »';
        }
        if ((string) $filters['amount_from'] !== '' || (string) $filters['amount_to'] !== '') {
            $meta[] = 'Montant : ' . $this->amountRangeLabel((string) $filters['amount_from'], (string) $filters['amount_to']);
        }

        return $meta;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'brouillon' => 'Brouillon',
            'valide' => 'Validé',
            'expedie' => 'Expédié',
            'recu' => 'Reçu',
            'annule' => 'Annulé',
            default => ucfirst($status),
        };
    }

    /** @param array<string, mixed> $item */
    private function counterpartyLabel(array $item): string
    {
        if ($this->type === 'entree') {
            return (string) ($item['supplier_name'] ?? '') !== '' ? (string) $item['supplier_name'] : '—';
        }
        if ($this->type === 'transfert') {
            return (string) ($item['dest_name'] ?? '') !== '' ? (string) $item['dest_name'] : '—';
        }
        $motif = (string) ($item['motif'] ?? '');

        return $motif !== '' ? ($this->motifs[$motif] ?? ucfirst($motif)) : '—';
    }

    private function counterpartyHeader(): string
    {
        return match ($this->type) {
            'entree' => 'Fournisseur',
            'transfert' => 'Destination',
            default => 'Motif',
        };
    }

    private function documentTitle(): string
    {
        return match ($this->type) {
            'entree' => 'Bon d’entrée',
            'sortie' => 'Bon de sortie',
            'transfert' => 'Bon de transfert',
            default => 'Document de stock',
        };
    }

    /** @param array<int, \App\Domain\Entities\Location> $items */
    private function locationLabel(array $items, int $id): string
    {
        if ($id <= 0) {
            return 'Tous les sites';
        }
        foreach ($items as $item) {
            if ($item->id() === $id) {
                return $item->name();
            }
        }

        return 'Site #' . $id;
    }

    private function dateRangeLabel(string $from, string $to): string
    {
        if ($from !== '' && $to !== '') {
            return 'du ' . date('d/m/Y', strtotime($from)) . ' au ' . date('d/m/Y', strtotime($to));
        }
        if ($from !== '') {
            return 'depuis le ' . date('d/m/Y', strtotime($from));
        }

        return 'jusqu’au ' . date('d/m/Y', strtotime($to));
    }

    private function amountRangeLabel(string $min, string $max): string
    {
        if ($min !== '' && $max !== '') {
            return 'de ' . money($min) . ' à ' . money($max);
        }
        if ($min !== '') {
            return '≥ ' . money($min);
        }

        return '≤ ' . money($max);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<int, string> $errors
     */
    protected function formView(array $old, array $errors): Response
    {
        $refs = new PdoReferenceRepository();

        return View::render('documents/form', [
            'title'      => $this->createTitle(),
            'type'       => $this->type,
            'label'      => $this->label,
            'route'      => $this->route,
            'motifs'     => $this->motifs,
            'locations'  => $refs->locations(true),
            'suppliers'  => $this->type === 'entree' ? $refs->suppliers(true) : [],
            'products'   => (new PdoProductRepository())->allActiveSimple(),
            'old'        => $old,
            'errors'     => $errors,
        ]);
    }

    private function createTitle(): string
    {
        return match ($this->type) {
            'entree' => 'Nouvelle entrée de stock',
            'sortie' => 'Nouvelle sortie de stock',
            'transfert' => 'Nouveau transfert',
            default => 'Nouveau document',
        };
    }
}
