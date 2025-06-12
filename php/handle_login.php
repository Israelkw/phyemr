<?php
error_log("DEBUG: handle_login.php - Execution Start");
// Order of includes can be important. Start session first.
require_once '../includes/SessionManager.php';
SessionManager::startSession();
error_log("DEBUG: handle_login.php - Session Started. Session ID: " . session_id());

// Include the ErrorHandler and register it
require_once '../includes/ErrorHandler.php';
ErrorHandler::register(); // ErrorHandler also starts session if not started, ensure SessionManager is first for consistent settings
error_log("DEBUG: handle_login.php - ErrorHandler Registered");

// CSRF Token Validation
$submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!SessionManager::validateCsrfToken($submittedToken)) {
    error_log("DEBUG: handle_login.php - CSRF Token Invalid or Missing. Redirecting to login.");
    SessionManager::set('login_error', 'Invalid or missing CSRF token. Please try logging in again.');
    header('Location: ../pages/login.php');
    exit;
}
error_log("DEBUG: handle_login.php - CSRF Token Valid.");

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("DEBUG: handle_login.php - Not a POST request. Redirecting to login.");
    header('Location: ../pages/login.php');
    exit;
}
error_log("DEBUG: handle_login.php - Is a POST request.");

// Include the database connection file and Database class
error_log("DEBUG: handle_login.php - Before db_connect include");
require_once '../includes/db_connect.php'; // Provides $pdo
require_once '../includes/Database.php';    // Provides Database class

// Instantiate the Database class
$db = new Database($pdo);
error_log("DEBUG: handle_login.php - Database connection and Database class instantiated.");
if (!$pdo) { error_log("DEBUG: handle_login.php - PDO object is NULL or false!"); }


// Retrieve username and password from the $_POST array
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
error_log("DEBUG: handle_login.php - Username: '" . $username . "', Password is " . (empty($password) ? "empty" : "not empty"));

// Clear any previous login errors
SessionManager::remove('login_error');

if (empty($username) || empty($password)) {
    error_log("DEBUG: handle_login.php - Empty username or password. Redirecting to login.");
    SessionManager::set('login_error', 'Username and password are required.');
    header('Location: ../pages/login.php');
    exit;
}

// Prepare SQL to fetch user from the database
$sql = "SELECT id, username, password_hash, role, first_name, last_name, is_active FROM users WHERE username = :username";
error_log("DEBUG: handle_login.php - Before preparing SQL query: " . $sql);

try {
    $stmt = $db->prepare($sql);
    error_log("DEBUG: handle_login.php - SQL query prepared.");
    error_log("DEBUG: handle_login.php - Before executing query for username: " . $username);
    $db->execute($stmt, ['username' => $username]);
    error_log("DEBUG: handle_login.php - Query executed.");
    $user = $db->fetch($stmt);
    error_log("DEBUG: handle_login.php - User fetched from DB. User data: " . ($user ? json_encode($user) : "Not Found/False"));

    if ($user) {
        error_log("DEBUG: handle_login.php - User found. Verifying password.");
        // Verify password
        if (password_verify($password, $user['password_hash'])) {
            error_log("DEBUG: handle_login.php - Password verified successfully.");
            error_log("DEBUG: handle_login.php - Checking if user is active. is_active: " . ($user['is_active'] ?? 'N/A'));
            // Check if the user is active
            if ($user['is_active'] != 1) {
                error_log("DEBUG: handle_login.php - User is inactive. Redirecting to login.");
                SessionManager::set('login_error', 'Your account is inactive. Please contact an administrator.');
                header('Location: ../pages/login.php');
                exit;
            }

            // Authentication successful
            error_log("DEBUG: handle_login.php - Authentication successful. Regenerating session.");
            SessionManager::regenerate(); // Regenerate session ID first
            SessionManager::set('user_id', $user['id']);
            SessionManager::set('username', $user['username']);
            SessionManager::set('role', $user['role']);
            SessionManager::set('first_name', $user['first_name']);
            SessionManager::set('last_name', $user['last_name']);

            error_log("DEBUG: handle_login.php - Redirecting to dashboard.");
            header('Location: ../pages/dashboard.php');
            exit;
        } else {
            error_log("DEBUG: handle_login.php - Password verification failed.");
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
    error_log("DEBUG: handle_login.php - PDOException Caught: " . $e->getMessage());
    // Use ErrorHandler to handle the exception
    // The ErrorHandler will log the error, set a generic session message, and redirect to error.php
    ErrorHandler::handleException($e);
    // Execution will stop in handleException due to exit/die
}

// No need to explicitly close PDOStatement or PDO connection with this class structure,
// as PDO handles statement closure and connection closure (on script end or object destruction).
?>
