<?php
// config.php - Store this file outside of public web directory for security
// Alternatively, use environment variables for sensitive data

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'family_wishlist');
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
define('EMAIL_FROM_NAME', 'Family Wishlist');

// Application settings
define('SITE_NAME', 'Family Wishlist');
define('SITE_URL', 'https://yoursite.com');
define('SESSION_LIFETIME', 3600); // 1 hour
define('TIMEZONE', 'America/New_York');

// Security settings
define('SECURE_COOKIES', true);
define('CSRF_TOKEN_LIFETIME', 3600);
define('PASSWORD_MIN_LENGTH', 8);

// File upload settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// Theme settings
define('PRIMARY_COLOR', '#007bff');
define('SECONDARY_COLOR', '#6c757d');
define('BACKGROUND_COLOR', '#e3f2fd'); // Light blue background

// Development/Production mode
define('DEBUG_MODE', false);
define('LOG_ERRORS', true);
define('ERROR_LOG_PATH', __DIR__ . '/logs/errors.log');

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

// Database connection function
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            die("Connection failed: " . $e->getMessage());
        } else {
            die("Connection failed. Please contact administrator.");
        }
    }
}

// CSRF token functions
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    if ($_SESSION['csrf_token_time'] + CSRF_TOKEN_LIFETIME < time()) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Sanitization functions
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function sanitizeEmail($email) {
    return filter_var($email, FILTER_SANITIZE_EMAIL);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// For production, add Content Security Policy
if (!DEBUG_MODE) {
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net; font-src 'self' cdn.jsdelivr.net;");
}