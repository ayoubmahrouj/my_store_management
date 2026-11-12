-- =========================================================================
-- Gestion Our Stock — schéma unique
-- Base : gestion_our_stock
-- Tables partagées : roles, locations, users, clients, brands, categories,
--                    units, suppliers, products, settings, audit_logs…
-- Modules : stock_* / inventory_* · sales, credits… · cheques_*
-- =========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `gestion_our_stock`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gestion_our_stock`;

-- -------------------------------------------------------------------------
-- Socle partagé
-- -------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(30) NOT NULL,
  `label` VARCHAR(60) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `locations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(20) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `type` ENUM('depot','point_vente') NOT NULL DEFAULT 'depot',
  `address` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `locations_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `login` VARCHAR(60) NOT NULL,
  `email` VARCHAR(160) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role_id` INT UNSIGNED NOT NULL,
  `location_id` INT UNSIGNED DEFAULT NULL,
  `modules` VARCHAR(120) NOT NULL DEFAULT 'stock,credits,cheques',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_login_unique` (`login`),
  KEY `users_role_index` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `fk_users_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de correspondance des imports (id legacy → id cible).
-- Permet de relancer un import sans créer de doublons.
CREATE TABLE IF NOT EXISTS `import_map` (
  `source` VARCHAR(60) NOT NULL,
  `entity` VARCHAR(40) NOT NULL,
  `legacy_id` VARCHAR(80) NOT NULL,
  `target_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`source`, `entity`, `legacy_id`),
  KEY `import_map_target_index` (`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clients partagés (crédits + chèques/bénéficiaires)
CREATE TABLE IF NOT EXISTS `clients` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `email` VARCHAR(160) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `ice` VARCHAR(30) DEFAULT NULL,
  `type` ENUM('PERSONNE','SOCIETE') NOT NULL DEFAULT 'PERSONNE',
  `credit_limit` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_passenger` TINYINT(1) NOT NULL DEFAULT 0,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clients_name_index` (`name`),
  KEY `clients_phone_index` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `brands` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `brands_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `units` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(20) NOT NULL,
  `label` VARCHAR(60) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `units_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(160) NOT NULL,
  `ice` VARCHAR(30) DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `email` VARCHAR(160) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `suppliers_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku` VARCHAR(40) DEFAULT NULL,
  `barcode` VARCHAR(60) DEFAULT NULL,
  `designation` VARCHAR(180) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `brand_id` INT UNSIGNED DEFAULT NULL,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `unit_id` INT UNSIGNED DEFAULT NULL,
  `purchase_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `sale_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `avg_sale_price` DECIMAL(12,2) DEFAULT NULL,
  `min_stock` DECIMAL(14,3) NOT NULL DEFAULT 0.000,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_sku_unique` (`sku`),
  UNIQUE KEY `products_barcode_unique` (`barcode`),
  KEY `products_designation_index` (`designation`),
  KEY `products_brand_index` (`brand_id`),
  CONSTRAINT `fk_products_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_products_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Module Stock
-- -------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `stock_documents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` ENUM('entree','sortie','transfert','inventaire','ouverture') NOT NULL,
  `motif` VARCHAR(40) DEFAULT NULL,
  `numero` VARCHAR(40) NOT NULL,
  `doc_date` DATE NOT NULL,
  `status` ENUM('brouillon','valide','expedie','recu','annule') NOT NULL DEFAULT 'brouillon',
  `location_id` INT UNSIGNED NOT NULL,
  `dest_location_id` INT UNSIGNED DEFAULT NULL,
  `supplier_id` INT UNSIGNED DEFAULT NULL,
  `reference` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `total_cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total_sale` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `created_by` INT UNSIGNED NOT NULL,
  `validated_by` INT UNSIGNED DEFAULT NULL,
  `validated_at` DATETIME DEFAULT NULL,
  `shipped_by` INT UNSIGNED DEFAULT NULL,
  `shipped_at` DATETIME DEFAULT NULL,
  `received_by` INT UNSIGNED DEFAULT NULL,
  `received_at` DATETIME DEFAULT NULL,
  `cancel_reason` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stock_documents_numero_unique` (`numero`),
  KEY `idx_documents_type_status` (`type`, `status`, `doc_date`),
  CONSTRAINT `fk_documents_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`),
  CONSTRAINT `fk_documents_dest` FOREIGN KEY (`dest_location_id`) REFERENCES `locations` (`id`),
  CONSTRAINT `fk_documents_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `fk_documents_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_document_lines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `qty` DECIMAL(14,3) NOT NULL,
  `unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `notes` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_lines_document_index` (`document_id`),
  CONSTRAINT `fk_lines_document` FOREIGN KEY (`document_id`) REFERENCES `stock_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lines_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` BIGINT UNSIGNED DEFAULT NULL,
  `document_line_id` BIGINT UNSIGNED DEFAULT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `location_id` INT UNSIGNED NOT NULL,
  `direction` ENUM('in','out') NOT NULL,
  `qty` DECIMAL(14,3) NOT NULL,
  `unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `movement_date` DATETIME NOT NULL,
  `inventory_session_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_movements_stock` (`product_id`, `location_id`, `movement_date`),
  KEY `idx_movements_date` (`movement_date`),
  KEY `idx_movements_inventory` (`inventory_session_id`),
  UNIQUE KEY `uq_movements_line` (`document_line_id`),
  CONSTRAINT `fk_movements_document` FOREIGN KEY (`document_id`) REFERENCES `stock_documents` (`id`),
  CONSTRAINT `fk_movements_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_movements_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`),
  CONSTRAINT `fk_movements_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_levels` (
  `product_id` INT UNSIGNED NOT NULL,
  `location_id` INT UNSIGNED NOT NULL,
  `qty` DECIMAL(14,3) NOT NULL DEFAULT 0.000,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`product_id`, `location_id`),
  CONSTRAINT `fk_levels_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_levels_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `numero` VARCHAR(40) NOT NULL,
  `location_id` INT UNSIGNED NOT NULL,
  `status` ENUM('ouvert','comptage','valide','annule') NOT NULL DEFAULT 'ouvert',
  `started_by` INT UNSIGNED NOT NULL,
  `started_at` DATETIME NOT NULL,
  `validated_by` INT UNSIGNED DEFAULT NULL,
  `validated_at` DATETIME DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_numero_unique` (`numero`),
  CONSTRAINT `fk_inventory_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`),
  CONSTRAINT `fk_inventory_user` FOREIGN KEY (`started_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_lines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` BIGINT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `qty_theoretical` DECIMAL(14,3) NOT NULL DEFAULT 0.000,
  `qty_counted` DECIMAL(14,3) DEFAULT NULL,
  `gap` DECIMAL(14,3) DEFAULT NULL,
  `unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_product` (`session_id`, `product_id`),
  CONSTRAINT `fk_inventory_lines_session` FOREIGN KEY (`session_id`) REFERENCES `inventory_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inventory_lines_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Module Crédits & Ventes
-- -------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `sales` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` BIGINT UNSIGNED DEFAULT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `date` DATE NOT NULL,
  `subtotal` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `paid` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(20) NOT NULL DEFAULT 'cash',
  `status` VARCHAR(20) NOT NULL DEFAULT 'paid',
  `note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sales_date_index` (`date`),
  KEY `sales_client_index` (`client_id`),
  CONSTRAINT `sales_client_fk` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sale_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_id` BIGINT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED DEFAULT NULL,
  `brand` VARCHAR(120) NOT NULL DEFAULT '',
  `product_name` VARCHAR(200) NOT NULL,
  `qty` DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  `unit_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `sale_items_sale_index` (`sale_id`),
  CONSTRAINT `sale_items_sale_fk` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sale_items_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `credits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_id` BIGINT UNSIGNED NOT NULL,
  `client_id` BIGINT UNSIGNED NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `paid` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `remaining` DECIMAL(14,2) NOT NULL,
  `due_date` DATE DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'open',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `credits_sale_unique` (`sale_id`),
  KEY `credits_client_index` (`client_id`),
  KEY `credits_status_index` (`status`),
  CONSTRAINT `credits_sale_fk` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `credits_client_fk` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` BIGINT UNSIGNED DEFAULT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `date` DATE NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `method` VARCHAR(20) NOT NULL DEFAULT 'cash',
  `note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payments_date_index` (`date`),
  KEY `payments_client_index` (`client_id`),
  CONSTRAINT `payments_client_fk` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_allocations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id` BIGINT UNSIGNED NOT NULL,
  `credit_id` BIGINT UNSIGNED NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `allocations_payment_index` (`payment_id`),
  KEY `allocations_credit_index` (`credit_id`),
  CONSTRAINT `allocations_payment_fk` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `allocations_credit_fk` FOREIGN KEY (`credit_id`) REFERENCES `credits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bons remis par les sociétés partenaires (crédits), encaissés puis payés.
-- Chaque bon peut être photographié / scanné et conservé avec sa fiche.
CREATE TABLE IF NOT EXISTS `credit_bons` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Module Chèques & Effets
-- -------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `cheques_personnes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(150) NOT NULL,
  `role` ENUM('EMPLOYE','GERANT','PROPRIETAIRE_SOCIETE') NOT NULL DEFAULT 'EMPLOYE',
  `telephone` VARCHAR(30) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `adresse` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cheques_personnes_nom_index` (`nom`),
  KEY `cheques_personnes_role_index` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cheques_carnets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `banque` VARCHAR(100) DEFAULT NULL,
  `libelle` VARCHAR(100) NOT NULL,
  `type` ENUM('CHEQUE','EFFET') NOT NULL DEFAULT 'CHEQUE',
  `code` INT DEFAULT NULL,
  `personne_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cheques_carnets_libelle_index` (`libelle`),
  KEY `cheques_carnets_type_index` (`type`),
  CONSTRAINT `fk_cheques_carnet_personne` FOREIGN KEY (`personne_id`) REFERENCES `cheques_personnes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cheques_documents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `banque` VARCHAR(100) DEFAULT NULL,
  `date_emission` DATE NOT NULL,
  `date_echeance` DATE NOT NULL,
  `montant` DECIMAL(14,2) NOT NULL,
  `numero` VARCHAR(50) NOT NULL,
  `remarque` VARCHAR(500) DEFAULT NULL,
  `image_path` VARCHAR(255) DEFAULT NULL,
  `image_back_path` VARCHAR(255) DEFAULT NULL,
  `type` ENUM('CHEQUE','EFFET') NOT NULL DEFAULT 'CHEQUE',
  `beneficiaire_id` BIGINT UNSIGNED NOT NULL,
  `personne_id` BIGINT UNSIGNED DEFAULT NULL,
  `carnet` VARCHAR(100) DEFAULT NULL,
  `carnet_id` BIGINT UNSIGNED DEFAULT NULL,
  `date_reglement` DATE DEFAULT NULL,
  `regle` TINYINT(1) NOT NULL DEFAULT 0,
  `rejete` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cheques_carnet_numero` (`carnet_id`, `numero`),
  KEY `idx_cheques_numero` (`numero`),
  KEY `idx_cheques_type` (`type`),
  KEY `idx_cheques_echeance` (`date_echeance`),
  KEY `idx_cheques_beneficiaire` (`beneficiaire_id`),
  KEY `idx_cheques_personne` (`personne_id`),
  KEY `idx_cheques_regle` (`regle`),
  CONSTRAINT `fk_cheques_beneficiaire` FOREIGN KEY (`beneficiaire_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `fk_cheques_personne` FOREIGN KEY (`personne_id`) REFERENCES `cheques_personnes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cheques_carnet` FOREIGN KEY (`carnet_id`) REFERENCES `cheques_carnets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cheques_alert_config` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `frequence` ENUM('HEBDOMADAIRE','QUOTIDIEN') NOT NULL DEFAULT 'HEBDOMADAIRE',
  `canal` ENUM('EMAIL','LES_DEUX','SMS') NOT NULL DEFAULT 'EMAIL',
  `jours_avant` INT NOT NULL DEFAULT 7,
  `heure_envoi` TIME NOT NULL DEFAULT '09:00:00',
  `jour_semaine` INT DEFAULT NULL,
  `emails` VARCHAR(1000) DEFAULT NULL,
  `telephones` VARCHAR(1000) DEFAULT NULL,
  `derniere_execution` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cheques_alert_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `envoye_at` DATETIME NOT NULL,
  `canal` ENUM('EMAIL','LES_DEUX','SMS') NOT NULL DEFAULT 'EMAIL',
  `destinataire` VARCHAR(150) NOT NULL,
  `sujet` VARCHAR(255) DEFAULT NULL,
  `message` VARCHAR(2000) DEFAULT NULL,
  `document_id` BIGINT UNSIGNED DEFAULT NULL,
  `document_numero` VARCHAR(50) DEFAULT NULL,
  `succes` TINYINT(1) NOT NULL DEFAULT 0,
  `simule` TINYINT(1) NOT NULL DEFAULT 0,
  `erreur` VARCHAR(1000) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cheques_alert_date` (`envoye_at`),
  KEY `idx_cheques_alert_document` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Paramètres, audit, sécurité
-- -------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(80) NOT NULL,
  `v` TEXT DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(60) NOT NULL,
  `entity_type` VARCHAR(60) DEFAULT NULL,
  `entity_id` BIGINT UNSIGNED DEFAULT NULL,
  `old_values` JSON DEFAULT NULL,
  `new_values` JSON DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`, `created_at`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_date` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `login` VARCHAR(100) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_login` (`login`, `created_at`),
  KEY `idx_attempts_ip` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_sequences` (
  `type` VARCHAR(20) NOT NULL,
  `year` SMALLINT UNSIGNED NOT NULL,
  `last_number` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`type`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Informations de la société affichées dans les documents (module Chèques).
CREATE TABLE IF NOT EXISTS `parametres_societe` (
  `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `nom` VARCHAR(150) NOT NULL DEFAULT '',
  `adresse` VARCHAR(255) DEFAULT NULL,
  `telephone` VARCHAR(30) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Vues de compatibilité
-- -------------------------------------------------------------------------
-- Le module Crédits historique attend une table « products » avec les
-- colonnes brand / name / price / is_active. Les produits vivent désormais
-- dans la table partagée `products`, exposée ici sous une vue dédiée.
CREATE OR REPLACE VIEW `credits_products` AS
SELECT p.`id`,
       COALESCE(b.`name`, '') AS `brand`,
       p.`designation`        AS `name`,
       p.`description`,
       p.`sale_price`         AS `price`,
       p.`active`             AS `is_active`,
       p.`created_at`,
       p.`brand_id`,
       p.`category_id`,
       p.`unit_id`
FROM `products` p
LEFT JOIN `brands` b ON b.`id` = p.`brand_id`;

-- Le module Chèques historique utilise encore les noms de tables d'origine.
-- Ces vues restent modifiables (SELECT simple sur une seule table) afin que
-- les INSERT / UPDATE / DELETE continuent de fonctionner sans réécriture.
CREATE OR REPLACE VIEW `document_paiement` AS SELECT * FROM `cheques_documents`;
CREATE OR REPLACE VIEW `carnet`            AS SELECT * FROM `cheques_carnets`;
CREATE OR REPLACE VIEW `personne`          AS SELECT * FROM `cheques_personnes`;
CREATE OR REPLACE VIEW `alerte_config`     AS SELECT * FROM `cheques_alert_config`;
CREATE OR REPLACE VIEW `alerte_log`        AS SELECT * FROM `cheques_alert_log`;

-- Les bénéficiaires sont désormais les clients partagés (crédits + chèques).
CREATE OR REPLACE VIEW `beneficiaire` AS
SELECT `id`,
       `name`      AS `nom`,
       `phone`     AS `telephone`,
       `address`   AS `adresse`,
       `email`,
       `ice`,
       `type`,
       `created_at`,
       `updated_at`,
       `is_active`
FROM `clients`;

-- Journal d'audit historique en lecture seule, alimenté par `audit_logs`.
CREATE OR REPLACE VIEW `audit_log` AS
SELECT a.`id`,
       a.`created_at` AS `date_action`,
       u.`login`      AS `utilisateur`,
       a.`action`,
       a.`entity_type` AS `entite`,
       a.`entity_id`   AS `entite_id`,
       COALESCE(
           JSON_UNQUOTE(JSON_EXTRACT(a.`new_values`, '$.details')),
           a.`new_values`
       ) AS `details`
FROM `audit_logs` a
LEFT JOIN `users` u ON u.`id` = a.`user_id`;

SET FOREIGN_KEY_CHECKS = 1;
