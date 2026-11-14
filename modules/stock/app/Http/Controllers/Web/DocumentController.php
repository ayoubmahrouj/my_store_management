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
            'title'     => t($this->label),
            'type'      => $this->type,
            'label'     => t($this->label),
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
            'motifs'    => array_map(static fn (string $text): string => t($text), $this->motifs),
            'filters'   => $filters,
        ]);
    }

    public function export(Request $request): Response
    {
        $GLOBALS['gos_export_french'] = true;
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
                title: t('Détail des lignes') . ' · ' . t($this->label),
                subtitle: t('Chaque produit entré ou sorti, avec sa quantité, son prix unitaire et sa valeur totale.'),
                meta: $meta,
                summary: [
                    ['label' => t('Lignes'), 'value' => (string) count($lines)],
                    ['label' => t('Quantité totale'), 'value' => qty($totalQty)],
                    ['label' => t('Total achat'), 'value' => money($totalCost)],
                    ['label' => t('Total vente'), 'value' => money($totalSale)],
                ],
                columns: [
                    ['label' => t('N° document'), 'width' => 1.0],
                    ['label' => t('Date'), 'width' => 0.8, 'align' => 'center'],
                    ['label' => t('Statut'), 'width' => 0.8, 'align' => 'center'],
                    ['label' => t('Site'), 'width' => 1.2],
                    ['label' => t($this->counterpartyHeader()), 'width' => 1.2],
                    ['label' => 'SKU', 'width' => 0.9],
                    ['label' => t('Produit'), 'width' => 2.0],
                    ['label' => t('Unité'), 'width' => 0.55, 'align' => 'center'],
                    ['label' => t('Quantité'), 'width' => 0.75, 'align' => 'right', 'type' => 'qty'],
                    ['label' => t('Prix achat (:currency)', ['currency' => currency()]), 'width' => 0.9, 'align' => 'right', 'type' => 'money'],
                    ['label' => t('Total achat (:currency)', ['currency' => currency()]), 'width' => 1.0, 'align' => 'right', 'type' => 'money'],
                    ['label' => t('Prix vente (:currency)', ['currency' => currency()]), 'width' => 0.9, 'align' => 'right', 'type' => 'money'],
                    ['label' => t('Total vente (:currency)', ['currency' => currency()]), 'width' => 1.0, 'align' => 'right', 'type' => 'money'],
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
            title: t('Registre') . ' · ' . t($this->label),
            subtitle: t('Liste filtrée des documents, avec leur montant d’achat, de vente et leur nombre de lignes.'),
            meta: $meta,
            summary: [
                ['label' => t('Documents'), 'value' => (string) count($documents)],
                ['label' => t('Lignes'), 'value' => (string) $totalLines],
                ['label' => t('Total achat'), 'value' => money($totalCost)],
                ['label' => t('Total vente'), 'value' => money($totalSale)],
            ],
            columns: [
                ['label' => t('N° document'), 'width' => 1.0],
                ['label' => t('Date'), 'width' => 0.8, 'align' => 'center'],
                ['label' => t('Statut'), 'width' => 0.9, 'align' => 'center'],
                ['label' => t('Site'), 'width' => 1.4],
                ['label' => t($this->counterpartyHeader()), 'width' => 1.4],
                ['label' => t('Référence'), 'width' => 1.0],
                ['label' => t('Lignes'), 'width' => 0.6, 'align' => 'right', 'type' => 'number'],
                ['label' => t('Total achat (:currency)', ['currency' => currency()]), 'width' => 1.1, 'align' => 'right', 'type' => 'money'],
                ['label' => t('Total vente (:currency)', ['currency' => currency()]), 'width' => 1.1, 'align' => 'right', 'type' => 'money'],
                ['label' => t('Créé par'), 'width' => 1.1],
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
        $GLOBALS['gos_export_french'] = true;
        $document = (new PdoDocumentRepository())->find((int) $id);
        if ($document === null || $document->type() !== $this->type) {
            return View::render('errors/404', ['title' => t('Document introuvable')])->withStatus(404);
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
            t('Document : :value', ['value' => $document->numero()]),
            t('Date : :value', ['value' => date('d/m/Y', strtotime($document->docDate()))]),
            t('Statut : :value', ['value' => t($document->statusLabel())]),
            t('Site : :label', ['label' => $document->locationName()]),
        ];
        if ($document->supplierName() !== null) {
            $meta[] = t('Fournisseur : :value', ['value' => $document->supplierName()]);
        }
        if ($document->destLocationName() !== null) {
            $meta[] = t('Destination : :value', ['value' => $document->destLocationName()]);
        }
        if ($document->motif() !== null) {
            $meta[] = t('Motif : :value', ['value' => t($this->motifs[$document->motif()] ?? ucfirst($document->motif()))]);
        }
        if ($document->reference() !== null && $document->reference() !== '') {
            $meta[] = t('Référence : :value', ['value' => $document->reference()]);
        }

        $export = new ExportDocument(
            filename: $this->type . '-' . $document->numero() . '-' . date('Ymd-Hi'),
            title: t($this->documentTitle()) . ' · ' . $document->numero(),
            subtitle: t('Détail complet des produits, quantités et prix de ce document.'),
            meta: $meta,
            summary: [
                ['label' => t('Lignes'), 'value' => (string) count($rows)],
                ['label' => t('Quantité totale'), 'value' => qty($totalQty)],
                ['label' => t('Total achat'), 'value' => money($totalCost)],
                ['label' => t('Total vente'), 'value' => money($totalSale)],
            ],
            columns: [
                ['label' => 'SKU', 'width' => 0.9],
                ['label' => t('Produit'), 'width' => 2.6],
                ['label' => t('Unité'), 'width' => 0.6, 'align' => 'center'],
                ['label' => t('Quantité'), 'width' => 0.8, 'align' => 'right', 'type' => 'qty'],
                ['label' => t('Prix achat (:currency)', ['currency' => currency()]), 'width' => 1.0, 'align' => 'right', 'type' => 'money'],
                ['label' => t('Total achat (:currency)', ['currency' => currency()]), 'width' => 1.1, 'align' => 'right', 'type' => 'money'],
                ['label' => t('Prix vente (:currency)', ['currency' => currency()]), 'width' => 1.0, 'align' => 'right', 'type' => 'money'],
                ['label' => t('Total vente (:currency)', ['currency' => currency()]), 'width' => 1.1, 'align' => 'right', 'type' => 'money'],
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
                ? t('Le document a été enregistré et validé. Le stock est à jour.')
                : t('Le document a été enregistré en brouillon.'));

            return Response::redirect(url($this->route . '/' . $id));
        } catch (Throwable $e) {
            if ($createdId > 0 && $request->input('action') === 'validate') {
                try {
                    $service->cancelDocument($createdId, $userId, t('Validation immédiate échouée.'));
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
            return View::render('errors/404', ['title' => t('Document introuvable')])->withStatus(404);
        }

        return View::render('documents/show', [
            'title'    => $document->numero(),
            'type'     => $this->type,
            'label'    => t($this->label),
            'route'    => $this->route,
            'motifs'   => array_map(static fn (string $text): string => t($text), $this->motifs),
            'document' => $document,
        ]);
    }

    public function validateDocument(Request $request, string $id): Response
    {
        try {
            (new StockService())->validateDocument((int) $id, Auth::id());
            flash('success', t('Document validé : les mouvements de stock ont été générés.'));
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        return Response::redirect(url($this->route . '/' . (int) $id));
    }

    public function cancel(Request $request, string $id): Response
    {
        try {
            (new StockService())->cancelDocument((int) $id, Auth::id(), (string) $request->input('reason', ''));
            flash('success', t('Le document a été annulé.'));
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
        $meta = [t('Site : :label', ['label' => $this->locationLabel($refs->locations(true), (int) $filters['location_id'])])];
        if ((string) $filters['status'] !== '') {
            $meta[] = t('Statut : :value', ['value' => $this->statusLabel((string) $filters['status'])]);
        }
        if ((string) $filters['date_from'] !== '' || (string) $filters['date_to'] !== '') {
            $meta[] = t('Période : :label', ['label' => $this->dateRangeLabel((string) $filters['date_from'], (string) $filters['date_to'])]);
        }
        if ((int) $filters['supplier_id'] > 0) {
            foreach ($refs->suppliers(true) as $supplier) {
                if ($supplier->id() === (int) $filters['supplier_id']) {
                    $meta[] = t('Fournisseur : :value', ['value' => $supplier->name()]);
                    break;
                }
            }
        }
        if ((string) $filters['product_q'] !== '') {
            $meta[] = t('Produit : « :q »', ['q' => (string) $filters['product_q']]);
        }
        if ((string) $filters['motif'] !== '') {
            $meta[] = t('Motif : :value', ['value' => t($this->motifs[(string) $filters['motif']] ?? ucfirst((string) $filters['motif']))]);
        }
        if ((string) $filters['q'] !== '') {
            $meta[] = t('Recherche : « :q »', ['q' => (string) $filters['q']]);
        }
        if ((string) $filters['amount_from'] !== '' || (string) $filters['amount_to'] !== '') {
            $meta[] = t('Montant : :label', ['label' => $this->amountRangeLabel((string) $filters['amount_from'], (string) $filters['amount_to'])]);
        }

        return $meta;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'brouillon' => t('Brouillon'),
            'valide' => t('Validé'),
            'expedie' => t('Expédié'),
            'recu' => t('Reçu'),
            'annule' => t('Annulé'),
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

        return $motif !== '' ? t($this->motifs[$motif] ?? ucfirst($motif)) : '—';
    }

    private function counterpartyHeader(): string
    {
        return match ($this->type) {
            'entree' => t('Fournisseur'),
            'transfert' => t('Destination'),
            default => t('Motif'),
        };
    }

    private function documentTitle(): string
    {
        return match ($this->type) {
            'entree' => t('Bon d’entrée'),
            'sortie' => t('Bon de sortie'),
            'transfert' => t('Bon de transfert'),
            default => t('Document de stock'),
        };
    }

    /** @param array<int, \App\Domain\Entities\Location> $items */
    private function locationLabel(array $items, int $id): string
    {
        if ($id <= 0) {
            return t('Tous les sites');
        }
        foreach ($items as $item) {
            if ($item->id() === $id) {
                return $item->name();
            }
        }

        return t('Site #:id', ['id' => $id]);
    }

    private function dateRangeLabel(string $from, string $to): string
    {
        if ($from !== '' && $to !== '') {
            return t('du :from au :to', ['from' => date('d/m/Y', strtotime($from)), 'to' => date('d/m/Y', strtotime($to))]);
        }
        if ($from !== '') {
            return t('depuis le :date', ['date' => date('d/m/Y', strtotime($from))]);
        }

        return t('jusqu’au :date', ['date' => date('d/m/Y', strtotime($to))]);
    }

    private function amountRangeLabel(string $min, string $max): string
    {
        if ($min !== '' && $max !== '') {
            return t('de :min à :max', ['min' => money($min), 'max' => money($max)]);
        }
        if ($min !== '') {
            return t('≥ :min', ['min' => money($min)]);
        }

        return t('≤ :max', ['max' => money($max)]);
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
            'label'      => t($this->label),
            'route'      => $this->route,
            'motifs'     => array_map(static fn (string $text): string => t($text), $this->motifs),
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
            'entree' => t('Nouvelle entrée de stock'),
            'sortie' => t('Nouvelle sortie de stock'),
            'transfert' => t('Nouveau transfert'),
            default => t('Nouveau document'),
        };
    }
}
