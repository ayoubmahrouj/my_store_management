SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    label VARCHAR(60) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    type ENUM('depot','point_vente') NOT NULL DEFAULT 'depot',
    address VARCHAR(255) NULL,
    phone VARCHAR(30) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    login VARCHAR(60) NOT NULL UNIQUE,
    email VARCHAR(160) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    location_id INT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id),
    CONSTRAINT fk_users_location FOREIGN KEY (location_id) REFERENCES locations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS brands (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS units (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    label VARCHAR(60) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL UNIQUE,
    ice VARCHAR(30) NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(160) NULL,
    address VARCHAR(255) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(40) NOT NULL UNIQUE,
    barcode VARCHAR(60) NULL UNIQUE,
    designation VARCHAR(180) NOT NULL,
    brand_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    unit_id INT UNSIGNED NOT NULL,
    purchase_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'CUMP / prix moyen achat',
    sale_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    avg_sale_price DECIMAL(12,2) NULL,
    min_stock DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_products_designation (designation),
    CONSTRAINT fk_products_brand FOREIGN KEY (brand_id) REFERENCES brands (id),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id),
    CONSTRAINT fk_products_unit FOREIGN KEY (unit_id) REFERENCES units (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('entree','sortie','transfert','inventaire','ouverture') NOT NULL,
    motif VARCHAR(40) NULL,
    numero VARCHAR(40) NOT NULL UNIQUE,
    doc_date DATE NOT NULL,
    status ENUM('brouillon','valide','expedie','recu','annule') NOT NULL DEFAULT 'brouillon',
    location_id INT UNSIGNED NOT NULL,
    dest_location_id INT UNSIGNED NULL,
    supplier_id INT UNSIGNED NULL,
    reference VARCHAR(100) NULL,
    notes TEXT NULL,
    total_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_sale DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_by INT UNSIGNED NOT NULL,
    validated_by INT UNSIGNED NULL,
    validated_at DATETIME NULL,
    shipped_by INT UNSIGNED NULL,
    shipped_at DATETIME NULL,
    received_by INT UNSIGNED NULL,
    received_at DATETIME NULL,
    cancel_reason VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_documents_type_status (type, status, doc_date),
    CONSTRAINT fk_documents_location FOREIGN KEY (location_id) REFERENCES locations (id),
    CONSTRAINT fk_documents_dest FOREIGN KEY (dest_location_id) REFERENCES locations (id),
    CONSTRAINT fk_documents_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id),
    CONSTRAINT fk_documents_creator FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_document_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    qty DECIMAL(14,3) NOT NULL,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes VARCHAR(255) NULL,
    CONSTRAINT fk_lines_document FOREIGN KEY (document_id) REFERENCES stock_documents (id) ON DELETE CASCADE,
    CONSTRAINT fk_lines_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id BIGINT UNSIGNED NULL,
    document_line_id BIGINT UNSIGNED NULL,
    product_id INT UNSIGNED NOT NULL,
    location_id INT UNSIGNED NOT NULL,
    direction ENUM('in','out') NOT NULL,
    qty DECIMAL(14,3) NOT NULL,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    movement_date DATETIME NOT NULL,
    inventory_session_id BIGINT UNSIGNED NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_movements_stock (product_id, location_id, movement_date),
    INDEX idx_movements_date (movement_date),
    INDEX idx_movements_inventory (inventory_session_id),
    CONSTRAINT fk_movements_document FOREIGN KEY (document_id) REFERENCES stock_documents (id),
    CONSTRAINT fk_movements_product FOREIGN KEY (product_id) REFERENCES products (id),
    CONSTRAINT fk_movements_location FOREIGN KEY (location_id) REFERENCES locations (id),
    CONSTRAINT fk_movements_user FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_levels (
    product_id INT UNSIGNED NOT NULL,
    location_id INT UNSIGNED NOT NULL,
    qty DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    updated_at DATETIME NULL,
    PRIMARY KEY (product_id, location_id),
    CONSTRAINT fk_levels_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_levels_location FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(40) NOT NULL UNIQUE,
    location_id INT UNSIGNED NOT NULL,
    status ENUM('ouvert','comptage','valide','annule') NOT NULL DEFAULT 'ouvert',
    started_by INT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL,
    validated_by INT UNSIGNED NULL,
    validated_at DATETIME NULL,
    notes TEXT NULL,
    CONSTRAINT fk_inventory_location FOREIGN KEY (location_id) REFERENCES locations (id),
    CONSTRAINT fk_inventory_user FOREIGN KEY (started_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    qty_theoretical DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    qty_counted DECIMAL(14,3) NULL,
    gap DECIMAL(14,3) NULL,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    UNIQUE KEY uq_inventory_product (session_id, product_id),
    CONSTRAINT fk_inventory_lines_session FOREIGN KEY (session_id) REFERENCES inventory_sessions (id) ON DELETE CASCADE,
    CONSTRAINT fk_inventory_lines_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(60) NOT NULL,
    entity_type VARCHAR(60) NULL,
    entity_id BIGINT UNSIGNED NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_user (user_id, created_at),
    INDEX idx_audit_entity (entity_type, entity_id),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(100) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attempts_login (login, created_at),
    INDEX idx_attempts_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    k VARCHAR(60) NOT NULL PRIMARY KEY,
    v TEXT NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_sequences (
    type VARCHAR(20) NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    last_number INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (type, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
