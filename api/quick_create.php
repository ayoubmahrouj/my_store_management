<?php
declare(strict_types=1);

/**
 * Création rapide (bouton « + » à côté des listes déroulantes).
 * POST JSON — authentifié + jeton CSRF.
 */

require __DIR__ . '/../shared/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sh_json(['ok' => false, 'error' => 'Méthode non autorisée.'], 405);
}

core_require_login();
core_csrf_check_or_fail();

$raw = file_get_contents('php://input') ?: '';
$payload = [];
if ($raw !== '' && str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'json')) {
    $decoded = json_decode($raw, true);
    $payload = is_array($decoded) ? $decoded : [];
} else {
    $payload = $_POST;
}

$aliases = [
    'marque' => 'brand', 'categorie' => 'category', 'catégorie' => 'category',
    'fournisseur' => 'supplier', 'site' => 'location', 'beneficiaire' => 'client',
    'bénéficiaire' => 'client', 'produit' => 'product', 'carnet' => 'carnet',
    'personne' => 'personne', 'client' => 'client', 'brand' => 'brand',
    'societe' => 'societe', 'société' => 'societe', 'company' => 'societe',
    'category' => 'category', 'unit' => 'unit', 'supplier' => 'supplier',
    'location' => 'location', 'product' => 'product',
];
$entity = strtolower(trim((string) ($payload['entity'] ?? '')));
$entity = $aliases[$entity] ?? $entity;

$str = static function (string $key, int $max = 160) use ($payload): string {
    return mb_substr(trim((string) ($payload[$key] ?? '')), 0, $max);
};
$num = static function (string $key, float $default = 0.0) use ($payload): float {
    $value = str_replace([' ', ','], ['', '.'], (string) ($payload[$key] ?? ''));
    return $value === '' ? $default : (float) $value;
};
$intOrNull = static function (string $key) use ($payload): ?int {
    $value = (int) ($payload[$key] ?? 0);
    return $value > 0 ? $value : null;
};

function qc_fail(string $message, int $status = 422): never
{
    sh_json(['ok' => false, 'error' => $message], $status);
}

function qc_required(array $values): void
{
    foreach ($values as $label => $value) {
        if (trim((string) $value) === '') {
            qc_fail('Le champ « ' . $label . ' » est obligatoire.');
        }
    }
}

function qc_default_id(string $type): int
{
    if ($type === 'brand') {
        $id = core_scalar("SELECT id FROM brands WHERE name = 'Sans marque' LIMIT 1");
        if ($id !== false) {
            return (int) $id;
        }
        core_q("INSERT INTO brands (name) VALUES ('Sans marque')");
        return core_insert_id();
    }
    if ($type === 'category') {
        $id = core_scalar("SELECT id FROM categories WHERE name = 'Général' LIMIT 1");
        if ($id !== false) {
            return (int) $id;
        }
        core_q("INSERT INTO categories (name) VALUES ('Général')");
        return core_insert_id();
    }
    $id = core_scalar("SELECT id FROM units WHERE code = 'U' LIMIT 1");
    if ($id !== false) {
        return (int) $id;
    }
    core_q("INSERT INTO units (code, label) VALUES ('U', 'Unité')");
    return core_insert_id();
}

