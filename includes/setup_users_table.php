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

if ($mysqli->query($sqlCreateTable)) {
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
    $stmtInsert = $mysqli->prepare($sqlInsertUser);

    if (!$stmtInsert) {
        echo "<p>Error preparing insert statement: " . htmlspecialchars($mysqli->error) . "</p>";
    } else {
        // Prepare statement for checking if user exists
        $sqlCheckUser = "SELECT id FROM users WHERE username = ?";
        $stmtCheck = $mysqli->prepare($sqlCheckUser);

        if (!$stmtCheck) {
            echo "<p>Error preparing select statement: " . htmlspecialchars($mysqli->error) . "</p>";
        } else {
            foreach ($defaultUsers as $user) {
                // Check if user already exists
                $stmtCheck->bind_param("s", $user['username']);
                $stmtCheck->execute();
                $stmtCheck->store_result();

                if ($stmtCheck->num_rows > 0) {
                    echo "<p>User '" . htmlspecialchars($user['username']) . "' already exists.</p>";
                } else {
                    // Hash the password
                    $passwordHash = password_hash($user['password'], PASSWORD_DEFAULT);

                    // Bind parameters and execute insert statement
                    $stmtInsert->bind_param("sssss", $user['username'], $passwordHash, $user['role'], $user['first_name'], $user['last_name']);
                    if ($stmtInsert->execute()) {
                        echo "<p>User '" . htmlspecialchars($user['username']) . "' inserted successfully.</p>";
                    } else {
                        echo "<p>Error inserting user '" . htmlspecialchars($user['username']) . "': " . htmlspecialchars($stmtInsert->error) . "</p>";
                    }
                }
            }
            $stmtCheck->close();
        }
        $stmtInsert->close();
    }
} else {
    echo "<p>Error creating users table: " . htmlspecialchars($mysqli->error) . "</p>";
}

// Close the database connection
$mysqli->close();
echo "<p>Setup script finished.</p>";
?>
