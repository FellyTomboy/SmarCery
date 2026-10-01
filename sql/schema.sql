-- =========================================================================
-- SmarCery — MySQL schema
-- Database: smarcery
-- =========================================================================

DROP DATABASE IF EXISTS smarcery;
CREATE DATABASE smarcery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smarcery;

-- -------------------------------------------------------------------------
-- Users
-- -------------------------------------------------------------------------
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(150) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('user','admin') NOT NULL DEFAULT 'user',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_role (role)
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- Master Allergens
-- -------------------------------------------------------------------------
CREATE TABLE allergens (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    name    VARCHAR(50) NOT NULL UNIQUE,
    slug    VARCHAR(50) NOT NULL UNIQUE,
    icon    VARCHAR(8) NULL
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- User Profiles (health & diet preferences)
-- -------------------------------------------------------------------------
CREATE TABLE user_profiles (
    user_id     INT PRIMARY KEY,
    diet_tags   JSON NULL,
    other_notes VARCHAR(500) NULL,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- User Allergens (many-to-many with severity)
-- -------------------------------------------------------------------------
CREATE TABLE user_allergens (
    user_id     INT NOT NULL,
    allergen_id INT NOT NULL,
    severity    ENUM('avoid','intolerance','allergy') NOT NULL DEFAULT 'avoid',
    PRIMARY KEY (user_id, allergen_id),
    FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (allergen_id) REFERENCES allergens(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- Categories (hierarchical tree via parent_id)
-- -------------------------------------------------------------------------
CREATE TABLE categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    parent_id   INT NULL,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    icon        VARCHAR(8) NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_categories_parent (parent_id)
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- Bookmarks (user <-> MongoDB product_id)
-- -------------------------------------------------------------------------
CREATE TABLE bookmarks (
    user_id     INT NOT NULL,
    product_id  VARCHAR(24) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_bookmarks_user (user_id, created_at DESC)
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- Transactions (purchase history)
-- -------------------------------------------------------------------------
CREATE TABLE transactions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    total_amount  DECIMAL(12,2) NOT NULL DEFAULT 0,
    purchased_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_tx_user_date (user_id, purchased_at DESC)
) ENGINE=InnoDB;

CREATE TABLE transaction_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id  INT NOT NULL,
    product_id      VARCHAR(24) NOT NULL,
    product_name    VARCHAR(200) NOT NULL,
    qty             INT NOT NULL DEFAULT 1,
    price           DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    INDEX idx_txi_tx (transaction_id)
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- Contribution moderation (references MongoDB ObjectId as string)
-- -------------------------------------------------------------------------
CREATE TABLE contribution_status (
    contribution_id VARCHAR(24) PRIMARY KEY,
    status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by     INT NULL,
    reviewed_at     DATETIME NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
