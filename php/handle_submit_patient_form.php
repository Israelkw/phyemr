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
    header("Location: ../dashboard.php");
    exit();
}

// 3. Check if a user is logged in and if their role is 'clinician'
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'clinician') {
    $_SESSION['message'] = "Unauthorized action. Please login as a clinician.";
    header("Location: ../login.php"); // Redirect to login page if not authorized
    exit();
}

// 4. Retrieve patient_id and form_name from the $_POST data
if (!isset($_POST['patient_id']) || empty(trim($_POST['patient_id'])) || 
    !isset($_POST['form_name']) || empty(trim($_POST['form_name']))) {
    $_SESSION['message'] = "Patient ID or Form Name missing in submission.";
    header("Location: ../select_patient_for_form.php"); // Or a more general error page
    exit();
}

$patient_id = trim($_POST['patient_id']);
$original_form_name = trim($_POST['form_name']); // e.g., "cervical.html"

// 5. Validate patient_id and form_name
$patient_index = findPatientIndexById($patient_id, isset($_SESSION['patients']) ? $_SESSION['patients'] : []);
$patient_data = ($patient_index !== -1) ? $_SESSION['patients'][$patient_index] : null;

if (!$patient_data) {
    $_SESSION['message'] = "Invalid Patient ID: " . htmlspecialchars($patient_id);
    header("Location: ../select_patient_for_form.php");
    exit();
}

// Validate form_name (basename to prevent path traversal, check extension)
$form_basename = basename($original_form_name);
if ($form_basename !== $original_form_name || pathinfo($form_basename, PATHINFO_EXTENSION) !== 'html') {
    $_SESSION['message'] = "Invalid Form Name: " . htmlspecialchars($original_form_name);
    // Redirect to the page where the form was filled, if possible, or form selection for the patient
    header("Location: ../fill_patient_form.php?form_name=" . urlencode($original_form_name)); // Requires selected_patient_id_for_form to be in session
    exit();
}
// Further validation: check if the form file actually exists in the patient_evaluation_form directory
$form_template_path = '../patient_evaluation_form/' . $form_basename;
if (!file_exists($form_template_path)) {
    $_SESSION['message'] = "Form template '" . htmlspecialchars($form_basename) . "' not found.";
    header("Location: ../select_form_for_patient.php?patient_id=" . urlencode($patient_id));
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
        header("Location: ../fill_patient_form.php?form_name=" . urlencode($original_form_name)); 
        exit();
    }
}

// 8. Collect all submitted data from $_POST.
$submitted_data = $_POST;
$submitted_data['submission_timestamp'] = $timestamp; // Add server-side timestamp
// patient_id and form_name are already in $_POST, so they will be included.

// 9. Convert the collected data array to a JSON string
$json_data = json_encode($submitted_data, JSON_PRETTY_PRINT);

if ($json_data === false) {
    $_SESSION['message'] = "Error: Could not encode form data to JSON. Error: " . json_last_error_msg();
    header("Location: ../fill_patient_form.php?form_name=" . urlencode($original_form_name));
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
        'file_path' => 'submitted_forms/' . $patient_id . '/' . $json_filename // Relative to project root
    ];

    // 13. Set a success message
    $patient_display_name = htmlspecialchars($patient_data['first_name'] . ' ' . $patient_data['last_name']);
    $_SESSION['message'] = "Form '" . htmlspecialchars($form_name_without_ext) . "' submitted successfully for patient " . $patient_display_name . " and saved as " . htmlspecialchars($json_filename) . ".";
    
    // 14. Redirect the user
    header("Location: ../dashboard.php");
    exit();

} else {
    // 15. Handle potential errors during file saving
    $_SESSION['message'] = "Error: Could not save the submitted form data to " . htmlspecialchars($file_path_to_save) . ". Please check server permissions.";
    // Redirect back to the form they were filling
    header("Location: ../fill_patient_form.php?form_name=" . urlencode($original_form_name));
    exit();
}
?>
