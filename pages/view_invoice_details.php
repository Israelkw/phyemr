<?php
$path_to_root = "../";
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();
SessionManager::hasRole(['receptionist', 'admin'], $path_to_root . 'pages/dashboard.php', 'Access Denied.');

require_once $path_to_root . 'includes/db_connect.php';
require_once $path_to_root . 'includes/Database.php';
$db = new Database($pdo);

$page_title = "View Invoice Details";

$invoice_id = filter_input(INPUT_GET, 'invoice_id', FILTER_VALIDATE_INT);
$invoice_details = null;
$invoice_items = [];
$patient_info = null;

if (!$invoice_id) {
    SessionManager::set('message', 'No invoice ID provided.');
    header("Location: " . $path_to_root . "pages/receptionist_view_patient_billing.php"); // Redirect to a safe page
    exit;
}

try {
    // Fetch invoice details
    $sql_invoice = "SELECT i.*, p.first_name AS p_first_name, p.last_name AS p_last_name, p.email AS p_email,
                           u.first_name AS created_by_first, u.last_name AS created_by_last
                    FROM invoices i
                    JOIN patients p ON i.patient_id = p.id
                    JOIN users u ON i.created_by_user_id = u.id
                    WHERE i.id = :invoice_id";
    $stmt_invoice = $db->prepare($sql_invoice);
    $db->execute($stmt_invoice, [':invoice_id' => $invoice_id]);
    $invoice_details = $db->fetch($stmt_invoice);

    if (!$invoice_details) {
        SessionManager::set('message', "Invoice not found for ID: " . htmlspecialchars($invoice_id));
        header("Location: " . $path_to_root . "pages/receptionist_view_patient_billing.php");
        exit;
    }

    // Fetch invoice items
    $sql_items = "SELECT ii.procedure_name_snapshot, ii.price_snapshot
                  FROM invoice_items ii
                  WHERE ii.invoice_id = :invoice_id
                  ORDER BY ii.id ASC"; // Or by name_snapshot
    $stmt_items = $db->prepare($sql_items);
    $db->execute($stmt_items, [':invoice_id' => $invoice_id]);
    $invoice_items = $db->fetchAll($stmt_items);

    // Fetch latest payment details
    $latest_payment = null;
    if ($invoice_details) { // Only fetch if invoice exists
        $sql_latest_payment = "SELECT payment_date, payment_method, payment_notes
                               FROM payments
                               WHERE invoice_id = :invoice_id
                               ORDER BY payment_date DESC, id DESC
                               LIMIT 1";
        $stmt_latest_payment = $db->prepare($sql_latest_payment);
        $db->execute($stmt_latest_payment, [':invoice_id' => $invoice_id]);
        $latest_payment = $db->fetch($stmt_latest_payment);
    }

} catch (PDOException $e) {
    error_log("Error fetching invoice or payment details: " . $e->getMessage());
    SessionManager::set('message', "Error fetching invoice or payment details: Database operation failed.");
    header("Location: " . $path_to_root . "pages/receptionist_view_patient_billing.php"); // Redirect on DB error
    exit;
}

$balance_due = floatval($invoice_details['total_amount']) - floatval($invoice_details['amount_paid']);
$is_payable = ($invoice_details['payment_status'] == 'unpaid' || $invoice_details['payment_status'] == 'partially_paid') && $balance_due > 0;

$csrf_token_payment = SessionManager::generateCsrfToken();
$old_input_payment = SessionManager::get('form_old_input_record_payment', []);
SessionManager::remove('form_old_input_record_payment');

require_once $path_to_root . 'includes/header.php';
?>

