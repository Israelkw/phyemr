<?php
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();
$csrf_token = SessionManager::generateCsrfToken(); // Generate CSRF token

// 1. Check if user is logged in and is a clinician or nurse
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['clinician', 'nurse'])) {
    $_SESSION['message'] = "Unauthorized access. Please login as a clinician or nurse.";
    header("Location: dashboard.php");
    exit();
}

// Determine appropriate redirect paths based on role
$select_patient_page = ($_SESSION['role'] === 'nurse') ? 'nurse_select_patient.php' : 'select_patient_for_form.php';
$select_form_page = ($_SESSION['role'] === 'nurse') ? 'nurse_select_form.php' : 'select_form_for_patient.php';

// Check if a patient_id is provided in the URL and update/set the session
// This allows direct linking to this page with a patient context.
if (isset($_GET['patient_id']) && !empty(trim($_GET['patient_id']))) {
    $patient_id_from_get = trim($_GET['patient_id']);
    // Basic validation: ensure it's a positive integer. Adjust if patient IDs have a different format.
    if (filter_var($patient_id_from_get, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        $_SESSION['selected_patient_id_for_form'] = $patient_id_from_get;
    } else {
        // Optional: Log this attempt or handle more gracefully.
        // For now, if an invalid patient_id is passed, it might be better to clear any existing
        // selected_patient_id_for_form to force re-selection, or redirect with an error.
        // For simplicity here, we'll let it fall through to the session check below,
        // which would fail if the GET patient_id was invalid and no valid one was in session.
        // A more robust handling might be:
        // $_SESSION['message'] = "Invalid patient ID format provided in URL.";
        // header("Location: " . $select_patient_page);
        // exit();
        // However, if a valid ID is already in session, we might want to use that.
        // The current approach is to override session if a valid patient_id is in GET.
    }
}

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

// Connect to DB to get patient name for display
require_once $path_to_root . 'includes/db_connect.php'; // Provides $pdo
require_once $path_to_root . 'includes/Database.php';    // Provides Database class
$db = new Database($pdo); // Instantiate Database class

$patient_full_name = "Patient"; // Default

try {
    $stmt_patient_name = $db->prepare("SELECT first_name, last_name FROM patients WHERE id = :id");
    $db->execute($stmt_patient_name, [':id' => $selected_patient_id]);
    $patient_details_db = $db->fetch($stmt_patient_name);

    if ($patient_details_db) {
        $patient_full_name = htmlspecialchars($patient_details_db['first_name'] . " " . $patient_details_db['last_name']);
    } else {
        $_SESSION['message'] = "Patient details not found in database. Please re-select the patient.";
        header("Location: " . $select_patient_page);
        exit();
    }
} catch (PDOException $e) {
    error_log("Database error fetching patient name: " . $e->getMessage());
    $_SESSION['message'] = "Database error fetching patient details. Please try again or contact support.";
    header("Location: " . $select_patient_page);
    exit();
}

// Check if the requested form is a clinical evaluation form and if general info is filled
if ($form_directory_from_url === 'patient_evaluation_form') {
    try {
        $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM patient_form_submissions WHERE patient_id = :patient_id AND form_name = 'general-information.html' AND form_directory = 'patient_general_info'");
        $db->execute($check_stmt, [':patient_id' => $selected_patient_id]);
        $row = $db->fetch($check_stmt); // Fetches the single row with the count

        if ($row && $row['count'] == 0) {
            $_SESSION['message'] = "Please fill out the General Information form for this patient before accessing clinical evaluations.";
            // Redirect to the general-information.html form
            header("Location: fill_patient_form.php?form_name=general-information.html&form_directory=patient_general_info&patient_id=" . urlencode($selected_patient_id));
            exit();
        }
    } catch (PDOException $e) {
        error_log("Database error checking for general info form: " . $e->getMessage());
        $_SESSION['message'] = "Error checking for general information form. Please try again or contact support.";
        header("Location: dashboard.php"); // Or $select_patient_page
        exit();
    }
}

// 6. Set page title
$form_display_name = ucwords(str_replace(['_', '-'], ' ', pathinfo($form_file_basename, PATHINFO_FILENAME)));
$page_title = "Fill Form: " . htmlspecialchars($form_display_name) . " for " . $patient_full_name;

// ---- START: Fetch Recent General Info for Clinician's Evaluation Forms ----
$recent_general_info_data = null;
if ($_SESSION['role'] === 'clinician' && $form_directory_from_url === 'patient_evaluation_form') {
    try {
        $sql_gen_info = "SELECT form_data
                         FROM patient_form_submissions
                         WHERE patient_id = :patient_id
                           AND form_name = 'general-information.html'
                           AND form_directory = 'patient_general_info'
                         ORDER BY submission_timestamp DESC
                         LIMIT 1";
        $stmt_gen_info = $db->prepare($sql_gen_info);
        $db->execute($stmt_gen_info, [':patient_id' => $selected_patient_id]);
        $gen_info_row = $db->fetch($stmt_gen_info);

        if ($gen_info_row && !empty($gen_info_row['form_data'])) {
            $recent_general_info_data = json_decode($gen_info_row['form_data'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Log error, don't break page
                error_log("JSON decode error for recent general info: " . json_last_error_msg());
                $recent_general_info_data = null; // Reset on error
            }
        }
    } catch (PDOException $e) {
        error_log("Database error fetching recent general info: " . $e->getMessage());
        // Don't break the page, just won't show the info
    }
}
// ---- END: Fetch Recent General Info ----

// 7. Include header
include_once $path_to_root . 'includes/header.php';
?>

<!-- Inject JavaScript variables for patient_id and clinician_id (user_id) -->
<script>
window.currentPatientId = <?php echo json_encode($selected_patient_id); ?>;
window.currentClinicianId = <?php echo json_encode($current_user_id); ?>;
// Also make form_name available if js/form_handler.js needs it directly, though it's also in hidden field
window.currentFormName = <?php echo json_encode($form_file_basename); ?>;
window.csrfToken = <?php echo json_encode($csrf_token); ?>; // Inject CSRF token
</script>

<?php
// 8. Display heading
?>

<!-- Added mt-4 for spacing -->
<h2 class="mb-4">
    <?php echo htmlspecialchars($form_display_name); ?>
    <small class="text-muted">for <?php echo $patient_full_name; ?></small>
</h2>

<?php
// ---- START: Display Recent General Info ----
if ($recent_general_info_data && $_SESSION['role'] === 'clinician' && $form_directory_from_url === 'patient_evaluation_form') :
?>
<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <strong>Most Recent General Information (Read-Only)</strong>
    </div>
    <div class="card-body">
        <dl class="row">
            <?php
            // Iterate through the decoded JSON data. Assuming it's an array of field objects {name, value, label}.
            // You might need to adjust this based on the actual structure of 'general-information.html' form_data.
            if (is_array($recent_general_info_data)) {
                foreach ($recent_general_info_data as $field) :
                    if (isset($field['label']) && isset($field['value']) && !empty(trim($field['value']))):
            ?>
                <dt class="col-sm-4"><?php echo htmlspecialchars($field['label']); ?>:</dt>
                <dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($field['value'])); ?></dd>
            <?php
                    endif;
                endforeach;
            } else {
                echo "<dd class='col-sm-12'>General information data is not in the expected format.</dd>";
            }
            ?>
        </dl>
    </div>
</div>
<?php
endif;
// ---- END: Display Recent General Info ----
?>

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
    <a href="<?php echo $select_form_page; ?>?patient_id=<?php echo htmlspecialchars($selected_patient_id); ?>"
        class="btn btn-secondary">Back to Form Selection</a>
    <a href="dashboard.php" class="btn btn-info">Back to Dashboard</a>
</div>


<?php
// 14. Include footer
include_once $path_to_root . 'includes/footer.php';


?>