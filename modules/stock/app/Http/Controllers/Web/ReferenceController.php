<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Infrastructure\Persistence\PdoReferenceRepository;
use Throwable;

final class ReferenceController
{
    private const PER_PAGE = 40;

    public function index(Request $request): Response
    {
        $repo = new PdoReferenceRepository();

        $brands = $repo->brands();
        $categories = $repo->categories();
        $suppliers = $repo->suppliers();

        $brandsPage = $this->pageOf($request, 'pb', count($brands));
        $categoriesPage = $this->pageOf($request, 'pc', count($categories));
        $suppliersPage = $this->pageOf($request, 'pf', count($suppliers));

        return View::render('references/index', [
            'title'          => t('Référentiels'),
            'brands'         => $this->slice($brands, $brandsPage),
            'brandsTotal'    => count($brands),
            'brandsPage'     => $brandsPage,
            'brandsPages'    => $this->pages(count($brands)),
            'categories'     => $this->slice($categories, $categoriesPage),
            'categoriesTotal' => count($categories),
            'categoriesPage' => $categoriesPage,
            'categoriesPages' => $this->pages(count($categories)),
            'units'          => $repo->units(),
            'suppliers'      => $this->slice($suppliers, $suppliersPage),
            'suppliersTotal' => count($suppliers),
            'suppliersPage'  => $suppliersPage,
            'suppliersPages' => $this->pages(count($suppliers)),
            'locations'      => $repo->locations(),
            'queryParams'    => array_filter(
                ['pb' => $brandsPage, 'pc' => $categoriesPage, 'pf' => $suppliersPage],
                static fn (int $value): bool => $value > 1
            ),
            'canManage'      => Auth::isRole('admin', 'responsable'),
        ]);
    }

    private function pages(int $total): int
    {
        return max(1, (int) ceil($total / self::PER_PAGE));
    }

    private function pageOf(Request $request, string $key, int $total): int
    {
        return min(max(1, (int) $request->query($key, 1)), $this->pages($total));
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, mixed>
     */
    private function slice(array $items, int $page): array
    {
        return array_slice($items, ($page - 1) * self::PER_PAGE, self::PER_PAGE);
    }

    public function store(Request $request, string $type): Response
    {
        if (!Auth::isRole('admin', 'responsable')) {
            flash('error', t('Votre rôle ne permet pas de modifier les référentiels.'));
            return Response::redirect(url('/referentiels'));
        }

        try {
            (new PdoReferenceRepository())->create($type, $request->all());
            flash('success', t('Référentiel ajouté.'));
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        return Response::redirect(url('/referentiels'));
    }

    public function toggle(Request $request, string $type, string $id): Response
    {
        if (!Auth::isRole('admin', 'responsable')) {
            flash('error', t('Votre rôle ne permet pas de modifier les référentiels.'));
            return Response::redirect(url('/referentiels'));
        }

        try {
            (new PdoReferenceRepository())->toggle($type, (int) $id);
            flash('success', t('Statut du référentiel modifié.'));
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        return Response::redirect(url('/referentiels'));
    }
}
