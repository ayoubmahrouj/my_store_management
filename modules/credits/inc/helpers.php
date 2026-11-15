<?php
declare(strict_types=1);

/* -------------------------------------------------------------------------
| Échappement, URLs, redirections
|-------------------------------------------------------------------------*/

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function money($value): string
{
    return number_format((float) $value, 2, ',', ' ') . ' ' . CURRENCY;
}

function url(string $page = 'dashboard', array $params = []): string
{
    $query = array_merge(['p' => $page], $params);

    return BASE . '/index.php?' . http_build_query($query);
}

function redirect(string $page = 'dashboard', array $params = []): never
{
    header('Location: ' . url($page, $params));
    exit;
}

function post(string $key, $default = '')
{
    return $_POST[$key] ?? $default;
}

function get(string $key, $default = '')
{
    return $_GET[$key] ?? $default;
}

function post_int(string $key): int
{
    return (int) ($_POST[$key] ?? 0);
}

function post_float(string $key): float
{
    return (float) str_replace(',', '.', (string) ($_POST[$key] ?? 0));
}

/* -------------------------------------------------------------------------
| Messages flash
|-------------------------------------------------------------------------*/

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
    // Les messages remontent aussi via la coquille commune.
    $_SESSION['_gos_flash'][] = ['message' => $message, 'type' => $type];
}

function take_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $items;
}

/* -------------------------------------------------------------------------
| Jeton CSRF
|-------------------------------------------------------------------------*/

function csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }

    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['_token'] ?? '';
    $expected = $_SESSION['csrf'] ?? '';

    if ($expected === '' || ! is_string($token) || ! hash_equals($expected, $token)) {
        http_response_code(403);
        exit('Session expirée. Rechargez la page.');
    }
}

/* -------------------------------------------------------------------------
| Authentification
|-------------------------------------------------------------------------*/

/**
 * Utilisateur connecté (compte unique du socle) enrichi pour le module.
 */
function user(): ?array
{
    $user = core_user();

    if ($user === null) {
        return null;
    }

    // Compatibilité : le module lit `password` et `username`.
    $user['password'] = (string) ($user['password_hash'] ?? '');

    return $user;
}

function is_admin(): bool
{
    return core_is_admin();
}

function require_login(): void
{
    core_require_login('credits');
}

function require_admin(): void
{
    require_login();

    if (! is_admin()) {
        flash(t('access_denied'), 'error');
        redirect('dashboard');
    }
}

/* -------------------------------------------------------------------------
| Langue (FR / AR)
|-------------------------------------------------------------------------*/

function locale(): string
{
    return $_SESSION['locale'] ?? 'fr';
}

function is_rtl(): bool
{
    return locale() === 'ar';
}

function t(string $key, array $replace = []): string
{
    static $lines = null;

    if ($lines === null) {
        $lines = require __DIR__ . '/lang.php';
    }

    $locale = locale();
    $text = $lines[$locale][$key] ?? $lines['fr'][$key] ?? $key;

    foreach ($replace as $search => $value) {
        $text = str_replace(':' . $search, (string) $value, $text);
    }

    return $text;
}

/* -------------------------------------------------------------------------
| Dates
|-------------------------------------------------------------------------*/

function today(): string
{
    return date('Y-m-d');
}

function fr_date(?string $date): string
{
    if (! $date) {
        return '—';
    }

    $ts = strtotime($date);

    return $ts ? date('d/m/Y', $ts) : $date;
}

function fr_datetime(?string $date): string
{
    if (! $date) {
        return '—';
    }

    $ts = strtotime($date);

    return $ts ? date('d/m/Y H:i', $ts) : $date;
}

