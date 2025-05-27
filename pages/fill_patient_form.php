<?php
$path_to_root = "../"; // Define $path_to_root for includes
session_start();

// 1. Check if user is logged in and is a clinician or nurse
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['clinician', 'nurse'])) {
    $_SESSION['message'] = "Unauthorized access. Please login as a clinician or nurse.";
    header("Location: dashboard.php");
    exit();
}

// Determine appropriate redirect paths based on role
$select_patient_page = ($_SESSION['role'] === 'nurse') ? 'nurse_select_patient.php' : 'select_patient_for_form.php';
$select_form_page = ($_SESSION['role'] === 'nurse') ? 'nurse_select_form.php' : 'select_form_for_patient.php';


// 2. Retrieve form_name and form_directory from URL
if (!isset($_GET['form_name']) || empty(trim($_GET['form_name']))) {
    $_SESSION['message'] = "No form selected.";
    header("Location: " . $select_form_page . (isset($_SESSION['selected_patient_id_for_form']) ? '?patient_id=' . $_SESSION['selected_patient_id_for_form'] : ''));
    exit();
}
$form_name_from_url = trim($_GET['form_name']);

if (!isset($_GET['form_directory']) || empty(trim($_GET['form_directory']))) {
    $_SESSION['message'] = "Form directory not specified.";
    header("Location: " . $select_form_page . (isset($_SESSION['selected_patient_id_for_form']) ? '?patient_id=' . $_SESSION['selected_patient_id_for_form'] : ''));
    exit();
}
$form_directory_from_url = trim($_GET['form_directory']);

// 3. Retrieve patient_id from session
if (!isset($_SESSION['selected_patient_id_for_form']) || empty($_SESSION['selected_patient_id_for_form'])) {
    $_SESSION['message'] = "Patient not selected or session expired. Please select a patient first.";
    header("Location: " . $select_patient_page);
    exit();
}
$selected_patient_id = $_SESSION['selected_patient_id_for_form']; // Renamed for clarity
$current_user_id = $_SESSION['user_id']; // This will be the clinician_id or nurse_id

// 4. Validate form_directory and form_name, and file existence
$allowed_directories = ['patient_evaluation_form', 'patient_general_info'];
if (!in_array($form_directory_from_url, $allowed_directories)) {
    $_SESSION['message'] = "Invalid form directory specified.";
    // Note: $patient_id_from_session was used here before, ensure $selected_patient_id is used if that's the correct variable now
    header("Location: " . $select_form_page . '?patient_id=' . htmlspecialchars($selected_patient_id)); 
    exit();
}

$form_file_basename = basename($form_name_from_url); // Prevent directory traversal
// Adjust form_file_path to be relative to the root, as form directories are at the root
$form_file_path = $path_to_root . rtrim($form_directory_from_url, '/') . '/' . $form_file_basename;

if ($form_file_basename !== $form_name_from_url || !file_exists($form_file_path) || pathinfo($form_file_path, PATHINFO_EXTENSION) !== 'html') {
    $_SESSION['message'] = "Selected form '" . htmlspecialchars($form_name_from_url) . "' is invalid or not found in the specified directory. Path: " . $form_file_path;
    header("Location: " . $select_form_page . '?patient_id=' . htmlspecialchars($selected_patient_id));
    exit();
}

// Connect to DB to get patient name for display (as session method is removed)
require_once $path_to_root . 'includes/db_connect.php';
$patient_full_name = "Patient"; // Default
$stmt_patient_name = $mysqli->prepare("SELECT first_name, last_name FROM patients WHERE id = ?");
if ($stmt_patient_name) {
    $stmt_patient_name->bind_param("i", $selected_patient_id);
    if ($stmt_patient_name->execute()) {
        $result_patient_name = $stmt_patient_name->get_result();
        if ($patient_details_db = $result_patient_name->fetch_assoc()) {
            $patient_full_name = htmlspecialchars($patient_details_db['first_name'] . " " . $patient_details_db['last_name']);
        } else {
            $_SESSION['message'] = "Patient details not found in database. Please re-select the patient.";
            $stmt_patient_name->close();
            header("Location: " . $select_patient_page);
            exit();
        }
    } else {
        error_log("Error executing patient name fetch: " . $stmt_patient_name->error);
        $_SESSION['message'] = "Error fetching patient details.";
        $stmt_patient_name->close();
        header("Location: " . $select_patient_page);
        exit();
    }
    $stmt_patient_name->close();
} else {
    error_log("Error preparing patient name fetch: " . $mysqli->error);
    $_SESSION['message'] = "Database error fetching patient details.";
    header("Location: " . $select_patient_page);
    exit();
}


// 6. Set page title
$form_display_name = ucwords(str_replace(['_', '-'], ' ', pathinfo($form_file_basename, PATHINFO_FILENAME)));
$page_title = "Fill Form: " . htmlspecialchars($form_display_name) . " for " . $patient_full_name;

// 7. Include header
include_once $path_to_root . 'includes/header.php';
?>

<!-- Inject JavaScript variables for patient_id and clinician_id (user_id) -->
<script>
    window.currentPatientId = <?php echo json_encode($selected_patient_id); ?>;
    window.currentClinicianId = <?php echo json_encode($current_user_id); ?>;
    // Also make form_name available if js/form_handler.js needs it directly, though it's also in hidden field
    window.currentFormName = <?php echo json_encode($form_file_basename); ?>; 
</script>

<?php
// 8. Display heading
?>
<div class="container mt-4"> <!-- Added mt-4 for spacing -->
    <h2 class="mb-4">
        <?php echo htmlspecialchars($form_display_name); ?>
        <small class="text-muted">for <?php echo $patient_full_name; ?></small>
    </h2>

    <?php
    // 9. Read the content of the selected HTML form file
    $form_content = file_get_contents($form_file_path);
    if ($form_content === false) {
        echo "<div class='alert alert-danger'>Error: Could not read the form content.</div>";
    } else {
        // Echo the raw form content directly.
        // The static HTML forms should be designed to use js/form_handler.js
        // and pick up window.currentPatientId, window.currentClinicianId, window.currentFormName.
        echo $form_content;
    }
    ?>

    <!-- 13. Navigation links -->
    <div class="mt-4 mb-4">
        <a href="<?php echo $select_form_page; ?>?patient_id=<?php echo htmlspecialchars($selected_patient_id); ?>" class="btn btn-secondary">Back to Form Selection</a>
        <a href="dashboard.php" class="btn btn-info">Back to Dashboard</a>
    </div>
</div>

<?php
// 14. Include footer
include_once $path_to_root . 'includes/footer.php';
?>
