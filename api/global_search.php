<?php
declare(strict_types=1);

/**
 * Recherche globale (palette de commandes Ctrl/Cmd + K).
 *
 * GET /api/global_search.php?q=...
 *
 * Réponse volontairement compacte : 6 résultats maximum par groupe, uniquement
 * les colonnes utiles à l'affichage. Les requêtes sont préparées et bornées par
 * LIMIT, ce qui garde la palette instantanée même avec des dizaines de
 * milliers de lignes (produits, ventes, chèques).
 */

require __DIR__ . '/../shared/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    sh_json(['ok' => false, 'error' => 'Méthode non autorisée.'], 405);
}

core_require_login();

$q = trim((string) ($_GET['q'] ?? ''));
$q = preg_replace('/\s+/u', ' ', $q) ?? '';
if (mb_strlen($q) < 2) {
    sh_json(['ok' => true, 'groups' => [], 'hint' => sh_t('search_hint')]);
}

$needle = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
$prefix = str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
$limit = 6;

$groups = [];

/* --------------------------------- Produits -------------------------------- */
try {
    $rows = core_all(
        'SELECT p.id, p.designation, p.sku, p.barcode, p.sale_price,
                b.name AS brand, c.name AS category
         FROM products p
         LEFT JOIN brands b ON b.id = p.brand_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.active = 1
           AND (p.designation LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR b.name LIKE ?)
         ORDER BY (p.designation LIKE ?) DESC, (p.barcode = ?) DESC, p.designation
         LIMIT ' . $limit,
        [$needle, $needle, $needle, $needle, $prefix, $q]
    );

    $items = [];
    foreach ($rows as $row) {
        $sub = array_values(array_filter([
            (string) ($row['brand'] ?? ''),
            (string) ($row['sku'] ?? ''),
            (string) ($row['barcode'] ?? ''),
        ], static fn ($v) => $v !== ''));
        $items[] = [
            'title' => (string) $row['designation'],
            'subtitle' => implode(' · ', $sub),
            'meta' => sh_money((float) $row['sale_price']),
            'url' => sh_module_url('stock', '/produits?q=' . rawurlencode((string) ($row['sku'] !== '' ? $row['sku'] : $row['designation']))),
            'icon' => 'box',
        ];
    }
    if ($items !== []) {
        $groups[] = ['key' => 'products', 'label' => sh_t('group_products'), 'items' => $items];
    }
} catch (Throwable) {
    // Table absente : on ignore le groupe.
}

/* ---------------------------------- Clients -------------------------------- */
try {
    $rows = core_all(
        'SELECT id, name, phone, credit_limit, is_passenger
         FROM clients
         WHERE (name LIKE ? OR phone LIKE ?)
         ORDER BY (name LIKE ?) DESC, name
         LIMIT ' . $limit,
        [$needle, $needle, $prefix]
    );

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'title' => (string) $row['name'],
            'subtitle' => trim((string) ($row['phone'] ?? '')) . ((int) $row['is_passenger'] === 1 ? ' · ' . sh_t('group_clients') : ''),
            'meta' => (float) $row['credit_limit'] > 0 ? sh_money((float) $row['credit_limit']) : '',
            'url' => sh_module_url('credits', '/index.php?p=client&id=' . (int) $row['id']),
            'icon' => 'user',
        ];
    }
    if ($items !== []) {
        $groups[] = ['key' => 'clients', 'label' => sh_t('group_clients'), 'items' => $items];
    }
} catch (Throwable) {
}

/* ------------------------------- Bénéficiaires ----------------------------- */
try {
    $rows = core_all(
        'SELECT id, nom, type, telephone
         FROM beneficiaire
         WHERE nom LIKE ? OR telephone LIKE ?
         ORDER BY (nom LIKE ?) DESC, nom
         LIMIT ' . $limit,
        [$needle, $needle, $prefix]
    );

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'title' => (string) $row['nom'],
            'subtitle' => trim((string) ($row['type'] ?? '') . ' ' . (string) ($row['telephone'] ?? '')),
            'meta' => '',
            'url' => sh_module_url('cheques', '/documents/index.php?q=' . rawurlencode((string) $row['nom'])),
            'icon' => 'users',
        ];
    }
    if ($items !== []) {
        $groups[] = ['key' => 'beneficiaires', 'label' => sh_t('group_beneficiaires'), 'items' => $items];
    }
} catch (Throwable) {
}

