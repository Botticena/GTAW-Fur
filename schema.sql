-- =============================================
-- GTAW Furniture Catalog v1 - Database Schema
-- MySQL 8.0+ / MariaDB 10.6+
-- =============================================

-- Create database (run separately if needed)
-- CREATE DATABASE IF NOT EXISTS gtaw_furniture
--     CHARACTER SET utf8mb4
--     COLLATE utf8mb4_unicode_ci;

-- USE gtaw_furniture;

-- =============================================
-- CATEGORIES
-- Furniture categories for filtering
-- =============================================
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(50) DEFAULT '📁',
    sort_order INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_slug (slug),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TAGS
-- Descriptive tags for furniture (style, size, etc.)
-- =============================================
CREATE TABLE IF NOT EXISTS tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#6b7280',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- FURNITURE
-- Main furniture items table
-- =============================================
CREATE TABLE IF NOT EXISTS furniture (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    price INT UNSIGNED DEFAULT 0,
    image_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_furniture_category 
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    
    INDEX idx_name (name),
    INDEX idx_category (category_id),
    INDEX idx_price (price),
    FULLTEXT idx_search (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- FURNITURE_TAGS
-- Many-to-many relationship between furniture and tags
-- =============================================
CREATE TABLE IF NOT EXISTS furniture_tags (
    furniture_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    
    PRIMARY KEY (furniture_id, tag_id),
    
    CONSTRAINT fk_ft_furniture 
        FOREIGN KEY (furniture_id) REFERENCES furniture(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ft_tag 
        FOREIGN KEY (tag_id) REFERENCES tags(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    INDEX idx_tag (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- USERS
-- Users authenticated via GTAW OAuth
-- =============================================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gtaw_id INT UNSIGNED NOT NULL UNIQUE,
    username VARCHAR(100) NOT NULL,
    gtaw_role VARCHAR(50) DEFAULT NULL,
    main_character VARCHAR(100) DEFAULT NULL,
    is_banned BOOLEAN DEFAULT FALSE,
    ban_reason VARCHAR(255) DEFAULT NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_gtaw_id (gtaw_id),
    INDEX idx_username (username),
    INDEX idx_banned (is_banned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- FAVORITES
-- User's saved furniture items
-- =============================================
CREATE TABLE IF NOT EXISTS favorites (
    user_id INT UNSIGNED NOT NULL,
    furniture_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (user_id, furniture_id),
    
    CONSTRAINT fk_fav_user 
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_fav_furniture 
        FOREIGN KEY (furniture_id) REFERENCES furniture(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    INDEX idx_user (user_id),
    INDEX idx_furniture (furniture_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- ADMINS
-- Separate admin accounts (not OAuth-dependent)
-- =============================================
CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- SEED DATA: Default Categories
-- =============================================
INSERT INTO categories (name, slug, icon, sort_order) VALUES
('Walls', 'walls', '🧱', 1),
('Floors', 'floors', '🟫', 2),
('Doors', 'doors', '🚪', 3),
('Windows', 'windows', '🪟', 4),
('Seating', 'seating', '🪑', 5),
('Tables', 'tables', '🪵', 6),
('Beds', 'beds', '🛏️', 7),
('Storage', 'storage', '🗄️', 8),
('Appliances', 'appliances', '🍳', 9),
('Lighting', 'lighting', '💡', 10),
('Decorations', 'decorations', '🖼️', 11),
('Electronics', 'electronics', '📺', 12),
('Bathroom', 'bathroom', '🚿', 13),
('Kitchen', 'kitchen', '🍽️', 14),
('Outdoor', 'outdoor', '🌳', 15),
('Office', 'office', '💼', 16),
('Industrial', 'industrial', '🏭', 17),
('Casino', 'casino', '🎰', 18),
('Bar', 'bar', '🍺', 19),
('Medical', 'medical', '🏥', 20),
('Miscellaneous', 'misc', '📦', 99)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- =============================================
-- SEED DATA: Default Tags
-- =============================================
INSERT INTO tags (name, slug, color) VALUES
('modern', 'modern', '#3b82f6'),
('rustic', 'rustic', '#92400e'),
('luxury', 'luxury', '#eab308'),
('industrial', 'industrial', '#6b7280'),
('minimalist', 'minimalist', '#a3a3a3'),
('vintage', 'vintage', '#a16207'),
('cozy', 'cozy', '#f97316'),
('professional', 'professional', '#1e40af'),
('small', 'small', '#06b6d4'),
('medium', 'medium', '#8b5cf6'),
('large', 'large', '#ec4899'),
('animated', 'animated', '#10b981')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- =============================================
-- SEED DATA: Sample Furniture (for testing)
-- =============================================
INSERT INTO furniture (name, category_id, price) VALUES
('prop_table_01', (SELECT id FROM categories WHERE slug = 'tables'), 450),
('prop_table_02', (SELECT id FROM categories WHERE slug = 'tables'), 550),
('prop_table_03', (SELECT id FROM categories WHERE slug = 'tables'), 650),
('prop_chair_01', (SELECT id FROM categories WHERE slug = 'seating'), 150),
('prop_chair_02', (SELECT id FROM categories WHERE slug = 'seating'), 200),
('prop_sofa_01', (SELECT id FROM categories WHERE slug = 'seating'), 1200),
('prop_bed_01', (SELECT id FROM categories WHERE slug = 'beds'), 800),
('prop_bed_02', (SELECT id FROM categories WHERE slug = 'beds'), 1500),
('prop_lamp_01', (SELECT id FROM categories WHERE slug = 'lighting'), 75),
('prop_lamp_02', (SELECT id FROM categories WHERE slug = 'lighting'), 120),
('prop_tv_01', (SELECT id FROM categories WHERE slug = 'electronics'), 450),
('prop_tv_02', (SELECT id FROM categories WHERE slug = 'electronics'), 800),
('prop_cabinet_01', (SELECT id FROM categories WHERE slug = 'storage'), 350),
('prop_shelf_01', (SELECT id FROM categories WHERE slug = 'storage'), 200),
('prop_desk_01', (SELECT id FROM categories WHERE slug = 'office'), 400),
('prop_plant_01', (SELECT id FROM categories WHERE slug = 'decorations'), 50),
('prop_plant_02', (SELECT id FROM categories WHERE slug = 'decorations'), 75),
('prop_painting_01', (SELECT id FROM categories WHERE slug = 'decorations'), 150),
('prop_rug_01', (SELECT id FROM categories WHERE slug = 'floors'), 200),
('prop_toilet_01', (SELECT id FROM categories WHERE slug = 'bathroom'), 250)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Add some tags to sample furniture
INSERT INTO furniture_tags (furniture_id, tag_id)
SELECT f.id, t.id FROM furniture f, tags t 
WHERE f.name = 'prop_sofa_01' AND t.slug IN ('modern', 'luxury')
ON DUPLICATE KEY UPDATE furniture_id = furniture_id;

INSERT INTO furniture_tags (furniture_id, tag_id)
SELECT f.id, t.id FROM furniture f, tags t 
WHERE f.name = 'prop_table_01' AND t.slug IN ('rustic', 'medium')
ON DUPLICATE KEY UPDATE furniture_id = furniture_id;

INSERT INTO furniture_tags (furniture_id, tag_id)
SELECT f.id, t.id FROM furniture f, tags t 
WHERE f.name = 'prop_desk_01' AND t.slug IN ('professional', 'modern')
ON DUPLICATE KEY UPDATE furniture_id = furniture_id;

