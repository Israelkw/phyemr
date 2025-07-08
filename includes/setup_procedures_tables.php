<?php
ini_set('display_errors', 1); // Temporarily display errors
error_reporting(E_ALL);     // Report all PHP errors

echo "<h1>Simplified Procedures Module Setup Script Test</h1>";
echo "<p>Attempting to include db_connect.php...</p>";

try {
    require_once 'db_connect.php'; // Provides $pdo
    echo "<p style='color:green;'>Successfully included db_connect.php.</p>";

    if (isset($pdo)) {
        echo "<p style='color:green;'>PDO object is available after including db_connect.php.</p>";
        echo "<p>Attempting to create 'procedures' table (simplified)...</p>";

        // Simplified SQL to create procedures table
        $sqlCreateProceduresTable = "
        CREATE TABLE IF NOT EXISTS procedures (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            price DECIMAL(10, 2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_procedure_name (name)
        ) ENGINE=InnoDB;";

        $pdo->exec($sqlCreateProceduresTable);
        echo "<p style='color:green;'>Table 'procedures' created successfully or already exists.</p>";

        echo "<p>Attempting to create 'patient_procedures' table (simplified)...</p>";
        // Simplified SQL to create patient_procedures table
        $sqlCreatePatientProceduresTable = "
        CREATE TABLE IF NOT EXISTS patient_procedures (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            procedure_id INT NOT NULL,
            clinician_id INT NOT NULL,
            date_performed DATE NOT NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_pp_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            CONSTRAINT fk_pp_procedure FOREIGN KEY (procedure_id) REFERENCES procedures(id) ON DELETE RESTRICT,
            CONSTRAINT fk_pp_clinician FOREIGN KEY (clinician_id) REFERENCES users(id) ON DELETE RESTRICT,
            INDEX idx_pp_patient_id (patient_id),
            INDEX idx_pp_procedure_id (procedure_id),
            INDEX idx_pp_clinician_id (clinician_id),
            INDEX idx_pp_date_performed (date_performed)
        ) ENGINE=InnoDB;";
        $pdo->exec($sqlCreatePatientProceduresTable);
        echo "<p style='color:green;'>Table 'patient_procedures' created successfully or already exists.</p>";

        echo "<h2 style='color:green;'>Simplified setup script completed successfully.</h2>";

    } else {
        echo "<p style='color:red;'>ERROR: \$pdo object is NOT available after including db_connect.php. Check db_connect.php for errors.</p>";
    }

} catch (PDOException $e) {
    echo "<h2 style='color:red;'>PDOException Caught:</h2>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . htmlspecialchars($e->getLine()) . "</p>";
    echo "<pre><strong>Trace:</strong>\n" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
} catch (Exception $e) {
    echo "<h2 style='color:red;'>General Exception Caught:</h2>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . htmlspecialchars($e->getLine()) . "</p>";
    echo "<pre><strong>Trace:</strong>\n" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<p>End of simplified script.</p>";
?>
