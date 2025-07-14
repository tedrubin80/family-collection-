# Family Wishlist - Feature Roadmap

## Phase 1: Enhanced Organization (1-2 weeks)

### 1. **Multi-Tag System**
```sql
CREATE TABLE tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    color VARCHAR(7) DEFAULT '#6c757d',
    usage_count INT DEFAULT 0
);

CREATE TABLE item_tags (
    item_id INT,
    tag_id INT,
    PRIMARY KEY (item_id, tag_id),
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);
```

### 2. **Item Notes & Discussions**
```sql
CREATE TABLE item_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    note_text TEXT,
    note_type ENUM('comment', 'review', 'reminder') DEFAULT 'comment',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### 3. **Joint Ratings**
```sql
CREATE TABLE item_ratings (
    item_id INT,
    user_id INT,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id, user_id),
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

## Phase 2: Smart Features (2-3 weeks)

### 4. **Price History Tracking**
```sql
CREATE TABLE price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    source VARCHAR(100),
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE
);

CREATE TABLE price_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    target_price DECIMAL(10, 2),
    alert_type ENUM('below', 'above') DEFAULT 'below',
    is_active BOOLEAN DEFAULT true,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### 5. **Smart Lists & Templates**
```sql
CREATE TABLE list_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_by INT,
    is_public BOOLEAN DEFAULT false,
    template_data JSON,
    usage_count INT DEFAULT 0,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE recurring_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    frequency_days INT NOT NULL,
    last_purchased DATE,
    next_reminder DATE,
    is_active BOOLEAN DEFAULT true,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE
);
```

## Phase 3: Visual & Research Tools (3-4 weeks)

### 6. **Image Management**
```sql
CREATE TABLE item_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    image_url VARCHAR(500),
    image_path VARCHAR(255),
    is_primary BOOLEAN DEFAULT false,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE
);

CREATE TABLE item_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    document_type ENUM('manual', 'receipt', 'warranty', 'review', 'other'),
    file_name VARCHAR(255),
    file_path VARCHAR(500),
    file_size INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE
);
```

### 7. **Comparison Features**
```sql
CREATE TABLE comparisons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE comparison_items (
    comparison_id INT,
    item_id INT,
    position INT,
    PRIMARY KEY (comparison_id, item_id),
    FOREIGN KEY (comparison_id) REFERENCES comparisons(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE
);

CREATE TABLE comparison_criteria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comparison_id INT,
    criteria_name VARCHAR(100),
    weight INT DEFAULT 1,
    FOREIGN KEY (comparison_id) REFERENCES comparisons(id) ON DELETE CASCADE
);
```

## Phase 4: Integration & Analytics (4-5 weeks)

### 8. **Purchase Analytics**
```sql
CREATE TABLE purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    purchased_price DECIMAL(10, 2),
    purchase_date DATE,
    store_name VARCHAR(100),
    satisfaction_rating INT,
    would_buy_again BOOLEAN,
    notes TEXT,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id)
);

CREATE TABLE budget_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT,
    budget_amount DECIMAL(10, 2),
    period ENUM('weekly', 'monthly', 'yearly'),
    start_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
);
```

### 9. **External Integrations**
```sql
CREATE TABLE external_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    service_type ENUM('amazon', 'youtube', 'review_site', 'price_tracker', 'other'),
    service_url VARCHAR(500),
    last_checked TIMESTAMP,
    cached_data JSON,
    FOREIGN KEY (item_id) REFERENCES wishlist_items(id) ON DELETE CASCADE
);

CREATE TABLE api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_name VARCHAR(50),
    token_encrypted TEXT,
    expires_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

## Implementation Priority

### Quick Wins (Do First):
1. **Tags & Advanced Search** - Immediate organization improvement
2. **Joint Ratings** - Simple but powerful for couples
3. **Price Alerts** - High value, relatively simple
4. **Item Notes** - Enables discussion and memory

### Medium Effort, High Impact:
5. **Templates** - Saves time for power users
6. **Price History** - Valuable for smart shopping
7. **Image Uploads** - Visual organization
8. **Basic Analytics** - Spending insights

### Advanced Features (Later):
9. **AI Recommendations** - Requires ML infrastructure
10. **Mobile App** - Significant development effort
11. **Voice Integration** - Complex but convenient
12. **API Ecosystem** - For power users

## Database Optimization

Add these indexes for performance:
```sql
CREATE INDEX idx_item_created ON wishlist_items(created_at);
CREATE INDEX idx_item_priority ON wishlist_items(priority);
CREATE INDEX idx_price_history ON price_history(item_id, recorded_at);
CREATE INDEX idx_tags_name ON tags(name);
CREATE FULLTEXT INDEX idx_item_search ON wishlist_items(title, description);
```

## Security Enhancements

### API Rate Limiting
```sql
CREATE TABLE api_rate_limits (
    ip_address VARCHAR(45),
    endpoint VARCHAR(100),
    requests INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ip_address, endpoint)
);
```

### Audit Trail
```sql
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(50),
    entity_type VARCHAR(50),
    entity_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```