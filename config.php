<?php
/**
 * Configuration File for Thrill Yari Travel Booking System
 * 
 * This file contains all configuration settings for the application.
 * Update these values according to your environment.
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'thrill_yari');

// Application Configuration
define('APP_NAME', 'Thrill Yari');
define('APP_URL', 'http://localhost/WEBPROJECT');
define('UPLOAD_DIR', 'uploads/');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2MB in bytes

// Session Configuration
define('SESSION_LIFETIME', 3600); // 1 hour in seconds
define('COOKIE_LIFETIME', 86400 * 7); // 7 days in seconds

// Security Configuration
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 8);

// Payment Configuration (for future integration)
define('PAYMENT_GATEWAY', 'test'); // 'test', 'razorpay', 'paytm', etc.
define('CURRENCY', 'INR');
define('GST_RATE', 0.05); // 5% GST
define('CONVENIENCE_FEE', 25); // Fixed convenience fee

// Email Configuration (for future integration)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('FROM_EMAIL', 'noreply@thrillyari.com');
define('FROM_NAME', 'Thrill Yari');

// Error Reporting (set to false in production)
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    session_start();
}
?>

