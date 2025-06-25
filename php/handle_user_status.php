<?php
$path_to_root = "../"; // Relative path to the root directory
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();
require_once $path_to_root . 'includes/db_connect.php'; // Provides $pdo
require_once $path_to_root . 'includes/Database.php';    // Provides Database class

$db = new Database($pdo);

// 1. Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    SessionManager::set('message', 'Unauthorized access.');
    header("Location: " . $path_to_root . "pages/login.php");
    exit();
}

// 2. Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    SessionManager::set('message', 'Invalid request method.');
    header("Location: " . $path_to_root . "pages/manage_clinicians.php");
    exit();
}

// 3. Validate CSRF token
$submittedToken = $_POST['csrf_token'] ?? '';
if (!SessionManager::validateCsrfToken($submittedToken)) {
    SessionManager::set('message', 'Invalid CSRF token. Action aborted.');
    header("Location: " . $path_to_root . "pages/manage_clinicians.php");
    exit();
}

// 4. Get and validate inputs
$user_id_to_change = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

if (!$user_id_to_change || !$action || !in_array($action, ['activate', 'deactivate'])) {
    SessionManager::set('message', 'Invalid user ID or action specified.');
    header("Location: " . $path_to_root . "pages/manage_clinicians.php");
    exit();
}

// 5. Prevent admin from deactivating themselves
if ($action === 'deactivate' && $user_id_to_change == $_SESSION['user_id']) {
    SessionManager::set('message', 'Error: You cannot deactivate your own account.');
    header("Location: " . $path_to_root . "pages/manage_clinicians.php");
    exit();
}

// 6. Determine the new status
$new_status = ($action === 'activate') ? 1 : 0;

// 7. Update user status in the database
try {
    // Check if user exists (optional, but good practice)
    $stmt_check = $db->prepare("SELECT id FROM users WHERE id = :user_id");
    $db->execute($stmt_check, [':user_id' => $user_id_to_change]);
    if (!$db->fetch($stmt_check)) {
        SessionManager::set('message', "Error: User with ID {$user_id_to_change} not found.");
        header("Location: " . $path_to_root . "pages/manage_clinicians.php");
        exit();
    }

    $sql_update = "UPDATE users SET is_active = :is_active WHERE id = :user_id";
    $stmt_update = $db->prepare($sql_update);
    $db->execute($stmt_update, [':is_active' => $new_status, ':user_id' => $user_id_to_change]);

    $action_past_tense = ($action === 'activate') ? 'activated' : 'deactivated';
    SessionManager::set('message', "User successfully {$action_past_tense}.");

} catch (PDOException $e) {
    error_log("Error in handle_user_status.php: " . $e->getMessage());
    SessionManager::set('message', 'A database error occurred. Could not update user status.');
    // In a production environment, you might want to log this error more formally
    // and show a generic error message to the user.
}

// 8. Redirect back to the manage clinicians page
header("Location: " . $path_to_root . "pages/manage_clinicians.php");
exit();

?>
