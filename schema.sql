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
-- TAG GROUPS
-- Logical groupings for tags (Style, Mood, Size, etc.)
-- =============================================
CREATE TABLE IF NOT EXISTS tag_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#6b7280',
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
    group_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_tag_group 
        FOREIGN KEY (group_id) REFERENCES tag_groups(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    
    INDEX idx_slug (slug),
    INDEX idx_group (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- FURNITURE
-- Main furniture items table
-- =============================================
CREATE TABLE IF NOT EXISTS furniture (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price INT UNSIGNED DEFAULT 0,
    image_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_price (price),
    INDEX idx_created_at (created_at),
    INDEX idx_updated_at (updated_at),
    FULLTEXT idx_search (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- FURNITURE_CATEGORIES
-- Many-to-many relationship between furniture and categories
-- =============================================
CREATE TABLE IF NOT EXISTS furniture_categories (
    furniture_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    
    PRIMARY KEY (furniture_id, category_id),
    
    CONSTRAINT fk_fc_furniture 
        FOREIGN KEY (furniture_id) REFERENCES furniture(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_fc_category 
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    INDEX idx_category (category_id),
    INDEX idx_primary (furniture_id, is_primary)
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
    INDEX idx_furniture (furniture_id),
    INDEX idx_created_at (created_at)
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
-- COLLECTIONS
-- User-created furniture collections/wishlists
-- =============================================
CREATE TABLE IF NOT EXISTS collections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    is_public BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT chk_description_length CHECK (CHAR_LENGTH(description) <= 5000),
    
    CONSTRAINT fk_collection_user 
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    UNIQUE KEY uk_user_slug (user_id, slug),
    INDEX idx_user (user_id),
    INDEX idx_public (is_public),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- COLLECTION_ITEMS
-- Furniture items within collections
-- =============================================
CREATE TABLE IF NOT EXISTS collection_items (
    collection_id INT UNSIGNED NOT NULL,
    furniture_id INT UNSIGNED NOT NULL,
    sort_order INT UNSIGNED DEFAULT 0,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (collection_id, furniture_id),
    
    CONSTRAINT fk_ci_collection 
        FOREIGN KEY (collection_id) REFERENCES collections(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ci_furniture 
        FOREIGN KEY (furniture_id) REFERENCES furniture(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    INDEX idx_furniture (furniture_id),
    INDEX idx_sort (collection_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- SUBMISSIONS
-- User-submitted furniture additions/edits
-- =============================================
CREATE TABLE IF NOT EXISTS submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('new', 'edit') NOT NULL DEFAULT 'new',
    furniture_id INT UNSIGNED DEFAULT NULL COMMENT 'For edit submissions, references existing furniture',
    
    -- Submission data (JSON for flexibility)
    data JSON NOT NULL COMMENT 'Contains name, category_id, price, image_url, tags array',
    
    -- Status tracking
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    admin_notes TEXT DEFAULT NULL COMMENT 'Feedback from admin on rejection',
    reviewed_by INT UNSIGNED DEFAULT NULL,
    reviewed_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_submission_user 
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_submission_furniture 
        FOREIGN KEY (furniture_id) REFERENCES furniture(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_submission_admin 
        FOREIGN KEY (reviewed_by) REFERENCES admins(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_type (type),
    INDEX idx_created (created_at),
    INDEX idx_pending (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- SEED DATA: Default Categories
-- =============================================
INSERT INTO `categories` (`id`, `name`, `slug`, `icon`, `sort_order`, `created_at`) VALUES
(1, 'Architecture', 'architecture', '🏗', 1, '2025-12-01 14:07:47'),
(2, 'Seating', 'seating', '🛋', 2, '2025-12-01 14:09:22'),
(3, 'Tables', 'tables', '🛎', 3, '2025-12-01 14:09:55'),
(4, 'Beds', 'beds', '🛏️', 4, '2025-11-30 16:25:45'),
(5, 'Storage', 'storage', '🗄️', 5, '2025-11-30 16:25:45'),
(6, 'Kitchen', 'kitchen', '🍽', 6, '2025-12-01 14:12:04'),
(7, 'Bathroom', 'bathroom', '🚿', 7, '2025-11-30 16:25:45'),
(8, 'Lighting', 'lighting', '💡', 8, '2025-11-30 16:25:45'),
(9, 'Visual Effects', 'visual-effects', '✨', 9, '2025-12-01 14:19:14'),
(10, 'Electronics', 'electronics', '📺', 10, '2025-11-30 16:25:45'),
(11, 'Decor', 'decor', '🎨', 11, '2025-12-01 14:20:00'),
(12, 'Plants', 'plants', '🌿', 12, '2025-12-01 14:20:15'),
(13, 'Rugs & Carpets', 'rugs-carpets', '🧶', 13, '2025-12-01 14:20:31'),
(14, 'Textiles', 'textiles', '🧵', 14, '2025-12-01 14:20:52'),
(15, 'Office', 'office', '💼', 15, '2025-12-01 14:21:12'),
(16, 'Retail', 'retail', '🏬', 16, '2025-12-01 14:21:28'),
(17, 'Hospitality', 'hospitality', '🍹', 17, '2025-12-01 14:21:51'),
(18, 'Industrial', 'industrial', '🏭', 18, '2025-12-01 14:22:42'),
(19, 'Outdoor', 'outdoor', '🌳', 19, '2025-12-01 14:23:14'),
(20, 'Pets', 'pets', '🐾', 20, '2025-12-01 14:23:27'),
(21, 'Kids', 'kids', '🧸', 21, '2025-12-01 14:23:40'),
(22, 'Food & Drink', 'food-drink', '🍔', 22, '2025-12-01 14:23:55'),
(23, 'Medical', 'medical', '🏥', 23, '2025-12-01 14:24:10'),
(24, 'Tools & Equipment', 'tools-equipment', '🛠', 24, '2025-12-01 14:24:25'),
(25, 'Signs & Labels', 'signs-labels', '🚧', 25, '2025-12-01 14:24:39'),
(26, 'Vehicles & Garage', 'vehicles-garage', '🚗', 26, '2025-12-01 14:24:51'),
(99, 'Miscellaneous', 'miscellaneous', '📦', 99, '2025-12-01 14:25:04')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- =============================================
-- SEED DATA: Default Tag Groups
-- =============================================
INSERT INTO `tag_groups` (`id`, `name`, `slug`, `color`, `sort_order`, `created_at`) VALUES
(1, 'Style', 'style', '#3b82f6', 1, '2025-12-01 15:13:05'),
(2, 'Mood', 'mood', '#f97316', 2, '2025-12-01 15:13:05'),
(3, 'Size', 'size', '#22c55e', 3, '2025-12-01 15:13:05'),
(4, 'Placement', 'placement', '#14b8a6', 4, '2025-12-01 15:13:05'),
(5, 'Materials', 'materials', '#6366f1', 5, '2025-12-01 15:13:05'),
(6, 'Color', 'color', '#ec4899', 6, '2025-12-01 15:13:05'),
(7, 'Effects', 'effects', '#a855f7', 7, '2025-12-01 15:13:05'),
(8, 'Theme', 'theme', '#6b7280', 8, '2025-12-01 15:13:05')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- =============================================
-- SEED DATA: Default Tags 
-- =============================================
-- Style / Era tags (group_id = 1)
INSERT INTO tags (name, slug, color, group_id) VALUES
('modern', 'modern', '#3b82f6', 1),
('contemporary', 'contemporary', '#3b82f6', 1),
('minimalist', 'minimalist', '#3b82f6', 1),
('industrial', 'industrial', '#3b82f6', 1),
('rustic', 'rustic', '#3b82f6', 1),
('vintage', 'vintage', '#3b82f6', 1),
('classic', 'classic', '#3b82f6', 1),
('luxury', 'luxury', '#3b82f6', 1),
('boho', 'boho', '#3b82f6', 1),
('futuristic', 'futuristic', '#3b82f6', 1)
ON DUPLICATE KEY UPDATE group_id = VALUES(group_id);

-- Mood / Atmosphere tags (group_id = 2)
INSERT INTO tags (name, slug, color, group_id) VALUES
('cozy', 'cozy', '#f97316', 2),
('elegant', 'elegant', '#f97316', 2),
('casual', 'casual', '#f97316', 2),
('gritty', 'gritty', '#f97316', 2),
('formal', 'formal', '#f97316', 2),
('playful', 'playful', '#f97316', 2),
('dark', 'dark', '#f97316', 2),
('bright', 'bright', '#f97316', 2)
ON DUPLICATE KEY UPDATE group_id = VALUES(group_id);

-- Size / Presence tags (group_id = 3)
INSERT INTO tags (name, slug, color, group_id) VALUES
('small', 'small', '#22c55e', 3),
('medium', 'medium', '#22c55e', 3),
('large', 'large', '#22c55e', 3),
('compact', 'compact', '#22c55e', 3),
('oversized', 'oversized', '#22c55e', 3)
ON DUPLICATE KEY UPDATE group_id = VALUES(group_id);

-- Form / Placement tags (group_id = 4)
INSERT INTO tags (name, slug, color, group_id) VALUES
('wall-mounted', 'wall-mounted', '#14b8a6', 4),
('ceiling-mounted', 'ceiling-mounted', '#14b8a6', 4),
('floor-standing', 'floor-standing', '#14b8a6', 4),
('corner-piece', 'corner-piece', '#14b8a6', 4),
('low-profile', 'low-profile', '#14b8a6', 4),
('tall', 'tall', '#14b8a6', 4)
ON DUPLICATE KEY UPDATE group_id = VALUES(group_id);

-- Materials / Finish tags (group_id = 5)
INSERT INTO tags (name, slug, color, group_id) VALUES
('wood', 'wood', '#6366f1', 5),
('metal', 'metal', '#6366f1', 5),
('glass', 'glass', '#6366f1', 5),
('stone', 'stone', '#6366f1', 5),
('fabric', 'fabric', '#6366f1', 5),
('leather', 'leather', '#6366f1', 5),
('plastic', 'plastic', '#6366f1', 5),
('wicker', 'wicker', '#6366f1', 5),
('marble', 'marble', '#6366f1', 5),
('neon', 'neon', '#6366f1', 5)
ON DUPLICATE KEY UPDATE group_id = VALUES(group_id);

-- Color-Oriented tags (group_id = 6)
INSERT INTO tags (name, slug, color, group_id) VALUES
('light-tone', 'light-tone', '#ec4899', 6),
('dark-tone', 'dark-tone', '#ec4899', 6),
('monochrome', 'monochrome', '#ec4899', 6),
('colorful', 'colorful', '#ec4899', 6),
('pastel', 'pastel', '#ec4899', 6)
ON DUPLICATE KEY UPDATE group_id = VALUES(group_id);

-- Effects / Behavior tags (group_id = 7)
INSERT INTO tags (name, slug, color, group_id) VALUES
('animated', 'animated', '#a855f7', 7),
('glowing', 'glowing', '#a855f7', 7),
('light-fx', 'light-fx', '#a855f7', 7),
('water-fx', 'water-fx', '#a855f7', 7),
('interactive', 'interactive', '#a855f7', 7)
ON DUPLICATE KEY UPDATE group_id = VALUES(group_id);

-- Theme / Use-Case tags (group_id = 8)
INSERT INTO tags (name, slug, color, group_id) VALUES
('festive', 'festive', '#6b7280', 8),
('seasonal', 'seasonal', '#6b7280', 8),
('holiday', 'holiday', '#6b7280', 8),
('romantic', 'romantic', '#6b7280', 8),
('party', 'party', '#6b7280', 8),
('sport', 'sport', '#6b7280', 8),
('music', 'music', '#6b7280', 8),
('arcade', 'arcade', '#6b7280', 8),
('lux-brand', 'lux-brand', '#6b7280', 8),
('street', 'street', '#6b7280', 8),
('racing', 'racing', '#6b7280', 8),
('crime', 'crime', '#6b7280', 8),
('professional', 'professional', '#6b7280', 8)
ON DUPLICATE KEY UPDATE group_id = VALUES(group_id);