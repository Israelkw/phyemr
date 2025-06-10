<?php
// Include the database connection file
require_once 'db_connect.php';

echo "<h1>Patients Table Setup Script</h1>";

// SQL to create patients table - Aligned with physio_db_schema.sql and instructions
$sqlCreateTable = "
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NULL, -- Changed from NOT NULL
    last_name VARCHAR(100) NULL,  -- Changed from NOT NULL
    date_of_birth DATE NULL,      -- Changed from NOT NULL
    sex VARCHAR(20) NULL,
    address TEXT NULL,
    phone_number VARCHAR(50) NULL,
    email VARCHAR(255) NULL UNIQUE,
    assigned_clinician_id INT NULL, -- Kept from original PHP
    registered_by_user_id INT NOT NULL, -- Kept from original PHP
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Replaces registration_date
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_patients_assigned_clinician FOREIGN KEY (assigned_clinician_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_patients_registered_by_user FOREIGN KEY (registered_by_user_id) REFERENCES users(id) ON DELETE CASCADE,

    INDEX idx_patients_name (last_name, first_name),
    INDEX idx_patients_email (email),
    INDEX idx_assigned_clinician (assigned_clinician_id) -- Kept from original PHP
)";
// Note: `registered_by_user_id` and `assigned_clinician_id` and its index `idx_assigned_clinician` are kept as per instructions/original PHP script.
// `created_at` replaces `registration_date`.