/** « novembre 2026 » — libellé de mois lisible dans les KPI. */
function fr_month_year(?string $date = null): string
{
    $ts = $date ? strtotime($date) : time();

    if (! $ts) {
        return '—';
    }

    $months = [
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    ];

    return $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

/* -------------------------------------------------------------------------
| Métier : crédits
|-------------------------------------------------------------------------*/

function client_balance(int $clientId): float
{
    return (new \Gos\Domain\Repositories\TiersRepository())->balance($clientId);
}

function client_total_due(int $clientId): float
{
    return client_balance($clientId);
}

/**
 * Recalcule une vente après ajout / modification / suppression d'un article.
 *
 * Met à jour le sous-total, le total, le statut et le crédit éventuel.
 * À appeler dans une transaction. Retourne null si tout est correct, sinon
 * une clé de message d'erreur.
 */
function recalculate_sale(int $saleId): ?string
{
    $sale = one(
        'SELECT s.*, c.is_passenger
         FROM sales s JOIN clients c ON c.id = s.client_id
         WHERE s.id = ?',
        [$saleId]
    );

    if (! $sale) {
        return 'no_results';
    }

    $credit = one('SELECT * FROM credits WHERE sale_id = ?', [$saleId]);

    if ($credit) {
        $allocations = (int) scalar(
            'SELECT COUNT(*) FROM payment_allocations WHERE credit_id = ?',
            [(int) $credit['id']]
        );

        if ($allocations > 0) {
            return 'cannot_edit_sale';
        }
    }

    $subtotal = round((float) scalar(
        'SELECT COALESCE(SUM(total), 0) FROM sale_items WHERE sale_id = ?',
        [$saleId]
    ), 2);

    $discount = max(0, (float) $sale['discount']);
    $total = round(max(0, $subtotal - $discount), 2);
    $isPassenger = (int) $sale['is_passenger'] === 1;
    $paid = $isPassenger ? $total : round((float) $sale['paid'], 2);

    if (! $isPassenger && $paid > $total + 0.009) {
        return 'total_below_received';
    }

    $remaining = round($total - $paid, 2);

    if ($remaining <= 0.009) {
        $status = 'paid';
        $method = $sale['payment_method'] === 'credit' ? 'cash' : $sale['payment_method'];
        $remaining = 0.0;
    } elseif ($paid > 0) {
        $status = 'partial';
        $method = $sale['payment_method'] === 'credit' ? 'cash' : $sale['payment_method'];
    } else {
        $status = 'credit';
        $method = 'credit';
    }

    q(
        'UPDATE sales SET subtotal = ?, total = ?, paid = ?, payment_method = ?, status = ? WHERE id = ?',
        [$subtotal, $total, $paid, $method, $status, $saleId]
    );

    if ($remaining > 0.009) {
        if ($credit) {
            $creditPaid = (float) $credit['paid'];
            $creditRemaining = round(max(0, $remaining - $creditPaid), 2);

            q(
                'UPDATE credits SET amount = ?, remaining = ?, status = ? WHERE id = ?',
                [$remaining, $creditRemaining, $creditRemaining <= 0.009 ? 'paid' : 'open', (int) $credit['id']]
            );
        } else {
            $days = max(1, (int) setting('credit_alert_days', '30'));
            $dueDate = date('Y-m-d', strtotime($sale['date'] . ' +' . $days . ' days'));

            q(
                'INSERT INTO credits (sale_id, client_id, amount, paid, remaining, due_date, status)
                 VALUES (?, ?, ?, 0, ?, ?, ?)',
                [$saleId, (int) $sale['client_id'], $remaining, $remaining, $dueDate, 'open']
            );
        }
    } elseif ($credit) {
        q('DELETE FROM credits WHERE id = ?', [(int) $credit['id']]);
    }

    return null;
}

/**
 * Impute un paiement sur les crédits ouverts du client (du plus ancien au plus récent).
 */
function allocate_payment(int $paymentId, int $clientId, float $amount): void
{
    $credits = all(
        'SELECT id, remaining FROM credits WHERE client_id = ? AND status = ? ORDER BY created_at, id',
        [$clientId, 'open']
    );

    $left = $amount;

    foreach ($credits as $credit) {
        if ($left <= 0.0001) {
            break;
        }

        $take = min($left, (float) $credit['remaining']);

        q(
            'INSERT INTO payment_allocations (payment_id, credit_id, amount) VALUES (?, ?, ?)',
            [$paymentId, $credit['id'], $take]
        );

        $newRemaining = round((float) $credit['remaining'] - $take, 2);

        q(
            'UPDATE credits SET paid = paid + ?, remaining = ?, status = ? WHERE id = ?',
            [$take, $newRemaining, $newRemaining <= 0.009 ? 'paid' : 'open', $credit['id']]
        );

        $left = round($left - $take, 2);
    }
}

/**
 * Client spécial « Passager » : ventes au comptoir sans compte client.
 * Le client est créé automatiquement la première fois.
 */
function passenger_client(): array
{
    $client = one('SELECT * FROM clients WHERE is_passenger = 1 ORDER BY id LIMIT 1');

    if ($client) {
        if (! $client['is_active']) {
            q('UPDATE clients SET is_active = 1 WHERE id = ?', [$client['id']]);
            $client['is_active'] = 1;
        }

        return $client;
    }

    q("INSERT INTO clients (name, phone, address, credit_limit, is_active, is_passenger) VALUES ('Passager', '', '', 0, 1, 1)");

    return one('SELECT * FROM clients WHERE id = ?', [(int) db()->lastInsertId()]);
}

/**
 * Annule l'imputation d'un versement (les crédits retrouvent leur dû).
 */
function reverse_payment_allocations(int $paymentId): void
{
    $allocations = all('SELECT * FROM payment_allocations WHERE payment_id = ?', [$paymentId]);

    foreach ($allocations as $allocation) {
        $credit = one('SELECT * FROM credits WHERE id = ?', [(int) $allocation['credit_id']]);

        if (! $credit) {
            continue;
        }

        $paid = max(0, round((float) $credit['paid'] - (float) $allocation['amount'], 2));
        $remaining = round((float) $credit['remaining'] + (float) $allocation['amount'], 2);

        q(
            'UPDATE credits SET paid = ?, remaining = ?, status = ? WHERE id = ?',
            [$paid, $remaining, $remaining > 0.009 ? 'open' : 'paid', $credit['id']]
        );
    }

    q('DELETE FROM payment_allocations WHERE payment_id = ?', [$paymentId]);
}

/**
 * Marque d'une ligne de vente.
 *
 * Les nouvelles lignes stockent la marque dans `brand`. Les anciennes
 * lignes l'avaient fusionnée dans `product_name` sous la forme
 * « MARQUE — PRODUIT » : on la retrouve ici pour rester compatible.
 */
function item_brand(array $item): string
{
    $brand = trim((string) ($item['brand'] ?? ''));

    if ($brand !== '') {
        return $brand;
    }

    $name = (string) ($item['product_name'] ?? '');

    if (str_contains($name, ' — ')) {
        return trim(explode(' — ', $name, 2)[0]);
    }

    return '';
}

/**
 * Désignation seule d'une ligne de vente (sans la marque).
 */
function item_name(array $item): string
{
    $name = (string) ($item['product_name'] ?? '');
    $brand = trim((string) ($item['brand'] ?? ''));

    if ($brand !== '' && str_starts_with($name, $brand . ' — ')) {
        return trim(substr($name, strlen($brand . ' — ')));
    }

    if ($brand === '' && str_contains($name, ' — ')) {
        return trim(explode(' — ', $name, 2)[1]);
    }

    return $name;
}

/**
 * Libellé complet d'une ligne de vente : « Désignation — Marque ».
 */
function item_label(array $item): string
{
    $name = item_name($item);
    $brand = item_brand($item);

    return $brand !== '' ? $name . ' — ' . $brand : $name;
}

/**
 * Libellé complet d'un produit : « Désignation — Marque ».
 */
function product_label(array $product): string
{
    $name = trim((string) ($product['name'] ?? ''));
    $brand = trim((string) ($product['brand'] ?? ''));

    return $brand !== '' ? $name . ' — ' . $brand : $name;
}

/**
 * Identifiant d'une marque (créée au besoin).
 */
function product_brand_id(string $brand): ?int
{
    $brand = trim($brand);

    if ($brand === '') {
        return null;
    }

    return (new \Gos\Domain\Repositories\MarqueRepository())->quickCreate($brand)->id();
}

/**
 * Correspondance entre les rôles du module Crédits et les rôles partagés.
 */
function credit_role_map(): array
{
    return [
        'admin'      => 'admin',
        'manager'    => 'responsable',
        'cashier'    => 'vendeur',
        'accountant' => 'auditeur',
    ];
}

/** Code de rôle partagé correspondant à un rôle du module. */
function credit_role_id(string $code): int
{
    $map = credit_role_map();
    $shared = $map[$code] ?? 'vendeur';

    $id = scalar('SELECT id FROM roles WHERE code = ?', [$shared]);

    if ($id === false || $id === null) {
        $id = scalar("SELECT id FROM roles WHERE code = 'vendeur'");
    }

    return (int) $id;
}

/** Code de rôle du module correspondant à un rôle partagé. */
function credit_role_code(string $sharedCode): string
{
    $map = array_flip(credit_role_map());

    return $map[$sharedCode] ?? 'cashier';
}

function credit_status_label(string $status): string
{
    // Statuts historiques : « active » (ancien) = « open », « settled » = « paid ».
    return match ($status) {
        'paid', 'settled', 'closed' => t('status_paid'),
        default => t('status_open'),
    };
}

function sale_status_label(string $status): string
{
    return match ($status) {
        'paid' => t('status_paid'),
        'credit', 'unpaid' => t('status_credit'),
        'partial' => t('status_partial'),
        default => $status,
    };
}

/**
 * Pagination serveur des listes (ventes, crédits, produits, versements).
 *
 * Les listes du module affichaient auparavant toutes les lignes : avec une
 * base de plusieurs milliers de ventes, chaque page devenait lourde. On ne
 * charge plus qu'une tranche de $perPage lignes et on conserve les filtres
 * courants dans les liens.
 */
function paginate(int $total, int $perPage = 50, string $param = 'page'): array
{
    $perPage = max(1, $perPage);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = (int) get($param, 1);
    $page = min(max(1, $page), $pages);

    return [
        'page' => $page,
        'pages' => $pages,
        'per_page' => $perPage,
        'offset' => ($page - 1) * $perPage,
        'total' => $total,
    ];
}

/** Liens de pagination : conserve tous les filtres de l'URL. */
function pager(array $p, string $param = 'page'): void
{
    $total = (int) $p['total'];
    $pages = (int) $p['pages'];
    $page = (int) $p['page'];
    $perPage = (int) $p['per_page'];

    if ($pages <= 1) {
        return;
    }

    $link = static function (int $target) use ($param): string {
        $query = $_GET;
        $query[$param] = $target;
        return '?' . http_build_query($query);
    };

    $from = ($page - 1) * $perPage + 1;
    $to = min($total, $page * $perPage);

    $window = [];
    $start = max(1, $page - 2);
    $end = min($pages, $page + 2);
    for ($i = $start; $i <= $end; $i++) {
        $window[] = $i;
    }

    echo '<div class="gos-pager">';
    echo '<span>' . number_format($from, 0, ',', ' ') . '–' . number_format($to, 0, ',', ' ')
        . ' / ' . number_format($total, 0, ',', ' ') . '</span>';
    echo '<div class="gos-pager-links">';

    if ($page > 1) {
        echo '<a href="' . e($link($page - 1)) . '" rel="prev" aria-label="' . e(t('previous')) . '">‹</a>';
    }
    if ($start > 1) {
        echo '<a href="' . e($link(1)) . '">1</a>';
        if ($start > 2) {
            echo '<span class="is-gap">…</span>';
        }
    }
    foreach ($window as $i) {
        if ($i === $page) {
            echo '<span class="is-current" aria-current="page">' . $i . '</span>';
        } else {
            echo '<a href="' . e($link($i)) . '">' . $i . '</a>';
        }
    }
    if ($end < $pages) {
        if ($end < $pages - 1) {
            echo '<span class="is-gap">…</span>';
        }
        echo '<a href="' . e($link($pages)) . '">' . $pages . '</a>';
    }
    if ($page < $pages) {
        echo '<a href="' . e($link($page + 1)) . '" rel="next" aria-label="' . e(t('next')) . '">›</a>';
    }

    echo '</div></div>';
}
