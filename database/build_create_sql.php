<?php
declare(strict_types=1);

/**
 * Génère database/create_database.sql.
 *
 * Le fichier produit est un script SQL autonome : création de la base, des
 * tables, des vues et des données de base. Il s'importe tel quel dans
 * phpMyAdmin, cPanel ou en ligne de commande (mysql < fichier.sql).
 *
 *   php database/build_create_sql.php
 *
 * La structure vient de database/schema.sql (source de vérité, utilisée par
 * install.php). Les données de base sont dans le bloc $seed ci-dessous.
 * Relancer ce script après toute modification du schéma.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Script réservé à la ligne de commande.\n");
}

$root = dirname(__DIR__);
$schemaFile = $root . '/database/schema.sql';
$outFile = $root . '/database/create_database.sql';

$schema = (string) file_get_contents($schemaFile);
if (trim($schema) === '') {
    fwrite(STDERR, "schema.sql est vide.\n");
    exit(1);
}

// L'en-tête de schema.sql est remplacé par celui du fichier généré.
$schema = preg_replace('/\A\s*--.*?\n(?=SET NAMES)/s', '', $schema) ?? $schema;

$header = <<<'SQL'
-- =========================================================================
-- Gestion Our Stock — création complète de la base
-- -------------------------------------------------------------------------
-- Fichier autonome : base + tables + index + vues + données de base.
--
-- Import :
--   phpMyAdmin  : onglet « Importer » → choisir ce fichier → Exécuter
--   Terminal    : mysql -u root -p < database/create_database.sql
--
-- Compte initial : admin / Admin@2026  (à changer dès la première connexion)
--
-- Généré depuis database/schema.sql par database/build_create_sql.php.
-- Ne pas modifier ce fichier à la main : éditer schema.sql puis régénérer.
-- =========================================================================

SQL;

$seed = <<<'SQL'

-- -------------------------------------------------------------------------
-- Données de base
-- Tout est idempotent : réimporter ce fichier ne crée aucun doublon et
-- n'écrase pas les valeurs déjà personnalisées (INSERT IGNORE).
-- -------------------------------------------------------------------------

INSERT IGNORE INTO `roles` (`code`, `label`) VALUES
  ('admin',       'Administrateur'),
  ('responsable', 'Responsable'),
  ('magasinier',  'Magasinier'),
  ('vendeur',     'Vendeur'),
  ('auditeur',    'Auditeur');

-- Compte administrateur : admin / Admin@2026 (bcrypt).
INSERT IGNORE INTO `users` (`name`, `login`, `email`, `password_hash`, `role_id`, `modules`, `active`)
SELECT 'Administrateur', 'admin', NULL,
       '$2y$12$0MjHJVHENxaI2qeyMO7GYujulGadfrtOrDsZC/qLPUYfKe/JL3Tc2',
       `roles`.`id`, 'stock,credits,cheques', 1
FROM `roles`
WHERE `roles`.`code` = 'admin';

INSERT IGNORE INTO `settings` (`k`, `v`, `updated_at`) VALUES
  ('company_name',        'Gestion Our Stock', NOW()),
  ('currency',            'MAD',               NOW()),
  ('invoice_prefix',      'FAC',               NOW()),
  ('credit_alert_days',   '30',                NOW()),
  ('low_stock_alerts',    '1',                 NOW()),
  ('cheques_company_name','Gestion Our Stock', NOW());

-- Référentiels minimaux : la création rapide (« + ») fonctionne tout de suite.
INSERT IGNORE INTO `brands` (`name`, `active`) VALUES ('Sans marque', 1);
INSERT IGNORE INTO `categories` (`name`, `active`) VALUES ('Général', 1);
INSERT IGNORE INTO `units` (`code`, `label`) VALUES ('U', 'Unité');
INSERT IGNORE INTO `locations` (`code`, `name`, `type`, `active`)
VALUES ('DEP01', 'Dépôt principal', 'depot', 1);

-- -------------------------------------------------------------------------
-- Fin du script
-- -------------------------------------------------------------------------
SQL;

$sql = $header . $schema . $seed;

if (file_put_contents($outFile, $sql) === false) {
    fwrite(STDERR, "Impossible d'écrire {$outFile}\n");
    exit(1);
}

printf(
    "OK : %s (%d octets, %d instructions SQL)\n",
    'database/create_database.sql',
    strlen($sql),
    substr_count($sql, ';')
);
