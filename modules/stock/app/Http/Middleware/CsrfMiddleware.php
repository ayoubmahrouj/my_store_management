<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;

final class CsrfMiddleware
{
    public static function web(): callable
    {
        return static function (Request $request): ?Response {
            if ($request->method() !== 'POST') {
                return null;
            }
            $token = $request->input('_token') ?? $request->header('X-CSRF-Token');
            if (Csrf::verify(is_string($token) ? $token : null)) {
                return null;
            }
            return Response::html(
                '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Session expirée</title>'
                . '<body style="font-family:system-ui;padding:40px;background:#0b1220;color:#fff">'
                . '<h1>Session expirée</h1><p>Rechargez la page et réessayez.</p>'
                . '<a style="color:#34d399" href="' . e(url('/login')) . '">Retour à la connexion</a></body></html>',
                419
            );
        };
    }
}
