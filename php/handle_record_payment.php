<?php
$path_to_root = "../";
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();
SessionManager::hasRole(['receptionist', 'admin'], $path_to_root . 'pages/dashboard.php', 'Access Denied.');

require_once $path_to_root . 'includes/db_connect.php';
require_once $path_to_root . 'includes/Database.php';
$db = new Database($pdo);

$user_id = SessionManager::get('user_id'); // For logging who recorded payment, if needed in future

// CSRF Token Validation
$submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
$invoice_id_for_redirect = filter_input(INPUT_POST, 'invoice_id', FILTER_VALIDATE_INT);
$redirect_url = $path_to_root . "pages/view_invoice_details.php" . ($invoice_id_for_redirect ? "?invoice_id=" . $invoice_id_for_redirect : "");
$fallback_redirect_url = $path_to_root . "pages/receptionist_view_patient_billing.php";


if (!SessionManager::validateCsrfToken($submittedToken)) {
    SessionManager::set('message', 'Invalid or missing CSRF token.');
    header("Location: " . ($invoice_id_for_redirect ? $redirect_url : $fallback_redirect_url));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    SessionManager::set('message', 'Invalid request method.');
    header("Location: " . ($invoice_id_for_redirect ? $redirect_url : $fallback_redirect_url));
    exit;
}

// Retrieve POST data
$invoice_id = filter_input(INPUT_POST, 'invoice_id', FILTER_VALIDATE_INT);
$amount_being_paid_str = isset($_POST['amount_being_paid']) ? trim($_POST['amount_being_paid']) : '';
$payment_date_str = isset($_POST['payment_date']) ? trim($_POST['payment_date']) : ''; // This is datetime-local
$payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
$payment_notes_new = isset($_POST['payment_notes']) ? trim($_POST['payment_notes']) : '';
$manual_receipt_number = isset($_POST['manual_receipt_number']) ? trim($_POST['manual_receipt_number']) : '';

// Store old input in session for repopulation
$old_input = $_POST;
unset($old_input['csrf_token']);
SessionManager::set('form_old_input_record_payment', $old_input);

// --- Validation ---
if (!$invoice_id) {
    SessionManager::set('message', 'Invoice ID is missing or invalid.');
    header("Location: " . $fallback_redirect_url); // Redirect to general billing if invoice_id is lost
    exit;
}

if (!is_numeric($amount_being_paid_str) || floatval($amount_being_paid_str) <= 0) {
    SessionManager::set('message', 'Amount being paid must be a positive number.');
    header("Location: " . $redirect_url);
    exit;
}
$amount_being_paid = floatval($amount_being_paid_str);

if (empty($payment_date_str)) {
    SessionManager::set('message', 'Payment date is required.');
    header("Location: " . $redirect_url);
    exit;
}
try {
    $payment_date_obj = new DateTime($payment_date_str); // Handles 'Y-m-d\TH:i'
    $payment_date_sql = $payment_date_obj->format('Y-m-d H:i:s');
} catch (Exception $e) {
    SessionManager::set('message', 'Invalid payment date format.');
    header("Location: " . $redirect_url);
    exit;
}


if (empty($payment_method)) {
    SessionManager::set('message', 'Payment method is required.');
    header("Location: " . $redirect_url);
    exit;
}
$allowed_payment_methods = ['Cash', 'Credit Card', 'Debit Card', 'Bank Transfer', 'Insurance', 'Other'];
if (!in_array($payment_method, $allowed_payment_methods)) {
    SessionManager::set('message', 'Invalid payment method selected.');
    header("Location: " . $redirect_url);
    exit;
}

// Validate Manual Receipt Number (must not be empty)
if (empty($manual_receipt_number)) {
    SessionManager::set('message', 'Manual Receipt Number is required.');
    header("Location: " . $redirect_url);
    exit;
}


