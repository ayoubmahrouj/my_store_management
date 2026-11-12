<?php
declare(strict_types=1);

/* ------------------------------------------------------------------ */
/* URL / redirections                                                  */
/* ------------------------------------------------------------------ */

function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function app_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

/**
 * En-têtes de sécurité communs à toutes les pages PHP.
 */
function security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    if (app_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Retourne uniquement un chemin local utilisable après connexion.
 * Empêche les redirections ouvertes vers un autre site.
 */
function local_return_path(string $path): string
{
    $path = trim($path);
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
        return '';
    }
    if (str_contains($path, "\r") || str_contains($path, "\n") || str_contains($path, '\\')) {
        return '';
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $path)) {
        return '';
    }
    if (BASE_URL !== '/' && !str_starts_with($path, BASE_URL)) {
        return '';
    }

    $pathOnly = parse_url($path, PHP_URL_PATH);
    if (!is_string($pathOnly)) {
        return '';
    }
    foreach (explode('/', rawurldecode($pathOnly)) as $segment) {
        if ($segment === '..') {
            return '';
        }
    }

    return $path;
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function current_path(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    if (BASE_URL !== '/' && str_starts_with($path, BASE_URL)) {
        $path = substr($path, strlen(BASE_URL) - 1);
    }
    return $path;
}

function query_url(string $path, array $params = []): string
{
    $params = array_filter($params, static fn ($v) => $v !== null && $v !== '');
    $qs = http_build_query($params);
    return url($path) . ($qs !== '' ? '?' . $qs : '');
}

