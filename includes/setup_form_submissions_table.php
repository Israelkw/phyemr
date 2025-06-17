<?php
// Include the database connection file
require_once 'db_connect.php';

echo "<h1>Patient Form Submissions Table Setup Script</h1>";

// SQL to create patient_form_submissions table - Aligned with physio_db_schema.sql and instructions
$sqlCreateTable = "
CREATE TABLE IF NOT EXISTS patient_form_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    submitted_by_user_id INT NOT NULL,
    form_name VARCHAR(255) NOT NULL,
    form_directory VARCHAR(255) NOT NULL,
    submission_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    treating_clinician VARCHAR(255) NULL,
    chief_complaint TEXT NULL,
    evaluation_summary_diagnosis TEXT NULL,
    submission_notes TEXT NULL,
    form_data JSON NOT NULL,

    CONSTRAINT fk_submission_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_submission_user FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,

    INDEX idx_submission_patient_id (patient_id),
    INDEX idx_submission_user_id (submitted_by_user_id),
    INDEX idx_submission_form_name (form_name),
    INDEX idx_submission_timestamp (submission_timestamp)
)";
// Comments from original PHP for form_name, form_directory, form_data are omitted for brevity but can be kept.
// fk_submission_user changed to ON DELETE RESTRICT

try {
    $pdo->exec($sqlCreateTable);
    echo "<p>Patient Form Submissions table created successfully or already exists using base schema.</p>";

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

    function getForeignKeyDefinition($pdo, $tableName, $constraintName) {
        $sql = "SELECT DELETE_RULE FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tableName, $constraintName]);
        return $stmt->fetchColumn();
    }

    $tableName = 'patient_form_submissions';

    // Columns to add
    $columnsToAdd = [
        ['name' => 'treating_clinician', 'definition' => 'VARCHAR(255) NULL'],
        ['name' => 'chief_complaint', 'definition' => 'TEXT NULL'],
        ['name' => 'evaluation_summary_diagnosis', 'definition' => 'TEXT NULL'],
        ['name' => 'submission_notes', 'definition' => 'TEXT NULL'],
    ];

    foreach ($columnsToAdd as $column) {
        if (!columnExists($pdo, $tableName, $column['name'])) {
            $sqlAlter = "ALTER TABLE `$tableName` ADD COLUMN `" . $column['name'] . "` " . $column['definition'];
            $pdo->exec($sqlAlter);
            echo "<p>Column `" . htmlspecialchars($column['name']) . "` added to $tableName table.</p>";
        } else {
            echo "<p>Column `" . htmlspecialchars($column['name']) . "` already exists in $tableName table.</p>";
        }
    }

    // Foreign Keys - Check and update ON DELETE rule for fk_submission_user
    $fkUserConstraintName = 'fk_submission_user';
    if (constraintExists($pdo, $tableName, $fkUserConstraintName)) {
        // This is a simplified check. Information schema query is needed to get current ON DELETE rule.
        // For robust solution, parse SHOW CREATE TABLE or query INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
        // We will attempt to drop and re-add with RESTRICT if it exists.
        // Note: This requires `submitted_by_user_id` column to exist.
        $currentDeleteRule = getForeignKeyDefinition($pdo, $tableName, $fkUserConstraintName);
        if ($currentDeleteRule && strtoupper($currentDeleteRule) !== 'RESTRICT') {
            try {
                $pdo->exec("ALTER TABLE `$tableName` DROP FOREIGN KEY `$fkUserConstraintName`");
                $pdo->exec("ALTER TABLE `$tableName` ADD CONSTRAINT `$fkUserConstraintName` FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE RESTRICT");
                echo "<p>Foreign Key `$fkUserConstraintName` updated to ON DELETE RESTRICT.</p>";
            } catch (PDOException $e) {
                echo "<p>Error updating Foreign Key `$fkUserConstraintName`: " . htmlspecialchars($e->getMessage()) . ". Manual check may be required.</p>";
            }
        } elseif (!$currentDeleteRule && columnExists($pdo, $tableName, 'submitted_by_user_id')) {
             // FK existed by name but couldn't get rule, or it was somehow dropped by name but column exists. Try to add.
            try {
                $pdo->exec("ALTER TABLE `$tableName` ADD CONSTRAINT `$fkUserConstraintName` FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE RESTRICT");
                echo "<p>Foreign Key `$fkUserConstraintName` (re-)added with ON DELETE RESTRICT.</p>";
            } catch (PDOException $e) {
                echo "<p>Error (re-)adding Foreign Key `$fkUserConstraintName`: " . htmlspecialchars($e->getMessage()) . ".</p>";
            }
        } else {
             echo "<p>Foreign Key `$fkUserConstraintName` already exists with correct ON DELETE rule or column missing.</p>";
        }
    } elseif (columnExists($pdo, $tableName, 'submitted_by_user_id')) { // If constraint doesn't exist by name, add it
        $pdo->exec("ALTER TABLE `$tableName` ADD CONSTRAINT `$fkUserConstraintName` FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE RESTRICT");
        echo "<p>Foreign Key `$fkUserConstraintName` added with ON DELETE RESTRICT.</p>";
    }


    $fkPatientConstraintName = 'fk_submission_patient';
    if (!constraintExists($pdo, $tableName, $fkPatientConstraintName) && columnExists($pdo, $tableName, 'patient_id')) {
        $pdo->exec("ALTER TABLE `$tableName` ADD CONSTRAINT `$fkPatientConstraintName` FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE");
        echo "<p>Foreign Key `$fkPatientConstraintName` added.</p>";
    } else {
        echo "<p>Foreign Key `$fkPatientConstraintName` already exists or patient_id column missing.</p>";
    }

    // Indexes to add/rename
    // Drop old indexes if they exist and new ones are preferred
    if (indexExists($pdo, $tableName, 'idx_patient_form_timestamp') && !indexExists($pdo, $tableName, 'idx_submission_patient_id')) {
        // This specific rename isn't ideal as idx_submission_patient_id is simpler.
        // The SQL schema has idx_submission_patient_id (patient_id) and idx_submission_timestamp (submission_timestamp)
        // The old PHP had idx_patient_form_timestamp (patient_id, submission_timestamp DESC)
        // We will add the SQL schema's preferred simple indexes.
        // And remove the old composite one if it exists.
        try {
            $pdo->exec("ALTER TABLE `$tableName` DROP INDEX `idx_patient_form_timestamp`");
            echo "<p>Index `idx_patient_form_timestamp` dropped in $tableName table.</p>";
        } catch (PDOException $e) {
            echo "<p>Notice: Could not drop index `idx_patient_form_timestamp`. It might not exist or is needed by a FK (unlikely for non-primary key indexes): ".htmlspecialchars($e->getMessage())."</p>";
        }
    }
    if (indexExists($pdo, $tableName, 'idx_form_name_directory') && !indexExists($pdo, $tableName, 'idx_submission_form_name')) {
         try {
            $pdo->exec("ALTER TABLE `$tableName` DROP INDEX `idx_form_name_directory`");
            echo "<p>Index `idx_form_name_directory` dropped from $tableName table.</p>";
        } catch (PDOException $e) {
            echo "<p>Notice: Could not drop index `idx_form_name_directory`: ".htmlspecialchars($e->getMessage())."</p>";
        }
    }

    $indexesToAdd = [
        ['name' => 'idx_submission_patient_id', 'columns' => '(patient_id)'],
        ['name' => 'idx_submission_user_id', 'columns' => '(submitted_by_user_id)'],
        ['name' => 'idx_submission_form_name', 'columns' => '(form_name)'], // SQL schema has this
        ['name' => 'idx_submission_timestamp', 'columns' => '(submission_timestamp)'], // SQL schema has this
    ];

    foreach ($indexesToAdd as $index) {
        if (!indexExists($pdo, $tableName, $index['name'])) {
            $canCreateIndex = true;
            $cols = explode(',', str_replace(['(', ')', ' DESC', ' ASC', ' '], '', $index['columns']));
            foreach ($cols as $col) {
                if (!columnExists($pdo, $tableName, trim($col))) {
                    $canCreateIndex = false;
                    echo "<p>Skipping Index `" . htmlspecialchars($index['name']) . "` for $tableName table because column `".trim($col)."` is missing.</p>";
                    break;
                }
            }
            if ($canCreateIndex) {
                $sqlAlterIndex = "ALTER TABLE `$tableName` ADD INDEX `" . $index['name'] . "` " . $index['columns'];
                $pdo->exec($sqlAlterIndex);
                echo "<p>Index `" . htmlspecialchars($index['name']) . "` added to $tableName table.</p>";
            }
        } else {
            echo "<p>Index `" . htmlspecialchars($index['name']) . "` already exists in $tableName table.</p>";
        }
    }
    echo "<p>Patient Form Submissions table schema check completed.</p>";

} catch (PDOException $e) {
    echo "<p>Database error during Patient Form Submissions table setup: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Close the database connection
$pdo = null;
echo "<p>Patient Form Submissions table setup script finished.</p>";
?>
