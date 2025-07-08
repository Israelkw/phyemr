<?php
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();

// 1. Authorization: Ensure user is logged in and is a clinician or admin (admin for testing)
// Allow admin for easier testing and potential oversight roles.
if (!SessionManager::hasRole(['clinician', 'admin'], $path_to_root . 'pages/dashboard.php', 'Access Denied.')) {
    // The hasRole method already handles redirection and exit.
}
$current_user_id = SessionManager::get('user_id');
$current_user_role = SessionManager::get('role');


// 2. Get patient_id from URL
if (!isset($_GET['patient_id']) || empty($_GET['patient_id'])) {
    SessionManager::set('message', "No patient selected.");
    header("location: " . $path_to_root . "pages/view_my_patients.php"); // Or dashboard if more appropriate
    exit;
}
$patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);
if (!$patient_id) {
    SessionManager::set('message', "Invalid patient ID.");
    header("location: " . $path_to_root . "pages/view_my_patients.php"); // Or dashboard
    exit;
}

// 3. Database connection
require_once $path_to_root . 'includes/db_connect.php'; // Provides $pdo
require_once $path_to_root . 'includes/Database.php';    // Provides Database class
$db = new Database($pdo);

$patient = null;
$available_procedures = [];
$assigned_patient_procedures = [];
$db_error_message = '';

try {
    // Verify patient exists and, if user is clinician, is assigned to them
    $sql_patient_check = "SELECT id, first_name, last_name, date_of_birth FROM patients WHERE id = :patient_id";
    $params_patient_check = [':patient_id' => $patient_id];
    if ($current_user_role === 'clinician') {
        $sql_patient_check .= " AND assigned_clinician_id = :clinician_id";
        $params_patient_check[':clinician_id'] = $current_user_id;
    }

    $stmt_patient = $db->prepare($sql_patient_check);
    $db->execute($stmt_patient, $params_patient_check);
    $patient = $db->fetch($stmt_patient);

    if (!$patient) {
        SessionManager::set('message', "Patient not found or not assigned to you.");
        header("location: " . $path_to_root . ($current_user_role === 'clinician' ? "pages/view_my_patients.php" : "pages/dashboard.php"));
        exit;
    }

    // Fetch all available procedures for the assignment form
    $stmt_avail_proc = $db->prepare("SELECT id, name, price FROM procedures ORDER BY name ASC");
    $db->execute($stmt_avail_proc);
    $available_procedures = $db->fetchAll($stmt_avail_proc);

    // Fetch procedures already assigned to this patient
    $sql_assigned_proc = "
        SELECT pp.id, pp.date_performed, pp.notes, pr.name AS procedure_name, pr.price AS procedure_price,
               u.username AS assigned_by_username, u.first_name AS assigned_by_first, u.last_name AS assigned_by_last
        FROM patient_procedures pp
        JOIN procedures pr ON pp.procedure_id = pr.id
        JOIN users u ON pp.clinician_id = u.id
        WHERE pp.patient_id = :patient_id
        ORDER BY pp.date_performed DESC, pr.name ASC";
    $stmt_assigned_proc = $db->prepare($sql_assigned_proc);
    $db->execute($stmt_assigned_proc, [':patient_id' => $patient_id]);
    $assigned_patient_procedures = $db->fetchAll($stmt_assigned_proc);

} catch (PDOException $e) {
    error_log("Error fetching data for patient dashboard (ID: $patient_id): " . $e->getMessage());
    $db_error_message = "An error occurred while fetching patient or procedure data.";
    // For critical errors, might redirect, but here we'll display on page.
}

$page_title = "Patient Dashboard - " . htmlspecialchars($patient['first_name'] ?? '') . " " . htmlspecialchars($patient['last_name'] ?? '');
$csrf_token_remove = SessionManager::generateCsrfToken(); // For the remove patient form
$csrf_token_assign = SessionManager::generateCsrfToken(); // For assign procedures form

// Retrieve old form input (if any) for assign procedures form
$old_input_assign_proc = SessionManager::get('form_old_input_assign_proc', []);
SessionManager::remove('form_old_input_assign_proc');


require_once $path_to_root . 'includes/header.php';
?>

