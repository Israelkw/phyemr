<?php

// Centralized Database Connection

// Require the database configuration file once.
// Using __DIR__ to ensure path is correct regardless of where this file is included from,
// though for includes/db_config.php it's a sibling.
require_once __DIR__ . '/db_config.php';

// Attempt to connect to MySQL database
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Check connection
if ($mysqli->connect_error) {
    // Log the detailed error to the server's error log
    error_log("Database Connection Failed: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
    
    // For AJAX requests or APIs, it's often better to output a JSON error.
    // For general pages, a user-friendly HTML message might be better,
    // but since this can be included anywhere, JSON is a safer default for die().
    // If this script's failure leads to a broken page, the JSON might not be seen directly by user,
    // but it avoids breaking JSON responses for scripts that expect them.
    header('Content-Type: application/json'); // Ensure header is set for JSON output
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection error. Please check server logs or contact administrator.'
    ]));
}

// Set charset to utf8mb4 for broader character support (optional but recommended)
if (!$mysqli->set_charset("utf8mb4")) {
    error_log("Error loading character set utf8mb4: " . $mysqli->error);
    // Continue execution even if charset setting fails, but log it.
}

?>
