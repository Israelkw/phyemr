<?php
session_start();

// Include the database connection file
require_once '../includes/db_connect.php';

// 1. Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['message'] = "Invalid request method.";
    header("Location: ../pages/dashboard.php");
    exit();
}

// 2. Check if a user is logged in and if their role is 'clinician' or 'nurse'
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['clinician', 'nurse'])) {
    $_SESSION['message'] = "Unauthorized action. Please login as a clinician or nurse.";
    header("Location: ../pages/login.php"); // Redirect to login page if not authorized
    exit();
}

// Determine appropriate redirect paths based on role for error cases
$select_patient_page = ($_SESSION['role'] === 'nurse') ? '../pages/nurse_select_patient.php' : '../pages/select_patient_for_form.php';
$fill_form_page_base = "../pages/fill_patient_form.php"; // Base for redirecting back to form on error

// 3. Retrieve patient_id, form_name, and form_directory from the $_POST data
if (!isset($_POST['patient_id']) || empty(trim($_POST['patient_id'])) || 
    !isset($_POST['form_name']) || empty(trim($_POST['form_name'])) ||
    !isset($_POST['form_directory']) || empty(trim($_POST['form_directory']))) {
    $_SESSION['message'] = "Patient ID, Form Name, or Form Directory missing in submission.";
    if(isset($_POST['patient_id']) && !empty(trim($_POST['patient_id']))) {
        $redirect_patient_id = trim($_POST['patient_id']);
        $form_selection_page = ($_SESSION['role'] === 'nurse') ? '../pages/nurse_select_form.php' : '../pages/select_form_for_patient.php';
        header("Location: " . $form_selection_page . "?patient_id=" . urlencode($redirect_patient_id));
    } else {
        header("Location: " . $select_patient_page);
    }
    exit();
}

$patient_id_post = trim($_POST['patient_id']);
$original_form_name = trim($_POST['form_name']); // e.g., "cervical.html"
$form_directory = trim($_POST['form_directory']); // e.g., "patient_evaluation_form"
$submitted_by_user_id = $_SESSION['user_id'];

// Construct redirect URL for form filling page in case of errors related to this specific form
$redirect_to_fill_form = $fill_form_page_base . "?patient_id=" . urlencode($patient_id_post) . "&form_name=" . urlencode($original_form_name) . "&form_directory=" . urlencode($form_directory);

// 4. Validate patient_id by checking against the database
$stmt_check_patient = $mysqli->prepare("SELECT id, first_name, last_name FROM patients WHERE id = ?");
if (!$stmt_check_patient) {
    error_log("MySQLi prepare error (check patient): " . $mysqli->error);
    $_SESSION['message'] = "Error validating patient. Please try again.";
    header("Location: " . $select_patient_page);
    exit();
}
$stmt_check_patient->bind_param("i", $patient_id_post);
$stmt_check_patient->execute();
$result_patient = $stmt_check_patient->get_result();
if ($result_patient->num_rows === 1) {
    $patient_db_data = $result_patient->fetch_assoc();
} else {
    $_SESSION['message'] = "Invalid Patient ID: " . htmlspecialchars($patient_id_post);
    header("Location: " . $select_patient_page);
    exit();
}
$stmt_check_patient->close();


// 5. Validate form_directory and form_name (existing logic)
$allowed_directories = ['patient_evaluation_form', 'patient_general_info'];
if (!in_array($form_directory, $allowed_directories)) {
    $_SESSION['message'] = "Invalid Form Directory: " . htmlspecialchars($form_directory);
    $form_selection_page = ($_SESSION['role'] === 'nurse') ? '../pages/nurse_select_form.php' : '../pages/select_form_for_patient.php';
    header("Location: " . $form_selection_page . "?patient_id=" . urlencode($patient_id_post));
    exit();
}

$form_basename = basename($original_form_name);
if ($form_basename !== $original_form_name || pathinfo($form_basename, PATHINFO_EXTENSION) !== 'html') {
    $_SESSION['message'] = "Invalid Form Name structure: " . htmlspecialchars($original_form_name);
    header("Location: " . $redirect_to_fill_form);
    exit();
}

// Further validation: check if the form file actually exists in the specified directory
$form_template_path = '../' . rtrim($form_directory, '/') . '/' . $form_basename;
if (!file_exists($form_template_path)) {
    $_SESSION['message'] = "Form template '" . htmlspecialchars($form_basename) . "' not found in directory '" . htmlspecialchars($form_directory) . "'.";
    $form_selection_page = ($_SESSION['role'] === 'nurse') ? '../pages/nurse_select_form.php' : '../pages/select_form_for_patient.php';
    header("Location: " . $form_selection_page . "?patient_id=" . urlencode($patient_id_post));
    exit();
}

// 6. Prepare form data for JSON storage
// Exclude specific control fields from being part of the main JSON data if they have their own columns
$form_data_payload = $_POST;
// Optionally remove fields that are already stored in dedicated columns to avoid redundancy in JSON
// unset($form_data_payload['patient_id']); // Already stored in its own column
// unset($form_data_payload['form_name']); // Already stored
// unset($form_data_payload['form_directory']); // Already stored
// However, keeping them can be useful for a complete snapshot of what was posted. For this task, we keep all of $_POST.

$json_form_data = json_encode($form_data_payload, JSON_PRETTY_PRINT);

if ($json_form_data === false) {
    $_SESSION['message'] = "Error: Could not encode form data to JSON. Error: " . json_last_error_msg();
    header("Location: " . $redirect_to_fill_form);
    exit();
}

// 7. Insert into patient_form_submissions table
$sql_insert_submission = "INSERT INTO patient_form_submissions (patient_id, form_name, form_directory, submitted_by_user_id, form_data) VALUES (?, ?, ?, ?, ?)";
$stmt_insert_submission = $mysqli->prepare($sql_insert_submission);

if (!$stmt_insert_submission) {
    error_log("MySQLi prepare error (insert submission): " . $mysqli->error);
    $_SESSION['message'] = "Error preparing to save form submission. Please try again.";
    header("Location: " . $redirect_to_fill_form);
    exit();
}

$stmt_insert_submission->bind_param("issis", $patient_db_data['id'], $original_form_name, $form_directory, $submitted_by_user_id, $json_form_data);

if ($stmt_insert_submission->execute()) {
    $form_name_without_ext = pathinfo($form_basename, PATHINFO_FILENAME);
    $patient_display_name = htmlspecialchars($patient_db_data['first_name'] . ' ' . $patient_db_data['last_name']);
    $_SESSION['message'] = "Form '" . htmlspecialchars($form_name_without_ext) . "' (from " . htmlspecialchars($form_directory) . ") submitted successfully for patient " . $patient_display_name . " and saved to the database.";
    header("Location: ../pages/dashboard.php");
} else {
    error_log("MySQLi execute error (insert submission): " . $stmt_insert_submission->error);
    $_SESSION['message'] = "Error saving form submission to the database: " . htmlspecialchars($stmt_insert_submission->error);
    header("Location: " . $redirect_to_fill_form);
}

$stmt_insert_submission->close();
$mysqli->close();
exit();

?>
