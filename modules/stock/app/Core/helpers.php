<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = '/'): string
{
    $base = rtrim((string) Config::get('app.base', ''), '/');
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '/' : $path);
}

function asset(string $path): string
{
    return url('/assets/' . ltrim($path, '/'));
}

function setting(string $key, string $default = ''): string
{
    static $settings = null;
    if ($settings === null) {
        try {
            $settings = (new \App\Infrastructure\Persistence\PdoSettingsRepository())->all();
        } catch (\Throwable) {
            $settings = [];
        }
    }
    $value = trim((string) ($settings[$key] ?? ''));
    return $value !== '' ? $value : $default;
}

function currency(): string
{
    return setting('currency', 'MAD');
}

function money(float|int|string $value): string
{
    return number_format((float) $value, 2, ',', ' ') . ' ' . currency();
}

function qty(float|int|string $value): string
{
    $formatted = number_format((float) $value, 3, ',', ' ');
    $formatted = rtrim(rtrim($formatted, '0'), ',');
    return $formatted === '' ? '0' : $formatted;
}

function num_input(float|int|string|null $value, int $decimals = 3): string
{
    if ($value === null) {
        return '';
    }
    $formatted = number_format((float) $value, $decimals, '.', '');
    $formatted = rtrim(rtrim($formatted, '0'), '.');
    return $formatted === '' ? '0' : $formatted;
}

/**
 * Rendu uniforme d'un export à partir du modèle partagé.
 * Le format par défaut est Excel ; CSV et PDF restent disponibles partout.
 */
function export_response(string $format, \App\Infrastructure\Export\ExportDocument $document): \App\Core\Response
{
    $format = strtolower(trim($format));
    $base = preg_replace('/\.(xls|pdf|csv)$/i', '', $document->filename) ?: 'export';

    return match ($format) {
        'pdf' => \App\Core\Response::pdf($base . '.pdf', (new \App\Infrastructure\Export\PdfExporter())->render($document)),
        'csv' => \App\Core\Response::csv($base . '.csv', (new \App\Infrastructure\Export\CsvExporter())->render($document)),
        default => \App\Core\Response::xls($base . '.xls', (new \App\Infrastructure\Export\XlsExporter())->render($document)),
    };
}

function money_input(float|int|string|null $value): string
{
    return num_input($value, 2);
}

function status_class(string $status): string
{
    return match ($status) {
        'valide', 'recu' => 'pill-success',
        'expedie', 'comptage', 'ouvert' => 'pill-warning',
        'annule' => 'pill-danger',
        default => 'pill-muted',
    };
}

function doc_type_label(string $type): string
{
    return match ($type) {
        'entree' => 'Entrée',
        'sortie' => 'Sortie',
        'transfert' => 'Transfert',
        'inventaire' => 'Inventaire',
        'ouverture' => 'Ouverture',
        default => ucfirst($type),
    };
}

function csrf_field(): string
{
    return Csrf::field();
}

function auth_user(): ?\App\Domain\Entities\User
{
    return Auth::user();
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return is_string($value) ? $value : null;
}

function is_active(string $prefix): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    return str_starts_with($path, $prefix) ? 'active' : '';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= mb_substr($part, 0, 1);
    }
    return mb_strtoupper($letters !== '' ? $letters : 'U');
}
