<?php
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();

// Check if user is logged in and is a clinician
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clinician') {
    $_SESSION['message'] = "Unauthorized access. Please login as a clinician.";
    header("Location: login.php"); // Sibling page
    exit();
}

require_once $path_to_root . 'includes/db_connect.php'; // Provides $pdo
require_once $path_to_root . 'includes/Database.php';    // Provides Database class

$db = new Database($pdo); // Instantiate Database class

$patient_id = null;
$patient_details = null;
$submissions = [];
$error_message = ''; // For general errors
$db_error_message = '';   // Specifically for database interaction errors

// 1. Retrieve and validate patient_id from GET parameter
if (!isset($_GET['patient_id']) || empty($_GET['patient_id']) || !is_numeric($_GET['patient_id'])) {
    $_SESSION['message'] = "Invalid or missing patient ID.";
    header("Location: view_my_patients.php"); // Redirect to clinician's patient list
    exit();
}
$patient_id = (int)$_GET['patient_id'];
$current_clinician_id = $_SESSION['user_id'];

// 2. Fetch Patient Details from Database
// Ensure the patient is assigned to the current clinician for authorization
try {
    $sql_patient = "SELECT id, first_name, last_name, date_of_birth FROM patients WHERE id = :patient_id AND assigned_clinician_id = :clinician_id";
    $stmt_patient = $db->prepare($sql_patient);
    $db->execute($stmt_patient, [':patient_id' => $patient_id, ':clinician_id' => $current_clinician_id]);
    $patient_details = $db->fetch($stmt_patient);

    if (!$patient_details) {
        $error_message = "Patient not found or you are not authorized to view this patient's history.";
    }
} catch (PDOException $e) {
    error_log("Error fetching patient details: " . $e->getMessage());
    $db_error_message = "An error occurred while fetching patient data.";
    // Optional: ErrorHandler::handleException($e);
}

// Initialize flags for nurse-submitted forms
$hasNurseVitals = false;
$hasNurseGeneralOverview = false;

// 3. Check for specific nurse-submitted forms AND Fetch All Form Submissions
// if patient details were found and no DB error occurred for patient fetch
if ($patient_details && empty($db_error_message)) {
    try {
        // Check for nurse-submitted vital_signs.html
        $sql_check_vitals = "SELECT 1 FROM patient_form_submissions pfs
                             JOIN users u ON pfs.submitted_by_user_id = u.id
                             WHERE pfs.patient_id = :patient_id
                             AND pfs.form_name = 'vital_signs.html'
                             AND pfs.form_directory = 'patient_general_info'
                             AND u.role = 'nurse'
                             LIMIT 1";
        $stmt_check_vitals = $db->prepare($sql_check_vitals);
        $db->execute($stmt_check_vitals, [':patient_id' => $patient_id]);
        if ($db->fetch($stmt_check_vitals)) {
            $hasNurseVitals = true;
        }

        // Check for nurse-submitted general_patient_overview.html
        $sql_check_overview = "SELECT 1 FROM patient_form_submissions pfs
                               JOIN users u ON pfs.submitted_by_user_id = u.id
                               WHERE pfs.patient_id = :patient_id
                               AND pfs.form_name = 'general_patient_overview.html'
                               AND pfs.form_directory = 'patient_general_info'
                               AND u.role = 'nurse'
                               LIMIT 1";
        $stmt_check_overview = $db->prepare($sql_check_overview);
        $db->execute($stmt_check_overview, [':patient_id' => $patient_id]);
        if ($db->fetch($stmt_check_overview)) {
            $hasNurseGeneralOverview = true;
        }

    } catch (PDOException $e) {
        error_log("Error checking for nurse-specific forms: " . $e->getMessage());
        // Don't set $db_error_message here as it might overwrite a patient fetch error
        // This check is supplementary; failing it shouldn't block viewing history if patient data is okay.
        // We can add a specific message for this if needed.
    }

    // Fetch all submissions (existing logic)
    $sql_submissions = "
        SELECT 
            pfs.id, 
            pfs.form_name, 
            pfs.form_directory, 
            pfs.submission_timestamp, 
            pfs.submitted_by_user_id,
            u.first_name AS submitter_first_name,
            u.last_name AS submitter_last_name
        FROM patient_form_submissions pfs
        LEFT JOIN users u ON pfs.submitted_by_user_id = u.id
        WHERE pfs.patient_id = :patient_id
        ORDER BY pfs.submission_timestamp DESC";
    
    try {
        $stmt_submissions = $db->prepare($sql_submissions);
        $db->execute($stmt_submissions, [':patient_id' => $patient_id]);
        $submissions = $db->fetchAll($stmt_submissions);
    } catch (PDOException $e) {
        error_log("Error fetching form submissions: " . $e->getMessage());
        $db_error_message = "An error occurred while fetching submission history.";
        // Optional: ErrorHandler::handleException($e);
    }
}

// No explicit $mysqli->close() or $stmt->close() needed with PDO and Database class

$page_title = "Patient Form History";
include_once $path_to_root . 'includes/header.php';
?>