/* -------------------------------- Fournisseurs ----------------------------- */
try {
    $rows = core_all(
        'SELECT id, name, phone
         FROM suppliers
         WHERE active = 1 AND (name LIKE ? OR phone LIKE ?)
         ORDER BY (name LIKE ?) DESC, name
         LIMIT ' . $limit,
        [$needle, $needle, $prefix]
    );

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'title' => (string) $row['name'],
            'subtitle' => (string) ($row['phone'] ?? ''),
            'meta' => '',
            'url' => sh_module_url('stock', '/referentiels?q=' . rawurlencode((string) $row['name'])),
            'icon' => 'users',
        ];
    }
    if ($items !== []) {
        $groups[] = ['key' => 'suppliers', 'label' => sh_t('group_suppliers'), 'items' => $items];
    }
} catch (Throwable) {
}

/* ---------------------------------- Chèques -------------------------------- */
try {
    $rows = core_all(
        'SELECT d.id, d.numero, d.type, d.montant, d.date_echeance, d.regle, d.rejete,
                b.nom AS beneficiaire_nom
         FROM cheques_documents d
         LEFT JOIN beneficiaire b ON b.id = d.beneficiaire_id
         WHERE d.numero LIKE ? OR b.nom LIKE ?
         ORDER BY d.date_echeance DESC
         LIMIT ' . $limit,
        [$needle, $needle]
    );

    $items = [];
    foreach ($rows as $row) {
        $flags = [];
        if ((int) $row['rejete'] === 1) {
            $flags[] = sh_t('kpi_alerts');
        } elseif ((int) $row['regle'] === 0) {
            $flags[] = (string) $row['date_echeance'];
        }
        $items[] = [
            'title' => ($row['type'] !== '' ? (string) $row['type'] : 'Chèque') . ' n° ' . (string) $row['numero'],
            'subtitle' => (string) ($row['beneficiaire_nom'] ?? '') . ($flags !== [] ? ' · ' . implode(' · ', $flags) : ''),
            'meta' => sh_money((float) $row['montant']),
            'url' => sh_module_url('cheques', '/documents/index.php?q=' . rawurlencode((string) $row['numero'])),
            'icon' => 'wallet',
        ];
    }
    if ($items !== []) {
        $groups[] = ['key' => 'cheques', 'label' => sh_t('group_cheques'), 'items' => $items];
    }
} catch (Throwable) {
}

/* ---------------------------------- Ventes --------------------------------- */
try {
    $rows = core_all(
        'SELECT s.id, s.date, s.total, s.status, c.name AS client_name
         FROM sales s
         LEFT JOIN clients c ON c.id = s.client_id
         WHERE c.name LIKE ? OR s.note LIKE ?
         ORDER BY s.id DESC
         LIMIT ' . $limit,
        [$needle, $needle]
    );

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'title' => 'Vente #' . (int) $row['id'] . ' · ' . (string) ($row['client_name'] ?? ''),
            'subtitle' => (string) $row['date'] . ' · ' . (string) $row['status'],
            'meta' => sh_money((float) $row['total']),
            'url' => sh_module_url('credits', '/index.php?p=sale&id=' . (int) $row['id']),
            'icon' => 'receipt',
        ];
    }
    if ($items !== []) {
        $groups[] = ['key' => 'sales', 'label' => sh_t('group_sales'), 'items' => $items];
    }
} catch (Throwable) {
}

$count = 0;
foreach ($groups as $group) {
    $count += count($group['items']);
}

sh_json([
    'ok' => true,
    'q' => $q,
    'count' => $count,
    'groups' => $groups,
]);
