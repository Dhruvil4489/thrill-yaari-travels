<?php
/**
 * Helper Functions for Thrill Yari Travel Booking System
 * 
 * This file contains common utility functions used throughout the application.
 */

/**
 * Sanitize input string
 * 
 * @param string $data The input string to sanitize
 * @return string Sanitized string
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email address
 * 
 * @param string $email Email address to validate
 * @return bool True if valid, false otherwise
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (10 digits)
 * 
 * @param string $phone Phone number to validate
 * @return bool True if valid, false otherwise
 */
function validate_phone($phone) {
    return preg_match('/^[0-9]{10}$/', $phone) === 1;
}

/**
 * Set flash message in session
 * 
 * @param string $message The message to display
 * @param string $type Type of message (success, error, warning, info)
 */
function set_flash_message($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Get and clear flash message from session
 * 
 * @return array|null Array with 'message' and 'type', or null if no message
 */
function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * Display flash message as HTML
 * 
 * @return string HTML for flash message or empty string
 */
function display_flash_message() {
    $flash = get_flash_message();
    if ($flash) {
        $type_class = 'alert-' . $flash['type'];
        $icon = '';
        switch ($flash['type']) {
            case 'success':
                $icon = '✅';
                break;
            case 'error':
                $icon = '❌';
                break;
            case 'warning':
                $icon = '⚠️';
                break;
            case 'info':
            default:
                $icon = 'ℹ️';
                break;
        }
        return '<div class="alert ' . htmlspecialchars($type_class, ENT_QUOTES, 'UTF-8') . ' alert-dismissible" role="alert">
            ' . $icon . ' ' . htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') . '
            <button type="button" class="btn-close" onclick="this.parentElement.style.display=\'none\'" aria-label="Close">&times;</button>
        </div>';
    }
    return '';
}

/**
 * Check if user is logged in
 * 
 * @return bool True if logged in, false otherwise
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * Get current user ID
 * 
 * @return int User ID or 0 if not logged in
 */
function get_user_id() {
    return $_SESSION['user_id'] ?? 0;
}

/**
 * Require user to be logged in, redirect if not
 * 
 * @param string $redirect_url URL to redirect to if not logged in
 */
function require_login($redirect_url = 'index.php') {
    if (!is_logged_in()) {
        header('Location: ' . $redirect_url);
        exit;
    }
}

/**
 * Generate random PNR/Ticket number
 * 
 * @param string $prefix Prefix for the number (e.g., 'TY', 'IR')
 * @param int $length Length of random number
 * @return string Generated PNR/Ticket number
 */
function generate_pnr($prefix = 'TY', $length = 6) {
    $min = pow(10, $length - 1);
    $max = pow(10, $length) - 1;
    return $prefix . mt_rand($min, $max);
}

/**
 * Format currency (Indian Rupees)
 * 
 * @param float $amount Amount to format
 * @return string Formatted currency string
 */
function format_currency($amount) {
    return '₹ ' . number_format($amount, 2);
}

/**
 * Calculate GST
 * 
 * @param float $amount Base amount
 * @param float $rate GST rate (default 0.05 for 5%)
 * @return float GST amount
 */
function calculate_gst($amount, $rate = 0.05) {
    return round($amount * $rate, 2);
}

/**
 * Escape output for HTML
 * 
 * @param string $string String to escape
 * @return string Escaped string
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect with message
 * 
 * @param string $url URL to redirect to
 * @param string $message Optional message to set as flash
 * @param string $type Type of message
 */
function redirect($url, $message = null, $type = 'info') {
    if ($message !== null) {
        set_flash_message($message, $type);
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Get query parameter safely
 * 
 * @param string $key Parameter key
 * @param mixed $default Default value if not found
 * @return mixed Parameter value or default
 */
function get_query($key, $default = null) {
    return $_GET[$key] ?? $default;
}

/**
 * Get POST parameter safely
 * 
 * @param string $key Parameter key
 * @param mixed $default Default value if not found
 * @return mixed Parameter value or default
 */
function get_post($key, $default = null) {
    return $_POST[$key] ?? $default;
}
?>

