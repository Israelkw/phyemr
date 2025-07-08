<?php
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();

// 1. Authorization: Ensure user is logged in and is a clinician or admin
if (!SessionManager::hasRole(['clinician', 'admin'], $path_to_root . 'pages/dashboard.php', 'Access Denied.')) {
    // hasRole handles redirection
}
$current_user_id = SessionManager::get('user_id');
$current_user_role = SessionManager::get('role');

// 2. Get patient_id from URL
if (!isset($_GET['patient_id']) || empty($_GET['patient_id'])) {
    SessionManager::set('message', "No patient selected.");
    header("location: " . $path_to_root . ($current_user_role === 'clinician' ? "pages/view_my_patients.php" : "pages/dashboard.php"));
    exit;
}
$patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);
if (!$patient_id) {
    SessionManager::set('message', "Invalid patient ID.");
    header("location: " . $path_to_root . ($current_user_role === 'clinician' ? "pages/view_my_patients.php" : "pages/dashboard.php"));
    exit;
}

// 3. Database connection
require_once $path_to_root . 'includes/db_connect.php'; // Provides $pdo
require_once $path_to_root . 'includes/Database.php';    // Provides Database class
$db = new Database($pdo);

$patient = null;
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
} catch (PDOException $e) {
    error_log("Error fetching patient details for dashboard (ID: $patient_id): " . $e->getMessage());
    $db_error_message = "An error occurred while fetching patient data.";
}

$page_title = "Patient Dashboard - " . htmlspecialchars($patient['first_name'] ?? '') . " " . htmlspecialchars($patient['last_name'] ?? '');
$csrf_token_remove = SessionManager::generateCsrfToken(); // For the remove patient form

// Clear any old form input for procedure assignment as it's not on this page anymore
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
            <a href="<?php echo $path_to_root; ?>pages/clinician_manage_patient_procedures.php?patient_id=<?php echo htmlspecialchars($patient['id']); ?>" class="list-group-item list-group-item-action">
                <i class="fas fa-file-medical"></i> Manage Patient Procedures
            </a>
        </div>

        <?php if ($current_user_role === 'clinician'): ?>
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
