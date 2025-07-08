<?php
$path_to_root = "../";
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();
SessionManager::hasRole(['receptionist', 'admin'], $path_to_root . 'pages/dashboard.php', 'Access Denied.');

require_once $path_to_root . 'includes/db_connect.php';
require_once $path_to_root . 'includes/Database.php';
$db = new Database($pdo);

$page_title = "Patient Invoicing & Billing";

// Patient Search/Selection part
$all_patients_for_select = []; // For initial dropdown if no search
$searched_patients_list = [];    // For search results
$searchTerm = filter_input(INPUT_GET, 'search_term', FILTER_SANITIZE_STRING);
$selected_patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);

if ($searchTerm) {
    $stmt_patients_search = $db->prepare("SELECT id, first_name, last_name, date_of_birth FROM patients
                                          WHERE first_name LIKE :term_fn OR last_name LIKE :term_ln OR id LIKE :term_id_like
                                          ORDER BY last_name, first_name LIMIT 20");
    $db->execute($stmt_patients_search, [
        ':term_fn' => "%" . $searchTerm . "%",
        ':term_ln' => "%" . $searchTerm . "%",
        ':term_id_like' => "%" . $searchTerm . "%"
    ]);
    $searched_patients_list = $db->fetchAll($stmt_patients_search);
} else if (!$selected_patient_id) {
    // Optionally, load all patients if no search and no selection, for a dropdown.
    // For performance, this might be removed if the patient list is very large.
    // $stmt_all_patients = $db->prepare("SELECT id, first_name, last_name FROM patients ORDER BY last_name, first_name LIMIT 100"); // Limit for sanity
    // $db->execute($stmt_all_patients);
    // $all_patients_for_select = $db->fetchAll($stmt_all_patients);
}

$patient_invoices = [];
$patient_details = null;
$filter_payment_status = filter_input(INPUT_GET, 'filter_payment_status', FILTER_SANITIZE_STRING);

if ($selected_patient_id) {
    // Fetch selected patient's details
    $stmt_patient_details = $db->prepare("SELECT id, first_name, last_name FROM patients WHERE id = :patient_id");
    $db->execute($stmt_patient_details, [':patient_id' => $selected_patient_id]);
    $patient_details = $db->fetch($stmt_patient_details);

    if ($patient_details) {
        // Base SQL for invoices
        $sql_invoices_base = "
            SELECT id AS invoice_id, invoice_number, invoice_date, due_date, total_amount, amount_paid, payment_status
            FROM invoices
            WHERE patient_id = :patient_id";

        $params = [':patient_id' => $selected_patient_id];

        // Apply payment status filter if provided
        if (!empty($filter_payment_status) && in_array($filter_payment_status, ['unpaid', 'partially_paid', 'paid', 'void'])) {
            $sql_invoices_base .= " AND payment_status = :payment_status";
            $params[':payment_status'] = $filter_payment_status;
        }

        $sql_invoices_base .= " ORDER BY invoice_date DESC, id DESC";

        $stmt_invoices = $db->prepare($sql_invoices_base);
        $db->execute($stmt_invoices, $params);
        $patient_invoices = $db->fetchAll($stmt_invoices);
    } else {
        SessionManager::set('message', 'Selected patient not found.');
        $selected_patient_id = null;
    }
}

require_once $path_to_root . 'includes/header.php';
?>

<div class="container mt-5">
    <h2><?php echo $page_title; ?></h2>

    <?php if (SessionManager::has('message')): ?>
    <div class="alert <?php echo strpos(strtolower(SessionManager::get('message')), 'success') !== false ? 'alert-success' : 'alert-danger'; ?>">
        <?php echo htmlspecialchars(SessionManager::get('message')); SessionManager::remove('message'); ?>
    </div>
    <?php endif; ?>

    <!-- Patient Search Form -->
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="mb-4 card card-body">
        <h5 class="card-title">Find Patient</h5>
        <div class="row">
            <div class="col-md-8 mb-2">
                <label for="search_term" class="form-label visually-hidden">Search Patient (Name or ID)</label>
                <input type="text" class="form-control" id="search_term" name="search_term" placeholder="Enter Name or ID" value="<?php echo htmlspecialchars($searchTerm ?? ''); ?>">
            </div>
            <div class="col-md-4 mb-2">
                <button type="submit" class="btn btn-info w-100"><i class="fas fa-search"></i> Search Patient</button>
            </div>
        </div>
         <!-- Hidden patient_id input if a patient is already selected, to persist selection if user searches again (optional) -->
        <?php if ($selected_patient_id && !$searchTerm): ?>
            <!-- <input type="hidden" name="patient_id" value="<?php echo $selected_patient_id; ?>"> -->
        <?php endif; ?>
    </form>

    <?php if ($searchTerm && empty($searched_patients_list) && !$selected_patient_id): ?>
        <p class="alert alert-warning">No patients found matching "<?php echo htmlspecialchars($searchTerm); ?>". Try a different search.</p>
    <?php elseif (!empty($searched_patients_list) && !$selected_patient_id): ?>
        <h4 class="mt-3">Search Results:</h4>
        <div class="list-group mb-4">
            <?php foreach ($searched_patients_list as $p): ?>
                <a href="?patient_id=<?php echo $p['id']; ?>" class="list-group-item list-group-item-action">
                    <?php echo htmlspecialchars($p['last_name'] . ', ' . $p['first_name'] . ' (ID: ' . $p['id'] . ', DOB: ' . $p['date_of_birth'] . ')'); ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>


    <?php if ($selected_patient_id && $patient_details): ?>
        <hr>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mt-4">Invoices for: <?php echo htmlspecialchars($patient_details['first_name'] . ' ' . $patient_details['last_name']); ?> (ID: <?php echo $selected_patient_id; ?>)</h3>
            <a href="<?php echo $path_to_root; ?>pages/generate_invoice.php?patient_id=<?php echo $selected_patient_id; ?>" class="btn btn-success">
                <i class="fas fa-plus-circle"></i> Generate New Invoice
            </a>
        </div>

        <!-- Filter Form -->
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="mb-3 p-3 border rounded bg-light">
            <input type="hidden" name="patient_id" value="<?php echo $selected_patient_id; ?>">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <label for="filter_payment_status" class="form-label">Filter by Payment Status:</label>
                    <select name="filter_payment_status" id="filter_payment_status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="unpaid" <?php echo (isset($_GET['filter_payment_status']) && $_GET['filter_payment_status'] === 'unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                        <option value="partially_paid" <?php echo (isset($_GET['filter_payment_status']) && $_GET['filter_payment_status'] === 'partially_paid') ? 'selected' : ''; ?>>Partially Paid</option>
                        <option value="paid" <?php echo (isset($_GET['filter_payment_status']) && $_GET['filter_payment_status'] === 'paid') ? 'selected' : ''; ?>>Paid</option>
                        <option value="void" <?php echo (isset($_GET['filter_payment_status']) && $_GET['filter_payment_status'] === 'void') ? 'selected' : ''; ?>>Void</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
                </div>
            </div>
        </form>

        <?php if (empty($patient_invoices)): ?>
            <p class="alert alert-info mt-3">No invoices found for this patient<?php echo (isset($_GET['filter_payment_status']) && !empty($_GET['filter_payment_status'])) ? ' matching the selected filter' : ''; ?>.</p>
        <?php else: ?>
            <table class="table table-striped table-hover mt-3">
                <thead class="table-dark">
                    <tr>
                        <th>Invoice #</th>
                        <th>Invoice Date</th>
                        <th>Due Date</th>
                        <th class="text-end">Total Amount</th>
                        <th class="text-end">Amount Paid</th>
                        <th class="text-center">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patient_invoices as $invoice):
                        $balance_due = floatval($invoice['total_amount']) - floatval($invoice['amount_paid']);
                        $status_class = '';
                        switch ($invoice['payment_status']) {
                            case 'paid': $status_class = 'badge bg-success'; break;
                            case 'partially_paid': $status_class = 'badge bg-warning text-dark'; break;
                            case 'unpaid': $status_class = 'badge bg-danger'; break;
                            case 'void': $status_class = 'badge bg-secondary'; break;
                        }
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                            <td><?php echo htmlspecialchars(date('M d, Y', strtotime($invoice['invoice_date']))); ?></td>
                            <td><?php echo $invoice['due_date'] ? htmlspecialchars(date('M d, Y', strtotime($invoice['due_date']))) : 'N/A'; ?></td>
                            <td class="text-end"><?php echo htmlspecialchars(number_format($invoice['total_amount'], 2)); ?></td>
                            <td class="text-end"><?php echo htmlspecialchars(number_format($invoice['amount_paid'], 2)); ?></td>
                            <td class="text-center"><span class="<?php echo $status_class; ?>"><?php echo ucfirst(str_replace('_', ' ', $invoice['payment_status'])); ?></span></td>
                            <td>
                                <a href="<?php echo $path_to_root; ?>pages/view_invoice_details.php?invoice_id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <!-- Print button could also be here or on details page -->
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
         <p class="mt-3"><a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">Clear Patient Selection / Search Again</a></p>
    <?php elseif ($selected_patient_id && !$patient_details): ?>
         <p class="alert alert-danger mt-3">Could not load details for Patient ID <?php echo $selected_patient_id; ?>.</p>
    <?php endif; ?>

</div>

<?php
require_once $path_to_root . 'includes/footer.php';
?>
