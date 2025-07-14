-- Create database
CREATE DATABASE IF NOT EXISTS family_wishlist;
USE family_wishlist;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#007bff',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Wishlists table
CREATE TABLE wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    is_public BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Wishlist items table
CREATE TABLE wishlist_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wishlist_id INT NOT NULL,
    category_id INT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    url VARCHAR(500),
    price DECIMAL(10, 2),
    show_price BOOLEAN DEFAULT true,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    is_purchased BOOLEAN DEFAULT false,
    purchased_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (wishlist_id) REFERENCES wishlists(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (purchased_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Email notifications table
CREATE TABLE email_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    is_sent BOOLEAN DEFAULT false,
    sent_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Shared wishlists table (for sharing between users)
CREATE TABLE shared_wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wishlist_id INT NOT NULL,
    shared_with_user_id INT NOT NULL,
    can_edit BOOLEAN DEFAULT false,
    shared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wishlist_id) REFERENCES wishlists(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_share (wishlist_id, shared_with_user_id)
);

-- Insert default users (passwords will be 'password123' - change these!)
INSERT INTO users (username, email, password_hash) VALUES
('Carole', 'carole@example.com', '$2y$10$YourHashedPasswordHere'),
('Ted', 'ted@example.com', '$2y$10$YourHashedPasswordHere');

-- Insert some default categories
INSERT INTO categories (name, description, color, created_by) VALUES
('Electronics', 'Gadgets and tech items', '#17a2b8', 1),
('Books', 'Reading materials', '#28a745', 1),
('Home & Garden', 'Items for the house', '#ffc107', 1),
('Clothing', 'Fashion and apparel', '#dc3545', 1),
('Hobbies', 'Hobby-related items', '#6c757d', 1),
('Kitchen', 'Cooking and kitchen items', '#fd7e14', 1);