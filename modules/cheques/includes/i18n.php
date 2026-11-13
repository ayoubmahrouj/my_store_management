<?php
declare(strict_types=1);

function i18n_dictionary(): array
{
    static $ar = null;
    if ($ar === null) {
        $file = APP_ROOT . '/assets/i18n/ar.json';
        $ar = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    }
    return $ar;
}

function lang(): string
{
    $lang = $_SESSION['lang'] ?? $_SESSION['locale'] ?? 'fr';
    return $lang === 'ar' ? 'ar' : 'fr';
}

function is_rtl(): bool
{
    return lang() === 'ar';
}

function t(string $key): string
{
    if (lang() === 'ar') {
        $dict = i18n_dictionary();
        if (isset($dict[$key]) && is_string($dict[$key]) && $dict[$key] !== '') {
            return $dict[$key];
        }
    }
    return $key;
}

function html_lang(): string
{
    return lang();
}

function html_dir(): string
{
    return is_rtl() ? 'rtl' : 'ltr';
}

function lang_switch_url(string $target): string
{
    $params = $_GET;
    $params['lang'] = $target;
    $path = ltrim(current_path(), '/');
    return query_url($path, $params);
}

function handle_lang_switch(): void
{
    if (isset($_GET['lang'])) {
        $target = $_GET['lang'] === 'ar' ? 'ar' : 'fr';
        // Garde le socle, Crédits et Chèques sur la même langue.
        sh_set_locale($target);
        $params = $_GET;
        unset($params['lang']);
        $path = ltrim(current_path(), '/');
        header('Location: ' . query_url($path, $params));
        exit;
    }
}
