<?php
$path_to_root = "../";
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();
SessionManager::hasRole(['receptionist', 'admin'], $path_to_root . 'pages/dashboard.php', 'Access Denied.');

require_once $path_to_root . 'includes/db_connect.php';
require_once $path_to_root . 'includes/Database.php';
$db = new Database($pdo);

$page_title = "Generate New Invoice";

// Patient Search/Selection part
$patients = [];
$searchTerm = filter_input(INPUT_GET, 'search_term', FILTER_SANITIZE_STRING);
$selected_patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);

if ($searchTerm) {
    $stmt_patients = $db->prepare("SELECT id, first_name, last_name, date_of_birth FROM patients
                                   WHERE first_name LIKE :term OR last_name LIKE :term OR id LIKE :term
                                   ORDER BY last_name, first_name LIMIT 20");
    $db->execute($stmt_patients, [':term' => "%" . $searchTerm . "%"]);
    $patients = $db->fetchAll($stmt_patients);
} elseif (!$selected_patient_id) { // If no specific patient is selected yet, and no search, fetch all for dropdown initially (can be slow)
    // To prevent loading all patients by default, let's require a search or explicit selection.
    // Or, provide a direct link from patient billing page with patient_id.
}


$un_invoiced_procedures = [];
$patient_details = null;

if ($selected_patient_id) {
    // Fetch selected patient's details
    $stmt_patient_details = $db->prepare("SELECT id, first_name, last_name FROM patients WHERE id = :patient_id");
    $db->execute($stmt_patient_details, [':patient_id' => $selected_patient_id]);
    $patient_details = $db->fetch($stmt_patient_details);

    if ($patient_details) {
        // Fetch un-invoiced procedures for this patient
        $sql_un_invoiced = "
            SELECT pp.id, pp.date_performed, p.name AS procedure_name, p.price AS procedure_price
            FROM patient_procedures pp
            JOIN procedures p ON pp.procedure_id = p.id
            WHERE pp.patient_id = :patient_id AND pp.invoice_id IS NULL
            ORDER BY pp.date_performed ASC, p.name ASC";
        $stmt_un_invoiced = $db->prepare($sql_un_invoiced);
        $db->execute($stmt_un_invoiced, [':patient_id' => $selected_patient_id]);
        $un_invoiced_procedures = $db->fetchAll($stmt_un_invoiced);
    } else {
        SessionManager::set('message', 'Selected patient not found.');
        // Clear selected_patient_id if patient not found to reset the state
        $selected_patient_id = null;
    }
}

$csrf_token = SessionManager::generateCsrfToken();
$old_input = SessionManager::get('form_old_input_generate_invoice', []);
SessionManager::remove('form_old_input_generate_invoice');

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
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="mb-4">
        <div class="row">
            <div class="col-md-6">
                <label for="search_term" class="form-label">Search Patient (Name or ID)</label>
                <input type="text" class="form-control" id="search_term" name="search_term" value="<?php echo htmlspecialchars($searchTerm ?? ''); ?>">
            </div>
            <div class="col-md-2 align-self-end">
                <button type="submit" class="btn btn-info w-100">Search</button>
            </div>
        </div>
    </form>

    <?php if ($searchTerm && empty($patients) && !$selected_patient_id): ?>
        <p class="alert alert-warning">No patients found matching your search term "<?php echo htmlspecialchars($searchTerm); ?>".</p>
    <?php elseif (!empty($patients) && !$selected_patient_id): ?>
        <h4 class="mt-3">Search Results:</h4>
        <ul class="list-group mb-4">
            <?php foreach ($patients as $p): ?>
                <li class="list-group-item">
                    <a href="?patient_id=<?php echo $p['id']; ?>">
                        <?php echo htmlspecialchars($p['last_name'] . ', ' . $p['first_name'] . ' (ID: ' . $p['id'] . ', DOB: ' . $p['date_of_birth'] . ')'); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>


    <?php if ($selected_patient_id && $patient_details): ?>
        <hr>
        <h3 class="mt-4">Generating Invoice for: <?php echo htmlspecialchars($patient_details['first_name'] . ' ' . $patient_details['last_name']); ?> (ID: <?php echo $selected_patient_id; ?>)</h3>

        <form action="<?php echo $path_to_root; ?>php/handle_generate_invoice.php" method="POST" id="generateInvoiceForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="patient_id" value="<?php echo $selected_patient_id; ?>">

            <div class="row mt-3">
                <div class="col-md-6 mb-3">
                    <label for="invoice_date" class="form-label">Invoice Date</label>
                    <input type="date" class="form-control" id="invoice_date" name="invoice_date"
                           value="<?php echo htmlspecialchars($old_input['invoice_date'] ?? date('Y-m-d')); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="due_date" class="form-label">Due Date (Optional)</label>
                    <input type="date" class="form-control" id="due_date" name="due_date"
                           value="<?php echo htmlspecialchars($old_input['due_date'] ?? ''); ?>">
                </div>
            </div>

            <h4 class="mt-3">Un-invoiced Procedures:</h4>
            <?php if (empty($un_invoiced_procedures)): ?>
                <p class="alert alert-info">No un-invoiced procedures found for this patient.</p>
            <?php else: ?>
                <p><input type="checkbox" id="selectAllProcedures" class="me-2"><label for="selectAllProcedures">Select/Deselect All</label></p>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th><i class="fas fa-check-square"></i></th>
                                <th>Date Performed</th>
                                <th>Procedure Name</th>
                                <th class="text-end">Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_potential_invoice_amount = 0;
                            foreach ($un_invoiced_procedures as $proc):
                                $total_potential_invoice_amount += floatval($proc['procedure_price']);
                                $checked = (isset($old_input['procedure_ids']) && is_array($old_input['procedure_ids']) && in_array($proc['id'], $old_input['procedure_ids']))
                                           || (empty($old_input) && !isset($_GET['search_term'])); // Check all by default if no old input and not a search result page
                            ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="procedure_ids[]" value="<?php echo $proc['id']; ?>"
                                               class="form-check-input procedure-checkbox"
                                               data-price="<?php echo htmlspecialchars($proc['procedure_price']); ?>"
                                               <?php if ($checked) echo 'checked'; ?>>
                                    </td>
                                    <td><?php echo htmlspecialchars($proc['date_performed']); ?></td>
                                    <td><?php echo htmlspecialchars($proc['procedure_name']); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars(number_format($proc['procedure_price'], 2)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 text-end">
                    <h4>Selected Invoice Total: <span id="selectedTotalAmount">0.00</span></h4>
                </div>
                <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-file-invoice"></i> Generate Invoice</button>
            <?php endif; ?>
        </form>
        <p class="mt-3"><a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">Clear Patient Selection / Start Over</a></p>
    <?php elseif ($selected_patient_id && !$patient_details): ?>
         <p class="alert alert-danger mt-3">Could not load details for Patient ID <?php echo $selected_patient_id; ?>.</p>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = document.querySelectorAll('.procedure-checkbox');
    const selectedTotalAmountDisplay = document.getElementById('selectedTotalAmount');
    const selectAllCheckbox = document.getElementById('selectAllProcedures');

    function updateTotal() {
        let currentTotal = 0;
        checkboxes.forEach(function (checkbox) {
            if (checkbox.checked) {
                currentTotal += parseFloat(checkbox.dataset.price);
            }
        });
        if (selectedTotalAmountDisplay) {
            selectedTotalAmountDisplay.textContent = currentTotal.toFixed(2);
        }
    }

    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', updateTotal);
    });

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateTotal();
        });
    }

    // Initial calculation on page load
    updateTotal();
});
</script>

<?php
require_once $path_to_root . 'includes/footer.php';
?>
