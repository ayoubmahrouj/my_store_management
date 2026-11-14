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
            $dir = sh_is_rtl() ? 'rtl' : 'ltr';
            return Response::html(
                '<!doctype html><html lang="' . e(sh_locale()) . '" dir="' . $dir . '"><meta charset="utf-8"><title>' . e(t('Session expirée')) . '</title>'
                . '<body style="font-family:system-ui;padding:40px;background:#0b1220;color:#fff">'
                . '<h1>' . e(t('Session expirée')) . '</h1><p>' . e(t('Rechargez la page et réessayez.')) . '</p>'
                . '<a style="color:#34d399" href="' . e(url('/login')) . '">' . e(t('Retour à la connexion')) . '</a></body></html>',
                419
            );
        };
    }
}
