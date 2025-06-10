<?php
require_once __DIR__ . '/../includes/SessionManager.php';
SessionManager::startSession();

// Include ErrorHandler and register it
require_once __DIR__ . '/../includes/ErrorHandler.php';
ErrorHandler::register();

// Include Database and db_connect (for $pdo)
require_once __DIR__ . '/../includes/db_connect.php'; // Provides $pdo
require_once __DIR__ . '/../includes/Database.php';    // Provides Database class

// CSRF Token Validation
$submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!SessionManager::validateCsrfToken($submittedToken)) {
    SessionManager::set('message', 'Invalid or missing CSRF token. Please try again.'); // Use 'message' for consistency with form
    header("Location: ../pages/add_clinician.php");
    exit;
}

// Check if user is logged in (already done by SessionManager in add_clinician.php, but good for direct access defense)
if (!SessionManager::get("user_id") || !SessionManager::get("role")) {
    SessionManager::set('message', "Please log in to perform this action.");
    header("Location: ../pages/login.php"); 
    exit;
}

// Check if user is admin
if (SessionManager::get("role") !== 'admin') {
    SessionManager::set('message', "Unauthorized action. Only administrators can add users.");
    header("Location: ../pages/dashboard.php"); 
    exit;
}

// Ensure the script only processes POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    SessionManager::set('message', "Invalid request method.");
    header("Location: ../pages/add_clinician.php");
    exit;
}

// Retrieve and trim data from POST
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : null;
$last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : null;
$role = isset($_POST['role']) ? trim($_POST['role']) : '';

// Store old input in session in case of validation errors (excluding password)
$old_input = $_POST;
unset($old_input['password'], $old_input['csrf_token']); // Don't repopulate password or CSRF token
SessionManager::set('form_old_input', $old_input);


if (empty($username) || empty($password) || empty($role)) {
    SessionManager::set('message', "Username, password, and role are required.");
    header("Location: ../pages/add_clinician.php");
    exit;
}

$allowed_roles = ['receptionist', 'nurse', 'clinician', 'admin'];
if (!in_array($role, $allowed_roles)) {
    SessionManager::set('message', "Invalid role selected.");
    header("Location: ../pages/add_clinician.php");
    exit;
}

$db = new Database($pdo); // Instantiate Database class

try {
    // Check for username uniqueness using PDO
    $stmt_check_username = $db->prepare("SELECT id FROM users WHERE username = :username");
    $db->execute($stmt_check_username, [':username' => $username]);
    if ($stmt_check_username->fetch()) {
        SessionManager::set('message', "Username '" . htmlspecialchars($username) . "' is already taken. Please choose another.");
        header("Location: ../pages/add_clinician.php");
        exit;
    }

    // Hash the password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    if ($password_hash === false) {
        // This is a server-side error, should be logged more discreetly for user
        error_log("CRITICAL: Password hashing failed for username: " . $username);
        SessionManager::set('message', "A critical error occurred during user creation (PHE01). Please contact support.");
        header("Location: ../pages/add_clinician.php");
        exit;
    }

    // Prepare SQL INSERT statement using PDO
    $sql_insert = "INSERT INTO users (username, password_hash, first_name, last_name, role, email, is_active, created_at, updated_at)
                   VALUES (:username, :password_hash, :first_name, :last_name, :role, :email, :is_active, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
    // email and is_active are not in the form, so set defaults or handle as NULL if schema allows
    // For now, setting email to NULL and is_active to 1 (default based on schema)
    $params_insert = [
        ':username' => $username,
        ':password_hash' => $password_hash,
        ':first_name' => !empty($first_name) ? $first_name : null,
        ':last_name' => !empty($last_name) ? $last_name : null,
        ':role' => $role,
        ':email' => null, // Assuming email is not collected in this form
        ':is_active' => 1 // Default to active
    ];
    $stmt_insert_user = $db->prepare($sql_insert);
    $db->execute($stmt_insert_user, $params_insert);

    SessionManager::set('message', "User '" . htmlspecialchars($username) . "' added successfully as " . htmlspecialchars($role) . ".");
    SessionManager::remove('form_old_input'); // Clear old input on success
    header("Location: ../pages/add_clinician.php"); // Redirect back to form page to show success or add another
    exit;

} catch (PDOException $e) {
    // Log the detailed error (ErrorHandler might do this if configured, or do it here)
    error_log("Database error during user creation: " . $e->getMessage());
    // Set a user-friendly error message
    SessionManager::set('message', "Failed to add user due to a database error. Please try again or contact support.");
    header("Location: ../pages/add_clinician.php");
    exit;
} catch (Exception $e) { // Catch any other general exceptions
    error_log("General error during user creation: " . $e->getMessage());
    SessionManager::set('message', "An unexpected error occurred. Please try again or contact support.");
    header("Location: ../pages/add_clinician.php");
    exit;
}
?>
