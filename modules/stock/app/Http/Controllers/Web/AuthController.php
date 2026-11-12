<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Services\AuthService;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class AuthController
{
    public function showLogin(Request $request): Response
    {
        // Une seule page de connexion pour toute l'application unifiée.
        if (Auth::check()) {
            return Response::redirect(url('/dashboard'));
        }

        return Response::redirect(sh_url('/login.php?next=' . rawurlencode(url('/dashboard'))));
    }

    public function login(Request $request): Response
    {
        // La connexion est gérée par le socle commun (login.php).
        return Response::redirect(sh_url('/login.php?next=' . rawurlencode(url('/dashboard'))));
    }

    public function logout(Request $request): Response
    {
        core_logout();
        return Response::redirect(sh_url('/login.php'));
    }
}
