<?php
declare(strict_types=1);

/**
 * Installation vide : rôles, compte administrateur et paramètres essentiels.
 * Aucun site, produit, marque, catégorie, unité, fournisseur ni mouvement.
 */

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
        $pdo->prepare('INSERT INTO roles (code, label) VALUES (:code, :label) ON DUPLICATE KEY UPDATE label = VALUES(label)')
            ->execute(['code' => $code, 'label' => $label]);
    }

    $adminRole = (int) $pdo->query("SELECT id FROM roles WHERE code = 'admin' LIMIT 1")->fetchColumn();
    if ($adminRole <= 0) {
        throw new RuntimeException('Le rôle administrateur n’a pas pu être créé.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users (name, login, email, password_hash, role_id, location_id, active)
         VALUES (:name, :login, :email, :hash, :role_id, NULL, 1)
         ON DUPLICATE KEY UPDATE name = VALUES(name), email = VALUES(email),
             role_id = VALUES(role_id), location_id = NULL, active = 1,
             password_hash = VALUES(password_hash)'
    );
    $stmt->execute([
        'name'    => 'Administrateur',
        'login'   => 'admin',
        'email'   => 'admin@suivi-stock.local',
        'hash'    => password_hash('Admin@2026', PASSWORD_DEFAULT),
        'role_id' => $adminRole,
    ]);

    $settings = [
        'company_name'         => 'Suivi Stock',
        'currency'             => 'MAD',
        'cump_method'          => 'global',
        'allow_negative_stock' => '0',
        'installed_at'         => date('Y-m-d H:i:s'),
        'install_mode'         => 'empty',
    ];
    foreach ($settings as $key => $value) {
        $pdo->prepare('INSERT INTO settings (k, v) VALUES (:k, :v) ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = NOW()')
            ->execute(['k' => $key, 'v' => $value]);
    }

    $pdo->commit();
    echo "Installation vide prête (rôles, administrateur et paramètres)." . PHP_EOL;
    echo "Connexion : admin / Admin@2026" . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