try {
    $pdo->beginTransaction();

    // Fetch current invoice details to validate payment
    // Note: Removed 'payment_notes AS existing_notes' as it does not exist in 'invoices' table
    $stmt_invoice = $db->prepare("SELECT total_amount, amount_paid, payment_status FROM invoices WHERE id = :invoice_id FOR UPDATE"); // Lock row
    $db->execute($stmt_invoice, [':invoice_id' => $invoice_id]);
    $invoice = $db->fetch($stmt_invoice);

    if (!$invoice) {
        SessionManager::set('message', 'Invoice not found.');
        $pdo->rollBack();
        header("Location: " . $redirect_url);
        exit;
    }

    if ($invoice['payment_status'] == 'paid' || $invoice['payment_status'] == 'void') {
        SessionManager::set('message', 'This invoice is already marked as \'' . $invoice['payment_status'] . '\' and cannot accept further payments.');
        $pdo->rollBack();
        header("Location: " . $redirect_url);
        exit;
    }

    $current_total_amount = floatval($invoice['total_amount']);
    $current_amount_paid_on_invoice = floatval($invoice['amount_paid']); // Amount currently marked as paid on the invoice
    $balance_due = $current_total_amount - $current_amount_paid_on_invoice;

    if ($amount_being_paid > $balance_due + 0.001) { // Add small tolerance for float comparisons
        SessionManager::set('message', 'Amount being paid (' . number_format($amount_being_paid,2) . ') cannot be greater than the balance due (' . number_format($balance_due,2) . ').');
        $pdo->rollBack();
        header("Location: " . $redirect_url);
        exit;
    }

    // Insert new payment record into 'payments' table
    $sql_insert_payment = "INSERT INTO payments (invoice_id, payment_date, amount_paid, payment_method, payment_notes, manual_receipt_number, recorded_by_user_id)
                           VALUES (:invoice_id, :payment_date, :amount_paid, :payment_method, :payment_notes, :manual_receipt_number, :recorded_by_user_id)";
    $stmt_insert_payment = $db->prepare($sql_insert_payment);
    $db->execute($stmt_insert_payment, [
        ':invoice_id' => $invoice_id,
        ':payment_date' => $payment_date_sql, // Formatted payment date
        ':amount_paid' => $amount_being_paid, // The actual amount of this specific payment
        ':payment_method' => $payment_method,
        ':payment_notes' => !empty($payment_notes_new) ? trim($payment_notes_new) : null, // Notes from form, or null
        ':manual_receipt_number' => !empty($manual_receipt_number) ? $manual_receipt_number : null, // Store as NULL if empty
        ':recorded_by_user_id' => $user_id
    ]);

    // Update invoice: total amount paid and payment status
    $new_total_amount_paid_on_invoice = $current_amount_paid_on_invoice + $amount_being_paid;
    $new_payment_status = $invoice['payment_status']; // Default to current

    if (abs($new_total_amount_paid_on_invoice - $current_total_amount) < 0.001) { // Check if fully paid
        $new_payment_status = 'paid';
    } elseif ($new_total_amount_paid_on_invoice > 0) {
        $new_payment_status = 'partially_paid';
    }
    // If $new_total_amount_paid_on_invoice is 0 (e.g. if it was negative, though validation prevents this), status remains as is or becomes 'unpaid' if not already.
    // The existing logic doesn't explicitly set to 'unpaid' if a payment somehow made it zero, but current validation $amount_being_paid > 0 prevents this.

    $sql_update_invoice = "UPDATE invoices
                           SET amount_paid = :amount_paid,
                               payment_status = :payment_status
                           WHERE id = :invoice_id";
    $stmt_update_invoice = $db->prepare($sql_update_invoice);
    $db->execute($stmt_update_invoice, [
        ':amount_paid' => $new_total_amount_paid_on_invoice,
        ':payment_status' => $new_payment_status,
        ':invoice_id' => $invoice_id
    ]);

    $pdo->commit();

    SessionManager::remove('form_old_input_record_payment');
    SessionManager::set('message', "Payment of " . number_format($amount_being_paid, 2) . " recorded successfully for invoice.");
    header("Location: " . $redirect_url);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error recording payment: " . $e->getMessage());
    SessionManager::set('message', "Error recording payment: Database operation failed. " . $e->getMessage());
    header("Location: " . $redirect_url);
    exit;
} catch (Exception $e) {
     if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("General error recording payment: " . $e->getMessage());
    SessionManager::set('message', "An unexpected error occurred while recording the payment.");
    header("Location: " . $redirect_url);
    exit;
}
?>
