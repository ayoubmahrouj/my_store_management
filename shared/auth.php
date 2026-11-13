<?php
declare(strict_types=1);

/**
 * Authentification unique : un seul compte pour les 3 modules.
 */

function core_user(): ?array
{
    static $cached = null;
    static $loaded = false;

    if ($loaded) {
        return $cached;
    }
    $loaded = true;

    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    try {
        $user = core_one(
            'SELECT u.*, r.code AS role_code, r.label AS role_label, l.name AS location_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN locations l ON l.id = u.location_id
             WHERE u.id = ? AND u.active = 1',
            [$id]
        );
    } catch (Throwable) {
        return null;
    }

    if ($user === null) {
        unset($_SESSION['user_id']);
        return null;
    }

    $user['username'] = $user['login'];
    $user['nom'] = $user['name'];
    $user['is_active'] = (int) $user['active'];
    $user['role'] = (string) $user['role_code'];

    $cached = $user;
    return $cached;
}

function core_user_id(): int
{
    return (int) (core_user()['id'] ?? 0);
}

function core_user_name(): string
{
    return (string) (core_user()['name'] ?? '');
}

function core_is_admin(): bool
{
    return (core_user()['role_code'] ?? '') === 'admin';
}

function core_user_modules(): array
{
    $modules = (string) (core_user()['modules'] ?? '');
    if ($modules === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $modules))));
}

function core_can_access(string $module): bool
{
    $user = core_user();
    if ($user === null) {
        return false;
    }
    if (($user['role_code'] ?? '') === 'admin') {
        return true;
    }
    if ($module === 'home') {
        return true;
    }
    return in_array($module, core_user_modules(), true);
}

function core_require_login(?string $module = null): void
{
    if (core_user() === null) {
        $next = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $target = sh_url('/login.php');
        if ($next !== '' && !str_contains($next, 'login.php')) {
            $target .= '?next=' . rawurlencode($next);
        }
        sh_redirect($target);
    }

    if ($module !== null && !core_can_access($module)) {
        http_response_code(403);
        echo '<!doctype html><html lang="' . sh_e(sh_locale()) . '" dir="' . (sh_is_rtl() ? 'rtl' : 'ltr') . '"><meta charset="utf-8"><title>' . sh_e(sh_t('access_denied')) . '</title>'
            . '<body style="font-family:system-ui;background:#080b10;color:#e6edf6;padding:48px">'
            . '<h1>403 — ' . sh_e(sh_t('access_denied')) . '</h1><p>' . sh_e(sh_t('no_module_access', ['module' => (string) $module])) . '</p>'
            . '<p><a style="color:#5b8cff" href="' . sh_e(sh_url('/index.php')) . '">' . sh_e(sh_t('back_to_dashboard')) . '</a></p></body></html>';
        exit;
    }
}

/* -------------------------------------------------------------------------
| CSRF
|-------------------------------------------------------------------------*/

function core_csrf(): string
{
    if (empty($_SESSION['_gos_csrf'])) {
        $_SESSION['_gos_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['_gos_csrf'];
}

function core_csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . sh_e(core_csrf()) . '">';
}

function core_verify_csrf(): bool
{
    $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = (string) ($_SESSION['_gos_csrf'] ?? '');
    return $expected !== '' && is_string($token) && hash_equals($expected, $token);
}

function core_csrf_check_or_fail(): void
{
    if (!core_verify_csrf()) {
        http_response_code(403);
        if (str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'json')) {
            sh_json(['ok' => false, 'error' => 'Jeton CSRF invalide. Rechargez la page.'], 403);
        }
        exit('Session expirée ou jeton CSRF invalide. Rechargez la page.');
    }
}

/* -------------------------------------------------------------------------
| Connexion / déconnexion
|-------------------------------------------------------------------------*/

function core_login_attempts_count(string $login, string $ip): int
{
    try {
        $window = (int) sh_config('security.lock_minutes', 15);
        return (int) core_scalar(
            'SELECT COUNT(*) FROM login_attempts
             WHERE login = ? AND ip = ? AND success = 0 AND created_at > (NOW() - INTERVAL ? MINUTE)',
            [$login, $ip, $window]
        );
    } catch (Throwable) {
        return 0;
    }
}

function core_record_login_attempt(string $login, string $ip, bool $success): void
{
    try {
        core_q(
            'INSERT INTO login_attempts (login, ip, success, created_at) VALUES (?, ?, ?, NOW())',
            [mb_substr($login, 0, 100), mb_substr($ip, 0, 45), $success ? 1 : 0]
        );
    } catch (Throwable) {
        // ignore
    }
}

/**
 * Tente une connexion. Retourne null si succès, sinon un message d'erreur.
 */
function core_login(string $login, string $password, string $ip = ''): ?string
{
    $login = trim($login);
    $ip = $ip !== '' ? $ip : (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

    if ($login === '' || $password === '') {
        return 'Veuillez saisir votre identifiant et votre mot de passe.';
    }

    $max = (int) sh_config('security.max_login_attempts', 5);
    if (core_login_attempts_count($login, $ip) >= $max) {
        return 'Trop de tentatives. Réessayez dans quelques minutes.';
    }

    try {
        $user = core_one('SELECT * FROM users WHERE login = ? LIMIT 1', [$login]);
    } catch (Throwable) {
        return 'Base de données indisponible. Vérifiez l\'installation.';
    }

    $ok = false;
    if ($user !== null) {
        $hash = (string) $user['password_hash'];
        // Compatibilité avec les anciens hashs Spring Boot "{bcrypt}$2a$…".
        $hash = preg_replace('/^\{[^}]+\}/', '', $hash) ?? $hash;
        $ok = password_verify($password, $hash);
    } else {
        password_verify($password, '$2y$12$6a8zAoPWCpyAa1ZWAdTO8OSPoS8bSE9gwDC5qaVeJJy4xJsQeeixa');
    }

    if (!$ok || (int) ($user['active'] ?? 0) !== 1) {
        core_record_login_attempt($login, $ip, false);
        usleep(250000);
        return 'Identifiant ou mot de passe incorrect.';
    }

    core_record_login_attempt($login, $ip, true);
    session_regenerate_id(true);
    unset($_SESSION['_gos_csrf']);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['last_activity'] = time();
    $_SESSION['login_at'] = time();

    try {
        core_q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [(int) $user['id']]);
    } catch (Throwable) {
        // ignore
    }

    core_audit('CONNEXION', 'USER', (int) $user['id']);
    return null;
}

function core_logout(): void
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId > 0) {
        core_audit('DECONNEXION', 'USER', $userId);
    }
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function core_password_errors(string $password): ?string
{
    if (mb_strlen($password) < 8 || !preg_match('/\p{L}/u', $password) || !preg_match('/\d/', $password)) {
        return 'Le mot de passe doit contenir au moins 8 caractères, avec une lettre et un chiffre.';
    }
    return null;
}
