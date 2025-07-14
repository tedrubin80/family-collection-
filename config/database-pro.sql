-- Enhanced Family Wishlist Database Schema
-- Includes all advanced features for organized couples

CREATE DATABASE IF NOT EXISTS family_wishlist_pro;
USE family_wishlist_pro;

-- =====================================================
-- CORE TABLES (Original)
-- =====================================================

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(500),
    notification_preferences JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#007bff',
    icon VARCHAR(50),
    parent_id INT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Wishlists table (enhanced)
CREATE TABLE wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    is_public BOOLEAN DEFAULT false,
    list_type ENUM('wishlist', 'shopping', 'project', 'comparison', 'collection') DEFAULT 'wishlist',
    budget_limit DECIMAL(10, 2),
    template_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Wishlist items table (enhanced)
CREATE TABLE wishlist_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wishlist_id INT NOT NULL,
    category_id INT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    url VARCHAR(500),
    price DECIMAL(10, 2),
    show_price BOOLEAN DEFAULT true,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('wanted', 'researching', 'decided', 'purchased', 'received', 'returned') DEFAULT 'wanted',
    is_purchased BOOLEAN DEFAULT false,
    purchased_by INT,
    purchase_date DATE,
    purchase_price DECIMAL(10, 2),
    store_name VARCHAR(100),
    quantity INT DEFAULT 1,
    notes JSON, -- Stores various notes and metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (wishlist_id) REFERENCES wishlists(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (purchased_by) REFERENCES users(id) ON DELETE SET NULL,
    FULLTEXT INDEX ft_search (title, description)
);

-- =====================================================
-- ENHANCED ORGANIZATION FEATURES
-- =====================================================

-- Tags table
CREATE TABLE tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    color VARCHAR(7) DEFAULT '#6c757d',
    usage_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Item tags relationship
CREATE TABLE item_tags (
    item_id INT,
    tag_id INT,
    added_by INT,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id, tag_id),
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
);

-- List templates
CREATE TABLE list_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    template_type ENUM('wishlist', 'shopping', 'project', 'packing', 'custom') DEFAULT 'custom',
    template_data JSON, -- Stores categories, default items, etc.
    created_by INT,
    is_public BOOLEAN DEFAULT false,
    usage_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- =====================================================
-- COUPLE-SPECIFIC FEATURES
-- =====================================================

-- Joint ratings
CREATE TABLE item_ratings (
    item_id INT,
    user_id INT,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    vote_type ENUM('want', 'maybe', 'veto') DEFAULT 'want',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id, user_id),
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Item discussions
CREATE TABLE item_discussions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    parent_id INT, -- For threaded discussions
    message TEXT NOT NULL,
    message_type ENUM('comment', 'pro', 'con', 'question', 'decision') DEFAULT 'comment',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    edited_at TIMESTAMP NULL,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (parent_id) REFERENCES item_discussions(id) ON DELETE CASCADE
);

-- Shared decisions tracking
CREATE TABLE couple_decisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    decision_status ENUM('discussing', 'researching', 'agreed', 'disagreed', 'purchased') DEFAULT 'discussing',
    final_decision TEXT,
    decision_date TIMESTAMP NULL,
    decided_by INT,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE,
    FOREIGN KEY (decided_by) REFERENCES users(id)
);

-- =====================================================
-- BUDGET AND FINANCIAL FEATURES
-- =====================================================

-- Budget tracking
CREATE TABLE budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    period ENUM('weekly', 'monthly', 'quarterly', 'yearly', 'one-time') DEFAULT 'monthly',
    category_id INT,
    start_date DATE NOT NULL,
    end_date DATE,
    created_by INT,
    is_shared BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Savings goals
CREATE TABLE savings_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    target_amount DECIMAL(10, 2) NOT NULL,
    current_amount DECIMAL(10, 2) DEFAULT 0,
    target_date DATE,
    item_id INT,
    created_by INT,
    is_shared BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    achieved_at TIMESTAMP NULL,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- =====================================================
-- SMART FEATURES
-- =====================================================

-- Price history tracking
CREATE TABLE price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    source VARCHAR(100),
    is_sale BOOLEAN DEFAULT false,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE,
    INDEX idx_price_history (item_id, recorded_at)
);

-- Price alerts
CREATE TABLE price_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    target_price DECIMAL(10, 2),
    alert_type ENUM('below', 'above', 'any_change') DEFAULT 'below',
    percentage_change INT, -- Alert when price changes by X%
    is_active BOOLEAN DEFAULT true,
    last_notified TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Recurring items (for consumables)
