<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class RoleMiddleware
{
    public static function web(string ...$roles): callable
    {
        return static function (Request $request) use ($roles): ?Response {
            if (!Auth::check()) {
                flash('error', t('Veuillez vous connecter pour continuer.'));
                return Response::redirect(url('/login'));
            }

            if (!Auth::isRole(...$roles)) {
                return View::render('errors/403', [
                    'title' => t('Accès refusé'),
                ])->withStatus(403);
            }

            return null;
        };
    }
}
