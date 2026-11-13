<?php
declare(strict_types=1);

/**
 * Helpers globaux du socle unifié.
 * Tous les noms sont préfixés sh_ / core_ pour ne jamais entrer en collision
 * avec les helpers propres à chaque module.
 */

function sh_config(?string $key = null, mixed $default = null): mixed
{
    $config = $GLOBALS['GOS_CONFIG'] ?? [];
    if ($key === null) {
        return $config;
    }
    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function sh_e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function sh_base(): string
{
    return (string) sh_config('app.base', '');
}

/** URL absolue (chemin) vers l'application unifiée. */
function sh_url(string $path = ''): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $base = rtrim(sh_base(), '/');
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '/' : $path);
}

function sh_module_base(string $module): string
{
    return sh_url('/modules/' . preg_replace('/[^a-z]/', '', $module));
}

function sh_module_url(string $module, string $path = ''): string
{
    $base = sh_module_base($module);
    if ($path === '') {
        return $base . '/';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return $base . '/' . ltrim($path, '/');
}

function sh_asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = GOS_ROOT . '/assets/' . $path;
    $version = is_file($file) ? (string) filemtime($file) : (string) sh_config('app.version', '1');
    return sh_url('/assets/' . $path) . '?v=' . rawurlencode($version);
}

function sh_module_asset(string $module, string $path): string
{
    $path = ltrim($path, '/');
    $file = GOS_ROOT . '/modules/' . $module . '/assets/' . $path;
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return sh_module_url($module, '/assets/' . $path) . '?v=' . rawurlencode($version);
}

function sh_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function sh_current_module(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    foreach (['stock', 'credits', 'cheques'] as $module) {
        if (str_contains($path, '/modules/' . $module)) {
            return $module;
        }
    }
    return 'home';
}

function sh_setting(string $key, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key] !== '' ? $cache[$key] : $default;
    }
    try {
        $value = core_scalar('SELECT v FROM settings WHERE k = ?', [$key]);
        $cache[$key] = $value === false || $value === null ? '' : (string) $value;
    } catch (Throwable) {
        $cache[$key] = '';
    }
    return $cache[$key] !== '' ? $cache[$key] : $default;
}

function sh_set_setting(string $key, string $value): void
{
    try {
        core_q(
            'INSERT INTO settings (k, v, updated_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = NOW()',
            [$key, $value]
        );
    } catch (Throwable) {
        // La base n'est peut-être pas encore installée.
    }
}

function sh_currency(): string
{
    return sh_setting('currency', 'MAD');
}

function sh_money(float|int|string|null $value): string
{
    return number_format((float) $value, 2, ',', ' ') . ' ' . sh_currency();
}

function sh_company_name(): string
{
    return sh_setting('company_name', (string) sh_config('app.name', 'Gestion Our Stock'));
}

/* -------------------------------------------------------------------------
| Messages flash
|-------------------------------------------------------------------------*/

function sh_flash(string $type, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_gos_flash'][] = ['type' => $type, 'message' => $message];
        return null;
    }
    return null;
}

/** @return array<int, array{type:string,message:string}> */
function sh_take_flashes(): array
{
    $items = $_SESSION['_gos_flash'] ?? [];
    unset($_SESSION['_gos_flash']);
    return is_array($items) ? $items : [];
}

function sh_render_flashes(): void
{
    foreach (sh_take_flashes() as $flash) {
        $type = in_array($flash['type'], ['success', 'error', 'warning', 'info'], true) ? $flash['type'] : 'info';
        echo '<div class="gos-alert gos-alert-' . sh_e($type) . '">'
            . '<span>' . sh_e($flash['message']) . '</span>'
            . '<button type="button" onclick="this.parentElement.remove()" aria-label="Fermer">&times;</button>'
            . '</div>';
    }
}

function sh_redirect(string $path, int $status = 302): never
{
    header('Location: ' . $path, true, $status);
    exit;
}

function sh_initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= mb_substr($part, 0, 1);
    }
    return mb_strtoupper($letters !== '' ? $letters : 'U');
}

function sh_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sh_date_long_fr(?string $date = null): string
{
    $ts = $date !== null ? strtotime($date) : time();
    if ($ts === false) {
        return (string) $date;
    }
    $days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    return $days[(int) date('w', $ts)] . ' ' . date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

/** Date longue localisée (français ou arabe, mois marocains). */
function sh_date_long(?string $date = null): string
{
    if (sh_locale() !== 'ar') {
        return sh_date_long_fr($date);
    }

    $ts = $date !== null ? strtotime($date) : time();
    if ($ts === false) {
        return (string) $date;
    }
    $days = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    $months = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'ماي', 'يونيو', 'يوليوز', 'غشت', 'شتنبر', 'أكتوبر', 'نونبر', 'دجنبر'];
    return $days[(int) date('w', $ts)] . ' ' . date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

function sh_post(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $default;
}

function sh_get(string $key, mixed $default = ''): mixed
{
    return $_GET[$key] ?? $default;
}

function sh_query(string $key, mixed $default = null): mixed
{
    return $_GET[$key] ?? $default;
}