CREATE TABLE recurring_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    frequency_days INT NOT NULL,
    last_purchased DATE,
    next_reminder DATE,
    auto_add_to_list BOOLEAN DEFAULT false,
    quantity_per_purchase INT DEFAULT 1,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE
);

-- Smart reminders
CREATE TABLE reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT,
    reminder_type ENUM('price_drop', 'restock', 'seasonal', 'birthday', 'custom') DEFAULT 'custom',
    title VARCHAR(200) NOT NULL,
    description TEXT,
    remind_at DATETIME NOT NULL,
    is_recurring BOOLEAN DEFAULT false,
    recurrence_pattern VARCHAR(50), -- 'daily', 'weekly', 'monthly', etc.
    is_completed BOOLEAN DEFAULT false,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE SET NULL
);

-- =====================================================
-- COMPARISON AND RESEARCH FEATURES
-- =====================================================

-- Comparisons
CREATE TABLE comparisons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    comparison_type ENUM('simple', 'weighted', 'pro_con') DEFAULT 'simple',
    created_by INT,
    is_shared BOOLEAN DEFAULT true,
    winner_item_id INT,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (winner_item_id) REFERENCES wishlist_items(id) ON DELETE SET NULL
);

-- Comparison items
CREATE TABLE comparison_items (
    comparison_id INT,
    item_id INT,
    position INT DEFAULT 0,
    added_by INT,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (comparison_id, item_id),
    FOREIGN KEY (comparison_id) REFERENCES comparisons(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id)
);

-- Comparison criteria
CREATE TABLE comparison_criteria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comparison_id INT NOT NULL,
    criteria_name VARCHAR(100) NOT NULL,
    criteria_type ENUM('rating', 'yes_no', 'text') DEFAULT 'rating',
    weight INT DEFAULT 1,
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (comparison_id) REFERENCES comparisons(id) ON DELETE CASCADE
);

-- Comparison scores
CREATE TABLE comparison_scores (
    comparison_id INT,
    item_id INT,
    criteria_id INT,
    user_id INT,
    score_value VARCHAR(255), -- Can store rating, yes/no, or text
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (comparison_id, item_id, criteria_id, user_id),
    FOREIGN KEY (comparison_id) REFERENCES comparisons(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE,
    FOREIGN KEY (criteria_id) REFERENCES comparison_criteria(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- =====================================================
-- RESEARCH AND DOCUMENTATION
-- =====================================================

-- Item research links
CREATE TABLE item_research (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    research_type ENUM('review', 'video', 'article', 'manual', 'specs') DEFAULT 'review',
    title VARCHAR(200),
    url VARCHAR(500),
    source_name VARCHAR(100),
    rating DECIMAL(3,2),
    key_points TEXT,
    added_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id)
);

-- Item attachments
CREATE TABLE item_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    file_type ENUM('image', 'pdf', 'receipt', 'warranty', 'manual', 'other') DEFAULT 'other',
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    uploaded_by INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- =====================================================
-- LOCATION AND STORE FEATURES
-- =====================================================

-- Stores
CREATE TABLE stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    website VARCHAR(255),
    physical_address TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    phone VARCHAR(20),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Item availability
CREATE TABLE item_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    store_id INT NOT NULL,
    is_available BOOLEAN DEFAULT true,
    price DECIMAL(10, 2),
    last_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aisle_location VARCHAR(50),
    stock_quantity INT,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
);

-- =====================================================
-- ANALYTICS AND INSIGHTS
-- =====================================================

-- Purchase history (detailed)
CREATE TABLE purchase_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    purchase_price DECIMAL(10, 2) NOT NULL,
    original_price DECIMAL(10, 2),
    discount_amount DECIMAL(10, 2),
    store_id INT,
    purchase_method ENUM('online', 'in_store', 'app') DEFAULT 'online',
    satisfaction_rating INT CHECK (satisfaction_rating >= 1 AND satisfaction_rating <= 5),
    would_recommend BOOLEAN,
    return_date DATE,
    return_reason TEXT,
    warranty_expires DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (store_id) REFERENCES stores(id)
);

-- Item usage tracking
CREATE TABLE item_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    usage_date DATE NOT NULL,
    usage_rating INT CHECK (usage_rating >= 1 AND usage_rating <= 5),
    notes TEXT,
    logged_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE,
    FOREIGN KEY (logged_by) REFERENCES users(id)
);

-- =====================================================
-- SHARING AND COLLABORATION
-- =====================================================

