<?php
session_start();

// Check if the user is logged in and is an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'admin') {
    $_SESSION['message'] = "Unauthorized access.";
    header("location: ../login.php"); // Redirect to login if not admin
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve data from form
    $username = trim($_POST['username']);
    $password = $_POST['password']; // In a real app, hash this!
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);

    // Basic validation (can be expanded)
    if (empty($username) || empty($password) || empty($first_name) || empty($last_name)) {
        $_SESSION['message'] = "All fields are required.";
        header("location: ../add_clinician.php"); // Redirect back to form
        exit;
    }

    // Simulate storing the clinician
    // Initialize the clinicians array in session if it doesn't exist
    if (!isset($_SESSION['clinicians'])) {
        $_SESSION['clinicians'] = [];
    }

    // Create a new clinician entry (associative array)
    // In a real app, this would be an ID from the database
    $new_clinician_id = count($_SESSION['clinicians']) + 1; 
    $new_clinician = [
        'id' => $new_clinician_id,
        'username' => $username,
        // DO NOT store plain password in real app. This is for simulation.
        'password_placeholder' => $password, 
        'first_name' => $first_name,
        'last_name' => $last_name,
        'role' => 'clinician' // Assign role
    ];

    // Add to our simulated "database" (session array)
    $_SESSION['clinicians'][$new_clinician_id] = $new_clinician;

    $_SESSION['message'] = "Clinician '".htmlspecialchars($username)."' added successfully!";
    header("location: ../dashboard.php");
    exit;

} else {
    // Not a POST request
    $_SESSION['message'] = "Invalid request method.";
    header("location: ../dashboard.php");
    exit;
}
?>
