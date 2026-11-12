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
    public function index(Request $request): Response
    {
        $repo = new PdoReferenceRepository();

        return View::render('references/index', [
            'title'      => 'Référentiels',
            'brands'     => $repo->brands(),
            'categories' => $repo->categories(),
            'units'      => $repo->units(),
            'suppliers'  => $repo->suppliers(),
            'locations'  => $repo->locations(),
            'canManage'  => Auth::isRole('admin', 'responsable'),
        ]);
    }

    public function store(Request $request, string $type): Response
    {
        if (!Auth::isRole('admin', 'responsable')) {
            flash('error', 'Votre rôle ne permet pas de modifier les référentiels.');
            return Response::redirect(url('/referentiels'));
        }

        try {
            (new PdoReferenceRepository())->create($type, $request->all());
            flash('success', 'Référentiel ajouté.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        return Response::redirect(url('/referentiels'));
    }

    public function toggle(Request $request, string $type, string $id): Response
    {
        if (!Auth::isRole('admin', 'responsable')) {
            flash('error', 'Votre rôle ne permet pas de modifier les référentiels.');
            return Response::redirect(url('/referentiels'));
        }

        try {
            (new PdoReferenceRepository())->toggle($type, (int) $id);
            flash('success', 'Statut du référentiel modifié.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        return Response::redirect(url('/referentiels'));
    }
}
