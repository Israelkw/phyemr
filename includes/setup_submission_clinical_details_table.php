<?php
// Include the database connection file
require_once 'db_connect.php';

echo "<h1>Submission Clinical Details Table Setup Script</h1>";

// SQL to create submission_clinical_details table
$sqlCreateTable = "
CREATE TABLE IF NOT EXISTS submission_clinical_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL UNIQUE,
    medical_history_summary TEXT NULL,
    current_medications TEXT NULL,
    evaluation_treatment_plan_summary TEXT NULL,
    evaluation_short_term_goals TEXT NULL,
    evaluation_long_term_goals TEXT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_details_submission FOREIGN KEY (submission_id) REFERENCES patient_form_submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
";

try {
    $pdo->exec($sqlCreateTable);
    echo "<p>Submission Clinical Details table created successfully or already exists.</p>";

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

    $tableName = 'submission_clinical_details';

    // Ensure FK constraint exists
    $fkName = 'fk_details_submission';
    if (!constraintExists($pdo, $tableName, $fkName)) {
        if (columnExists($pdo, $tableName, 'submission_id')) {
            // Ensure the referenced table patient_form_submissions exists
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

    echo "<p>Submission Clinical Details table schema setup completed.</p>";

} catch (PDOException $e) {
    echo "<p>Database error during Submission Clinical Details table setup: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Close the database connection
$pdo = null;
echo "<p>Submission Clinical Details table setup script finished.</p>";
?>
