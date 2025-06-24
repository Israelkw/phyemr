<?php
// Start session management first
require_once '../includes/SessionManager.php';
SessionManager::startSession();

// Include necessary files
require_once '../includes/db_connect.php'; // Provides $pdo
require_once '../includes/Database.php';    // Provides Database class
require_once '../includes/Validator.php';   // Provides Validator class
require_once '../includes/ErrorHandler.php'; // Provides ErrorHandler class

ErrorHandler::register();
$db = new Database($pdo);

// Authentication and Authorization
SessionManager::ensureUserIsLoggedIn('../pages/login.php');
SessionManager::hasRole(['receptionist'], '../pages/dashboard.php', "Unauthorized action.");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF Token Validation
    $submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!SessionManager::validateCsrfToken($submittedToken)) {
        SessionManager::set('error_message', 'Invalid or missing CSRF token. Please try again.');
        header("Location: ../pages/assign_existing_patient.php"); // Redirect back with error
        exit;
    }

    $validator = new Validator($_POST);
    $validator->addField('patient_id', 'required|numeric');
    $validator->addField('assigned_clinician_id', 'required|numeric');

    if (!$validator->validate()) {
        SessionManager::set('error_message', $validator->getFirstError());
        // Redirect back to the search page, perhaps with the search term if we can preserve it
        header("Location: ../pages/assign_existing_patient.php" . (isset($_POST['search_term_hidden']) ? '?search_term=' . urlencode($_POST['search_term_hidden']) : ''));
        exit;
    }

    $patient_id = $_POST['patient_id'];
    $assigned_clinician_id = $_POST['assigned_clinician_id'];
    $search_term_for_redirect = isset($_POST['search_term_hidden']) ? $_POST['search_term_hidden'] : '';


    // Validate patient exists
    $stmt_check_patient = $db->prepare("SELECT id, first_name, last_name FROM patients WHERE id = :id");
    $db->execute($stmt_check_patient, ['id' => $patient_id]);
    $patient = $db->fetch($stmt_check_patient);

    if (!$patient) {
        SessionManager::set('error_message', "Patient not found.");
        header("Location: ../pages/assign_existing_patient.php" . ($search_term_for_redirect ? '?search_term=' . urlencode($search_term_for_redirect) : ''));
        exit;
    }

    // Validate clinician exists and is active
    $stmt_check_clinician = $db->prepare("SELECT id, first_name, last_name FROM users WHERE id = :id AND role = 'clinician' AND is_active = 1");
    $db->execute($stmt_check_clinician, ['id' => $assigned_clinician_id]);
    $clinician = $db->fetch($stmt_check_clinician);

    if (!$clinician) {
        SessionManager::set('error_message', "Invalid or inactive clinician selected.");
        header("Location: ../pages/assign_existing_patient.php" . ($search_term_for_redirect ? '?search_term=' . urlencode($search_term_for_redirect) : ''));
        exit;
    }

    // Prepare SQL for updating patient's assigned clinician
    $sql = "UPDATE patients SET assigned_clinician_id = :assigned_clinician_id WHERE id = :patient_id";
    $params = [
        'assigned_clinician_id' => $assigned_clinician_id,
        'patient_id' => $patient_id
    ];

    try {
        $stmt = $db->prepare($sql);
        $db->execute($stmt, $params);
        SessionManager::set('message', "Patient " . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . " successfully assigned to clinician " . htmlspecialchars($clinician['first_name'] . ' ' . $clinician['last_name']) . ".");
        header("Location: ../pages/assign_existing_patient.php" . ($search_term_for_redirect ? '?search_term=' . urlencode($search_term_for_redirect) : ''));
    } catch (PDOException $e) {
        ErrorHandler::handleException($e); // This will redirect to error.php or display error
    }
    exit;

} else {
    // Not a POST request
    SessionManager::set('error_message', "Invalid request method.");
    header("Location: ../pages/assign_existing_patient.php");
    exit;
}
?>
