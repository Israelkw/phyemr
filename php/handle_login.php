<?php
session_start();

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/login.php');
    exit;
}

// Include the database connection file
require_once '../includes/db_connect.php';

// Retrieve username and password from the $_POST array
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Clear any previous login errors
unset($_SESSION['login_error']);

if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = 'Username and password are required.';
    header('Location: ../pages/login.php');
    exit;
}

// Prepare SQL to fetch user from the database
$sql = "SELECT id, username, password_hash, role, first_name, last_name, is_active FROM users WHERE username = ?";
$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    // Handle error, perhaps log it and show a generic error message
    error_log("MySQLi prepare error: " . $mysqli->error);
    $_SESSION['login_error'] = 'An internal server error occurred. Please try again later.';
    header('Location: ../pages/login.php');
    exit;
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // Verify password
    if (password_verify($password, $user['password_hash'])) {
        // Check if the user is active
        if ($user['is_active'] != 1) {
            $_SESSION['login_error'] = 'Your account is inactive. Please contact an administrator.';
            header('Location: ../pages/login.php');
            exit;
        }

        // Authentication successful
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        header('Location: ../pages/dashboard.php');
        exit;
    } else {
        // Password does not match
        $_SESSION['login_error'] = 'Invalid username or password.';
        header('Location: ../pages/login.php');
        exit;
    }
} else {
    // User not found
    $_SESSION['login_error'] = 'Invalid username or password.';
    header('Location: ../pages/login.php');
    exit;
}

$stmt->close();
$mysqli->close();
?>