try {
    switch ($entity) {
        case 'brand':
            $name = $str('name', 120);
            qc_required(['Nom de la marque' => $name]);
            $exists = core_scalar('SELECT id FROM brands WHERE name = ?', [$name]);
            if ($exists !== false) {
                sh_json(['ok' => true, 'item' => ['id' => (int) $exists, 'label' => $name]]);
            }
            core_q('INSERT INTO brands (name) VALUES (?)', [$name]);
            $id = core_insert_id();
            $label = $name;
            break;

        case 'category':
            $name = $str('name', 120);
            qc_required(['Nom de la catégorie' => $name]);
            $exists = core_scalar('SELECT id FROM categories WHERE name = ?', [$name]);
            if ($exists !== false) {
                sh_json(['ok' => true, 'item' => ['id' => (int) $exists, 'label' => $name]]);
            }
            core_q('INSERT INTO categories (name) VALUES (?)', [$name]);
            $id = core_insert_id();
            $label = $name;
            break;

        case 'unit':
            $code = mb_strtoupper($str('code', 20));
            $label = $str('label', 60);
            qc_required(['Code court' => $code, 'Libellé' => $label]);
            $exists = core_scalar('SELECT id FROM units WHERE code = ?', [$code]);
            if ($exists !== false) {
                sh_json(['ok' => true, 'item' => ['id' => (int) $exists, 'label' => $label . ' (' . $code . ')']]);
            }
            core_q('INSERT INTO units (code, label) VALUES (?, ?)', [$code, $label]);
            $id = core_insert_id();
            $label = $label . ' (' . $code . ')';
            break;

        case 'supplier':
            $name = $str('name', 160);
            qc_required(['Nom du fournisseur' => $name]);
            $exists = core_scalar('SELECT id FROM suppliers WHERE name = ?', [$name]);
            if ($exists !== false) {
                sh_json(['ok' => true, 'item' => ['id' => (int) $exists, 'label' => $name]]);
            }
            core_q(
                'INSERT INTO suppliers (name, ice, phone, email, address) VALUES (?, ?, ?, ?, ?)',
                [$name, $str('ice', 30), $str('phone', 30), $str('email', 160), $str('address', 255)]
            );
            $id = core_insert_id();
            $label = $name;
            break;

        case 'location':
            $name = $str('name', 120);
            $code = mb_strtoupper($str('code', 20));
            $type = strtolower($str('type', 20)) === 'point_vente' ? 'point_vente' : 'depot';
            qc_required(['Nom du site' => $name, 'Code' => $code]);
            $exists = core_scalar('SELECT id FROM locations WHERE code = ?', [$code]);
            if ($exists !== false) {
                sh_json(['ok' => true, 'item' => ['id' => (int) $exists, 'label' => $name . ' (' . $code . ')']]);
            }
            core_q(
                'INSERT INTO locations (code, name, type, address, phone) VALUES (?, ?, ?, ?, ?)',
                [$code, $name, $type, $str('address', 255), $str('phone', 30)]
            );
            $id = core_insert_id();
            $label = $name . ' (' . $code . ')';
            break;

        case 'client':
            $name = $str('name', 150);
            $type = strtoupper($str('type', 20)) === 'SOCIETE' ? 'SOCIETE' : 'PERSONNE';
            qc_required(['Nom / raison sociale' => $name]);
            core_q(
                'INSERT INTO clients (name, phone, email, address, ice, type, credit_limit, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)',
                [$name, $str('phone', 30), $str('email', 160), $str('address', 255), $str('ice', 30), $type, $num('credit_limit')]
            );
            $id = core_insert_id();
            $label = $name;
            break;

        case 'societe':
            $name = $str('name', 150);
            qc_required(['Nom / raison sociale' => $name]);
            core_q(
                "INSERT INTO clients (name, phone, email, address, ice, type, credit_limit, is_active)
                 VALUES (?, ?, ?, ?, ?, 'SOCIETE', ?, 1)",
                [$name, $str('phone', 30), $str('email', 160), $str('address', 255), $str('ice', 30), $num('credit_limit')]
            );
            $id = core_insert_id();
            $label = $name;
            break;

        case 'product':
            $designation = $str('designation', 180);
            qc_required(['Désignation' => $designation]);
            $sku = $str('sku', 40);
            $sku = $sku !== '' ? $sku : null;
            if ($sku !== null) {
                $exists = core_scalar('SELECT id FROM products WHERE sku = ?', [$sku]);
                if ($exists !== false) {
                    qc_fail('La référence « ' . $sku . ' » existe déjà.');
                }
            }
            $brandId = $intOrNull('brand_id') ?? qc_default_id('brand');
            $categoryId = $intOrNull('category_id') ?? qc_default_id('category');
            $unitId = $intOrNull('unit_id') ?? qc_default_id('unit');
            core_q(
                'INSERT INTO products (sku, designation, brand_id, category_id, unit_id, purchase_cost, sale_price, min_stock, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)',
                [$sku, $designation, $brandId, $categoryId, $unitId, $num('purchase_cost'), $num('sale_price'), $num('min_stock')]
            );
            $id = core_insert_id();
            $brandName = (string) (core_scalar('SELECT name FROM brands WHERE id = ?', [$brandId]) ?: '');
            $label = $designation . ($brandName !== '' && $brandName !== 'Sans marque' ? ' — ' . $brandName : '');
            break;

        case 'carnet':
            $libelle = $str('libelle', 100);
            $type = strtoupper($str('type', 20)) === 'EFFET' ? 'EFFET' : 'CHEQUE';
            qc_required(['Libellé' => $libelle]);
            core_q(
                'INSERT INTO cheques_carnets (banque, libelle, type, personne_id) VALUES (?, ?, ?, ?)',
                [$str('banque', 100), $libelle, $type, $intOrNull('personne_id')]
            );
            $id = core_insert_id();
            $label = $libelle;
            break;

        case 'personne':
            $nom = $str('nom', 150);
            qc_required(['Nom complet' => $nom]);
            $roles = ['EMPLOYE', 'GERANT', 'PROPRIETAIRE_SOCIETE'];
            $role = strtoupper($str('role', 30));
            if (!in_array($role, $roles, true)) {
                $role = 'EMPLOYE';
            }
            core_q(
                'INSERT INTO cheques_personnes (nom, role, telephone, email, adresse) VALUES (?, ?, ?, ?, ?)',
                [$nom, $role, $str('telephone', 30), $str('email', 150), $str('adresse', 255)]
            );
            $id = core_insert_id();
            $label = $nom;
            break;

        default:
            qc_fail('Type d\'élément inconnu.', 400);
    }
} catch (PDOException $e) {
    if ((string) $e->getCode() === '23000') {
        qc_fail('Cet élément existe déjà (doublon).');
    }
    qc_fail('Erreur base de données : ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    qc_fail('Erreur : ' . $e->getMessage(), 500);
}

core_audit('CREATION_RAPIDE', strtoupper($entity), $id, 'Créé via le bouton +');
sh_json(['ok' => true, 'item' => ['id' => (int) $id, 'label' => (string) $label]]);
