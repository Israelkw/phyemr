<?php
ini_set('display_errors', 1); // Enable error display for setup script
error_reporting(E_ALL);

require_once 'db_connect.php'; // Provides $pdo
require_once 'ErrorHandler.php'; // Include ErrorHandler - though it might be called by db_connect or other files
ErrorHandler::register(); // Register the error handler

echo "<h1>Invoicing Module Setup Script</h1>";

try {
    // Transactions are generally not needed for DDL statements as they are often auto-committing.
    // Removing explicit transaction handling to prevent "no active transaction" errors with DDL.

    // 1. Create 'invoices' table
    $sqlCreateInvoicesTable = "
    CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        invoice_number VARCHAR(50) NOT NULL UNIQUE,
        invoice_date DATE NOT NULL,
        due_date DATE NULL,
        total_amount DECIMAL(10, 2) NOT NULL,
        amount_paid DECIMAL(10, 2) NOT NULL DEFAULT 0.00, /* This will be sum of payments */
        payment_status ENUM('unpaid', 'paid', 'partially_paid', 'void') NOT NULL DEFAULT 'unpaid',
        /* payment_date, payment_method, payment_notes removed, will be in 'payments' table */
        created_by_user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_invoices_patient_id (patient_id),
        INDEX idx_invoices_invoice_number (invoice_number),
        INDEX idx_invoices_payment_status (payment_status),
        CONSTRAINT fk_invoices_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE RESTRICT,
        CONSTRAINT fk_invoices_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB;";
    $pdo->exec($sqlCreateInvoicesTable);
    echo "<p>Table 'invoices' created successfully or already exists.</p>";

    // 2. Create 'invoice_items' table
    $sqlCreateInvoiceItemsTable = "
    CREATE TABLE IF NOT EXISTS invoice_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        patient_procedure_id INT NOT NULL,
        procedure_name_snapshot VARCHAR(255) NOT NULL,
        price_snapshot DECIMAL(10, 2) NOT NULL,
        INDEX idx_invoiceitems_invoice_id (invoice_id),
        INDEX idx_invoiceitems_patient_procedure_id (patient_procedure_id),
        CONSTRAINT fk_invoiceitems_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
        CONSTRAINT fk_invoiceitems_patient_procedure FOREIGN KEY (patient_procedure_id) REFERENCES patient_procedures(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB;";
    $pdo->exec($sqlCreateInvoiceItemsTable);
    echo "<p>Table 'invoice_items' created successfully or already exists.</p>";

    // 3. Create 'payments' table
    $sqlCreatePaymentsTable = "
    CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        payment_date DATETIME NOT NULL,
        amount_paid DECIMAL(10, 2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        manual_receipt_number VARCHAR(50) NULL,
        payment_notes TEXT NULL,
        recorded_by_user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_payments_invoice_id (invoice_id),
        INDEX idx_payments_payment_date (payment_date),
        CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
        CONSTRAINT fk_payments_recorded_by FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB;";
    $pdo->exec($sqlCreatePaymentsTable);
    echo "<p>Table 'payments' created successfully or already exists.</p>";

    // 4. Modify 'invoices' table to remove old payment fields (if they exist)
    $invoiceTableColumnsToDrop = ['payment_date', 'payment_method', 'payment_notes'];
    foreach ($invoiceTableColumnsToDrop as $column) {
        $stmtCheckInvoiceColumn = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                                                 WHERE TABLE_SCHEMA = DATABASE()
                                                 AND TABLE_NAME = 'invoices' AND COLUMN_NAME = :column_name");
        $stmtCheckInvoiceColumn->execute([':column_name' => $column]);
        if ($stmtCheckInvoiceColumn->fetchColumn() > 0) {
            $pdo->exec("ALTER TABLE invoices DROP COLUMN `$column`");
            echo "<p>Column '$column' dropped from 'invoices' table.</p>";
        } else {
            echo "<p>Column '$column' does not exist in 'invoices' table (no action taken).</p>";
        }
    }


    // 5. Modify 'patient_procedures' table to add 'invoice_id'
    // Check if column exists before adding
    $stmtCheckColumn = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                                      WHERE TABLE_SCHEMA = DATABASE()
                                      AND TABLE_NAME = 'patient_procedures'
                                      AND COLUMN_NAME = 'invoice_id'");
    $stmtCheckColumn->execute();
    $columnExists = $stmtCheckColumn->fetchColumn();

    if (!$columnExists) {
        $sqlAlterPatientProceduresTable = "
        ALTER TABLE patient_procedures
        ADD COLUMN invoice_id INT NULL DEFAULT NULL,
        ADD CONSTRAINT fk_pp_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
        ADD INDEX idx_pp_invoice_id (invoice_id);";
        $pdo->exec($sqlAlterPatientProceduresTable);
        echo "<p>Column 'invoice_id' added to 'patient_procedures' table with foreign key and index.</p>";
    } else {
        echo "<p>Column 'invoice_id' already exists in 'patient_procedures' table.</p>";
        // Optionally, check if FK and index exist and add them if not. For simplicity, assuming if column exists, setup was done.
    }

    // $pdo->commit(); // Removed as DDL statements are generally auto-committing.
    echo "<h2 style='color:green;'>Invoicing module database setup completed successfully.</h2>";

} catch (PDOException $e) {
    // No explicit rollback needed here if we didn't start a transaction,
    // but checking pdo->inTransaction() before rollback is safe if other operations were mixed.
    // For a pure DDL script like this, rollback in catch might not be strictly necessary.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Display error directly because this is a setup script
    echo "<h3 style='color:red;'>PDOException during setup:</h3>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . htmlspecialchars($e->getLine()) . "</p>";
    echo "<pre><strong>Trace:</strong>\n" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    // Also log via ErrorHandler if it's available and working
    // ErrorHandler::handleException($e, "Database error during invoicing module setup: ");
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h3 style='color:red;'>Exception during setup:</h3>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    // ErrorHandler::handleException($e, "General error during invoicing module setup: ");
}

echo "<p>Invoicing setup script finished.</p>";
?>
