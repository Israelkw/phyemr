<?php
ini_set('display_errors', 1); // Temporarily display errors
error_reporting(E_ALL);     // Report all PHP errors

// Include the database connection file
require_once 'db_connect.php'; // Provides $pdo
require_once 'ErrorHandler.php'; // Include ErrorHandler
ErrorHandler::register(); // Register the error handler

echo "<h1>Procedures Module Setup Script</h1>";

try {
    // SQL to create procedures table
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
    echo "<p>Table 'procedures' created successfully or already exists.</p>";

    // SQL to create patient_procedures table
    $sqlCreatePatientProceduresTable = "
    CREATE TABLE IF NOT EXISTS patient_procedures (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        procedure_id INT NOT NULL,
        clinician_id INT NOT NULL,
        date_performed DATE NOT NULL,
        notes TEXT NULLABLE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_pp_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        CONSTRAINT fk_pp_procedure FOREIGN KEY (procedure_id) REFERENCES procedures(id) ON DELETE RESTRICT, -- Prevent deleting a procedure if it's been used
        CONSTRAINT fk_pp_clinician FOREIGN KEY (clinician_id) REFERENCES users(id) ON DELETE RESTRICT, -- Prevent deleting clinician if they performed procedure
        INDEX idx_pp_patient_id (patient_id),
        INDEX idx_pp_procedure_id (procedure_id),
        INDEX idx_pp_clinician_id (clinician_id),
        INDEX idx_pp_date_performed (date_performed)
    ) ENGINE=InnoDB;";
    $pdo->exec($sqlCreatePatientProceduresTable);
    echo "<p>Table 'patient_procedures' created successfully or already exists.</p>";

    echo "<p>Procedures module tables setup completed successfully.</p>";

} catch (PDOException $e) {
    // Use ErrorHandler for logging and displaying a user-friendly message
    ErrorHandler::handleException($e, "Database error during procedures module setup: ");
    // Optionally, echo a simpler message if ErrorHandler redirects or exits
    // echo "<p>An error occurred during setup. Check error logs for details.</p>";
} catch (Exception $e) {
    // Catch any other general exceptions
    ErrorHandler::handleException($e, "General error during procedures module setup: ");
}

// Close the database connection (optional, as PDO closes on script end)
$pdo = null;
echo "<p>Procedures setup script finished.</p>";
?>
