<?php
// Start session management first
require_once '../includes/SessionManager.php';
SessionManager::startSession();

// Include necessary files
require_once '../includes/db_connect.php'; // Provides $pdo
require_once '../includes/Database.php';    // Provides Database class
require_once '../includes/Validator.php';   // Provides Validator class
require_once '../includes/ErrorHandler.php'; // Provides ErrorHandler class

// Register the error handler (ErrorHandler also calls session_start if not already called, SessionManager handles it better)
ErrorHandler::register();

// Instantiate the Database class
$db = new Database($pdo);

// Authentication and Authorization
SessionManager::ensureUserIsLoggedIn('../pages/login.php');
SessionManager::hasRole(['clinician', 'receptionist'], '../pages/dashboard.php', "Unauthorized access. Only clinicians or receptionists can perform this action.");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF Token Validation
    $submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!SessionManager::validateCsrfToken($submittedToken)) {
        SessionManager::set('message', 'Invalid or missing CSRF token. Please try again.');
        header("Location: ../pages/add_patient.php");
        exit;
    }

    $validator = new Validator($_POST);

    $validator->addField('first_name', 'required|minLength:2|maxLength:50');
    $validator->addField('last_name', 'required|minLength:2|maxLength:50');
    $validator->addField('date_of_birth', 'required|date:Y-m-d');

    $registered_by_user_id = SessionManager::get('user_id');
    $assigned_clinician_id = null; // Initialize
    $current_user_role = SessionManager::get('role');

    if ($current_user_role === 'receptionist') {
        $validator->addField('assigned_clinician_id', 'required|numeric');
    }

    if (!$validator->validate()) {
        SessionManager::set('message', $validator->getFirstError()); // Get the first validation error
        header("Location: ../pages/add_patient.php");
        exit;
    }

    // Retrieve validated data
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $date_of_birth = $_POST['date_of_birth'];

    // Handle assigned_clinician_id based on role after basic validation
    if ($current_user_role === 'receptionist') {
        $assigned_clinician_id_post = $_POST['assigned_clinician_id'];
        // Validate assigned_clinician_id exists and is active (Database check)
        $stmt_check_clinician = $db->prepare("SELECT id FROM users WHERE id = :id AND role = 'clinician' AND is_active = 1");
        $db->execute($stmt_check_clinician, ['id' => $assigned_clinician_id_post]);
        $clinician = $db->fetch($stmt_check_clinician);

        if ($clinician) {
            $assigned_clinician_id = $assigned_clinician_id_post;
        } else {
            SessionManager::set('message', "Invalid or inactive clinician selected.");
            header("Location: ../pages/add_patient.php");
            exit;
        }
    } elseif ($current_user_role === 'clinician') {
        $assigned_clinician_id = $registered_by_user_id; // Clinician assigns to themselves
    }

    // Prepare SQL for inserting patient data
    $sql = "INSERT INTO patients (first_name, last_name, date_of_birth, assigned_clinician_id, registered_by_user_id)
            VALUES (:first_name, :last_name, :date_of_birth, :assigned_clinician_id, :registered_by_user_id)";

    $params = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'date_of_birth' => $date_of_birth,
        'assigned_clinician_id' => $assigned_clinician_id,
        'registered_by_user_id' => $registered_by_user_id
    ];

    try {
        $stmt = $db->prepare($sql);
        $db->execute($stmt, $params);
        SessionManager::set('selected_patient_id_for_form', $db->getLastInsertId());
        SessionManager::set('message', "Patient '" . htmlspecialchars($first_name) . " " . htmlspecialchars($last_name) . "' added successfully. Please fill out the demography form.");
        header("Location: ../pages/fill_patient_form.php?form_name=demo.html&form_directory=patient_general_info");
    } catch (PDOException $e) {
        // Use ErrorHandler for database exceptions
        ErrorHandler::handleException($e); // This will redirect to error.php
    }
    exit;

} else {
    // Not a POST request
    SessionManager::set('message', "Invalid request method.");
    header("Location: ../pages/dashboard.php");
    exit;
}
?>
