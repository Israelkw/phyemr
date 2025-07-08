<?php
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();

require_once $path_to_root . 'includes/ErrorHandler.php';
ErrorHandler::register();

require_once $path_to_root . 'includes/db_connect.php'; // Provides $pdo
require_once $path_to_root . 'includes/Database.php';    // Provides Database class

// CSRF Token Validation
$submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!SessionManager::validateCsrfToken($submittedToken)) {
    SessionManager::set('message', 'Invalid or missing CSRF token. Please try again.');
    header("Location: " . $path_to_root . "pages/clinician_assign_procedures.php");
    exit;
}

// Role check: Ensure user is clinician or admin (admin for testing/flexibility)
SessionManager::hasRole(['clinician', 'admin'], $path_to_root . 'pages/dashboard.php', 'You do not have permission to perform this action.');

// Ensure the script only processes POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    SessionManager::set('message', "Invalid request method.");
    header("Location: " . $path_to_root . "pages/clinician_assign_procedures.php");
    exit;
}

// Retrieve and validate data from POST
$patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
$procedure_ids = isset($_POST['procedure_ids']) && is_array($_POST['procedure_ids']) ? $_POST['procedure_ids'] : [];
$date_performed_str = isset($_POST['date_performed']) ? trim($_POST['date_performed']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
$clinician_id = SessionManager::get('user_id'); // Get clinician_id from session

// Store old input in session in case of validation errors
$old_input = $_POST;
unset($old_input['csrf_token']);
SessionManager::set('form_old_input', $old_input);

// --- Validation ---
$redirect_url = $path_to_root . "pages/clinician_assign_procedures.php";
if ($patient_id) { // Append patient_id to redirect URL if available to keep patient selected
    $redirect_url .= "?patient_id=" . $patient_id;
}

if (!$patient_id) {
    SessionManager::set('message', "Patient selection is required.");
    header("Location: " . $redirect_url);
    exit;
}

if (empty($procedure_ids)) {
    SessionManager::set('message', "At least one procedure must be selected.");
    header("Location: " . $redirect_url);
    exit;
}

// Validate each procedure ID
foreach ($procedure_ids as $pid) {
    if (!filter_var($pid, FILTER_VALIDATE_INT)) {
        SessionManager::set('message', "Invalid procedure ID submitted.");
        header("Location: " . $redirect_url);
        exit;
    }
}

if (empty($date_performed_str)) {
    SessionManager::set('message', "Date performed is required.");
    header("Location: " . $redirect_url);
    exit;
}

// Validate date format (Y-m-d)
$date_performed_obj = DateTime::createFromFormat('Y-m-d', $date_performed_str);
if (!$date_performed_obj || $date_performed_obj->format('Y-m-d') !== $date_performed_str) {
    SessionManager::set('message', "Invalid date format for Date Performed. Please use YYYY-MM-DD.");
    header("Location: " . $redirect_url);
    exit;
}
// Optional: check if date is in the future, if that's a business rule. For now, any valid date is accepted.

if (!$clinician_id) { // Should always be set if user is logged in
    SessionManager::set('message', "Error: Clinician ID not found in session. Please log in again.");
    header("Location: " . $path_to_root . "pages/login.php");
    exit;
}

$db = new Database($pdo);

try {
    // Fetch patient name for success message
    $stmt_patient_name = $db->prepare("SELECT first_name, last_name FROM patients WHERE id = :patient_id");
    $db->execute($stmt_patient_name, [':patient_id' => $patient_id]);
    $patient_info = $db->fetch($stmt_patient_name);
    $patient_name_display = $patient_info ? htmlspecialchars($patient_info['first_name'] . " " . $patient_info['last_name']) : "Patient ID " . $patient_id;

    $pdo->beginTransaction(); // Start transaction for multiple inserts

    $sql_insert = "INSERT INTO patient_procedures (patient_id, procedure_id, clinician_id, date_performed, notes)
                   VALUES (:patient_id, :procedure_id, :clinician_id, :date_performed, :notes)";
    $stmt_insert = $db->prepare($sql_insert);

    $assigned_count = 0;
    foreach ($procedure_ids as $procedure_id_int) {
        $procedure_id_int = intval($procedure_id_int); // Ensure it's an integer
        $params_insert = [
            ':patient_id' => $patient_id,
            ':procedure_id' => $procedure_id_int,
            ':clinician_id' => $clinician_id,
            ':date_performed' => $date_performed_str,
            ':notes' => $notes
        ];
        $db->execute($stmt_insert, $params_insert);
        $assigned_count++;
    }

    $pdo->commit(); // Commit transaction

    SessionManager::set('message', "$assigned_count procedure(s) assigned successfully to " . $patient_name_display . ".");
    SessionManager::remove('form_old_input'); // Clear old input on success

    // Redirect, possibly back to the assign page for the same patient or a patient dashboard
    header("Location: " . $path_to_root . "pages/clinician_assign_procedures.php?patient_id=" . $patient_id);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error during patient procedure assignment: " . $e->getMessage());
    SessionManager::set('message', "Failed to assign procedures due to a database error. Please try again or contact support.");
    header("Location: " . $redirect_url);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("General error during patient procedure assignment: " . $e->getMessage());
    SessionManager::set('message', "An unexpected error occurred. Please try again or contact support.");
    header("Location: " . $redirect_url);
    exit;
}
?>
