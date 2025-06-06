<?php
$path_to_root = "../"; // Define $path_to_root for includes
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a clinician
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clinician') {
    $_SESSION['message'] = "Unauthorized access. Please login as a clinician.";
    header("Location: login.php"); // Sibling page
    exit();
}

require_once $path_to_root . 'includes/db_connect.php'; // $mysqli connection object

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
$stmt_patient = $mysqli->prepare("SELECT id, first_name, last_name, date_of_birth FROM patients WHERE id = ? AND assigned_clinician_id = ?");
if ($stmt_patient === false) {
    error_log("Error preparing statement to fetch patient details: " . $mysqli->error);
    $db_error_message = "An error occurred while preparing to fetch patient data.";
} else {
    $stmt_patient->bind_param("ii", $patient_id, $current_clinician_id);
    if ($stmt_patient->execute()) {
        $result_patient = $stmt_patient->get_result();
        if ($result_patient->num_rows > 0) {
            $patient_details = $result_patient->fetch_assoc();
        } else {
            $error_message = "Patient not found or you are not authorized to view this patient's history.";
        }
    } else {
        error_log("Error executing statement to fetch patient details: " . $stmt_patient->error);
        $db_error_message = "An error occurred while fetching patient data.";
    }
    $stmt_patient->close();
}

// 3. Fetch Form Submissions if patient details were found
if ($patient_details && empty($db_error_message)) {
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
        WHERE pfs.patient_id = ? 
        ORDER BY pfs.submission_timestamp DESC";
    
    $stmt_submissions = $mysqli->prepare($sql_submissions);

    if ($stmt_submissions === false) {
        error_log("Error preparing statement to fetch form submissions: " . $mysqli->error);
        $db_error_message = "An error occurred while preparing to fetch submission history.";
    } else {
        $stmt_submissions->bind_param("i", $patient_id);
        if ($stmt_submissions->execute()) {
            $result_submissions = $stmt_submissions->get_result();
            while ($row = $result_submissions->fetch_assoc()) {
                $submissions[] = $row;
            }
        } else {
            error_log("Error executing statement to fetch form submissions: " . $stmt_submissions->error);
            $db_error_message = "An error occurred while fetching submission history.";
        }
        $stmt_submissions->close();
    }
}

// $mysqli->close(); // Connection can be closed at the end by PHP or db_connect

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
            <strong>Name:</strong> <?php echo htmlspecialchars($patient_details['first_name'] . ' ' . $patient_details['last_name']); ?><br>
            <strong>Date of Birth:</strong> <?php echo htmlspecialchars($patient_details['date_of_birth']); ?><br>
            <strong>Patient ID:</strong> <?php echo htmlspecialchars($patient_details['id']); ?>
        </p>

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
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($submission['submission_timestamp']))); ?></td>
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
