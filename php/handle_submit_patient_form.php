<?php
session_start();

// 1. Helper function to find patient index (as patient_id might not be a direct key if numeric array)
function findPatientIndexById($patient_id, $patients_array) {
    if (!is_array($patients_array)) return -1;
    foreach ($patients_array as $index => $patient) {
        if (isset($patient['id']) && $patient['id'] == $patient_id) {
            return $index;
        }
    }
    return -1;
}

// 2. Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['message'] = "Invalid request method.";
    header("Location: ../pages/dashboard.php");
    exit();
}

// 3. Check if a user is logged in and if their role is 'clinician' or 'nurse'
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['clinician', 'nurse'])) {
    $_SESSION['message'] = "Unauthorized action. Please login as a clinician or nurse.";
    header("Location: ../pages/login.php"); // Redirect to login page if not authorized
    exit();
}

// Determine appropriate redirect paths based on role for error cases
$select_patient_page = ($_SESSION['role'] === 'nurse') ? '../pages/nurse_select_patient.php' : '../pages/select_patient_for_form.php';
// $select_form_page = ($_SESSION['role'] === 'nurse') ? '../pages/nurse_select_form.php' : '../pages/select_form_for_patient.php'; // Not directly used here but good for context

// 4. Retrieve patient_id, form_name, and form_directory from the $_POST data
if (!isset($_POST['patient_id']) || empty(trim($_POST['patient_id'])) || 
    !isset($_POST['form_name']) || empty(trim($_POST['form_name'])) ||
    !isset($_POST['form_directory']) || empty(trim($_POST['form_directory']))) {
    $_SESSION['message'] = "Patient ID, Form Name, or Form Directory missing in submission.";
    // Intelligent redirect: if patient_id is known, go to form selection for that patient, else general patient selection
    if(isset($_POST['patient_id']) && !empty(trim($_POST['patient_id']))) {
        $redirect_patient_id = trim($_POST['patient_id']);
        $form_selection_page = ($_SESSION['role'] === 'nurse') ? '../pages/nurse_select_form.php' : '../pages/select_form_for_patient.php';
        header("Location: " . $form_selection_page . "?patient_id=" . urlencode($redirect_patient_id));
    } else {
        header("Location: " . $select_patient_page); // Already correctly points to pages/ via variable
    }
    exit();
}

$patient_id = trim($_POST['patient_id']);
$original_form_name = trim($_POST['form_name']); // e.g., "cervical.html"
$form_directory = trim($_POST['form_directory']); // e.g., "patient_evaluation_form"

// 5. Validate patient_id, form_directory and form_name
$patient_index = findPatientIndexById($patient_id, isset($_SESSION['patients']) ? $_SESSION['patients'] : []);
$patient_data = ($patient_index !== -1) ? $_SESSION['patients'][$patient_index] : null;

if (!$patient_data) {
    $_SESSION['message'] = "Invalid Patient ID: " . htmlspecialchars($patient_id);
    header("Location: " . $select_patient_page);
    exit();
}

// Validate form_directory
$allowed_directories = ['patient_evaluation_form', 'patient_general_info'];
if (!in_array($form_directory, $allowed_directories)) {
    $_SESSION['message'] = "Invalid Form Directory: " . htmlspecialchars($form_directory);
    $form_selection_page = ($_SESSION['role'] === 'nurse') ? '../pages/nurse_select_form.php' : '../pages/select_form_for_patient.php';
    header("Location: " . $form_selection_page . "?patient_id=" . urlencode($patient_id));
    exit();
}

// Validate form_name (basename to prevent path traversal, check extension)
$form_basename = basename($original_form_name);
if ($form_basename !== $original_form_name || pathinfo($form_basename, PATHINFO_EXTENSION) !== 'html') {
    $_SESSION['message'] = "Invalid Form Name: " . htmlspecialchars($original_form_name);
    // Redirect to the page where the form was filled
    header("Location: ../pages/fill_patient_form.php?form_name=" . urlencode($original_form_name) . "&form_directory=" . urlencode($form_directory));
    exit();
}

