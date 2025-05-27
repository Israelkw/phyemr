<?php
session_start();

// Ensure user is logged in and is a clinician or receptionist
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"], ['clinician', 'receptionist'])) {
    $_SESSION['message'] = "Unauthorized access. Only clinicians or receptionists can perform this action.";
    header("location: ../pages/login.php"); // Redirect to login if not authorized or session lost
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve data from form
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $date_of_birth = trim($_POST['date_of_birth']);
    $registered_by_user_id = $_SESSION['user_id'];
    $assigned_clinician_id = null;

    // Basic validation for common fields
    if (empty($first_name) || empty($last_name) || empty($date_of_birth)) {
        $_SESSION['message'] = "Patient's first name, last name, and date of birth are required.";
        header("location: ../pages/add_patient.php"); // Redirect back to form
        exit;
    }

    if ($_SESSION['role'] === 'receptionist') {
        if (!isset($_POST['assigned_clinician_id']) || empty($_POST['assigned_clinician_id'])) {
            $_SESSION['message'] = "An assigned clinician is required when a receptionist adds a patient.";
            header("location: ../pages/add_patient.php");
            exit;
        }
        $assigned_clinician_id = $_POST['assigned_clinician_id'];
        // Further validation could be to check if $assigned_clinician_id is a valid ID from the clinician list
    } elseif ($_SESSION['role'] === 'clinician') {
        // If a clinician is adding a patient, they are assigned to themselves
        $assigned_clinician_id = $_SESSION['user_id'];
    }


    // Simulate storing the patient data
    // Initialize the patients array in session if it doesn't exist
    if (!isset($_SESSION['patients'])) {
        $_SESSION['patients'] = [];
    }

    // Create a new patient entry
    $new_patient_id = count($_SESSION['patients']) + 1; 
    $new_patient = [
        'id' => $new_patient_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'date_of_birth' => $date_of_birth,
        'assigned_clinician_id' => $assigned_clinician_id,
        'registered_by_user_id' => $registered_by_user_id 
    ];

    $_SESSION['patients'][$new_patient_id] = $new_patient;

    $_SESSION['message'] = "Patient '".htmlspecialchars($first_name)." ".htmlspecialchars($last_name)."' added successfully!";
    header("location: ../pages/dashboard.php");
    exit;

} else {
    // Not a POST request
    $_SESSION['message'] = "Invalid request method.";
    header("location: ../pages/dashboard.php");
    exit;
}
?>
