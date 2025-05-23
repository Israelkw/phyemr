<?php
session_start();

// Ensure user is logged in and is a clinician
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'clinician') {
    $_SESSION['message'] = "Unauthorized access. Only clinicians can perform this action.";
    header("location: ../login.php"); // Redirect to login if not clinician or session lost
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve data from form
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $date_of_birth = trim($_POST['date_of_birth']);
    $clinician_id = $_SESSION['user_id']; // ID of the logged-in clinician

    // Basic validation
    if (empty($first_name) || empty($last_name) || empty($date_of_birth)) {
        $_SESSION['message'] = "All patient fields are required.";
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
        'added_by_clinician_id' => $clinician_id,
        'clinician_name' => $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] // Store for easy display
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