// Further validation: check if the form file actually exists in the specified directory
$form_template_path = '../' . rtrim($form_directory, '/') . '/' . $form_basename; // This path is relative to php/ so ../ goes to root
if (!file_exists($form_template_path)) {
    $_SESSION['message'] = "Form template '" . htmlspecialchars($form_basename) . "' not found in directory '" . htmlspecialchars($form_directory) . "'.";
    $form_selection_page = ($_SESSION['role'] === 'nurse') ? '../pages/nurse_select_form.php' : '../pages/select_form_for_patient.php';
    header("Location: " . $form_selection_page . "?patient_id=" . urlencode($patient_id));
    exit();
}

// 6. Create a unique filename for the JSON file
$timestamp = time();
$form_name_without_ext = pathinfo($form_basename, PATHINFO_FILENAME); // "cervical"
$json_filename = $form_name_without_ext . '_' . $timestamp . '.json'; // "cervical_1678886400.json"

// 7. Define the directory path for saving forms
$patient_specific_dir = '../submitted_forms/' . $patient_id . '/';

if (!is_dir($patient_specific_dir)) {
    if (!mkdir($patient_specific_dir, 0755, true)) {
        $_SESSION['message'] = "Error: Could not create directory for submitted forms at " . htmlspecialchars($patient_specific_dir);
        // Redirect to a page that makes sense, perhaps the form filling page or dashboard
        header("Location: ../pages/fill_patient_form.php?form_name=" . urlencode($original_form_name) . "&form_directory=" . urlencode($form_directory)); 
        exit();
    }
}

// 8. Collect all submitted data from $_POST.
$submitted_data = $_POST;
// Add server-side timestamp, user ID, and role
$submitted_data['submission_timestamp'] = $timestamp; 
$submitted_data['submitted_by_user_id'] = $_SESSION['user_id'];
$submitted_data['submitted_by_user_role'] = $_SESSION['role'];
// patient_id, form_name, and form_directory are already in $_POST, so they will be included.

// 9. Convert the collected data array to a JSON string
$json_data = json_encode($submitted_data, JSON_PRETTY_PRINT);

if ($json_data === false) {
    $_SESSION['message'] = "Error: Could not encode form data to JSON. Error: " . json_last_error_msg();
    header("Location: ../pages/fill_patient_form.php?form_name=" . urlencode($original_form_name) . "&form_directory=" . urlencode($form_directory));
    exit();
}

// 10. Save the JSON string to the file
$file_path_to_save = $patient_specific_dir . $json_filename;

if (file_put_contents($file_path_to_save, $json_data)) {
    // 11. File saved successfully.

    // 12. Update $_SESSION to record the submission
    if (!isset($_SESSION['patients'][$patient_index]['submitted_forms']) || !is_array($_SESSION['patients'][$patient_index]['submitted_forms'])) {
        $_SESSION['patients'][$patient_index]['submitted_forms'] = [];
    }

    $_SESSION['patients'][$patient_index]['submitted_forms'][] = [
        'form_name' => $original_form_name, // e.g., "cervical.html"
        'json_filename' => $json_filename,   // e.g., "cervical_1678886400.json"
        'submission_timestamp' => $timestamp,
        'file_path' => 'submitted_forms/' . $patient_id . '/' . $json_filename, // Relative to project root
        'submitted_by_user_id' => $_SESSION['user_id'],
        'submitted_by_user_role' => $_SESSION['role'],
        'form_directory' => $form_directory // Store this for easier access later if needed
    ];

    // 13. Set a success message
    $patient_display_name = htmlspecialchars($patient_data['first_name'] . ' ' . $patient_data['last_name']);
    $_SESSION['message'] = "Form '" . htmlspecialchars($form_name_without_ext) . "' (from " . htmlspecialchars($form_directory) . ") submitted successfully for patient " . $patient_display_name . " and saved as " . htmlspecialchars($json_filename) . ".";
    
    // 14. Redirect the user
    header("Location: ../pages/dashboard.php");
    exit();

} else {
    // 15. Handle potential errors during file saving
    $_SESSION['message'] = "Error: Could not save the submitted form data to " . htmlspecialchars($file_path_to_save) . ". Please check server permissions.";
    // Redirect back to the form they were filling
    header("Location: ../pages/fill_patient_form.php?form_name=" . urlencode($original_form_name) . "&form_directory=" . urlencode($form_directory));
    exit();
}
?>