-- Enhanced sharing
CREATE TABLE shared_wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wishlist_id INT NOT NULL,
    shared_with_user_id INT,
    share_token VARCHAR(64) UNIQUE, -- For sharing via link
    can_edit BOOLEAN DEFAULT false,
    can_add_items BOOLEAN DEFAULT false,
    can_see_prices BOOLEAN DEFAULT true,
    expires_at TIMESTAMP NULL,
    shared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wishlist_id) REFERENCES wishlists(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_share (wishlist_id, shared_with_user_id)
);

-- Family groups
CREATE TABLE family_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Family group members
CREATE TABLE family_group_members (
    group_id INT,
    user_id INT,
    role ENUM('admin', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id, user_id),
    FOREIGN KEY (group_id) REFERENCES family_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- NOTIFICATIONS AND ACTIVITY
-- =====================================================

-- Enhanced notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT,
    data JSON, -- Additional data for the notification
    is_read BOOLEAN DEFAULT false,
    is_email_sent BOOLEAN DEFAULT false,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Activity log
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_activity_user_date (user_id, created_at)
);

-- =====================================================
-- SYSTEM TABLES
-- =====================================================

-- API integrations
CREATE TABLE api_integrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_name VARCHAR(50) NOT NULL,
    api_key_encrypted TEXT,
    refresh_token_encrypted TEXT,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT true,
    last_sync TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_service (user_id, service_name)
);

-- User preferences
CREATE TABLE user_preferences (
    user_id INT PRIMARY KEY,
    theme ENUM('light', 'dark', 'auto') DEFAULT 'light',
    language VARCHAR(5) DEFAULT 'en',
    currency VARCHAR(3) DEFAULT 'USD',
    timezone VARCHAR(50) DEFAULT 'America/New_York',
    email_frequency ENUM('instant', 'daily', 'weekly', 'never') DEFAULT 'daily',
    show_prices BOOLEAN DEFAULT true,
    default_list_view ENUM('grid', 'list', 'compact') DEFAULT 'grid',
    preferences_json JSON, -- For additional preferences
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

CREATE INDEX idx_items_wishlist ON wishlist_items(wishlist_id, status);
CREATE INDEX idx_items_category ON wishlist_items(category_id);
CREATE INDEX idx_items_priority ON wishlist_items(priority);
CREATE INDEX idx_items_created ON wishlist_items(created_at);
CREATE INDEX idx_price_history_item ON price_history(item_id, recorded_at);
CREATE INDEX idx_tags_name ON tags(name);
CREATE INDEX idx_reminders_user ON reminders(user_id, remind_at);
CREATE INDEX idx_notifications_user ON notifications(user_id, is_read, created_at);

-- =====================================================
-- INITIAL DATA
-- =====================================================

-- Insert default users
INSERT INTO users (username, email, password_hash) VALUES
('Carole', 'carole@example.com', '$2y$10$YourHashedPasswordHere'),
('Ted', 'ted@example.com', '$2y$10$YourHashedPasswordHere');

-- Insert enhanced categories with icons
INSERT INTO categories (name, description, color, icon, created_by) VALUES
('Electronics', 'Gadgets and tech items', '#17a2b8', 'bi-cpu', 1),
('Books', 'Reading materials', '#28a745', 'bi-book', 1),
('Home & Garden', 'Items for the house', '#ffc107', 'bi-house', 1),
('Kitchen', 'Cooking and kitchen items', '#fd7e14', 'bi-egg-fried', 1),
('Travel', 'Travel gear and accessories', '#6f42c1', 'bi-airplane', 1),
('Fitness', 'Exercise and health items', '#dc3545', 'bi-heart-pulse', 1),
('Hobbies', 'Hobby-related items', '#6c757d', 'bi-palette', 1),
('Office', 'Work and productivity items', '#0dcaf0', 'bi-briefcase', 1);

-- Insert common tags
INSERT INTO tags (name, color) VALUES
('gift-idea', '#e83e8c'),
('on-sale', '#28a745'),
('researching', '#ffc107'),
('urgent', '#dc3545'),
('both-want', '#007bff'),
('eco-friendly', '#20c997'),
('local-store', '#6f42c1'),
('online-only', '#fd7e14');

-- Insert user preferences
INSERT INTO user_preferences (user_id) VALUES (1), (2);

-- Create a family group
INSERT INTO family_groups (name, description, created_by) VALUES
('Our Family', 'Carole and Ted''s family group', 1);

INSERT INTO family_group_members (group_id, user_id, role) VALUES
(1, 1, 'admin'),
(1, 2, 'admin');