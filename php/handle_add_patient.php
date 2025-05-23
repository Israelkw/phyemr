<?php
session_start();

// Ensure user is logged in and is a clinician, receptionist, or admin
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"], ['clinician', 'receptionist', 'admin'])) {
    $_SESSION['message'] = "Unauthorized access. Only clinicians, receptionists, or admins can perform this action.";
    header("location: ../login.php"); // Redirect to login if not authorized or session lost
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve data from form
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $date_of_birth = isset($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : '';
    $assigned_clinician_id = isset($_POST['assigned_clinician_id']) ? trim($_POST['assigned_clinician_id']) : '';
    $created_by_user_id = $_SESSION['user_id']; // ID of the logged-in user creating the record

    // Basic validation for all required fields
    if (empty($first_name) || empty($last_name) || empty($date_of_birth) || empty($assigned_clinician_id)) {
        $_SESSION['message'] = "All patient details and clinician assignment are required.";
        header("location: ../add_patient.php"); // Redirect back to form
        exit;
    }

    // Validate date format if necessary (HTML5 type=date helps)
    // For this simulation, we assume it's valid.

    // Simulate storing the patient data
    // Initialize the patients array in session if it doesn't exist
    if (!isset($_SESSION['patients'])) {
        $_SESSION['patients'] = [];
    }

    // Create a new patient entry
    // In a real app, this would be an auto-incrementing ID from the database
    $new_patient_id = count($_SESSION['patients']) + 1; 
    $new_patient = [
        'id' => $new_patient_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'date_of_birth' => $date_of_birth,
        'assigned_clinician_id' => $assigned_clinician_id,
        'created_by_user_id' => $created_by_user_id
        // 'clinician_name' field removed as per instructions
    ];

    // Add to our simulated "database" (session array)
    // Using patient ID as key for easier lookup if needed later
    $_SESSION['patients'][$new_patient_id] = $new_patient;

    $_SESSION['message'] = "Patient '".htmlspecialchars($first_name)." ".htmlspecialchars($last_name)."' added successfully!";
    header("location: ../dashboard.php"); // Or redirect to a patient list page
    exit;

} else {
    // Not a POST request
    $_SESSION['message'] = "Invalid request method.";
    header("location: ../dashboard.php");
    exit;
}
?>
