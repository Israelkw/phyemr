<?php
$path_to_root = "../";
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();
SessionManager::hasRole(['receptionist', 'admin'], $path_to_root . 'pages/dashboard.php', 'Access Denied.');

require_once $path_to_root . 'includes/db_connect.php';
require_once $path_to_root . 'includes/Database.php';
$db = new Database($pdo);

$user_id = SessionManager::get('user_id');

// CSRF Token Validation
$submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
$patient_id_for_redirect = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
// Default redirect is back to generate invoice page for the same patient
$redirect_url = $path_to_root . "pages/generate_invoice.php" . ($patient_id_for_redirect ? "?patient_id=" . $patient_id_for_redirect : "");

if (!SessionManager::validateCsrfToken($submittedToken)) {
    SessionManager::set('message', 'Invalid or missing CSRF token.');
    header("Location: " . $redirect_url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    SessionManager::set('message', 'Invalid request method.');
    header("Location: " . $redirect_url);
    exit;
}

// Retrieve POST data
$patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
$selected_procedure_ids = isset($_POST['procedure_ids']) && is_array($_POST['procedure_ids']) ? $_POST['procedure_ids'] : [];
$invoice_date_str = isset($_POST['invoice_date']) ? trim($_POST['invoice_date']) : '';
$due_date_str = isset($_POST['due_date']) ? trim($_POST['due_date']) : null;


// Store old input in session for repopulation in case of error
$old_input = $_POST;
unset($old_input['csrf_token']);
SessionManager::set('form_old_input_generate_invoice', $old_input);


// --- Validation ---
if (!$patient_id) {
    SessionManager::set('message', 'Patient ID is missing or invalid.');
    header("Location: " . $path_to_root . "pages/generate_invoice.php"); // General page if no patient_id
    exit;
}
// $redirect_url is already set with patient_id if it was available from POST

if (empty($selected_procedure_ids)) {
    SessionManager::set('message', 'No procedures selected to invoice.');
    header("Location: " . $redirect_url);
    exit;
}

if (empty($invoice_date_str)) {
    SessionManager::set('message', 'Invoice date is required.');
    header("Location: " . $redirect_url);
    exit;
}
$invoice_date_obj = DateTime::createFromFormat('Y-m-d', $invoice_date_str);
if (!$invoice_date_obj || $invoice_date_obj->format('Y-m-d') !== $invoice_date_str) {
    SessionManager::set('message', 'Invalid invoice date format. Please use YYYY-MM-DD.');
    header("Location: " . $redirect_url);
    exit;
}

if (!empty($due_date_str)) {
    $due_date_obj = DateTime::createFromFormat('Y-m-d', $due_date_str);
    if (!$due_date_obj || $due_date_obj->format('Y-m-d') !== $due_date_str) {
        SessionManager::set('message', 'Invalid due date format. Please use YYYY-MM-DD.');
        header("Location: " . $redirect_url);
        exit;
    }
    if ($due_date_obj < $invoice_date_obj) {
        SessionManager::set('message', 'Due date cannot be before the invoice date.');
        header("Location: " . $redirect_url);
        exit;
    }
}

// Fetch details of selected procedures to calculate total and for snapshots
$placeholders = rtrim(str_repeat('?,', count($selected_procedure_ids)), ',');
$sql_fetch_procs = "SELECT pp.id AS patient_procedure_id, p.name AS procedure_name, p.price AS procedure_price
                    FROM patient_procedures pp
                    JOIN procedures p ON pp.procedure_id = p.id
                    WHERE pp.id IN ($placeholders) AND pp.patient_id = ? AND pp.invoice_id IS NULL";
$params = array_merge($selected_procedure_ids, [$patient_id]);

$stmt_fetch_procs = $db->prepare($sql_fetch_procs);
$db->execute($stmt_fetch_procs, $params);
$procedures_to_invoice = $db->fetchAll($stmt_fetch_procs);

if (count($procedures_to_invoice) !== count($selected_procedure_ids)) {
    SessionManager::set('message', 'Error: Some selected procedures could not be found, are already invoiced, or do not belong to this patient. Please refresh and try again.');
    header("Location: " . $redirect_url);
    exit;
}

$total_amount = 0;
foreach ($procedures_to_invoice as $proc) {
    $total_amount += floatval($proc['procedure_price']);
}

if ($total_amount <= 0) {
    SessionManager::set('message', 'Invoice total amount must be greater than zero.');
    header("Location: " . $redirect_url);
    exit;
}

// --- Generate Invoice Number ---
function generateInvoiceNumber($pdo_conn) {
    $year = date('Y');
    $prefix = "INV-" . $year . "-";

    $sql_max_num = "SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '-', -1) AS UNSIGNED)) AS max_seq
                    FROM invoices
                    WHERE invoice_number LIKE ?";
    $stmt_max_num = $pdo_conn->prepare($sql_max_num);
    $stmt_max_num->execute([$prefix . '%']);
    $result = $stmt_max_num->fetch(PDO::FETCH_ASSOC);

    $next_seq = ($result && $result['max_seq']) ? intval($result['max_seq']) + 1 : 1;
    return $prefix . sprintf("%05d", $next_seq);
}
// Note: This simple sequence generation can have race conditions in high-concurrency.
// A dedicated sequence table or more robust locking might be needed for such environments.
// The UNIQUE constraint on invoice_number will prevent duplicates.

try {
    $pdo->beginTransaction();

    $invoice_number = generateInvoiceNumber($pdo);

    // Insert into invoices table
    $sql_insert_invoice = "INSERT INTO invoices (patient_id, invoice_number, invoice_date, due_date, total_amount, created_by_user_id, payment_status)
                           VALUES (:patient_id, :invoice_number, :invoice_date, :due_date, :total_amount, :created_by_user_id, :payment_status)";
    $stmt_insert_invoice = $db->prepare($sql_insert_invoice);
    $db->execute($stmt_insert_invoice, [
        ':patient_id' => $patient_id,
        ':invoice_number' => $invoice_number,
        ':invoice_date' => $invoice_date_str,
        ':due_date' => !empty($due_date_str) ? $due_date_str : null,
        ':total_amount' => $total_amount,
        ':created_by_user_id' => $user_id,
        ':payment_status' => 'unpaid' // Default status
    ]);
    $invoice_id = $db->getLastInsertId();

    // Insert into invoice_items and update patient_procedures
    $sql_insert_item = "INSERT INTO invoice_items (invoice_id, patient_procedure_id, procedure_name_snapshot, price_snapshot)
                        VALUES (:invoice_id, :patient_procedure_id, :name_snapshot, :price_snapshot)";
    $stmt_insert_item = $db->prepare($sql_insert_item);

    $sql_update_pp = "UPDATE patient_procedures SET invoice_id = :invoice_id WHERE id = :patient_procedure_id";
    $stmt_update_pp = $db->prepare($sql_update_pp);

    foreach ($procedures_to_invoice as $proc) {
        $db->execute($stmt_insert_item, [
            ':invoice_id' => $invoice_id,
            ':patient_procedure_id' => $proc['patient_procedure_id'],
            ':name_snapshot' => $proc['procedure_name'],
            ':price_snapshot' => $proc['procedure_price']
        ]);
        $db->execute($stmt_update_pp, [
            ':invoice_id' => $invoice_id,
            ':patient_procedure_id' => $proc['patient_procedure_id']
        ]);
    }

    $pdo->commit();

    SessionManager::remove('form_old_input_generate_invoice');
    SessionManager::set('message', "Invoice {$invoice_number} generated successfully for a total of {$total_amount}.");
    // Redirect to the new invoice details page
    header("Location: " . $path_to_root . "pages/view_invoice_details.php?invoice_id=" . $invoice_id);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error generating invoice: " . $e->getMessage());
    SessionManager::set('message', "Error generating invoice: Database operation failed. " . $e->getMessage()); // More detailed for debugging if needed
    header("Location: " . $redirect_url);
    exit;
} catch (Exception $e) { // Catch any other general exceptions
     if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("General error generating invoice: " . $e->getMessage());
    SessionManager::set('message', "An unexpected error occurred while generating the invoice.");
    header("Location: " . $redirect_url);
    exit;
}
?>
