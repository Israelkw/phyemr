<?php
// Session management and authorization
require_once '../includes/SessionManager.php';
SessionManager::startSession();
SessionManager::ensureUserIsLoggedIn('login.php'); // Adjusted path for pages directory
SessionManager::hasRole(['clinician', 'receptionist'], 'dashboard.php', "Unauthorized access. Only clinicians or receptionists can add patients.");

// Include ErrorHandler and register it
require_once '../includes/ErrorHandler.php';
ErrorHandler::register();

// Include Database and db_connect (for $pdo)
require_once '../includes/db_connect.php'; // Provides $pdo
require_once '../includes/Database.php';    // Provides Database class

$db = new Database($pdo); // Instantiate Database class

$page_title = "Add Patient";
$path_to_root = "../"; // Define $path_to_root for includes

$clinician_list_from_db = [];
$clinician_load_error = null;

if (SessionManager::get("role") === 'receptionist') {
    try {
        $sql_clinicians = "SELECT id, username, first_name, last_name FROM users WHERE role = 'clinician' AND is_active = 1 ORDER BY last_name, first_name";
        $stmt_clinicians = $db->prepare($sql_clinicians);
        $db->execute($stmt_clinicians);
        $clinician_list_from_db = $db->fetchAll($stmt_clinicians);
    } catch (PDOException $e) {
        ErrorHandler::handleException($e); // Log error and potentially redirect to error page
        // Set a user-friendly message if ErrorHandler doesn't redirect or if we want to stay on this page with a message
        $clinician_load_error = "Could not load clinician list due to a database error. Please try again or contact support.";
        // Note: ErrorHandler::handleException might exit, so this line might not be reached
        // depending on ErrorHandler's implementation. If it does exit, the page won't render.
    }
}

require_once $path_to_root . 'includes/header.php'; 
// header.php includes navigation.php, which should use SessionManager::get('message')
// This line will be removed when converting to Twig fully. (This comment is now also being removed)

// Initialize Twig
require_once $path_to_root . 'includes/twig_init.php'; // Initializes $twig

// Prepare data for Twig template
$template_data = [
    'page_title' => $page_title, // Already set: "Add Patient"
    'form_action_path' => $path_to_root . 'php/handle_add_patient.php',
    'csrf_token' => SessionManager::generateCsrfToken(),
    'clinicians' => $clinician_list_from_db,
    'clinician_load_error' => $clinician_load_error,
    'show_clinician_dropdown' => (SessionManager::get("role") === 'receptionist'),
    'dashboard_path' => 'dashboard.php', // Path for "Back to Dashboard" link
    'old_input' => SessionManager::get('form_old_input', []), // For re-populating form on error
    // path_to_root is needed if base.html.twig uses it for global assets, but we hardcoded those.
    // 'path_to_root' => $path_to_root,
];
SessionManager::remove('form_old_input'); // Clear after use

// Clear general messages, as base.html.twig will display them from session global
$message = SessionManager::get('message');
if ($message) {
    SessionManager::remove('message');
}
$error_message = SessionManager::get('error_message');
if ($error_message) {
    SessionManager::remove('error_message');
}
// login_error and logout_message are usually for login page, but clear if set
if(SessionManager::get('login_error')) SessionManager::remove('login_error');
if(SessionManager::get('logout_message')) SessionManager::remove('logout_message');


// Render the template
try {
    echo $twig->render('pages/add_patient.html.twig', $template_data);
} catch (Exception $e) {
    ErrorHandler::handleException($e); // Use global error handler
}

?>
