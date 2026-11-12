<?php
declare(strict_types=1);

use App\Core\Database;

require_once __DIR__ . '/bootstrap.php';

$pdo = Database::pdo();
$pdo->beginTransaction();

try {
    $roles = [
        'admin'       => 'Administrateur',
        'responsable' => 'Responsable stock',
        'magasinier'  => 'Magasinier',
        'vendeur'     => 'Vendeur',
        'auditeur'    => 'Auditeur',
    ];
    foreach ($roles as $code => $label) {
        $stmt = $pdo->prepare('INSERT INTO roles (code, label) VALUES (:code, :label) ON DUPLICATE KEY UPDATE label = VALUES(label)');
        $stmt->execute(['code' => $code, 'label' => $label]);
    }

    $locations = [
        ['DEP-01', 'Dépôt principal Marrakech', 'depot', 'Zone industrielle Sidi Ghanem', '0524 00 00 01'],
        ['POS-01', 'Point de vente Guéliz', 'point_vente', 'Avenue Mohammed V, Guéliz', '0524 00 00 02'],
    ];
    foreach ($locations as [$code, $name, $type, $address, $phone]) {
        $stmt = $pdo->prepare(
            'INSERT INTO locations (code, name, type, address, phone)
             VALUES (:code, :name, :type, :address, :phone)
             ON DUPLICATE KEY UPDATE name = VALUES(name), type = VALUES(type), address = VALUES(address), phone = VALUES(phone)'
        );
        $stmt->execute(compact('code', 'name', 'type', 'address', 'phone'));
    }

    foreach (['Générique', 'Médina', 'Atlas'] as $brand) {
        $pdo->prepare('INSERT INTO brands (name) VALUES (:name) ON DUPLICATE KEY UPDATE name = VALUES(name)')
            ->execute(['name' => $brand]);
    }

    foreach (['Boissons', 'Épicerie', 'Hygiène', 'Divers'] as $category) {
        $pdo->prepare('INSERT INTO categories (name) VALUES (:name) ON DUPLICATE KEY UPDATE name = VALUES(name)')
            ->execute(['name' => $category]);
    }

    $units = ['PCS' => 'Pièce', 'KG' => 'Kilogramme', 'L' => 'Litre', 'CARTON' => 'Carton'];
    foreach ($units as $code => $label) {
        $pdo->prepare('INSERT INTO units (code, label) VALUES (:code, :label) ON DUPLICATE KEY UPDATE label = VALUES(label)')
            ->execute(['code' => $code, 'label' => $label]);
    }

    foreach ([['Atlas Distribution', '001234567000001'], ['Marrakech Grossiste', '001234567000002']] as [$name, $ice]) {
        $stmt = $pdo->prepare('INSERT INTO suppliers (name, ice) VALUES (:name, :ice) ON DUPLICATE KEY UPDATE ice = VALUES(ice)');
        $stmt->execute(['name' => $name, 'ice' => $ice]);
    }

    $ids = static function (PDO $pdo, string $table, string $column, string $value): int {
        $allowed = ['roles' => 'code', 'locations' => 'code', 'brands' => 'name', 'categories' => 'name', 'units' => 'code', 'products' => 'sku'];
        if (($allowed[$table] ?? null) !== $column) {
            throw new RuntimeException('Table de référence non autorisée.');
        }
        $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE {$column} = :value LIMIT 1");
        $stmt->execute(['value' => $value]);
        return (int) $stmt->fetchColumn();
    };

    $adminRole = $ids($pdo, 'roles', 'code', 'admin');
    $depot = $ids($pdo, 'locations', 'code', 'DEP-01');
    $pos = $ids($pdo, 'locations', 'code', 'POS-01');

    $adminStmt = $pdo->prepare(
        'INSERT INTO users (name, login, email, password_hash, role_id, location_id, active)
         VALUES (:name, :login, :email, :hash, :role_id, :location_id, 1)
         ON DUPLICATE KEY UPDATE name = VALUES(name), role_id = VALUES(role_id), location_id = VALUES(location_id), active = 1'
    );
    $adminStmt->execute([
        'name'        => 'Administrateur',
        'login'       => 'admin',
        'email'       => 'admin@suivi-stock.local',
        'hash'        => password_hash('Admin@2026', PASSWORD_DEFAULT),
        'role_id'     => $adminRole,
        'location_id' => $depot,
    ]);

    $products = [
        ['EAU-50', 'Eau minérale 50cl', 'Générique', 'Boissons', 'PCS', 1.80, 3.00, 24, ['DEP-01' => 120, 'POS-01' => 48]],
        ['CAF-250', 'Café moulu 250g', 'Médina', 'Épicerie', 'PCS', 22.50, 35.00, 12, ['DEP-01' => 40, 'POS-01' => 15]],
        ['THE-200', 'Thé vert 200g', 'Atlas', 'Épicerie', 'PCS', 18.00, 28.00, 10, ['DEP-01' => 25, 'POS-01' => 8]],
        ['HUI-1L', 'Huile d\'olive 1L', 'Atlas', 'Épicerie', 'L', 45.00, 70.00, 8, ['DEP-01' => 18, 'POS-01' => 5]],
        ['SAV-100', 'Savon argan 100g', 'Médina', 'Hygiène', 'PCS', 8.00, 15.00, 20, ['DEP-01' => 60, 'POS-01' => 24]],
        ['JUS-1L', 'Jus d\'orange 1L', 'Générique', 'Boissons', 'L', 7.50, 12.00, 15, ['DEP-01' => 30, 'POS-01' => 0]],
        ['CAR-01', 'Carton vide', 'Générique', 'Divers', 'CARTON', 3.00, 5.00, 10, ['DEP-01' => 50, 'POS-01' => 0]],
        ['DAT-500', 'Dattes Majhoul 500g', 'Atlas', 'Épicerie', 'PCS', 30.00, 55.00, 6, ['DEP-01' => 4, 'POS-01' => 2]],
        ['GOB-20', 'Gobelets 20cl (x100)', 'Générique', 'Divers', 'CARTON', 12.00, 20.00, 50, ['DEP-01' => 20, 'POS-01' => 10]],
    ];

    foreach ($products as [$sku, $designation, $brand, $category, $unit, $cost, $sale, $min, $stock]) {
        $stmt = $pdo->prepare(
            'INSERT INTO products (sku, designation, brand_id, category_id, unit_id, purchase_cost, sale_price, min_stock, active)
             VALUES (:sku, :designation, :brand_id, :category_id, :unit_id, :cost, :sale, :min_stock, 1)
             ON DUPLICATE KEY UPDATE designation = VALUES(designation), brand_id = VALUES(brand_id),
                 category_id = VALUES(category_id), unit_id = VALUES(unit_id),
                 purchase_cost = VALUES(purchase_cost), sale_price = VALUES(sale_price),
                 min_stock = VALUES(min_stock), active = 1'
        );
        $stmt->execute([
            'sku'         => $sku,
            'designation' => $designation,
            'brand_id'    => $ids($pdo, 'brands', 'name', $brand),
            'category_id' => $ids($pdo, 'categories', 'name', $category),
            'unit_id'     => $ids($pdo, 'units', 'code', $unit),
            'cost'        => $cost,
            'sale'        => $sale,
            'min_stock'   => $min,
        ]);

        $productId = $ids($pdo, 'products', 'sku', $sku);
        foreach ($stock as $locationCode => $quantity) {
            $locationId = $locationCode === 'DEP-01' ? $depot : $pos;
            $level = $pdo->prepare(
                'INSERT INTO stock_levels (product_id, location_id, qty, updated_at)
                 VALUES (:product_id, :location_id, :qty, NOW())
                 ON DUPLICATE KEY UPDATE qty = VALUES(qty), updated_at = NOW()'
            );
            $level->execute(['product_id' => $productId, 'location_id' => $locationId, 'qty' => $quantity]);
        }
    }

    $settings = [
        'company_name'         => 'Suivi Stock',
        'currency'             => 'MAD',
        'cump_method'          => 'global',
        'allow_negative_stock' => '0',
        'installed_at'         => date('Y-m-d H:i:s'),
    ];
    foreach ($settings as $key => $value) {
        $pdo->prepare('INSERT INTO settings (k, v) VALUES (:k, :v) ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = NOW()')
            ->execute(['k' => $key, 'v' => $value]);
    }

    $pdo->commit();
    echo "Données de démonstration installées." . PHP_EOL;
    echo "Connexion : admin / Admin@2026" . PHP_EOL;
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
