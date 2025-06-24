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
    // Optional fields can be added here if they have specific validation rules
    // For now, 'sex', 'address', 'phone_number', 'email', 'insurance_details', 'reason_for_visit'
    // are treated as optional text or specific types handled by HTML5 form validation,
    // and their presence/format isn't strictly validated here beyond what Validator might do by default if rules were set.
    // Example for email if it were required: $validator->addField('email', 'required|email');

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
    // Retrieve optional fields, providing null if not set or empty
    $sex = !empty($_POST['sex']) ? $_POST['sex'] : null;
    $address = !empty($_POST['address']) ? $_POST['address'] : null;
    $phone_number = !empty($_POST['phone_number']) ? $_POST['phone_number'] : null;
    $email = !empty($_POST['email']) ? $_POST['email'] : null;
    $insurance_details = !empty($_POST['insurance_details']) ? $_POST['insurance_details'] : null;
    $reason_for_visit = !empty($_POST['reason_for_visit']) ? $_POST['reason_for_visit'] : null;

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

    // Prepare SQL for inserting patient data, including new optional fields
    $sql = "INSERT INTO patients (first_name, last_name, date_of_birth, sex, address, phone_number, email, insurance_details, reason_for_visit, assigned_clinician_id, registered_by_user_id)
            VALUES (:first_name, :last_name, :date_of_birth, :sex, :address, :phone_number, :email, :insurance_details, :reason_for_visit, :assigned_clinician_id, :registered_by_user_id)";

    $params = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'date_of_birth' => $date_of_birth,
        'sex' => $sex,
        'address' => $address,
        'phone_number' => $phone_number,
        'email' => $email,
        'insurance_details' => $insurance_details,
        'reason_for_visit' => $reason_for_visit,
        'assigned_clinician_id' => $assigned_clinician_id,
        'registered_by_user_id' => $registered_by_user_id
    ];

    try {
        $stmt = $db->prepare($sql);
        $db->execute($stmt, $params);
        // $patient_id = $db->getLastInsertId(); // Get new patient ID
        SessionManager::set('message', "Patient '" . htmlspecialchars($first_name) . " " . htmlspecialchars($last_name) . "' added successfully with all demographic information.");
        // Redirect to dashboard or a patient view page, as demo.html form filling is now part of add_patient.php
        header("Location: ../pages/dashboard.php");
    } catch (PDOException $e) {
        // Store form input in session to repopulate the form
        $_SESSION['form_old_input'] = $_POST;
        // Use ErrorHandler for database exceptions
        ErrorHandler::handleException($e); // This will redirect to error.php or display error
    }
    exit;

} else {
    // Not a POST request
    SessionManager::set('message', "Invalid request method.");
    header("Location: ../pages/dashboard.php"); // Redirect to dashboard
    exit;
}
?>
