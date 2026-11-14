<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Services\StockService;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Infrastructure\Export\ExportDocument;
use App\Infrastructure\Persistence\PdoInventoryRepository;
use App\Infrastructure\Persistence\PdoReferenceRepository;
use Throwable;

final class InventoryController
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $repo = new PdoInventoryRepository();

        return View::render('inventory/index', [
            'title'     => t('Inventaires'),
            'sessions'  => $repo->search($filters),
            'stats'     => $repo->stats(),
            'locations' => (new PdoReferenceRepository())->locations(true),
            'filters'   => $filters,
        ]);
    }

    public function export(Request $request): Response
    {
        $GLOBALS['gos_export_french'] = true;
        $filters = $this->filters($request);
        $repo = new PdoInventoryRepository();
        $sessions = $repo->search($filters);
        $detail = $request->query('detail') === '1';
        $meta = $this->meta($filters);

        if ($detail) {
            $rows = [];
            $totalTheoretical = 0.0;
            $totalCounted = 0.0;
            $totalGap = 0.0;
            $totalGapValue = 0.0;
            foreach ($sessions as $session) {
                $full = $repo->find($session->id());
                if ($full === null) {
                    continue;
                }
                foreach ($full->lines() as $line) {
                    $gap = $line->gap() ?? 0.0;
                    $gapValue = $line->gapValue();
                    $totalTheoretical += $line->theoretical();
                    $totalCounted += $line->counted() ?? 0.0;
                    $totalGap += $gap;
                    $totalGapValue += $gapValue;
                    $rows[] = [
                        $full->numero(),
                        date('d/m/Y H:i', strtotime($full->startedAt())),
                        $full->locationName(),
                        t($full->statusLabel()),
                        $line->sku(),
                        $line->designation(),
                        $line->unit(),
                        $line->theoretical(),
                        $line->counted(),
                        $gap,
                        $line->unitCost(),
                        $gapValue,
                    ];
                }
            }

            $document = new ExportDocument(
                filename: 'inventaires-lignes-' . date('Ymd-Hi'),
                title: t('Détail des inventaires'),
                subtitle: t('Écarts constatés ligne par ligne, avec quantités théoriques, comptées et valorisation.'),
                meta: $meta,
                summary: [
                    ['label' => t('Lignes'), 'value' => (string) count($rows)],
                    ['label' => t('Écart quantités'), 'value' => qty($totalGap)],
                    ['label' => t('Écart valorisé'), 'value' => money($totalGapValue)],
                    ['label' => t('Sessions'), 'value' => (string) count($sessions)],
                ],
                columns: [
                    ['label' => t('Inventaire'), 'width' => 1.0],
                    ['label' => t('Date'), 'width' => 1.0],
                    ['label' => t('Site'), 'width' => 1.3],
                    ['label' => t('Statut'), 'width' => 0.8, 'align' => 'center'],
                    ['label' => 'SKU', 'width' => 0.9],
                    ['label' => t('Produit'), 'width' => 2.2],
                    ['label' => t('Unité'), 'width' => 0.6, 'align' => 'center'],
                    ['label' => t('Théorique'), 'width' => 0.75, 'align' => 'right', 'type' => 'qty'],
                    ['label' => t('Compté'), 'width' => 0.75, 'align' => 'right', 'type' => 'qty'],
                    ['label' => t('Écart'), 'width' => 0.75, 'align' => 'right', 'type' => 'qty'],
                    ['label' => t('Coût unitaire (:currency)', ['currency' => currency()]), 'width' => 0.9, 'align' => 'right', 'type' => 'money'],
                    ['label' => t('Écart valorisé (:currency)', ['currency' => currency()]), 'width' => 1.0, 'align' => 'right', 'type' => 'money'],
                ],
                rows: $rows,
                totals: [null, null, null, null, null, null, null, $totalTheoretical, $totalCounted, $totalGap, null, $totalGapValue],
                landscape: true,
                currency: currency(),
            );

            return export_response((string) $request->query('format', 'xls'), $document);
        }

        $rows = [];
        $totalGap = 0.0;
        $totalLines = 0;
        foreach ($sessions as $session) {
            $totalGap += $session->gapValueTotal();
            $totalLines += $session->lineCount();
            $rows[] = [
                $session->numero(),
                date('d/m/Y H:i', strtotime($session->startedAt())),
                $session->locationName(),
                $session->startedByName(),
                t($session->statusLabel()),
                $session->countedLineCount(),
                $session->lineCount(),
                $session->gapValueTotal(),
                $session->validatedByName() ?? '',
                $session->validatedAt() !== null ? date('d/m/Y H:i', strtotime($session->validatedAt())) : '',
            ];
        }

        $document = new ExportDocument(
            filename: 'inventaires-' . date('Ymd-Hi'),
            title: t('Registre des inventaires'),
            subtitle: t('Sessions de contrôle physique, avancement du comptage et écart valorisé.'),
            meta: $meta,
            summary: [
                ['label' => t('Sessions'), 'value' => (string) count($sessions)],
                ['label' => t('Lignes'), 'value' => (string) $totalLines],
                ['label' => t('Écart valorisé'), 'value' => money($totalGap)],
                ['label' => t('Validés'), 'value' => (string) count(array_filter($sessions, static fn ($session): bool => $session->isValidated()))],
            ],
            columns: [
                ['label' => t('Inventaire'), 'width' => 1.1],
                ['label' => t('Date'), 'width' => 1.0],
                ['label' => t('Site'), 'width' => 1.5],
                ['label' => t('Démarré par'), 'width' => 1.1],
                ['label' => t('Statut'), 'width' => 0.8, 'align' => 'center'],
                ['label' => t('Comptés'), 'width' => 0.65, 'align' => 'right', 'type' => 'number'],
                ['label' => t('Lignes'), 'width' => 0.65, 'align' => 'right', 'type' => 'number'],
                ['label' => t('Écart valorisé (:currency)', ['currency' => currency()]), 'width' => 1.1, 'align' => 'right', 'type' => 'money'],
                ['label' => t('Validé par'), 'width' => 1.1],
                ['label' => t('Validé le'), 'width' => 1.1],
            ],
            rows: $rows,
            totals: [null, null, null, null, null, null, $totalLines, $totalGap, null, null],
            landscape: true,
            currency: currency(),
        );

        return export_response((string) $request->query('format', 'xls'), $document);
    }

    public function exportDocument(Request $request, string $id): Response
    {
        $GLOBALS['gos_export_french'] = true;
        $session = (new PdoInventoryRepository())->find((int) $id);
        if ($session === null) {
            return View::render('errors/404', ['title' => t('Inventaire introuvable')])->withStatus(404);
        }

        $rows = [];
        $totalTheoretical = 0.0;
        $totalCounted = 0.0;
        $totalGap = 0.0;
        $totalGapValue = 0.0;
        foreach ($session->lines() as $line) {
            $gap = $line->gap() ?? 0.0;
            $totalTheoretical += $line->theoretical();
            $totalCounted += $line->counted() ?? 0.0;
            $totalGap += $gap;
            $totalGapValue += $line->gapValue();
            $rows[] = [
                $line->sku(),
                $line->designation(),
                $line->unit(),
                $line->theoretical(),
                $line->counted(),
                $gap,
                $line->unitCost(),
                $line->gapValue(),
            ];
        }

        $meta = [
            t('Inventaire : :value', ['value' => $session->numero()]),
            t('Date : :value', ['value' => date('d/m/Y H:i', strtotime($session->startedAt()))]),
            t('Site : :label', ['label' => $session->locationName()]),
            t('Statut : :value', ['value' => t($session->statusLabel())]),
            t('Démarré par : :value', ['value' => $session->startedByName()]),
        ];
        if ($session->notes() !== null && $session->notes() !== '') {
            $meta[] = t('Notes : :value', ['value' => $session->notes()]);
        }

        $document = new ExportDocument(
            filename: 'inventaire-' . $session->numero() . '-' . date('Ymd-Hi'),
            title: t('Inventaire') . ' · ' . $session->numero(),
            subtitle: t('Comptage physique, écarts constatés et valorisation de l’ajustement.'),
            meta: $meta,
            summary: [
                ['label' => t('Lignes'), 'value' => (string) count($rows)],
                ['label' => t('Comptées'), 'value' => (string) $session->countedLineCount()],
                ['label' => t('Écart quantités'), 'value' => qty($totalGap)],
                ['label' => t('Écart valorisé'), 'value' => money($totalGapValue)],
            ],
            columns: [
                ['label' => 'SKU', 'width' => 0.9],
                ['label' => t('Produit'), 'width' => 2.6],
                ['label' => t('Unité'), 'width' => 0.6, 'align' => 'center'],
                ['label' => t('Théorique'), 'width' => 0.8, 'align' => 'right', 'type' => 'qty'],
                ['label' => t('Compté'), 'width' => 0.8, 'align' => 'right', 'type' => 'qty'],
                ['label' => t('Écart'), 'width' => 0.8, 'align' => 'right', 'type' => 'qty'],
                ['label' => t('Coût unitaire (:currency)', ['currency' => currency()]), 'width' => 1.0, 'align' => 'right', 'type' => 'money'],
                ['label' => t('Écart valorisé (:currency)', ['currency' => currency()]), 'width' => 1.1, 'align' => 'right', 'type' => 'money'],
            ],
            rows: $rows,
            totals: [null, null, null, $totalTheoretical, $totalCounted, $totalGap, null, $totalGapValue],
            landscape: false,
            currency: currency(),
        );

        return export_response((string) $request->query('format', 'xls'), $document);
    }

    /** @return array{q: string, status: string, location_id: int} */
    private function filters(Request $request): array
    {
        return [
            'q'           => trim((string) $request->query('q', '')),
            'status'      => trim((string) $request->query('statut', '')),
            'location_id' => (int) $request->query('site', 0),
        ];
    }

    /** @param array<string, mixed> $filters
     *  @return array<int, string>
     */
    private function meta(array $filters): array
    {
        $locations = (new PdoReferenceRepository())->locations(true);
        $meta = [t('Site : :label', ['label' => t('Tous les sites')])];
        if ((int) $filters['location_id'] > 0) {
            foreach ($locations as $location) {
                if ($location->id() === (int) $filters['location_id']) {
                    $meta[0] = t('Site : :label', ['label' => $location->name()]);
                    break;
                }
            }
        }
        if ((string) $filters['status'] !== '') {
            $meta[] = t('Statut : :value', ['value' => match ((string) $filters['status']) {
                'ouvert' => t('Ouvert'),
                'comptage' => t('Comptage'),
                'valide' => t('Validé'),
                'annule' => t('Annulé'),
                default => ucfirst((string) $filters['status']),
            }]);
        }
        if ((string) $filters['q'] !== '') {
            $meta[] = t('Recherche : « :q »', ['q' => (string) $filters['q']]);
        }

        return $meta;
    }

    public function create(Request $request): Response
    {
        return View::render('inventory/form', [
            'title'     => t('Nouvel inventaire'),
            'locations' => (new PdoReferenceRepository())->locations(true),
            'old'       => [],
            'errors'    => [],
        ]);
    }

    public function store(Request $request): Response
    {
        try {
            $id = (new StockService())->createInventory(
                (int) $request->input('location_id', 0),
                $request->input('notes') !== null ? (string) $request->input('notes') : null,
                Auth::id(),
            );
            flash('success', t('Inventaire créé. Saisissez les quantités comptées puis validez.'));
            return Response::redirect(url('/inventaire/' . $id));
        } catch (Throwable $e) {
            return View::render('inventory/form', [
                'title'     => t('Nouvel inventaire'),
                'locations' => (new PdoReferenceRepository())->locations(true),
                'old'       => $request->all(),
                'errors'    => [$e->getMessage()],
            ]);
        }
    }

    public function show(Request $request, string $id): Response
    {
        $session = (new PdoInventoryRepository())->find((int) $id);
        if ($session === null) {
            return View::render('errors/404', ['title' => t('Inventaire introuvable')])->withStatus(404);
        }

        return View::render('inventory/show', [
            'title'   => $session->numero(),
            'session' => $session,
        ]);
    }

    public function save(Request $request, string $id): Response
    {
        $counts = $request->input('counts', []);
        if (!is_array($counts)) {
            $counts = [];
        }

        try {
            (new StockService())->saveInventoryCounts((int) $id, $counts, Auth::id());
            flash('success', t('Comptage enregistré. Vérifiez les écarts puis validez l’inventaire.'));
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        return Response::redirect(url('/inventaire/' . (int) $id));
    }

    public function validateInventory(Request $request, string $id): Response
    {
        try {
            (new StockService())->validateInventory((int) $id, Auth::id());
            flash('success', t('Inventaire validé : les écarts ont été appliqués au stock.'));
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        return Response::redirect(url('/inventaire/' . (int) $id));
    }

    public function cancel(Request $request, string $id): Response
    {
        try {
            (new StockService())->cancelInventory((int) $id, Auth::id());
            flash('success', t('Inventaire annulé.'));
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        return Response::redirect(url('/inventaire/' . (int) $id));
    }
}
