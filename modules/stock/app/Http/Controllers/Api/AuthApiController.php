<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\AuthService;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class AuthApiController
{
    public function login(Request $request): Response
    {
        $login = trim((string) $request->input('login', ''));
        $password = (string) $request->input('password', '');

        if ($login === '' || $password === '') {
            return Response::json([
                'success' => false,
                'message' => 'Identifiants requis.',
                'errors'  => ['login' => ['Le login et le mot de passe sont obligatoires.']],
            ], 422);
        }

        $result = (new AuthService())->attempt($login, $password, $request->ip(), $request->userAgent());

        if ($result['error'] !== null || $result['user'] === null) {
            $status = str_contains((string) $result['error'], 'Trop de tentatives') ? 429 : 401;
            return Response::json([
                'success' => false,
                'message' => $result['error'],
                'errors'  => [],
            ], $status);
        }

        return Response::json([
            'success' => true,
            'data'    => $result['user']->toArray(),
            'meta'    => [],
            'errors'  => [],
        ]);
    }

    public function me(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::json(['success' => false, 'message' => 'Non authentifié.', 'errors' => []], 401);
        }

        return Response::json([
            'success' => true,
            'data'    => $user->toArray(),
            'meta'    => [],
            'errors'  => [],
        ]);
    }

    public function logout(Request $request): Response
    {
        Auth::logout();
        return Response::json([
            'success' => true,
            'data'    => ['message' => 'Déconnecté.'],
            'meta'    => [],
            'errors'  => [],
        ]);
    }
}
