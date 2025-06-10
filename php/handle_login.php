<?php
// Order of includes can be important. Start session first.
require_once '../includes/SessionManager.php';
SessionManager::startSession();

// Include the ErrorHandler and register it
require_once '../includes/ErrorHandler.php';
ErrorHandler::register(); // ErrorHandler also starts session if not started, ensure SessionManager is first for consistent settings

// CSRF Token Validation
$submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!SessionManager::validateCsrfToken($submittedToken)) {
    SessionManager::set('login_error', 'Invalid or missing CSRF token. Please try logging in again.');
    header('Location: ../pages/login.php');
    exit;
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/login.php');
    exit;
}

// Include the database connection file and Database class
require_once '../includes/db_connect.php'; // Provides $pdo
require_once '../includes/Database.php';    // Provides Database class

// Instantiate the Database class
$db = new Database($pdo);

// Retrieve username and password from the $_POST array
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Clear any previous login errors
SessionManager::remove('login_error');

if (empty($username) || empty($password)) {
    SessionManager::set('login_error', 'Username and password are required.');
    header('Location: ../pages/login.php');
    exit;
}

// Prepare SQL to fetch user from the database
$sql = "SELECT id, username, password_hash, role, first_name, last_name, is_active FROM users WHERE username = :username";

try {
    $stmt = $db->prepare($sql);
    $db->execute($stmt, ['username' => $username]);
    $user = $db->fetch($stmt);

    if ($user) {
        // Verify password
        if (password_verify($password, $user['password_hash'])) {
        // Check if the user is active
        if ($user['is_active'] != 1) {
            SessionManager::set('login_error', 'Your account is inactive. Please contact an administrator.');
            header('Location: ../pages/login.php');
            exit;
        }

        // Authentication successful
        SessionManager::regenerate(); // Regenerate session ID first
        SessionManager::set('user_id', $user['id']);
        SessionManager::set('username', $user['username']);
        SessionManager::set('role', $user['role']);
        SessionManager::set('first_name', $user['first_name']);
        SessionManager::set('last_name', $user['last_name']);
        
        header('Location: ../pages/dashboard.php');
        exit;
    } else {
        // Password does not match
        SessionManager::set('login_error', 'Invalid username or password.');
        header('Location: ../pages/login.php');
        exit;
    }
} else {
    // User not found
    SessionManager::set('login_error', 'Invalid username or password.'); // Specific login feedback
    header('Location: ../pages/login.php');
    exit;
}
} catch (PDOException $e) {
    // Use ErrorHandler to handle the exception
    // The ErrorHandler will log the error, set a generic session message, and redirect to error.php
    ErrorHandler::handleException($e);
    // Execution will stop in handleException due to exit/die
}

// No need to explicitly close PDOStatement or PDO connection with this class structure,
// as PDO handles statement closure and connection closure (on script end or object destruction).
?>
