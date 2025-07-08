<?php
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();
SessionManager::hasRole(['receptionist', 'admin'], $path_to_root . 'pages/dashboard.php', 'You do not have permission to access this page.'); // Allow admin for testing

require_once $path_to_root . 'includes/db_connect.php'; // Provides $pdo
require_once $path_to_root . 'includes/Database.php';    // Provides Database class
$db = new Database($pdo);

// Fetch all patients for selection
$stmt_patients = $db->prepare("SELECT id, first_name, last_name FROM patients ORDER BY last_name, first_name ASC");
$db->execute($stmt_patients);
$patients = $db->fetchAll($stmt_patients);

$selected_patient_id = null;
$patient_procedures_details = [];
$total_cost = 0;
$patient_name = '';

// Check if a patient is selected via GET (e.g., from another page or after form submission) or POST (from this page's form)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['patient_id'])) {
    $selected_patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
} elseif (isset($_GET['patient_id'])) {
    $selected_patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);
}


if ($selected_patient_id) {
    // Fetch patient's name
    $stmt_patient_info = $db->prepare("SELECT first_name, last_name FROM patients WHERE id = :patient_id");
    $db->execute($stmt_patient_info, [':patient_id' => $selected_patient_id]);
    $patient_info_data = $db->fetch($stmt_patient_info);
    if ($patient_info_data) {
        $patient_name = htmlspecialchars($patient_info_data['first_name'] . ' ' . $patient_info_data['last_name']);
    } else {
        SessionManager::set('message', "Selected patient not found.");
        $selected_patient_id = null; // Reset if patient not found
    }

    if ($selected_patient_id) { // Proceed if patient was found
        // Fetch procedures assigned to the selected patient along with procedure details
        $sql_patient_procedures = "
            SELECT
                pp.id, pp.date_performed, pp.notes,
                p.name AS procedure_name, p.price AS procedure_price,
                u.username AS clinician_username, u.first_name AS clinician_first_name, u.last_name AS clinician_last_name
            FROM patient_procedures pp
            JOIN procedures p ON pp.procedure_id = p.id
            JOIN users u ON pp.clinician_id = u.id
            WHERE pp.patient_id = :patient_id
            ORDER BY pp.date_performed DESC, p.name ASC";

        $stmt_patient_procedures = $db->prepare($sql_patient_procedures);
        $db->execute($stmt_patient_procedures, [':patient_id' => $selected_patient_id]);
        $patient_procedures_details = $db->fetchAll($stmt_patient_procedures);

        foreach ($patient_procedures_details as $detail) {
            $total_cost += floatval($detail['procedure_price']);
        }
    }
}

// Include header
require_once $path_to_root . 'includes/header.php';
?>

<div class="container mt-5">
    <h2 class="mb-4">Patient Billing Information</h2>

    <?php if (SessionManager::has('message')): ?>
        <div class="alert <?php echo strpos(strtolower(SessionManager::get('message')), 'success') !== false ? 'alert-success' : (strpos(strtolower(SessionManager::get('message')), 'error') !== false || strpos(strtolower(SessionManager::get('message')), 'fail') !== false ? 'alert-danger' : 'alert-info'); ?>">
            <?php
                echo htmlspecialchars(SessionManager::get('message'));
                SessionManager::remove('message');
            ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="mb-4">
        <input type="hidden" name="csrf_token" value="<?php echo SessionManager::generateCsrfToken(); // For potential future use, not strictly necessary for a GET-like action ?>">
        <div class="row">
            <div class="col-md-8">
                <label for="patient_id" class="form-label">Select Patient</label>
                <select class="form-select" id="patient_id" name="patient_id" required>
                    <option value="">-- Select a Patient --</option>
                    <?php foreach ($patients as $patient): ?>
                        <option value="<?php echo htmlspecialchars($patient['id']); ?>"
                                <?php echo ($selected_patient_id == $patient['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name'] . ' (ID: ' . $patient['id'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 align-self-end">
                <button type="submit" class="btn btn-primary w-100">View Billing Details</button>
            </div>
        </div>
    </form>

    <?php if ($selected_patient_id && $patient_name): ?>
        <hr>
        <h3 class="mt-4">Billing Details for: <?php echo $patient_name; ?> (ID: <?php echo $selected_patient_id; ?>)</h3>

        <?php if (!empty($patient_procedures_details)): ?>
            <table class="table table-striped mt-3">
                <thead>
                    <tr>
                        <th>Date Performed</th>
                        <th>Procedure Name</th>
                        <th>Clinician</th>
                        <th>Notes</th>
                        <th class="text-end">Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patient_procedures_details as $detail): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($detail['date_performed']); ?></td>
                            <td><?php echo htmlspecialchars($detail['procedure_name']); ?></td>
                            <td><?php echo htmlspecialchars($detail['clinician_first_name'] . ' ' . $detail['clinician_last_name'] . ' (' . $detail['clinician_username'] . ')'); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($detail['notes'] ?? 'N/A')); ?></td>
                            <td class="text-end"><?php echo htmlspecialchars(number_format($detail['procedure_price'], 2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-end">Total Cost:</th>
                        <th class="text-end"><?php echo htmlspecialchars(number_format($total_cost, 2)); ?></th>
                    </tr>
                </tfoot>
            </table>

            <div class="text-center mt-4">
                <a href="<?php echo $path_to_root; ?>pages/print_payment_attachment.php?patient_id=<?php echo $selected_patient_id; ?>"
                   class="btn btn-success" target="_blank">
                    <i class="fas fa-print"></i> Print Payment Attachment
                </a>
            </div>

        <?php else: ?>
            <p class="mt-3">No procedures found for this patient.</p>
        <?php endif; ?>
    <?php elseif ($selected_patient_id && !$patient_name): ?>
         <p class="mt-3 alert alert-warning">Could not load details for the selected patient ID.</p>
    <?php endif; ?>
</div>

<?php
// Include footer
require_once $path_to_root . 'includes/footer.php';
?>