/** URL de la page courante avec des paramètres remplacés. */
function page_url(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        }
    }
    $path = ltrim(current_path(), '/');
    return query_url($path, $params);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_ajax(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ------------------------------------------------------------------ */
/* Entrées / affichage                                                 */
/* ------------------------------------------------------------------ */

function h(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function param(string $key, mixed $default = ''): mixed
{
    if (array_key_exists($key, $_POST)) {
        return is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key];
    }
    if (array_key_exists($key, $_GET)) {
        return is_string($_GET[$key]) ? trim($_GET[$key]) : $_GET[$key];
    }
    return $default;
}

function int_param(string $key, int $default = 0): int
{
    $v = param($key, $default);
    return is_numeric($v) ? (int) $v : $default;
}

function float_param(string $key, ?float $default = null): ?float
{
    $v = param($key, '');
    if ($v === '' || $v === null) {
        return $default;
    }
    $v = str_replace([' ', ','], ['', '.'], (string) $v);
    return is_numeric($v) ? (float) $v : $default;
}

function isValidDate(?string $value): bool
{
    if (!$value) {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return $d !== false && $d->format('Y-m-d') === $value;
}

function today_iso(): string
{
    return date('Y-m-d');
}

function money(mixed $value): string
{
    return number_format((float) $value, 2, ',', ' ') . ' DH';
}

function date_fr(?string $iso): string
{
    if (!$iso) {
        return '—';
    }
    $ts = strtotime($iso);
    return $ts ? date('d/m/Y', $ts) : (string) $iso;
}

function datetime_fr(?string $value): string
{
    if (!$value) {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date('d/m/Y H:i', $ts) : (string) $value;
}

function date_long_fr(?string $iso = null): string
{
    $ts = $iso ? strtotime($iso) : time();
    $days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    return $days[(int) date('w', $ts)] . ' ' . date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

function month_label(string $month): string
{
    if (!preg_match('/^(\d{4})-(\d{2})$/', $month, $m)) {
        return $month;
    }
    $months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    return $months[(int) $m[2]] . ' ' . $m[1];
}

function type_label(?string $type): string
{
    return $type === 'EFFET' ? t('Effet') : t('Chèque');
}

function role_label(?string $role): string
{
    return match ($role) {
        'PROPRIETAIRE_SOCIETE' => t('Propriétaire'),
        'GERANT' => t('Gérant'),
        'EMPLOYE' => t('Employé'),
        default => (string) $role,
    };
}

function beneficiaire_type_label(?string $type): string
{
    return $type === 'SOCIETE' ? t('Société') : t('Personne');
}

function carnet_label(array $carnet): string
{
    $label = trim((string) ($carnet['libelle'] ?? ''));
    if (isset($carnet['code']) && $carnet['code'] !== null && $carnet['code'] !== '') {
        $label .= ($label !== '' ? ' · ' : '') . $carnet['code'];
    }
    return $label !== '' ? $label : '—';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out !== '' ? $out : 'GC';
}

function str_limit(string $value, int $limit = 60): string
{
    return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit - 1) . '…' : $value;
}

/* ------------------------------------------------------------------ */
/* Flash / CSRF                                                        */
/* ------------------------------------------------------------------ */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    // Affichage via la coquille commune.
    $_SESSION['_gos_flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if (!is_post()) {
        return;
    }
    $token = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($token === '' || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $token)) {
        http_response_code(419);
        echo '<!doctype html><meta charset="utf-8"><p style="font-family:Segoe UI,sans-serif;padding:40px">Session expirée. Rechargez la page puis réessayez.</p>';
        exit;
    }
}

/* ------------------------------------------------------------------ */
/* Statuts des documents                                               */
/* ------------------------------------------------------------------ */

function document_status(array $doc): string
{
    if (bit_true($doc['rejete'] ?? false)) {
        return 'rejete';
    }
    if (bit_true($doc['regle'] ?? false)) {
        return 'regle';
    }
    if (($doc['date_echeance'] ?? '') !== '' && $doc['date_echeance'] < today_iso()) {
        return 'retard';
    }
    $jours = days_until((string) ($doc['date_echeance'] ?? today_iso()));
    return $jours <= 7 ? 'bientot' : 'non_regle';
}

function days_until(?string $iso): int
{
    if (!$iso) {
        return 0;
    }
    $today = new DateTimeImmutable(today_iso());
    $date = new DateTimeImmutable($iso);
    return (int) $today->diff($date)->format('%r%a');
}

function status_badge(array $doc): string
{
    $status = document_status($doc);
    $map = [
        'regle' => ['badge-valid', 'Réglé'],
        'rejete' => ['badge-invalid', 'Rejeté'],
        'retard' => ['badge-late', 'En retard'],
        'bientot' => ['badge-soon', 'À échéance'],
        'non_regle' => ['badge-neutral', 'Non réglé'],
    ];
    [$class, $label] = $map[$status];
    return '<span class="badge ' . $class . '">' . h(t($label)) . '</span>';
}

function status_label(array $doc): string
{
    return match (document_status($doc)) {
        'regle' => t('Réglé'),
        'rejete' => t('Rejeté'),
        'retard' => t('En retard'),
        'bientot' => t('À échéance'),
        default => t('Non réglé'),
    };
}

/* ------------------------------------------------------------------ */
/* Pagination                                                          */
/* ------------------------------------------------------------------ */

function pagination(int $page, int $totalPages, int $totalElements, int $size, array $query = []): string
{
    if ($totalPages < 1) {
        $totalPages = 1;
    }
    $sizeOptions = [10, 25, 50, 100];
    $html = '<div class="pagination">';
    $html .= '<div class="page-info">' . h(t('Page')) . ' <strong>' . ($page + 1) . '</strong> / ' . $totalPages
        . ' · <strong>' . number_format($totalElements, 0, ',', ' ') . '</strong> ' . h(t('élément(s)')) . '</div>';
    $html .= '<div class="page-size"><label for="size">' . h(t('Par page')) . '</label><select id="size" class="select" onchange="window.location.href=this.value">';
    foreach ($sizeOptions as $opt) {
        $url = page_url(['size' => $opt, 'page' => 0]);
        $html .= '<option value="' . h($url) . '"' . ($opt === $size ? ' selected' : '') . '>' . $opt . '</option>';
    }
    $html .= '</select></div>';
    $html .= '<div class="page-buttons">';
    $prev = max(0, $page - 1);
    $next = min($totalPages - 1, $page + 1);
    $html .= '<a class="page-btn' . ($page <= 0 ? ' disabled' : '') . '" href="' . h(page_url(['page' => $prev])) . '">‹</a>';
    $start = max(0, $page - 2);
    $end = min($totalPages - 1, $page + 2);
    if ($start > 0) {
        $html .= '<a class="page-btn" href="' . h(page_url(['page' => 0])) . '">1</a>';
        if ($start > 1) {
            $html .= '<span class="page-btn disabled">…</span>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        $html .= '<a class="page-btn' . ($i === $page ? ' active' : '') . '" href="' . h(page_url(['page' => $i])) . '">' . ($i + 1) . '</a>';
    }
    if ($end < $totalPages - 1) {
        if ($end < $totalPages - 2) {
            $html .= '<span class="page-btn disabled">…</span>';
        }
        $html .= '<a class="page-btn" href="' . h(page_url(['page' => $totalPages - 1])) . '">' . $totalPages . '</a>';
    }
    $html .= '<a class="page-btn' . ($page >= $totalPages - 1 ? ' disabled' : '') . '" href="' . h(page_url(['page' => $next])) . '">›</a>';
    $html .= '</div></div>';
    return $html;
}

/* ------------------------------------------------------------------ */
/* Audit                                                               */
/* ------------------------------------------------------------------ */

function audit(string $action, string $entite, ?int $entiteId = null, ?string $details = null): void
{
    core_audit($action, $entite, $entiteId, $details !== null ? str_limit($details, 590) : null);
}

/* ------------------------------------------------------------------ */
/* Divers                                                              */
/* ------------------------------------------------------------------ */

function echeance_par_defaut(string $dateEmission): string
{
    $d = new DateTimeImmutable($dateEmission);
    return $d->modify('+3 months')->format('Y-m-d');
}

function add_days(string $iso, int $days): string
{
    $d = new DateTimeImmutable($iso);
    return $d->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
}

function month_bounds(string $month): array
{
    if (!preg_match('/^(\d{4})-(\d{2})$/', $month, $m) || (int) $m[2] < 1 || (int) $m[2] > 12) {
        return [null, null];
    }
    $start = $m[1] . '-' . $m[2] . '-01';
    $end = date('Y-m-t', strtotime($start));
    return [$start, $end];
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/** Paramètres de la société (ligne unique id = 1). */
function societe(): array
{
    static $societe = null;
    if ($societe === null) {
        $row = fetch_one('SELECT * FROM parametres_societe WHERE id = 1');
        $societe = $row ?: [
            'nom'       => APP_NAME,
            'adresse'   => null,
            'telephone' => null,
            'email'     => null,
        ];
    }
    return $societe;
}

/** Résumé lisible des filtres actifs (pour l'en-tête des exports). */
function filters_summary(array $input): string
{
    $parts = [];
    if (!empty($input['q'])) {
        $parts[] = 'Recherche : ' . $input['q'];
    }
    if (!empty($input['type'])) {
        $parts[] = 'Type : ' . ($input['type'] === 'EFFET' ? 'Effets' : 'Chèques');
    }
    if (($input['statut'] ?? '') === 'true') {
        $parts[] = 'Réglés';
    } elseif (($input['statut'] ?? '') === 'false') {
        $parts[] = 'Non réglés';
    } elseif (($input['statut'] ?? '') === 'rejete') {
        $parts[] = 'Rejetés';
    }
    if (!empty($input['mois_echeance'])) {
        $parts[] = "Échéance : " . month_label((string) $input['mois_echeance']);
    }
    if (!empty($input['mois_emission'])) {
        $parts[] = "Émission : " . month_label((string) $input['mois_emission']);
    }
    if (!empty($input['date_from']) || !empty($input['date_to'])) {
        $parts[] = 'Émission du ' . (!empty($input['date_from']) ? date_fr($input['date_from']) : '…')
            . ' au ' . (!empty($input['date_to']) ? date_fr($input['date_to']) : '…');
    }
    if (!empty($input['echeance_from']) || !empty($input['echeance_to'])) {
        $parts[] = 'Échéance du ' . (!empty($input['echeance_from']) ? date_fr($input['echeance_from']) : '…')
            . ' au ' . (!empty($input['echeance_to']) ? date_fr($input['echeance_to']) : '…');
    }
    if (!empty($input['carnet'])) {
        $carnet = fetch_one('SELECT libelle, code FROM carnet WHERE id = ?', [(int) $input['carnet']]);
        if ($carnet) {
            $parts[] = 'Carnet : ' . carnet_label($carnet);
        }
    }
    if (!empty($input['jours'])) {
        $parts[] = 'Échéance dans ' . (int) $input['jours'] . ' jours';
    }
    if (!empty($input['montant_min']) || !empty($input['montant_max'])) {
        $parts[] = 'Montant : ' . (!empty($input['montant_min']) ? money($input['montant_min']) : '0 DH')
            . ' – ' . (!empty($input['montant_max']) ? money($input['montant_max']) : '∞');
    }
    return $parts ? implode(' · ', $parts) : 'Tous les documents';
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['old'][$key] ?? $default;
}

function set_old(array $data): void
{
    $_SESSION['old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

function form_errors(): array
{
    return $_SESSION['errors'] ?? [];
}

function set_errors(array $errors): void
{
    $_SESSION['errors'] = $errors;
}

function clear_errors(): void
{
    unset($_SESSION['errors']);
}

function simulation_log_file(string $name): string
{
    $dir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'gestion-cheques';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir . DIRECTORY_SEPARATOR . $name;
}
