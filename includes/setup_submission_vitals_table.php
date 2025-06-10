<?php
// Include the database connection file
require_once 'db_connect.php';

echo "<h1>Submission Vitals Table Setup Script</h1>";

// SQL to create submission_vitals table
$sqlCreateTable = "
CREATE TABLE IF NOT EXISTS submission_vitals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL UNIQUE,
    temperature DECIMAL(4,1) NULL,
    pulse_rate INT NULL,
    bp_systolic INT NULL,
    bp_diastolic INT NULL,
    respiratory_rate INT NULL,
    oxygen_saturation DECIMAL(4,1) NULL,
    height_cm DECIMAL(5,1) NULL,
    weight_kg DECIMAL(5,1) NULL,
    bmi DECIMAL(4,1) NULL,
    pain_scale INT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_vitals_submission FOREIGN KEY (submission_id) REFERENCES patient_form_submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
";

try {
    $pdo->exec($sqlCreateTable);
    echo "<p>Submission Vitals table created successfully or already exists.</p>";

    // Helper functions (can be refactored into a common file if used by many scripts)
    function columnExists($pdo, $tableName, $columnName) {
        $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tableName, $columnName]);
        return $stmt->fetchColumn() > 0;
    }

    function constraintExists($pdo, $tableName, $constraintName) {
        $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tableName, $constraintName]);
        return $stmt->fetchColumn() > 0;
    }

    $tableName = 'submission_vitals';

    // Example of checking/adding a column if it was missed in CREATE TABLE or needs modification
    // For this script, the CREATE TABLE is comprehensive based on physio_db_schema.sql
    /*
    if (!columnExists($pdo, $tableName, 'new_vital_column')) {
        $pdo->exec("ALTER TABLE `$tableName` ADD COLUMN `new_vital_column` VARCHAR(255) NULL");
        echo "<p>Column `new_vital_column` added to $tableName table.</p>";
    }
    */

    // Ensure FK constraint exists
    $fkName = 'fk_vitals_submission';
    if (!constraintExists($pdo, $tableName, $fkName)) {
        if (columnExists($pdo, $tableName, 'submission_id')) {
            // Ensure the referenced table patient_form_submissions exists before adding FK
            // This check might be too simplistic if patient_form_submissions setup hasn't run.
            // Ideally, setup scripts are run in order.
            $refTableExistsSql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'patient_form_submissions'";
            $refTableStmt = $pdo->query($refTableExistsSql);
            if ($refTableStmt && $refTableStmt->fetchColumn() > 0) {
                $sqlAlterFK = "ALTER TABLE `$tableName` ADD CONSTRAINT `$fkName` FOREIGN KEY (submission_id) REFERENCES patient_form_submissions(id) ON DELETE CASCADE";
                $pdo->exec($sqlAlterFK);
                echo "<p>Foreign Key `$fkName` added to $tableName table.</p>";
            } else {
                echo "<p>Skipping FK `$fkName` for $tableName table because referenced table `patient_form_submissions` does not exist (or check failed).</p>";
            }
        } else {
            echo "<p>Skipping FK `$fkName` for $tableName table because column `submission_id` is missing.</p>";
        }
    } else {
        echo "<p>Foreign Key `$fkName` already exists in $tableName table.</p>";
    }

    echo "<p>Submission Vitals table schema setup completed.</p>";

} catch (PDOException $e) {
    echo "<p>Database error during Submission Vitals table setup: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Close the database connection
$pdo = null;
echo "<p>Submission Vitals table setup script finished.</p>";
?>
