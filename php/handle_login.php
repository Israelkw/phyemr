<?php
session_start();

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

// Retrieve username and password from the $_POST array
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Sample users array
$users = [
    ['id' => '1', 'username' => 'admin', 'password' => 'adminpass', 'role' => 'admin', 'first_name' => 'Admin', 'last_name' => 'User'],
    ['id' => '2', 'username' => 'clinician1', 'password' => 'clinicpass', 'role' => 'clinician', 'first_name' => 'Clinical', 'last_name' => 'Staff'],
    ['id' => '3', 'username' => 'nurse1', 'password' => 'nursepass', 'role' => 'nurse', 'first_name' => 'Nurse', 'last_name' => 'Joy'],
    ['id' => '4', 'username' => 'reception1', 'password' => 'receptpass', 'role' => 'receptionist', 'first_name' => 'Reception', 'last_name' => 'Desk']
];

// Authentication Logic
$authenticated = false;
foreach ($users as $user) {
    if ($username === $user['username'] && $password === $user['password']) {
        // Authentication successful
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        
        $authenticated = true;
        header('Location: ../dashboard.php');
        exit;
    }
}

// If authentication failed after checking all users
if (!$authenticated) {
    $_SESSION['login_error'] = 'Invalid username or password.';
    header('Location: ../login.php');
    exit;
}
?>
