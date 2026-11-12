<?php
declare(strict_types=1);

/**
 * Mise à niveau de la base existante (sans perte de données).
 *
 *   php database/upgrade.php
 *
 * Ajoute les tables / colonnes introduites après l'installation initiale :
 *   - credit_bons        (bons remis par les sociétés partenaires)
 *   - cheques_documents.image_path / image_back_path (photos recto / verso)
 *
 * Idempotent : peut être relancé sans risque.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Mise à niveau accessible uniquement en ligne de commande.');
}

define('GOS_ROOT', dirname(__DIR__));
$GLOBALS['GOS_CONFIG'] = require GOS_ROOT . '/shared/config.php';
define('GOS_BASE', (string) ($GLOBALS['GOS_CONFIG']['app']['base'] ?? ''));
define('GOS_APP_NAME', (string) ($GLOBALS['GOS_CONFIG']['app']['name'] ?? 'Gestion Our Stock'));
define('GOS_VERSION', (string) ($GLOBALS['GOS_CONFIG']['app']['version'] ?? '1.0'));

require GOS_ROOT . '/shared/helpers.php';
require GOS_ROOT . '/shared/db.php';

$pdo = core_db();

echo "=== Gestion Our Stock — mise à niveau ===\n";

/* -------------------------------------------------------------------------
| 1. Table des bons société
|-------------------------------------------------------------------------*/

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS `credit_bons` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `client_id` BIGINT UNSIGNED NOT NULL,
        `reference` VARCHAR(80) DEFAULT NULL,
        `bon_date` DATE NOT NULL,
        `amount` DECIMAL(14,2) NOT NULL,
        `status` ENUM('attente','encaisse','paye') NOT NULL DEFAULT 'attente',
        `received_at` DATE DEFAULT NULL,
        `note` VARCHAR(500) DEFAULT NULL,
        `image_path` VARCHAR(255) DEFAULT NULL,
        `created_by` INT UNSIGNED DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `credit_bons_client_index` (`client_id`),
        KEY `credit_bons_status_index` (`status`),
        KEY `credit_bons_date_index` (`bon_date`),
        CONSTRAINT `credit_bons_client_fk` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
        CONSTRAINT `credit_bons_user_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
echo "[ OK ] Table credit_bons\n";

/* -------------------------------------------------------------------------
| 1 bis. Table de correspondance des imports
|-------------------------------------------------------------------------*/

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS `import_map` (
        `source` VARCHAR(60) NOT NULL,
        `entity` VARCHAR(40) NOT NULL,
        `legacy_id` VARCHAR(80) NOT NULL,
        `target_id` BIGINT UNSIGNED NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`source`, `entity`, `legacy_id`),
        KEY `import_map_target_index` (`target_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
echo "[ OK ] Table import_map\n";

/* -------------------------------------------------------------------------
| 2. Images des chèques / effets
|-------------------------------------------------------------------------*/

$columns = [
    'image_path'      => "ALTER TABLE `cheques_documents` ADD COLUMN `image_path` VARCHAR(255) DEFAULT NULL AFTER `remarque`",
    'image_back_path' => "ALTER TABLE `cheques_documents` ADD COLUMN `image_back_path` VARCHAR(255) DEFAULT NULL AFTER `image_path`",
];

foreach ($columns as $column => $sql) {
    $exists = $pdo->query("SHOW COLUMNS FROM `cheques_documents` LIKE " . $pdo->quote($column))->fetch();
    if ($exists === false) {
        $pdo->exec($sql);
        echo "[ OK ] Colonne cheques_documents.{$column} ajoutée\n";
    } else {
        echo "[ -- ] Colonne cheques_documents.{$column} déjà présente\n";
    }
}

// La vue SELECT * fige ses colonnes : on la recrée pour exposer les images.
$pdo->exec('CREATE OR REPLACE VIEW `document_paiement` AS SELECT * FROM `cheques_documents`');
echo "[ OK ] Vue document_paiement actualisée\n";

/* -------------------------------------------------------------------------
| 3. Dossier des téléversements
|-------------------------------------------------------------------------*/

$uploads = GOS_ROOT . '/storage/uploads';
foreach ([$uploads, $uploads . '/bons', $uploads . '/cheques'] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}
$deny = $uploads . '/.htaccess';
if (!is_file($deny)) {
    @file_put_contents(
        $deny,
        "# Fichiers téléversés : accès direct interdit.\n"
        . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
        . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n"
    );
}
echo "[ OK ] Dossier storage/uploads prêt\n";

/* -------------------------------------------------------------------------
| 4. Informations société : source unique `settings`
|
| La table historique `parametres_societe` est conservée (cible d'import),
| mais le socle lit et écrit désormais `settings`. On reprend une seule fois
| ses valeurs si `settings` ne contient pas encore le nom de la société.
|-------------------------------------------------------------------------*/

$companyInSettings = '';
try {
    $companyInSettings = (string) $pdo->query("SELECT v FROM settings WHERE k = 'company_name' LIMIT 1")->fetchColumn();
} catch (Throwable) {
    $companyInSettings = 'skip';
}

$legacyTableExists = false;
try {
    $legacyTableExists = (bool) $pdo->query("SHOW TABLES LIKE 'parametres_societe'")->fetchColumn();
} catch (Throwable) {
    $legacyTableExists = false;
}

if ($companyInSettings === '' && $legacyTableExists) {
    $legacy = $pdo->query('SELECT * FROM parametres_societe WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    if (is_array($legacy)) {
        $map = [
            'company_name'    => $legacy['nom'] ?? '',
            'company_address' => $legacy['adresse'] ?? '',
            'company_phone'   => $legacy['telephone'] ?? '',
            'company_email'   => $legacy['email'] ?? '',
        ];
        $set = $pdo->prepare('INSERT INTO settings (k, v, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = NOW()');
        foreach ($map as $key => $value) {
            if ((string) $value !== '') {
                $set->execute([$key, (string) $value]);
            }
        }
        echo "[ OK ] Informations société reprises de parametres_societe vers settings\n";
    }
} else {
    echo "[ -- ] Informations société déjà dans settings (parametres_societe conservée pour l'import)\n";
}

echo "=== Mise à niveau terminée ===\n";
