<?php

// Centralized Database Connection

// Require the database configuration file once.
// Using __DIR__ to ensure path is correct regardless of where this file is included from,
// though for includes/db_config.php it's a sibling.
require_once __DIR__ . '/db_config.php';

// DSN (Data Source Name) for PDO connection
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

// Options for PDO connection
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Enable exceptions for errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Set default fetch mode to associative array
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Disable emulation of prepared statements
];

try {
    // Attempt to connect to MySQL database using PDO
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
} catch (PDOException $e) {
    // Log the detailed error to the server's error log
    error_log("Database Connection Failed: " . $e->getMessage());
    
    // For AJAX requests or APIs, it's often better to output a JSON error.
    // For general pages, a user-friendly HTML message might be better,
    // but since this can be included anywhere, JSON is a safer default for die().
    // If this script's failure leads to a broken page, the JSON might not be seen directly by user,
    // but it avoids breaking JSON responses for scripts that expect them.
    header('Content-Type: application/json'); // Ensure header is set for JSON output
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection error. Please check server logs or contact administrator. Details: ' . $e->getMessage()
    ]));
}

// The $pdo variable now holds the PDO connection object.
// No need to set charset separately as it's part of the DSN.

?>
