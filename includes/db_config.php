<?php

// IMPORTANT: Database Configuration Settings
// -------------------------------------------
// For security, it is STRONGLY RECOMMENDED to use environment variables for database credentials
// in production environments. The fallback values provided below are placeholders for development
// and ARE NOT SECURE for production.
//
// How to set environment variables:
// 1. Server Configuration: Set them in your web server (Apache, Nginx) configuration.
//    Example for Apache (.htaccess or httpd.conf): SetEnv DB_HOST your_host
// 2. .env Files: Use a library (e.g., `vlucas/phpdotenv`) to load variables from a `.env` file
//    (create a `.env` file in the project root, add it to .gitignore).
//    Example .env file content:
//    DB_HOST="your_production_host"
//    DB_USER="your_production_user"
//    DB_PASSWORD="your_production_password"
//    DB_NAME="your_production_db_name"
// 3. System Environment: Set them directly in your operating system's environment.
//
// IF YOU ARE NOT USING ENVIRONMENT VARIABLES (e.g., for local development only):
// Ensure you replace the placeholder values below with your actual local database credentials.
// DO NOT commit actual credentials to version control if they are hardcoded here.

// Database Host (e.g., 'localhost' or '127.0.0.1')
// Attempts to get DB_HOST from environment variables, otherwise falls back to 'localhost'.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');

// Database Username
// Attempts to get DB_USER from environment variables, otherwise falls back to 'your_db_user'.
define('DB_USER', getenv('DB_USER') ?: 'your_db_user');

// Database Password
// Attempts to get DB_PASSWORD from environment variables, otherwise falls back to 'your_db_password'.
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'your_db_password');

// Database Name
// Attempts to get DB_NAME from environment variables, otherwise falls back to 'your_db_name'.
define('DB_NAME', getenv('DB_NAME') ?: 'your_db_name');

?>
