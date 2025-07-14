<?php
// config-pro.php - Enhanced configuration for all features
// Store this file outside of public web directory for security

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'family_wishlist_pro');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// Email configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your_email@gmail.com');
define('SMTP_PASSWORD', 'your_app_password');
define('SMTP_ENCRYPTION', 'tls');
define('EMAIL_FROM', 'noreply@familywishlist.com');
define('EMAIL_FROM_NAME', 'Family Wishlist Pro');

// Application settings
define('SITE_NAME', 'Family Wishlist Pro');
define('SITE_URL', 'https://yoursite.com');
define('SESSION_LIFETIME', 7200); // 2 hours
define('TIMEZONE', 'America/New_York');
define('DEFAULT_CURRENCY', 'USD');
define('DEFAULT_LANGUAGE', 'en');

// Security settings
define('SECURE_COOKIES', true);
define('CSRF_TOKEN_LIFETIME', 3600);
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('API_RATE_LIMIT', 100); // requests per hour

// File upload settings
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'txt']);
define('IMAGE_QUALITY', 85); // JPEG quality
define('THUMBNAIL_WIDTH', 300);
define('THUMBNAIL_HEIGHT', 300);

// Theme settings
define('PRIMARY_COLOR', '#007bff');
define('SECONDARY_COLOR', '#6c757d');
define('SUCCESS_COLOR', '#28a745');
define('WARNING_COLOR', '#ffc107');
define('DANGER_COLOR', '#dc3545');
define('INFO_COLOR', '#17a2b8');
define('BACKGROUND_COLOR', '#e3f2fd');
define('DARK_MODE_ENABLED', true);

// Feature flags
define('ENABLE_PRICE_TRACKING', true);
define('ENABLE_COMPARISONS', true);
define('ENABLE_BUDGETS', true);
define('ENABLE_ANALYTICS', true);
define('ENABLE_API', true);
define('ENABLE_MOBILE_APP', false);
define('ENABLE_SOCIAL_SHARING', true);
define('ENABLE_LOCATION_FEATURES', true);
define('ENABLE_BARCODE_SCANNING', true);

// API keys (encrypt these in production!)
define('GOOGLE_MAPS_API_KEY', 'your_google_maps_key');
define('AMAZON_API_KEY', 'your_amazon_api_key');
define('PRICE_TRACKING_API_KEY', 'your_price_api_key');
define('BARCODE_API_KEY', 'your_barcode_api_key');

// Analytics settings
define('ENABLE_GOOGLE_ANALYTICS', false);
define('GA_TRACKING_ID', 'UA-XXXXXXXX-X');
define('ENABLE_ERROR_TRACKING', true);
define('SENTRY_DSN', 'your_sentry_dsn');

// Cache settings
define('CACHE_ENABLED', true);
define('CACHE_DRIVER', 'file'); // 'file', 'redis', 'memcached'
define('CACHE_PREFIX', 'fwp_');
define('CACHE_LIFETIME', 3600); // 1 hour
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);

// Search settings
define('SEARCH_MIN_LENGTH', 3);
define('SEARCH_RESULTS_PER_PAGE', 20);
define('ENABLE_ELASTICSEARCH', false);
define('ELASTICSEARCH_HOST', 'localhost:9200');

// Notification settings
define('NOTIFICATION_CHANNELS', ['email', 'web', 'push']);
define('PUSH_VAPID_PUBLIC_KEY', 'your_vapid_public_key');
define('PUSH_VAPID_PRIVATE_KEY', 'your_vapid_private_key');

// Development/Production mode
define('DEBUG_MODE', false);
define('LOG_ERRORS', true);
define('ERROR_LOG_PATH', __DIR__ . '/logs/errors.log');
define('ACTIVITY_LOG_ENABLED', true);
define('SLOW_QUERY_LOG', true);
define('SLOW_QUERY_TIME', 1); // seconds

// Backup settings
define('BACKUP_ENABLED', true);
define('BACKUP_PATH', __DIR__ . '/backups/');
define('BACKUP_RETENTION_DAYS', 30);

// Import/Export settings
define('EXPORT_FORMATS', ['csv', 'json', 'pdf', 'excel']);
define('IMPORT_FORMATS', ['csv', 'json']);
define('BATCH_SIZE', 100); // for bulk operations

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', SECURE_COOKIES);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

// Database connection function with connection pooling
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true, // Connection pooling
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '" . TIMEZONE . "'"
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die("Connection failed: " . $e->getMessage());
            } else {
                logError("Database connection failed: " . $e->getMessage());
                die("Connection failed. Please contact administrator.");
            }
        }
    }
    
    return $pdo;
}