<div class="container mt-4">

    <?php if (SessionManager::has('message')): ?>
        <div class="alert <?php echo strpos(strtolower(SessionManager::get('message')), 'success') !== false ? 'alert-success' : (strpos(strtolower(SessionManager::get('message')), 'error') !== false || strpos(strtolower(SessionManager::get('message')), 'fail') !== false || strpos(strtolower(SessionManager::get('message')), 'invalid') !== false ? 'alert-danger' : 'alert-info'); ?>">
            <?php echo htmlspecialchars(SessionManager::get('message')); SessionManager::remove('message'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($db_error_message)): ?>
        <div class="alert alert-danger"><?php echo $db_error_message; ?></div>
    <?php endif; ?>

    <?php if ($patient): ?>
        <h2>Patient: <?php echo htmlspecialchars($patient['first_name'] . " " . $patient['last_name']); ?></h2>
        <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($patient['date_of_birth']); ?></p>
        <p><strong>Patient ID:</strong> <?php echo htmlspecialchars($patient['id']); ?></p>

        <hr>
        <h4 class="mb-3">Patient Actions</h4>
        <div class="list-group mb-4">
            <a href="view_patient_history.php?patient_id=<?php echo $patient_id; ?>" class="list-group-item list-group-item-action">
                <i class="fas fa-history"></i> View Full Patient History
            </a>
            <a href="select_form_for_patient.php?patient_id=<?php echo $patient_id; ?>" class="list-group-item list-group-item-action">
                <i class="fas fa-file-alt"></i> Add New Evaluation Form
            </a>
            <a href="fill_patient_form.php?patient_id=<?php echo $patient_id; ?>&form_name=general-information.html&form_directory=patient_general_info" class="list-group-item list-group-item-action">
                <i class="fas fa-notes-medical"></i> Fill General Information
            </a>
        </div>

        <!-- Display Assigned Procedures -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0">Assigned Procedures</h4>
            </div>
            <div class="card-body">
                <?php if (empty($assigned_patient_procedures) && empty($db_error_message)): ?>
                    <p>No procedures have been assigned to this patient yet.</p>
                <?php elseif (!empty($assigned_patient_procedures)): ?>
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Date Performed</th>
                                <th>Procedure</th>
                                <th>Assigned By</th>
                                <th>Notes</th>
                                <th class="text-end">Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assigned_patient_procedures as $proc): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($proc['date_performed']); ?></td>
                                    <td><?php echo htmlspecialchars($proc['procedure_name']); ?></td>
                                    <td><?php echo htmlspecialchars($proc['assigned_by_first'] . ' ' . $proc['assigned_by_last']); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($proc['notes'] ?? 'N/A')); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars(number_format($proc['procedure_price'], 2)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Assign New Procedures Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0">Assign New Procedures</h4>
            </div>
            <div class="card-body">
                <form action="<?php echo $path_to_root; ?>php/handle_assign_patient_procedures.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_assign; ?>">
                    <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($patient['id']); ?>">
                    <input type="hidden" name="source_page" value="clinician_patient_dashboard"> <!-- For redirect logic -->

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="date_performed" class="form-label">Date Performed</label>
                            <input type="date" class="form-control" id="date_performed" name="date_performed"
                                   value="<?php echo htmlspecialchars($old_input_assign_proc['date_performed'] ?? date('Y-m-d')); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Select Procedures (Scrollable List)</label>
                        <?php if (empty($available_procedures) && empty($db_error_message)): ?>
                            <p>No procedures available to assign. Please contact an administrator.</p>
                        <?php elseif(!empty($available_procedures)): ?>
                            <div class="border p-2" style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($available_procedures as $procedure): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               name="procedure_ids[]"
                                               value="<?php echo htmlspecialchars($procedure['id']); ?>"
                                               id="procedure_<?php echo htmlspecialchars($procedure['id']); ?>"
                                               <?php echo (isset($old_input_assign_proc['procedure_ids']) && is_array($old_input_assign_proc['procedure_ids']) && in_array($procedure['id'], $old_input_assign_proc['procedure_ids'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="procedure_<?php echo htmlspecialchars($procedure['id']); ?>">
                                            <?php echo htmlspecialchars($procedure['name']); ?>
                                            (<?php echo htmlspecialchars(number_format($procedure['price'], 2)); ?>)
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($old_input_assign_proc['notes'] ?? ''); ?></textarea>
                    </div>

                    <?php if (!empty($available_procedures)): ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Assign Selected Procedures
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>


        <?php if ($current_user_role === 'clinician'): // Only show administrative actions for the assigned clinician ?>
        <hr>
        <h4 class="mt-4 mb-3">Administrative Actions for this Patient</h4>
        <form action="<?php echo $path_to_root; ?>php/handle_remove_patient_from_list.php" method="POST" class="mt-3"
              onsubmit="return confirm('Are you sure you want to remove this patient from your active list? This patient will become unassigned.');">
            <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($patient['id']); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_remove; ?>">
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-user-minus"></i> Remove Patient from My List
            </button>
        </form>
        <?php endif; ?>

        <div class="mt-4 mb-5">
            <a href="<?php echo $path_to_root . ($current_user_role === 'clinician' ? 'pages/view_my_patients.php' : 'pages/dashboard.php'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to <?php echo ($current_user_role === 'clinician' ? 'My Patients List' : 'Dashboard'); ?>
            </a>
        </div>

    <?php elseif (empty($db_error_message)) : ?>
        <div class="alert alert-warning">Patient data could not be loaded.</div>
    <?php endif; ?>
</div>

<?php
require_once $path_to_root . 'includes/footer.php';
?>