<div class="container mt-4">
    <h2 class="mb-4">Patient Form Submission History</h2>

    <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    <?php if (!empty($db_error_message)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($db_error_message); ?></div>
    <?php endif; ?>

    <?php if ($patient_details && empty($error_message) && empty($db_error_message)): ?>
    <h4 class="mb-3">Patient Details</h4>
    <p>
        <strong>Name:</strong>
        <?php echo htmlspecialchars($patient_details['first_name'] . ' ' . $patient_details['last_name']); ?><br>
        <strong>Date of Birth:</strong>
        <?php 
  $dob = $patient_details['date_of_birth'];
  echo htmlspecialchars($dob); 
  
  $birthDate = new DateTime($dob);
  $today = new DateTime('today');
  $diff = $birthDate->diff($today); 
  
  if ($diff->y >= 1) {
    $ageText = $diff->y . " year" . ($diff->y > 1 ? "s" : "");
  } elseif ($diff->m >= 1) {
    $ageText = $diff->m . " month" . ($diff->m > 1 ? "s" : "");
  } else {
    $ageText = $diff->d . " day" . ($diff->d > 1 ? "s" : "");
  }
?>
        <br>
        <strong>Age:</strong> <?php echo $ageText; ?>

        <strong>Patient ID:</strong> <?php echo htmlspecialchars($patient_details['id']); ?>
    </p>

    <?php // Prompts for missing nurse information ?>
    <!-- <?php if (!$hasNurseVitals): ?>
            <div class="alert alert-warning">
                <strong>Action Required:</strong> Vital signs information from a nurse is missing for this patient.
                <a href="fill_patient_form.php?patient_id=<?php echo htmlspecialchars($patient_id); ?>&form_name=vital_signs.html&form_directory=patient_general_info" class="btn btn-sm btn-outline-warning ms-2">Enter Vitals</a>
            </div>
        <?php endif; ?>
        <?php if (!$hasNurseGeneralOverview): ?>
            <div class="alert alert-warning mt-2">
                <strong>Action Required:</strong> General patient overview (allergies, medications) from a nurse is missing.
                <a href="fill_patient_form.php?patient_id=<?php echo htmlspecialchars($patient_id); ?>&form_name=general_patient_overview.html&form_directory=patient_general_info" class="btn btn-sm btn-outline-warning ms-2">Enter General Overview</a>
            </div>
        <?php endif; ?> -->


    <h4 class="mt-4 mb-3">Submissions</h4>
    <?php if (!empty($submissions)): ?>
    <table class="table table-striped table-hover table-bordered">
        <thead>
            <tr>
                <th>Submission ID</th>
                <th>Form Name</th>
                <th>Date Submitted</th>
                <th>Submitted By</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($submissions as $submission): ?>
            <?php
                            // Format form_name and form_directory for display
                            $form_file_name = pathinfo($submission['form_name'], PATHINFO_FILENAME); // e.g., "cervical"
                            $formatted_form_name = ucwords(str_replace(['_', '-'], ' ', $form_file_name));
                            $formatted_directory_name = ucwords(str_replace(['_', '-'], ' ', $submission['form_directory']));
                            $display_form_name = $formatted_form_name . " (" . $formatted_directory_name . ")";

                            // Prepare submitter display name
                            $submitter_name = "N/A"; // Default if user not found or name is empty
                            if (!empty($submission['submitter_first_name']) || !empty($submission['submitter_last_name'])) {
                                $submitter_name = htmlspecialchars(trim($submission['submitter_first_name'] . ' ' . $submission['submitter_last_name']));
                            } elseif (isset($submission['submitted_by_user_id'])) {
                                $submitter_name = "User ID: " . htmlspecialchars($submission['submitted_by_user_id']);
                            }
                            
                            $view_data_link = "view_submission_detail.php?submission_id=" . htmlspecialchars($submission['id']);
                        ?>
            <tr>
                <td><?php echo htmlspecialchars($submission['id']); ?></td>
                <td><?php echo htmlspecialchars($display_form_name); ?></td>
                <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($submission['submission_timestamp']))); ?>
                </td>
                <td><?php echo $submitter_name; // Already escaped if names exist, or displays ID safely ?></td>
                <td>
                    <a href="<?php echo $view_data_link; ?>" class="btn btn-info btn-sm">View Data</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="alert alert-info">No form submissions found for this patient.</div>
    <?php endif; ?>
    <?php elseif (empty($error_message) && empty($db_error_message)): ?>
    <?php // This case should ideally be caught by $error_message if patient not found, but as a fallback: ?>
    <div class="alert alert-warning">Could not retrieve patient details or history.</div>
    <?php endif; ?>

    <div class="mt-4">
        <a href="view_my_patients.php" class="btn btn-secondary">Back to My Patients</a> <!-- Sibling page -->
        <a href="dashboard.php" class="btn btn-info">Back to Dashboard</a> <!-- Sibling page -->
    </div>
</div>

<?php
include_once $path_to_root . 'includes/footer.php';
?>