// Enhanced CSRF token functions with multiple token support
function generateCSRFToken($key = 'default') {
    if (!isset($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }
    
    if (!isset($_SESSION['csrf_tokens'][$key]) || 
        $_SESSION['csrf_tokens'][$key]['time'] + CSRF_TOKEN_LIFETIME < time()) {
        $_SESSION['csrf_tokens'][$key] = [
            'token' => bin2hex(random_bytes(32)),
            'time' => time()
        ];
    }
    
    return $_SESSION['csrf_tokens'][$key]['token'];
}

function verifyCSRFToken($token, $key = 'default') {
    if (!isset($_SESSION['csrf_tokens'][$key])) {
        return false;
    }
    
    $tokenData = $_SESSION['csrf_tokens'][$key];
    
    if ($tokenData['time'] + CSRF_TOKEN_LIFETIME < time()) {
        unset($_SESSION['csrf_tokens'][$key]);
        return false;
    }
    
    return hash_equals($tokenData['token'], $token);
}

// Enhanced sanitization functions
function sanitize($data, $type = 'string') {
    switch ($type) {
        case 'string':
            return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
        case 'int':
            return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
        case 'url':
            return filter_var($data, FILTER_SANITIZE_URL);
        case 'html':
            // Allow some HTML tags
            $allowed = '<p><br><strong><em><u><a><ul><ol><li><blockquote><h3><h4>';
            return strip_tags(trim($data), $allowed);
        default:
            return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

// Input validation functions
function validate($data, $type, $options = []) {
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_VALIDATE_EMAIL) !== false;
        case 'url':
            return filter_var($data, FILTER_VALIDATE_URL) !== false;
        case 'int':
            $min = $options['min'] ?? PHP_INT_MIN;
            $max = $options['max'] ?? PHP_INT_MAX;
            return filter_var($data, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => $min, 'max_range' => $max]
            ]) !== false;
        case 'float':
            return filter_var($data, FILTER_VALIDATE_FLOAT) !== false;
        case 'date':
            $d = DateTime::createFromFormat($options['format'] ?? 'Y-m-d', $data);
            return $d && $d->format($options['format'] ?? 'Y-m-d') === $data;
        case 'regex':
            return preg_match($options['pattern'], $data) === 1;
        default:
            return !empty($data);
    }
}

// Error logging function
function logError($message, $context = []) {
    if (LOG_ERRORS) {
        $logEntry = date('Y-m-d H:i:s') . ' - ' . $message;
        if (!empty($context)) {
            $logEntry .= ' - Context: ' . json_encode($context);
        }
        $logEntry .= PHP_EOL;
        
        error_log($logEntry, 3, ERROR_LOG_PATH);
        
        // Send to Sentry if enabled
        if (ENABLE_ERROR_TRACKING && defined('SENTRY_DSN')) {
            // Sentry integration would go here
        }
    }
}

// Activity logging function
function logActivity($userId, $action, $entityType = null, $entityId = null, $description = null) {
    if (ACTIVITY_LOG_ENABLED) {
        $db = getDBConnection();
        $stmt = $db->prepare("
            INSERT INTO activity_log (user_id, action_type, entity_type, entity_id, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
}

// Rate limiting function
function checkRateLimit($identifier, $limit = API_RATE_LIMIT) {
    if (!ENABLE_API) return true;
    
    $db = getDBConnection();
    $key = 'rate_limit_' . $identifier;
    
    // Simple implementation - enhance with Redis for production
    $stmt = $db->prepare("
        SELECT COUNT(*) as request_count 
        FROM activity_log 
        WHERE ip_address = ? 
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$identifier]);
    $result = $stmt->fetch();
    
    return $result['request_count'] < $limit;
}

// Cache functions
function cacheSet($key, $value, $ttl = CACHE_LIFETIME) {
    if (!CACHE_ENABLED) return false;
    
    $key = CACHE_PREFIX . $key;
    
    switch (CACHE_DRIVER) {
        case 'redis':
            // Redis implementation
            break;
        case 'memcached':
            // Memcached implementation
            break;
        default:
            // File cache
            $cacheFile = __DIR__ . '/cache/' . md5($key) . '.cache';
            $data = [
                'expires' => time() + $ttl,
                'value' => $value
            ];
            return file_put_contents($cacheFile, serialize($data));
    }
}

function cacheGet($key) {
    if (!CACHE_ENABLED) return false;
    
    $key = CACHE_PREFIX . $key;
    
    switch (CACHE_DRIVER) {
        case 'redis':
            // Redis implementation
            break;
        case 'memcached':
            // Memcached implementation
            break;
        default:
            // File cache
            $cacheFile = __DIR__ . '/cache/' . md5($key) . '.cache';
            if (file_exists($cacheFile)) {
                $data = unserialize(file_get_contents($cacheFile));
                if ($data['expires'] > time()) {
                    return $data['value'];
                }
                unlink($cacheFile);
            }
            return false;
    }
}

// Notification helper
function sendNotification($userId, $type, $title, $message, $data = []) {
    $db = getDBConnection();
    
    // Store in database
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, notification_type, title, message, data)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $userId,
        $type,
        $title,
        $message,
        json_encode($data)
    ]);
    
    // Send based on user preferences
    $stmt = $db->prepare("
        SELECT u.email, up.email_frequency, up.preferences_json
        FROM users u
        JOIN user_preferences up ON u.id = up.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user && $user['email_frequency'] === 'instant') {
        // Queue email
        require_once 'email-helper-pro.php';
        $emailHelper = new EmailHelperPro();
        $emailHelper->queueEmail($userId, $type, $title, $message);
    }
    
    return true;
}

// Security headers
if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    
    if (!DEBUG_MODE) {
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net; font-src 'self' cdn.jsdelivr.net; img-src 'self' data: https:;");
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    }
}

// Auto-load classes
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/classes/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});