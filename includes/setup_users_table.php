<?php
// Include the database connection file
require_once 'db_connect.php';

echo "<h1>User Setup Script</h1>";

// SQL to create users table - Aligned with physio_db_schema.sql
$sqlCreateTable = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    email VARCHAR(255) NULL UNIQUE,
    role VARCHAR(50) NULL,
    is_active TINYINT(1) DEFAULT 1, -- Kept from original PHP, not in SQL schema
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_username (username),
    INDEX idx_users_email (email),
    INDEX idx_role (role) -- Kept from original PHP, not in SQL schema but useful
)";

try {
    $pdo->exec($sqlCreateTable);
    echo "<p>Users table created successfully or already exists.</p>";

    // Helper functions (idempotent schema modifications)
    function columnExists($pdo, $tableName, $columnName) {
        $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tableName, $columnName]);
        return $stmt->fetchColumn() > 0;
    }

    function indexExists($pdo, $tableName, $indexName) {
        $sql = "SHOW INDEX FROM `$tableName` WHERE Key_name = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$indexName]);
        return $stmt->fetch() !== false;
    }

    $tableName = 'users';

    // Schema items to check/add (columns and indexes)
    // Note: `is_active` and `idx_role` are kept from the original PHP script for now.
    // `first_name`, `last_name` are kept instead of `full_name` from SQL schema as per instruction.
    $schemaItems = [
        // Columns
        ['type' => 'column', 'name' => 'username', 'definition' => 'VARCHAR(100) NOT NULL UNIQUE'],
        ['type' => 'column', 'name' => 'email', 'definition' => 'VARCHAR(255) NULL UNIQUE'],
        ['type' => 'column', 'name' => 'role', 'definition' => 'VARCHAR(50) NULL'], // Changed to NULL
        ['type' => 'column', 'name' => 'created_at', 'definition' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
        ['type' => 'column', 'name' => 'updated_at', 'definition' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
        // Indexes
        ['type' => 'index', 'name' => 'idx_users_username', 'columns' => '(username)'],
        ['type' => 'index', 'name' => 'idx_users_email', 'columns' => '(email)'],
    ];

    foreach ($schemaItems as $item) {
        switch ($item['type']) {
            case 'column':
                if (!columnExists($pdo, $tableName, $item['name'])) {
                    $sqlAlter = "ALTER TABLE `$tableName` ADD COLUMN `" . $item['name'] . "` " . $item['definition'];
                    $pdo->exec($sqlAlter);
                    echo "<p>Column `" . htmlspecialchars($item['name']) . "` added to $tableName table.</p>";
                } else {
                    // Optionally, modify existing columns if definition changes, e.g., VARCHAR length, NULL status
                    // For this script, we'll focus on adding if missing.
                    // Example: $sqlModify = "ALTER TABLE `$tableName` MODIFY COLUMN `" . $item['name'] . "` " . $item['definition'];
                    // $pdo->exec($sqlModify);
                    echo "<p>Column `" . htmlspecialchars($item['name']) . "` already exists in $tableName table.</p>";
                }
                break;
            case 'index':
                if (!indexExists($pdo, $tableName, $item['name'])) {
                     // Ensure columns for index exist before attempting to create index
                    $canCreateIndex = true;
                    $cols = explode(',', str_replace(['(', ')', ' DESC', ' ASC', ' '], '', $item['columns']));
                    foreach ($cols as $col) {
                        if (!columnExists($pdo, $tableName, $col)) {
                            $canCreateIndex = false;
                            echo "<p>Skipping Index `" . htmlspecialchars($item['name']) . "` because column `$col` is missing.</p>";
                            break;
                        }
                    }
                    if ($canCreateIndex) {
                        $sqlAlterIndex = "ALTER TABLE `$tableName` ADD INDEX `" . $item['name'] . "` " . $item['columns'];
                        $pdo->exec($sqlAlterIndex);
                        echo "<p>Index `" . htmlspecialchars($item['name']) . "` added to $tableName table.</p>";
                    }
                } else {
                    echo "<p>Index `" . htmlspecialchars($item['name']) . "` already exists in $tableName table.</p>";
                }
                break;
        }
    }


    // Array of default users - added email
    $defaultUsers = [
        ['username' => 'admin', 'password' => 'adminpass', 'role' => 'admin', 'first_name' => 'Admin', 'last_name' => 'User', 'email' => 'admin@example.com'],
        ['username' => 'clinician1', 'password' => 'clinicpass', 'role' => 'clinician', 'first_name' => 'Clinician', 'last_name' => 'One', 'email' => 'clinician1@example.com'],
        ['username' => 'nurse1', 'password' => 'nursepass', 'role' => 'nurse', 'first_name' => 'Nurse', 'last_name' => 'One', 'email' => 'nurse1@example.com'],
        ['username' => 'reception1', 'password' => 'receptpass', 'role' => 'receptionist', 'first_name' => 'Reception', 'last_name' => 'One', 'email' => 'reception1@example.com'],
    ];

    // Prepare statement for inserting users - added email
    $sqlInsertUser = "INSERT INTO users (username, password_hash, role, first_name, last_name, email) VALUES (?, ?, ?, ?, ?, ?)";
    $stmtInsert = $pdo->prepare($sqlInsertUser);

    // Prepare statement for checking if user exists
    $sqlCheckUser = "SELECT id FROM users WHERE username = ?";
    $stmtCheck = $pdo->prepare($sqlCheckUser);

    foreach ($defaultUsers as $user) {
        $stmtCheck->execute([$user['username']]);

        if ($stmtCheck->fetchColumn() > 0) {
            echo "<p>User '" . htmlspecialchars($user['username']) . "' already exists.</p>";
        } else {
            $passwordHash = password_hash($user['password'], PASSWORD_DEFAULT);
            $stmtInsert->execute([
                $user['username'],
                $passwordHash,
                $user['role'],
                $user['first_name'],
                $user['last_name'],
                $user['email'] // Added email
            ]);
            echo "<p>User '" . htmlspecialchars($user['username']) . "' inserted successfully.</p>";
        }
    }
    echo "<p>Users table schema check and default user population completed.</p>";

} catch (PDOException $e) {
    echo "<p>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Close the database connection
$pdo = null;
echo "<p>User setup script finished.</p>";
?>
