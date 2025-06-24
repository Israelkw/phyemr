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

// Authentication and Authorization: Ensure user is a clinician
SessionManager::ensureUserIsLoggedIn('../pages/login.php');
SessionManager::hasRole(['clinician'], '../pages/dashboard.php', "Unauthorized action. Only clinicians can remove patients from their list.");

$current_clinician_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF Token Validation
    $submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!SessionManager::validateCsrfToken($submittedToken)) {
        SessionManager::set('message', 'Invalid or missing CSRF token. Please try again.'); // Using 'message' for info/error display
        header("Location: ../pages/view_my_patients.php");
        exit;
    }

    $validator = new Validator($_POST);
    $validator->addField('patient_id', 'required|numeric');

    if (!$validator->validate()) {
        SessionManager::set('message', $validator->getFirstError());
        header("Location: ../pages/view_my_patients.php");
        exit;
    }

    $patient_id = $_POST['patient_id'];

    // Verify that the patient is actually assigned to this clinician before removing
    $stmt_check_assignment = $db->prepare("SELECT id FROM patients WHERE id = :patient_id AND assigned_clinician_id = :clinician_id");
    $db->execute($stmt_check_assignment, [':patient_id' => $patient_id, ':clinician_id' => $current_clinician_id]);
    $patient_assigned = $db->fetch($stmt_check_assignment);

    if (!$patient_assigned) {
        SessionManager::set('message', "Patient not found in your list or not authorized for this action.");
        header("Location: ../pages/view_my_patients.php");
        exit;
    }

    // Prepare SQL for updating patient's assigned_clinician_id to NULL
    $sql = "UPDATE patients SET assigned_clinician_id = NULL WHERE id = :patient_id AND assigned_clinician_id = :current_clinician_id";
    $params = [
        'patient_id' => $patient_id,
        'current_clinician_id' => $current_clinician_id // Ensure we only unassign if currently assigned to this clinician
    ];

    try {
        $stmt = $db->prepare($sql);
        $db->execute($stmt, $params);

        if ($stmt->rowCount() > 0) {
            SessionManager::set('message', "Patient (ID: " . htmlspecialchars($patient_id) . ") has been successfully removed from your active list and is now unassigned.");
        } else {
            // This case might happen if, between page load and form submission, the patient was reassigned by someone else.
            SessionManager::set('message', "Could not remove patient. They might have been reassigned or the action was already performed. Please refresh and try again.");
        }
        header("Location: ../pages/view_my_patients.php");
    } catch (PDOException $e) {
        ErrorHandler::handleException($e); // This will redirect to error.php or display error
    }
    exit;

} else {
    // Not a POST request
    SessionManager::set('message', "Invalid request method.");
    header("Location: ../pages/view_my_patients.php");
    exit;
}
?>
