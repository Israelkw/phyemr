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

if ($mysqli->query($sqlCreateTable)) {
    echo "<p>Patients table created successfully or already exists.</p>";

    // Helper function to check if a column exists
    function columnExists($mysqli, $tableName, $columnName) {
        $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            echo "<p>Error preparing column check statement: " . htmlspecialchars($mysqli->error) . "</p>";
            return false; // Consider true to prevent alter attempts on error
        }
        $stmt->bind_param("ss", $tableName, $columnName);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return $count > 0;
    }

    // Helper function to check if an index exists
    function indexExists($mysqli, $tableName, $indexName) {
        $sql = "SHOW INDEX FROM " . $mysqli->real_escape_string($tableName) . " WHERE Key_name = ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            echo "<p>Error preparing index check statement: " . htmlspecialchars($mysqli->error) . "</p>";
            return false; // Consider true to prevent alter attempts on error
        }
        $stmt->bind_param("s", $indexName);
        $stmt->execute();
        $stmt->store_result();
        $count = $stmt->num_rows;
        $stmt->close();
        return $count > 0;
    }

    // Helper function to check if a foreign key constraint exists
    function constraintExists($mysqli, $tableName, $constraintName) {
        $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            echo "<p>Error preparing constraint check statement: " . htmlspecialchars($mysqli->error) . "</p>";
            return false; // Consider true to prevent alter attempts on error
        }
        $stmt->bind_param("ss", $tableName, $constraintName);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return $count > 0;
    }

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
        if (isset($item['column']) && !columnExists($mysqli, 'patients', $item['column'])) {
            $sqlAlter = "ALTER TABLE patients ADD COLUMN " . $item['column'] . " " . $item['definition'];
            if ($mysqli->query($sqlAlter)) {
                echo "<p>Column " . htmlspecialchars($item['column']) . " added successfully.</p>";
            } else {
                echo "<p>Error adding column " . htmlspecialchars($item['column']) . ": " . htmlspecialchars($mysqli->error) . "</p>";
            }
        }

        // Check and add Foreign Key
        if (isset($item['fk']) && !constraintExists($mysqli, 'patients', $item['fk']['name'])) {
            // Ensure the column exists before adding FK, especially if column add failed
            if (isset($item['column']) && columnExists($mysqli, 'patients', $item['column'])) {
                 $sqlAlterFK = "ALTER TABLE patients ADD CONSTRAINT " . $item['fk']['name'] . " " . $item['fk']['definition'];
                if ($mysqli->query($sqlAlterFK)) {
                    echo "<p>Foreign Key " . htmlspecialchars($item['fk']['name']) . " added successfully.</p>";
                } else {
                    echo "<p>Error adding Foreign Key " . htmlspecialchars($item['fk']['name']) . ": " . htmlspecialchars($mysqli->error) . "</p>";
                }
            } else if (isset($item['column'])) {
                 echo "<p>Skipping FK " . htmlspecialchars($item['fk']['name']) . " because column " . htmlspecialchars($item['column']) . " might be missing.</p>";
            }
        }
        
        // Check and add Index
        if (isset($item['index']) && !indexExists($mysqli, 'patients', $item['index']['name'])) {
            // Ensure column(s) exist before adding index
            $canAddIndex = true;
            if (isset($item['column'])) { // Simple check for single column index
                if(!columnExists($mysqli, 'patients', $item['column'])) {
                    $canAddIndex = false;
                    echo "<p>Skipping Index " . htmlspecialchars($item['index']['name']) . " because column " . htmlspecialchars($item['column']) . " might be missing.</p>";
                }
            } else { // For multi-column indexes like idx_lastname_firstname, assume columns from CREATE TABLE exist
                 if ($item['index']['name'] === 'idx_lastname_firstname') {
                    if (!columnExists($mysqli, 'patients', 'last_name') || !columnExists($mysqli, 'patients', 'first_name')) {
                        $canAddIndex = false;
                        echo "<p>Skipping Index " . htmlspecialchars($item['index']['name']) . " because one or more key columns might be missing.</p>";
                    }
                 }
            }

            if ($canAddIndex) {
                $sqlAlterIndex = "ALTER TABLE patients ADD INDEX " . $item['index']['name'] . " " . $item['index']['columns'];
                if ($mysqli->query($sqlAlterIndex)) {
                    echo "<p>Index " . htmlspecialchars($item['index']['name']) . " added successfully.</p>";
                } else {
                    echo "<p>Error adding Index " . htmlspecialchars($item['index']['name']) . ": " . htmlspecialchars($mysqli->error) . "</p>";
                }
            }
        }
    }

} else {
    echo "<p>Error creating patients table: " . htmlspecialchars($mysqli->error) . "</p>";
}

// Close the database connection
$mysqli->close();
echo "<p>Patients table setup script finished.</p>";
?>
