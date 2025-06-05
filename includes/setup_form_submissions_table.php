<?php
// Include the database connection file
require_once 'db_connect.php';

echo "<h1>Patient Form Submissions Table Setup Script</h1>";

// SQL to create patient_form_submissions table
$sqlCreateTable = "
CREATE TABLE IF NOT EXISTS patient_form_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    form_name VARCHAR(255) NOT NULL COMMENT 'e.g., cervical.html, basic_info.html',
    form_directory VARCHAR(255) NOT NULL COMMENT 'e.g., patient_evaluation_form, patient_general_info',
    submission_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_by_user_id INT NOT NULL,
    form_data JSON NOT NULL COMMENT 'To store all submitted form fields',
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_patient_form_timestamp (patient_id, submission_timestamp DESC),
    INDEX idx_form_name_directory (form_name, form_directory)
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
        // $tableName is used directly in SQL, ensure it's safe (here it's hardcoded)
        $sql = "SHOW INDEX FROM `$tableName` WHERE Key_name = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$indexName]);
        return $stmt->fetch() !== false; // If fetch() returns a row, index exists
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
    echo "<p>Patient Form Submissions table created successfully or already exists.</p>";

    // Define table name
    $tableName = 'patient_form_submissions';

    // Columns, FKs, and Indexes to check/add
    $schemaItems = [
        // Columns
        ['type' => 'column', 'name' => 'patient_id', 'definition' => 'INT NOT NULL'],
        ['type' => 'column', 'name' => 'form_name', 'definition' => "VARCHAR(255) NOT NULL COMMENT 'e.g., cervical.html, basic_info.html'"],
        ['type' => 'column', 'name' => 'form_directory', 'definition' => "VARCHAR(255) NOT NULL COMMENT 'e.g., patient_evaluation_form, patient_general_info'"],
        ['type' => 'column', 'name' => 'submission_timestamp', 'definition' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
        ['type' => 'column', 'name' => 'submitted_by_user_id', 'definition' => 'INT NOT NULL'],
        ['type' => 'column', 'name' => 'form_data', 'definition' => "JSON NOT NULL COMMENT 'To store all submitted form fields'"],
        
        // Foreign Keys
        ['type' => 'fk', 'name' => 'fk_submission_patient', 'column' => 'patient_id', 'definition' => "ADD CONSTRAINT fk_submission_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE"],
        ['type' => 'fk', 'name' => 'fk_submission_user', 'column' => 'submitted_by_user_id', 'definition' => "ADD CONSTRAINT fk_submission_user FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE CASCADE"],
        
        // Indexes
        ['type' => 'index', 'name' => 'idx_patient_form_timestamp', 'columns' => '(patient_id, submission_timestamp DESC)'],
        ['type' => 'index', 'name' => 'idx_form_name_directory', 'columns' => '(form_name, form_directory)'],
    ];

    foreach ($schemaItems as $item) {
        switch ($item['type']) {
            case 'column':
                if (!columnExists($pdo, $tableName, $item['name'])) {
                    $sqlAlter = "ALTER TABLE `$tableName` ADD COLUMN `" . $item['name'] . "` " . $item['definition'];
                    $pdo->exec($sqlAlter);
                    echo "<p>Column `" . htmlspecialchars($item['name']) . "` added successfully.</p>";
                } else {
                    echo "<p>Column `" . htmlspecialchars($item['name']) . "` already exists.</p>";
                }
                break;
            
            case 'fk':
                if (!constraintExists($pdo, $tableName, $item['name'])) {
                    if (columnExists($pdo, $tableName, $item['column'])) {
                        $sqlAlterFK = "ALTER TABLE `$tableName` " . $item['definition'];
                        $pdo->exec($sqlAlterFK);
                        echo "<p>Foreign Key `" . htmlspecialchars($item['name']) . "` added successfully.</p>";
                    } else {
                        echo "<p>Skipping FK `" . htmlspecialchars($item['name']) . "` because column `" . htmlspecialchars($item['column']) . "` is missing or its check failed.</p>";
                    }
                } else {
                    echo "<p>Foreign Key `" . htmlspecialchars($item['name']) . "` already exists.</p>";
                }
                break;

            case 'index':
                if (!indexExists($pdo, $tableName, $item['name'])) {
                    $firstColumnInIndex = explode(',', str_replace(['(', ')', ' DESC', ' ASC'], '', $item['columns']))[0];
                    if (columnExists($pdo, $tableName, trim($firstColumnInIndex))) {
                        $sqlAlterIndex = "ALTER TABLE `$tableName` ADD INDEX `" . $item['name'] . "` " . $item['columns'];
                        $pdo->exec($sqlAlterIndex);
                        echo "<p>Index `" . htmlspecialchars($item['name']) . "` added successfully.</p>";
                    } else {
                         echo "<p>Skipping Index `" . htmlspecialchars($item['name']) . "` because primary column `" . htmlspecialchars(trim($firstColumnInIndex)) ."` might be missing or its check failed.</p>";
                    }
                } else {
                    echo "<p>Index `" . htmlspecialchars($item['name']) . "` already exists.</p>";
                }
                break;
        }
    }
    echo "<p>Patient Form Submissions table schema check completed.</p>";

} catch (PDOException $e) {
    echo "<p>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Close the database connection
$pdo = null;
echo "<p>Patient Form Submissions table setup script finished.</p>";
?>
