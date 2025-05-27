<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php'; // $mysqli connection object

// Check if user is logged in
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"])) {
    $_SESSION['message'] = "Please log in to perform this action.";
    header("Location: ../pages/login.php"); 
    exit;
}

// Check if user is admin
if ($_SESSION["role"] !== 'admin') {
    $_SESSION['message'] = "Unauthorized action. Only administrators can add users.";
    header("Location: ../pages/dashboard.php"); 
    exit;
}

// Ensure the script only processes POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['message'] = "Invalid request method.";
    header("Location: ../pages/add_clinician.php");
    exit;
}

// Retrieve and trim data from POST
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : null;
$last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : null;
$role = isset($_POST['role']) ? trim($_POST['role']) : '';

// Validate required fields: username, password, role are specified as required in the task.
// First Name and Last Name are not explicitly required in the form fields description in the task,
// so allow them to be empty (will be stored as NULL or empty string based on DB schema if not provided).
if (empty($username) || empty($password) || empty($role)) {
    $_SESSION['message'] = "Username, password, and role are required.";
    header("Location: ../pages/add_clinician.php");
    exit;
}

// Validate role value from the provided list
$allowed_roles = ['receptionist', 'nurse', 'clinician', 'admin'];
if (!in_array($role, $allowed_roles)) {
    $_SESSION['message'] = "Invalid role selected. Please choose from the provided options.";
    header("Location: ../pages/add_clinician.php");
    exit;
}

// Check for username uniqueness
$stmt_check_username = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
if ($stmt_check_username === false) {
    error_log("CRITICAL: Failed to prepare username check statement: " . $mysqli->error);
    $_SESSION['message'] = "An internal server error occurred (DBP01). Please try again later.";
    header("Location: ../pages/add_clinician.php");
    exit;
}
$stmt_check_username->bind_param("s", $username);
if (!$stmt_check_username->execute()) {
    error_log("CRITICAL: Failed to execute username check statement: " . $stmt_check_username->error);
    $_SESSION['message'] = "An internal server error occurred (DBE01). Please try again later.";
    $stmt_check_username->close();
    header("Location: ../pages/add_clinician.php");
    exit;
}
$result_check_username = $stmt_check_username->get_result();
if ($result_check_username->num_rows > 0) {
    $_SESSION['message'] = "Username '" . htmlspecialchars($username) . "' is already taken. Please choose another.";
    $stmt_check_username->close();
    header("Location: ../pages/add_clinician.php");
    exit;
}
$stmt_check_username->close();

// Hash the password
$password_hash = password_hash($password, PASSWORD_DEFAULT);
if ($password_hash === false) {
    error_log("CRITICAL: Password hashing failed for username: " . $username);
    $_SESSION['message'] = "A critical error occurred during user creation (PHE01). Please contact support.";
    header("Location: ../pages/add_clinician.php");
    exit;
}

// Prepare SQL INSERT statement
$stmt_insert_user = $mysqli->prepare("INSERT INTO users (username, password_hash, first_name, last_name, role) VALUES (?, ?, ?, ?, ?)");
if ($stmt_insert_user === false) {
    error_log("CRITICAL: Failed to prepare user insert statement: " . $mysqli->error);
    $_SESSION['message'] = "An internal server error occurred (DBP02). Please try again later.";
    header("Location: ../pages/add_clinician.php");
    exit;
}

// Bind parameters
// If first_name or last_name were empty, they would be NULL from the trim/isset logic.
// The database schema allows NULL for first_name and last_name.
$stmt_insert_user->bind_param(
    "sssss", 
    $username, 
    $password_hash, 
    $first_name, // Will be NULL if not provided and DB column allows NULL
    $last_name,  // Will be NULL if not provided and DB column allows NULL
    $role
);

if ($stmt_insert_user->execute()) {
    $_SESSION['message'] = "User '" . htmlspecialchars($username) . "' added successfully as " . htmlspecialchars($role) . ".";
    // As per task: "Redirect to ../manage_clinicians.php (or ../add_clinician.php if manage_clinicians.php doesn't exist yet)."
    // Assuming manage_clinicians.php does not exist yet, redirect to add_clinician.php.
    header("Location: ../pages/add_clinician.php"); 
} else {
    error_log("CRITICAL: Failed to execute user insert statement for username " . $username . ": " . $stmt_insert_user->error);
    $_SESSION['message'] = "Failed to add user due to an unexpected database error (DBE02).";
    header("Location: ../pages/add_clinician.php");
}

$stmt_insert_user->close();
// $mysqli->close(); // Connection closed by PHP at script end or by db_connect.php if it implements a shutdown function.
exit;
?>
