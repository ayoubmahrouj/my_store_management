<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Authentification — module Chèques
|--------------------------------------------------------------------------
| Le compte, la session et la limitation des tentatives sont gérés par le
| socle unifié (shared/auth.php). Ce fichier ne conserve que les fonctions
| attendues par les pages historiques du module.
*/

/** La session est déjà démarrée par le socle. */
function start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/** Compatibilité : l'utilisateur courant est celui du socle. */
function current_user(): ?array
{
    $user = core_user();

    if ($user === null) {
        return null;
    }

    return [
        'id'       => (int) $user['id'],
        'username' => (string) ($user['login'] ?? ''),
        'nom'      => (string) ($user['name'] ?? ''),
        'role'     => core_is_admin() ? 'ADMIN' : strtoupper((string) ($user['role_code'] ?? '')),
    ];
}

function is_logged_in(): bool
{
    return core_user() !== null;
}

function is_admin(): bool
{
    return core_is_admin();
}

function require_login(): void
{
    core_require_login('cheques');
}

function require_api_login(): void
{
    core_require_login('cheques');
}

/**
 * Tente une connexion. Retourne null si OK, sinon un message d'erreur.
 */
function attempt_login(string $username, string $password): ?string
{
    return core_login($username, $password);
}

function logout_user(): void
{
    core_logout();
}

/* ------------------------------------------------------------------ */
/* Mot de passe                                                        */
/* ------------------------------------------------------------------ */

function change_password(int $userId, string $current, string $new): ?string
{
    if (
        mb_strlen($new) < 8
        || !preg_match('/\p{L}/u', $new)
        || !preg_match('/\d/', $new)
    ) {
        return t('Le nouveau mot de passe doit contenir au moins 8 caractères, avec une lettre et un chiffre.');
    }

    $row = fetch_one('SELECT password_hash FROM users WHERE id = ?', [$userId]);
    if (!$row) {
        return t('Utilisateur introuvable.');
    }

    $hash = preg_replace('/^\{[^}]+\}/', '', (string) $row['password_hash']) ?? (string) $row['password_hash'];
    if (!password_verify($current, $hash)) {
        return t('Le mot de passe actuel est incorrect.');
    }
    if (password_verify($new, $hash)) {
        return t('Le nouveau mot de passe doit être différent de l’ancien.');
    }

    q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $userId]);
    session_regenerate_id(true);
    audit('MODIFICATION', 'UTILISATEUR', $userId, 'Mot de passe modifié');

    return null;
}
