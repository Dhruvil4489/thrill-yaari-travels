<?php
/**
 * Database Connection File
 * 
 * This file handles the database connection for the application.
 * It uses the configuration from config.php
 */

// Include configuration if not already included
if (!defined('DB_HOST')) {
    require_once 'config.php';
}

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        die("Connection failed: " . $conn->connect_error);
    } else {
        die("Database connection failed. Please try again later.");
    }
}

// Set charset to utf8mb4 for proper Unicode support
$conn->set_charset("utf8mb4");
?>