<div class="container mt-5">

    <?php if (SessionManager::has('message')): ?>
    <div class="alert <?php echo strpos(strtolower(SessionManager::get('message')), 'success') !== false ? 'alert-success' : 'alert-danger'; ?> mb-3">
        <?php echo htmlspecialchars(SessionManager::get('message')); SessionManager::remove('message'); ?>
    </div>
    <?php endif; ?>

    <?php if ($invoice_details): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Invoice #<?php echo htmlspecialchars($invoice_details['invoice_number']); ?></h2>
            <a href="<?php echo $path_to_root; ?>pages/print_invoice.php?invoice_id=<?php echo $invoice_id; ?>" class="btn btn-info" target="_blank"><i class="fas fa-print"></i> Print Invoice</a>
        </div>

        <div class="card">
            <div class="card-header">
                <h4>Invoice Summary</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Patient:</strong> <?php echo htmlspecialchars($invoice_details['p_first_name'] . ' ' . $invoice_details['p_last_name']); ?></p>
                        <p><strong>Patient ID:</strong> <?php echo htmlspecialchars($invoice_details['patient_id']); ?></p>
                        <p><strong>Patient Email:</strong> <?php echo htmlspecialchars($invoice_details['p_email'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Invoice Date:</strong> <?php echo htmlspecialchars(date('M d, Y', strtotime($invoice_details['invoice_date']))); ?></p>
                        <p><strong>Due Date:</strong> <?php echo $invoice_details['due_date'] ? htmlspecialchars(date('M d, Y', strtotime($invoice_details['due_date']))) : 'N/A'; ?></p>
                        <p><strong>Created By:</strong> <?php echo htmlspecialchars($invoice_details['created_by_first'] . ' ' . $invoice_details['created_by_last']); ?></p>
                    </div>
                </div>
                <hr>
                <h5>Financials:</h5>
                <p><strong>Total Amount:</strong> <?php echo htmlspecialchars(number_format($invoice_details['total_amount'], 2)); ?></p>
                <p><strong>Amount Paid:</strong> <?php echo htmlspecialchars(number_format($invoice_details['amount_paid'], 2)); ?></p>
                <p><strong>Balance Due:</strong> <strong class="<?php echo $balance_due > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo htmlspecialchars(number_format($balance_due, 2)); ?></strong></p>
                <p><strong>Payment Status:</strong>
                    <?php
                        $status_class = '';
                        switch ($invoice_details['payment_status']) {
                            case 'paid': $status_class = 'badge bg-success'; break;
                            case 'partially_paid': $status_class = 'badge bg-warning text-dark'; break;
                            case 'unpaid': $status_class = 'badge bg-danger'; break;
                            case 'void': $status_class = 'badge bg-secondary'; break;
                        }
                    ?>
                    <span class="<?php echo $status_class; ?>"><?php echo ucfirst(str_replace('_', ' ', $invoice_details['payment_status'])); ?></span>
                </p>
                <?php if ($latest_payment && isset($latest_payment['payment_date']) && $latest_payment['payment_date']): ?>
                <p><strong>Last Payment Date:</strong> <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($latest_payment['payment_date']))); ?></p>
                <?php endif; ?>
                <?php if ($latest_payment && isset($latest_payment['payment_method']) && $latest_payment['payment_method']): ?>
                <p><strong>Last Payment Method:</strong> <?php echo htmlspecialchars($latest_payment['payment_method']); ?></p>
                <?php endif; ?>
                 <?php if ($latest_payment && isset($latest_payment['payment_notes']) && !empty(trim($latest_payment['payment_notes']))): ?>
                <p><strong>Payment Notes:</strong> <?php echo nl2br(htmlspecialchars($latest_payment['payment_notes'])); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h4>Invoice Items</h4>
            </div>
            <div class="card-body">
                <?php if (empty($invoice_items)): ?>
                    <p>No items found for this invoice.</p>
                <?php else: ?>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Item/Procedure</th>
                                <th class="text-end">Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($invoice_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['procedure_name_snapshot']); ?></td>
                                <td class="text-end"><?php echo htmlspecialchars(number_format($item['price_snapshot'], 2)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th class="text-end">Total:</th>
                                <th class="text-end"><?php echo htmlspecialchars(number_format($invoice_details['total_amount'], 2)); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($is_payable): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h4>Record Payment</h4>
            </div>
            <div class="card-body">
                <form action="<?php echo $path_to_root; ?>php/handle_record_payment.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_payment; ?>">
                    <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="amount_being_paid" class="form-label">Amount Being Paid</label>
                            <input type="number" step="0.01" min="0.01" max="<?php echo htmlspecialchars($balance_due); ?>" class="form-control"
                                   id="amount_being_paid" name="amount_being_paid"
                                   value="<?php echo htmlspecialchars($old_input_payment['amount_being_paid'] ?? number_format($balance_due, 2, '.', '')); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="payment_date_form" class="form-label">Payment Date</label>
                            <input type="datetime-local" class="form-control" id="payment_date_form" name="payment_date"
                                   value="<?php echo htmlspecialchars($old_input_payment['payment_date'] ?? date('Y-m-d\TH:i')); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="payment_method" class="form-label">Payment Method</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="">-- Select Method --</option>
                                <option value="Cash" <?php echo (($old_input_payment['payment_method'] ?? '') === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                                <option value="Credit Card" <?php echo (($old_input_payment['payment_method'] ?? '') === 'Credit Card') ? 'selected' : ''; ?>>Credit Card</option>
                                <option value="Debit Card" <?php echo (($old_input_payment['payment_method'] ?? '') === 'Debit Card') ? 'selected' : ''; ?>>Debit Card</option>
                                <option value="Bank Transfer" <?php echo (($old_input_payment['payment_method'] ?? '') === 'Bank Transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="Insurance" <?php echo (($old_input_payment['payment_method'] ?? '') === 'Insurance') ? 'selected' : ''; ?>>Insurance</option>
                                <option value="Other" <?php echo (($old_input_payment['payment_method'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="manual_receipt_number" class="form-label">Manual Receipt Number (Optional)</label>
                            <input type="text" class="form-control" id="manual_receipt_number" name="manual_receipt_number"
                                   value="<?php echo htmlspecialchars($old_input_payment['manual_receipt_number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="payment_notes" class="form-label">Payment Notes (Optional)</label>
                            <textarea class="form-control" id="payment_notes" name="payment_notes" rows="2"><?php echo htmlspecialchars($old_input_payment['payment_notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Record Payment</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="mt-4 mb-5">
            <a href="<?php echo $path_to_root; ?>pages/receptionist_view_patient_billing.php?patient_id=<?php echo htmlspecialchars($invoice_details['patient_id']); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Patient's Invoices
            </a>
        </div>

    <?php else: ?>
        <p class="alert alert-warning">Invoice details could not be loaded.</p>
    <?php endif; ?>
</div>

<?php
require_once $path_to_root . 'includes/footer.php';
?>
