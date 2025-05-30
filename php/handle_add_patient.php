<?php
session_start();

// Include the database connection file
require_once '../includes/db_connect.php';

// Ensure user is logged in and is a clinician or receptionist
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"], ['clinician', 'receptionist'])) {
    $_SESSION['message'] = "Unauthorized access. Only clinicians or receptionists can perform this action.";
    header("Location: ../pages/login.php"); // Redirect to login if not authorized or session lost
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve data from form
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $date_of_birth = trim($_POST['date_of_birth']); // Expects YYYY-MM-DD format
    $registered_by_user_id = $_SESSION['user_id'];
    $assigned_clinician_id = null;

    // Basic validation for common fields
    if (empty($first_name) || empty($last_name) || empty($date_of_birth)) {
        $_SESSION['message'] = "Patient's first name, last name, and date of birth are required.";
        header("Location: ../pages/add_patient.php"); // Redirect back to form
        exit;
    }

    // Validate date_of_birth format (basic check, more robust validation can be added)
    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date_of_birth)) {
        $_SESSION['message'] = "Invalid date of birth format. Please use YYYY-MM-DD.";
        header("Location: ../pages/add_patient.php");
        exit;
    }

    if ($_SESSION['role'] === 'receptionist') {
        if (!isset($_POST['assigned_clinician_id']) || empty($_POST['assigned_clinician_id'])) {
            $_SESSION['message'] = "An assigned clinician is required when a receptionist adds a patient.";
            header("Location: ../pages/add_patient.php");
            exit;
        }
        $assigned_clinician_id_post = $_POST['assigned_clinician_id'];

        // Validate assigned_clinician_id
        $stmt_check_clinician = $mysqli->prepare("SELECT id FROM users WHERE id = ? AND role = 'clinician' AND is_active = 1");
        if (!$stmt_check_clinician) {
            error_log("MySQLi prepare error (check clinician): " . $mysqli->error);
            $_SESSION['message'] = "Error validating clinician. Please try again.";
            header("Location: ../pages/add_patient.php");
            exit;
        }
        $stmt_check_clinician->bind_param("i", $assigned_clinician_id_post);
        $stmt_check_clinician->execute();
        $stmt_check_clinician->store_result();
        
        if ($stmt_check_clinician->num_rows == 1) {
            $assigned_clinician_id = $assigned_clinician_id_post;
        } else {
            $_SESSION['message'] = "Invalid or inactive clinician selected.";
            header("Location: ../pages/add_patient.php");
            exit;
        }
        $stmt_check_clinician->close();

    } elseif ($_SESSION['role'] === 'clinician') {
        // If a clinician is adding a patient, they are assigned to themselves
        $assigned_clinician_id = $_SESSION['user_id'];
    }

    // Prepare SQL for inserting patient data
    $sql = "INSERT INTO patients (first_name, last_name, date_of_birth, assigned_clinician_id, registered_by_user_id) VALUES (?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        error_log("MySQLi prepare error (insert patient): " . $mysqli->error);
        $_SESSION['message'] = "Error preparing to save patient data. Please try again.";
        header("Location: ../pages/add_patient.php");
        exit;
    }

    // Bind parameters
    // assigned_clinician_id can be null if a clinician from a different system (not a user) is assigned,
    // but current logic assigns a user ID or null if no selection / error.
    // For this implementation, it's INT NULL, so null is acceptable if logic allows.
    // Here, it's either a valid clinician ID or the current clinician's ID.
    $stmt->bind_param("sssis", $first_name, $last_name, $date_of_birth, $assigned_clinician_id, $registered_by_user_id);

    // Execute the statement
    if ($stmt->execute()) {
        $_SESSION['selected_patient_id_for_form'] = $stmt->insert_id; // Store new patient ID
        $_SESSION['message'] = "Patient '" . htmlspecialchars($first_name) . " " . htmlspecialchars($last_name) . "' added successfully. Please fill out the demography form.";
        header("Location: ../pages/fill_patient_form.php?form_name=demo.html&form_directory=patient_general_info"); // Redirect to demo form
    } else {
        error_log("MySQLi execute error (insert patient): " . $stmt->error);
        $_SESSION['message'] = "Error saving patient data: " . htmlspecialchars($stmt->error);
        // Check for specific errors, e.g., duplicate entry if there's a unique constraint violated
        // For now, a generic error related to DB operation.
        header("Location: ../pages/add_patient.php");
    }
    $stmt->close();
    $mysqli->close();
    exit;

} else {
    // Not a POST request
    $_SESSION['message'] = "Invalid request method.";
    header("Location: ../pages/dashboard.php"); // Or login page, or add_patient form
    exit;
}
?>
