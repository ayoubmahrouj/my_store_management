<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class AuthMiddleware
{
    public static function web(): callable
    {
        return static function (Request $request): ?Response {
            if (Auth::check()) {
                return null;
            }
            $next = (string) ($_SERVER['REQUEST_URI'] ?? '');
            $target = sh_url('/login.php');
            if ($next !== '' && !str_contains($next, 'login.php')) {
                $target .= '?next=' . rawurlencode($next);
            }
            return Response::redirect($target);
        };
    }

    public static function api(): callable
    {
        return static function (Request $request): ?Response {
            if (Auth::check()) {
                return null;
            }
            return Response::json([
                'success' => false,
                'message' => t('Authentification requise.'),
                'errors'  => [],
            ], 401);
        };
    }
}