try {
    $pdo->exec($sqlCreateTable);
    echo "<p>Patients table created successfully or already exists using base schema.</p>";

    // Helper functions
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

    function constraintExists($pdo, $tableName, $constraintName) {
        $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tableName, $constraintName]);
        return $stmt->fetchColumn() > 0;
    }

    $tableName = 'patients';

    // Schema items to check/add/modify
    $schemaItems = [
        // Columns to add
        ['type' => 'column', 'name' => 'first_name', 'definition' => 'VARCHAR(100) NULL'], // Ensure nullability
        ['type' => 'column', 'name' => 'last_name', 'definition' => 'VARCHAR(100) NULL'],   // Ensure nullability
        ['type' => 'column', 'name' => 'date_of_birth', 'definition' => 'DATE NULL'],       // Ensure nullability
        ['type' => 'column', 'name' => 'sex', 'definition' => 'VARCHAR(20) NULL'],
        ['type' => 'column', 'name' => 'address', 'definition' => 'TEXT NULL'],
        ['type' => 'column', 'name' => 'phone_number', 'definition' => 'VARCHAR(50) NULL'],
        ['type' => 'column', 'name' => 'email', 'definition' => 'VARCHAR(255) NULL UNIQUE'],
        ['type' => 'column', 'name' => 'created_at', 'definition' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
        ['type' => 'column', 'name' => 'updated_at', 'definition' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
        // Columns to keep (ensure they exist, definitions from CREATE TABLE statement are leading)
        ['type' => 'column', 'name' => 'assigned_clinician_id', 'definition' => 'INT NULL'],
        ['type' => 'column', 'name' => 'registered_by_user_id', 'definition' => 'INT NOT NULL'],

        // Foreign Keys (ensure names are as specified)
        ['type' => 'fk', 'name' => 'fk_patients_assigned_clinician', 'column' => 'assigned_clinician_id', 'definition' => "ADD CONSTRAINT fk_patients_assigned_clinician FOREIGN KEY (assigned_clinician_id) REFERENCES users(id) ON DELETE SET NULL"],
        ['type' => 'fk', 'name' => 'fk_patients_registered_by_user', 'column' => 'registered_by_user_id', 'definition' => "ADD CONSTRAINT fk_patients_registered_by_user FOREIGN KEY (registered_by_user_id) REFERENCES users(id) ON DELETE CASCADE"],

        // Indexes
        ['type' => 'index', 'name' => 'idx_patients_name', 'columns' => '(last_name, first_name)'],
        ['type' => 'index', 'name' => 'idx_patients_email', 'columns' => '(email)'],
        ['type' => 'index', 'name' => 'idx_assigned_clinician', 'columns' => '(assigned_clinician_id)'], // Kept from original
    ];

    // Drop old `registration_date` if `created_at` exists
    if (columnExists($pdo, $tableName, 'created_at') && columnExists($pdo, $tableName, 'registration_date')) {
        // Before dropping, you might want to migrate data if necessary. For this script, we assume it's a schema alignment.
        // $pdo->exec("UPDATE patients SET created_at = registration_date WHERE created_at IS NULL");
        $pdo->exec("ALTER TABLE $tableName DROP COLUMN registration_date");
        echo "<p>Column `registration_date` dropped from $tableName table (replaced by `created_at`).</p>";
    }

    // Drop old index `idx_lastname_firstname` if `idx_patients_name` exists or will be created
    if (indexExists($pdo, $tableName, 'idx_lastname_firstname') && !indexExists($pdo, $tableName, 'idx_patients_name')) {
         // This logic is a bit tricky: if the new one isn't there yet but old one is, we might recreate with new name.
         // The CREATE TABLE statement already defines idx_patients_name.
         // We'll ensure the old one is dropped if the new one IS present.
    }
    if (indexExists($pdo, $tableName, 'idx_patients_name') && indexExists($pdo, $tableName, 'idx_lastname_firstname') && 'idx_patients_name' !== 'idx_lastname_firstname') {
        $pdo->exec("ALTER TABLE $tableName DROP INDEX idx_lastname_firstname");
        echo "<p>Index `idx_lastname_firstname` dropped (replaced by `idx_patients_name`).</p>";
    }


    foreach ($schemaItems as $item) {
        switch ($item['type']) {
            case 'column':
                if (!columnExists($pdo, $tableName, $item['name'])) {
                    $sqlAlter = "ALTER TABLE `$tableName` ADD COLUMN `" . $item['name'] . "` " . $item['definition'];
                    $pdo->exec($sqlAlter);
                    echo "<p>Column `" . htmlspecialchars($item['name']) . "` added to $tableName table.</p>";
                } else {
                    // Modify column to ensure it matches the definition (e.g. nullability)
                    // This is important for VARCHAR lengths and NULL/NOT NULL constraints
                    $sqlModify = "ALTER TABLE `$tableName` MODIFY COLUMN `" . $item['name'] . "` " . $item['definition'];
                    try {
                        $pdo->exec($sqlModify);
                        echo "<p>Column `" . htmlspecialchars($item['name']) . "` modified to match schema in $tableName table.</p>";
                    } catch (PDOException $e) {
                        echo "<p>Error modifying column `" . htmlspecialchars($item['name']) . "`: " . htmlspecialchars($e->getMessage()) . ". It might be part of an index or FK constraint that needs to be dropped and re-added.</p>";
                    }
                }
                break;
            case 'fk':
                if (!constraintExists($pdo, $tableName, $item['name'])) {
                    // Drop old FK if it was named differently and references the same column
                    // This part is complex and error-prone, usually handled by schema migration tools.
                    // For now, we just add if the specific name doesn't exist.
                    if (columnExists($pdo, $tableName, $item['column'])) {
                        $sqlAlterFK = "ALTER TABLE `$tableName` " . $item['definition'];
                        $pdo->exec($sqlAlterFK);
                        echo "<p>Foreign Key `" . htmlspecialchars($item['name']) . "` added to $tableName table.</p>";
                    } else {
                        echo "<p>Skipping FK `" . htmlspecialchars($item['name']) . "` because column `" . htmlspecialchars($item['column']) . "` is missing.</p>";
                    }
                } else {
                    echo "<p>Foreign Key `" . htmlspecialchars($item['name']) . "` already exists in $tableName table.</p>";
                }
                break;
            case 'index':
                if (!indexExists($pdo, $tableName, $item['name'])) {
                    $canCreateIndex = true;
                    $cols = explode(',', str_replace(['(', ')', ' DESC', ' ASC', ' '], '', $item['columns']));
                    foreach ($cols as $col) {
                        if (!columnExists($pdo, $tableName, trim($col))) { // trim space for multi-column indexes
                            $canCreateIndex = false;
                            echo "<p>Skipping Index `" . htmlspecialchars($item['name']) . "` for $tableName table because column `$col` is missing.</p>";
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
    echo "<p>Patients table schema check completed.</p>";

} catch (PDOException $e) {
    echo "<p>Database error during patients table setup: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Close the database connection
$pdo = null;
echo "<p>Patients table setup script finished.</p>";
?>
