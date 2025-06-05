<?php
// Include the database connection file
require_once 'db_connect.php';

echo "<h1>Patients Table Setup Script</h1>";

// SQL to create patients table
$sqlCreateTable = "
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    assigned_clinician_id INT NULL,
    registered_by_user_id INT NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_clinician_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (registered_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_assigned_clinician (assigned_clinician_id),
    INDEX idx_lastname_firstname (last_name, first_name)
)";

try {
    // Helper function to check if a column exists (PDO version)
    function columnExists($pdo, $tableName, $columnName) {
        $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tableName, $columnName]);
        return $stmt->fetchColumn() > 0;
    }

    // Helper function to check if an index exists (PDO version)
    function indexExists($pdo, $tableName, $indexName) {
        // Note: $tableName is used directly in the SQL string.
        // In this script, $tableName is always 'patients', so it's safe.
        // For dynamic table names, consider whitelisting or quoting.
        $sql = "SHOW INDEX FROM `$tableName` WHERE Key_name = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$indexName]);
        return $stmt->fetch() !== false; // If fetch() returns a row, the index exists
    }

    // Helper function to check if a foreign key constraint exists (PDO version)
    function constraintExists($pdo, $tableName, $constraintName) {
        $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tableName, $constraintName]);
        return $stmt->fetchColumn() > 0;
    }

    $pdo->exec($sqlCreateTable);
    echo "<p>Patients table created successfully or already exists.</p>";

    // Columns, FKs, and Indexes to check/add
    $columnsAndConstraints = [
        [
            'column' => 'assigned_clinician_id',
            'definition' => 'INT NULL',
            'index' => ['name' => 'idx_assigned_clinician', 'columns' => '(assigned_clinician_id)'],
            'fk' => ['name' => 'fk_patients_assigned_clinician', 'definition' => 'FOREIGN KEY (assigned_clinician_id) REFERENCES users(id) ON DELETE SET NULL']
        ],
        [
            'column' => 'registered_by_user_id',
            'definition' => 'INT NOT NULL',
            'fk' => ['name' => 'fk_patients_registered_by_user', 'definition' => 'FOREIGN KEY (registered_by_user_id) REFERENCES users(id) ON DELETE CASCADE']
        ],
        [
            'column' => 'registration_date',
            'definition' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        // Index for last_name, first_name (already in CREATE TABLE, but good to check)
        [
            'index' => ['name' => 'idx_lastname_firstname', 'columns' => '(last_name, first_name)']
        ]
    ];

    foreach ($columnsAndConstraints as $item) {
        // Check and add column
        if (isset($item['column']) && !columnExists($pdo, 'patients', $item['column'])) {
            $sqlAlter = "ALTER TABLE patients ADD COLUMN `" . $item['column'] . "` " . $item['definition'];
            $pdo->exec($sqlAlter);
            echo "<p>Column " . htmlspecialchars($item['column']) . " added successfully.</p>";
        }

        // Check and add Foreign Key
        if (isset($item['fk']) && !constraintExists($pdo, 'patients', $item['fk']['name'])) {
            if (isset($item['column']) && columnExists($pdo, 'patients', $item['column'])) {
                 $sqlAlterFK = "ALTER TABLE patients ADD CONSTRAINT `" . $item['fk']['name'] . "` " . $item['fk']['definition'];
                $pdo->exec($sqlAlterFK);
                echo "<p>Foreign Key " . htmlspecialchars($item['fk']['name']) . " added successfully.</p>";
            } else if (isset($item['column'])) {
                 echo "<p>Skipping FK " . htmlspecialchars($item['fk']['name']) . " because column " . htmlspecialchars($item['column']) . " might be missing or its check failed.</p>";
            }
        }
        
        // Check and add Index
        if (isset($item['index']) && !indexExists($pdo, 'patients', $item['index']['name'])) {
            $canAddIndex = true;
            if (isset($item['column'])) {
                if(!columnExists($pdo, 'patients', $item['column'])) {
                    $canAddIndex = false;
                    echo "<p>Skipping Index " . htmlspecialchars($item['index']['name']) . " because column " . htmlspecialchars($item['column']) . " might be missing or its check failed.</p>";
                }
            } else {
                 if ($item['index']['name'] === 'idx_lastname_firstname') {
                    if (!columnExists($pdo, 'patients', 'last_name') || !columnExists($pdo, 'patients', 'first_name')) {
                        $canAddIndex = false;
                        echo "<p>Skipping Index " . htmlspecialchars($item['index']['name']) . " because one or more key columns might be missing or their checks failed.</p>";
                    }
                 }
            }

            if ($canAddIndex) {
                $sqlAlterIndex = "ALTER TABLE patients ADD INDEX `" . $item['index']['name'] . "` " . $item['index']['columns'];
                $pdo->exec($sqlAlterIndex);
                echo "<p>Index " . htmlspecialchars($item['index']['name']) . " added successfully.</p>";
            }
        }
    }

} catch (PDOException $e) {
    echo "<p>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Close the database connection
$pdo = null;
echo "<p>Patients table setup script finished.</p>";
?>
