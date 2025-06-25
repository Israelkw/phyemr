<?php
$path_to_root = "../"; // Relative path to the root directory
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();
require_once $path_to_root . 'includes/db_connect.php'; // Provides $pdo
require_once $path_to_root . 'includes/Database.php';    // Provides Database class
require_once $path_to_root . 'includes/Validator.php';  // Provides Validator class

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
    // Redirect back to manage_clinicians or the specific change password page if user_id is known
    header("Location: " . $path_to_root . "pages/manage_clinicians.php");
    exit();
}

// 3. Validate CSRF token
$submittedToken = $_POST['csrf_token'] ?? '';
if (!SessionManager::validateCsrfToken($submittedToken)) {
    SessionManager::set('message', 'Invalid CSRF token. Action aborted.');
    header("Location: " . $path_to_root . "pages/manage_clinicians.php"); // Or back to the form with an error
    exit();
}

// 4. Get and validate inputs
$user_id_to_change = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$new_password = $_POST['new_password'] ?? ''; // Raw password, will be validated
$confirm_password = $_POST['confirm_password'] ?? '';

$redirect_to_form = $path_to_root . "pages/admin_change_password.php?user_id=" . $user_id_to_change;

if (!$user_id_to_change) {
    SessionManager::set('message', 'Invalid user ID specified.');
    header("Location: " . $path_to_root . "pages/manage_clinicians.php");
    exit();
}

// 5. Validate passwords
$errors = [];
if (!Validator::isPasswordStrongEnough($new_password)) { // Assuming a general strength check
    $errors[] = "New password does not meet strength requirements (e.g., min 8 chars, letters, numbers).";
}
if ($new_password !== $confirm_password) {
    $errors[] = "New password and confirmation password do not match.";
}
if (empty($new_password)) { // Check after strength, as strength might imply not empty
    $errors[] = "Password cannot be empty.";
}


if (!empty($errors)) {
    SessionManager::set('error_message', implode("<br>", $errors));
    header("Location: " . $redirect_to_form);
    exit();
}

// 6. Hash the new password
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);
if ($password_hash === false) {
    // Handle password_hash failure, though rare with PASSWORD_DEFAULT
    error_log("Password hashing failed for user ID: " . $user_id_to_change);
    SessionManager::set('error_message', 'A critical error occurred while securing the new password. Please try again.');
    header("Location: " . $redirect_to_form);
    exit();
}

// 7. Update user's password in the database
try {
    // Ensure user exists before updating (good practice)
    $stmt_check = $db->prepare("SELECT id FROM users WHERE id = :user_id");
    $db->execute($stmt_check, [':user_id' => $user_id_to_change]);
    if (!$db->fetch($stmt_check)) {
        SessionManager::set('message', "Error: User with ID {$user_id_to_change} not found for password update.");
        header("Location: " . $path_to_root . "pages/manage_clinicians.php");
        exit();
    }

    $sql_update = "UPDATE users SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP WHERE id = :user_id";
    $stmt_update = $db->prepare($sql_update);
    $db->execute($stmt_update, [':password_hash' => $password_hash, ':user_id' => $user_id_to_change]);

    SessionManager::set('message', "Password for user ID {$user_id_to_change} successfully updated.");
    header("Location: " . $path_to_root . "pages/manage_clinicians.php");
    exit();

} catch (PDOException $e) {
    error_log("Error in handle_admin_change_password.php: " . $e->getMessage());
    SessionManager::set('error_message', 'A database error occurred. Could not update password.');
    header("Location: " . $redirect_to_form); // Redirect back to the change password form with error
    exit();
}
?>
