<?php
// Include the database connection file
require_once 'db_connect.php';

echo "<h1>User Setup Script</h1>";

// SQL to create users table
$sqlCreateTable = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_role (role)
)";

try {
    $pdo->exec($sqlCreateTable);
    echo "<p>Users table created successfully or already exists.</p>";

    // Array of default users
    $defaultUsers = [
        ['username' => 'admin', 'password' => 'adminpass', 'role' => 'admin', 'first_name' => 'Admin', 'last_name' => 'User'],
        ['username' => 'clinician1', 'password' => 'clinicpass', 'role' => 'clinician', 'first_name' => 'Clinician', 'last_name' => 'One'],
        ['username' => 'nurse1', 'password' => 'nursepass', 'role' => 'nurse', 'first_name' => 'Nurse', 'last_name' => 'One'],
        ['username' => 'reception1', 'password' => 'receptpass', 'role' => 'receptionist', 'first_name' => 'Reception', 'last_name' => 'One'],
    ];

    // Prepare statement for inserting users
    $sqlInsertUser = "INSERT INTO users (username, password_hash, role, first_name, last_name) VALUES (?, ?, ?, ?, ?)";
    $stmtInsert = $pdo->prepare($sqlInsertUser); // Changed to $pdo

    // Prepare statement for checking if user exists
    $sqlCheckUser = "SELECT id FROM users WHERE username = ?"; // Using ? for positional placeholder
    $stmtCheck = $pdo->prepare($sqlCheckUser); // Changed to $pdo

    foreach ($defaultUsers as $user) {
        // Check if user already exists
        $stmtCheck->execute([$user['username']]); // Pass username as array for execution

        if ($stmtCheck->fetchColumn() > 0) { // Check if any row was returned (user exists)
            echo "<p>User '" . htmlspecialchars($user['username']) . "' already exists.</p>";
        } else {
            // Hash the password
            $passwordHash = password_hash($user['password'], PASSWORD_DEFAULT);

            // Execute insert statement with an array of values
            $stmtInsert->execute([
                $user['username'],
                $passwordHash,
                $user['role'],
                $user['first_name'],
                $user['last_name']
            ]);
            echo "<p>User '" . htmlspecialchars($user['username']) . "' inserted successfully.</p>";
        }
    }
    // No need to explicitly close $stmtCheck and $stmtInsert with PDO in this script structure,
    // they will be closed when the script ends or if the variables are reassigned.

} catch (PDOException $e) {
    echo "<p>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Close the database connection (optional with PDO, but good practice)
$pdo = null;
echo "<p>Setup script finished.</p>";
?>
