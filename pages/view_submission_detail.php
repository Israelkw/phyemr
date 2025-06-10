<?php
$path_to_root = "../";
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();

require_once $path_to_root . 'includes/db_connect.php'; // $mysqli connection

// Initialize variables
$submission_id = null;
$submission_details = null;
$patient_details = null;
$form_data_array = null;
$error_message = '';
$db_error_message = '';
$page_title = "Form Submission Details";

// 1. Check if user is logged in and is a clinician
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'clinician') {
    $_SESSION['message'] = "Unauthorized access. Please login as a clinician.";
    header("Location: login.php"); // Adjust if login is in a different relative path
    exit();
}
$current_clinician_id = $_SESSION['user_id'];

// 2. Retrieve and validate submission_id from GET parameter
if (!isset($_GET['submission_id']) || empty($_GET['submission_id']) || !is_numeric($_GET['submission_id'])) {
    $error_message = "Invalid or missing submission ID.";
} else {
    $submission_id = (int)$_GET['submission_id'];

    // 3. Fetch Submission Details from patient_form_submissions
    $stmt_submission = $mysqli->prepare("SELECT patient_id, form_name, form_directory, submission_timestamp, form_data FROM patient_form_submissions WHERE id = ?");
    if (!$stmt_submission) {
        error_log("Error preparing statement to fetch submission: " . $mysqli->error);
        $db_error_message = "An error occurred while preparing to fetch submission data.";
    } else {
        $stmt_submission->bind_param("i", $submission_id);
        if ($stmt_submission->execute()) {
            $result_submission = $stmt_submission->get_result();
            if ($result_submission->num_rows > 0) {
                $submission_details = $result_submission->fetch_assoc();
                $patient_id_from_submission = $submission_details['patient_id'];

                // 4. Fetch Patient Details for Authorization
                $stmt_patient = $mysqli->prepare("SELECT id, first_name, last_name, date_of_birth, assigned_clinician_id FROM patients WHERE id = ?");
                if (!$stmt_patient) {
                    error_log("Error preparing statement for patient authorization: " . $mysqli->error);
                    $db_error_message = "An error occurred while preparing patient data for authorization.";
                } else {
                    $stmt_patient->bind_param("i", $patient_id_from_submission);
                    if ($stmt_patient->execute()) {
                        $result_patient = $stmt_patient->get_result();
                        if ($result_patient->num_rows > 0) {
                            $patient_details = $result_patient->fetch_assoc();
                            // Authorization Check: Patient must be assigned to the current clinician
                            if ($patient_details['assigned_clinician_id'] != $current_clinician_id) {
                                $error_message = "Authorization failed. You are not assigned to this patient.";
                                $submission_details = null; // Clear data to prevent display
                                $patient_details = null;    // Clear data
                            } else {
                                // Authorized: Decode JSON form data
                                $form_data_json = $submission_details['form_data'];
                                if ($form_data_json) {
                                    $form_data_array = json_decode($form_data_json, true);
                                    if (json_last_error() !== JSON_ERROR_NONE) {
                                        $error_message = "Error decoding form data: " . json_last_error_msg();
                                        $form_data_array = null; // Clear on error
                                    }
                                } else {
                                    $error_message = "No form data found for this submission.";
                                }
                            }
                        } else {
                            $error_message = "Patient associated with this submission not found.";
                        }
                    } else {
                        error_log("Error executing patient authorization statement: " . $stmt_patient->error);
                        $db_error_message = "An error occurred during patient authorization.";
                    }
                    $stmt_patient->close();
                }
            } else {
                $error_message = "Submission not found with ID: " . htmlspecialchars($submission_id);
            }
        } else {
            error_log("Error executing submission fetch statement: " . $stmt_submission->error);
            $db_error_message = "An error occurred while fetching submission data.";
        }
        $stmt_submission->close();
    }
}

include_once $path_to_root . 'includes/header.php';
?>

<div class="container mt-4">
    <h2 class="mb-4"><?php echo htmlspecialchars($page_title); ?></h2>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    <?php if (!empty($db_error_message)): ?>
        <div class="alert alert-danger">Database error: <?php echo htmlspecialchars($db_error_message); ?> Please contact support.</div>
    <?php endif; ?>

    <?php if ($submission_details && $patient_details && empty($error_message) && empty($db_error_message)): ?>
        <div class="card mb-4">
            <div class="card-header">
                Submission Overview
            </div>
            <div class="card-body">
                <p><strong>Patient:</strong> <?php echo htmlspecialchars($patient_details['first_name'] . ' ' . $patient_details['last_name']); ?> (ID: <?php echo htmlspecialchars($patient_details['id']); ?>)</p>
                <p><strong>Form Name:</strong> <?php echo htmlspecialchars(ucwords(str_replace(['_', '-'], ' ', pathinfo($submission_details['form_name'], PATHINFO_FILENAME)))); ?></p>
                <p><strong>Form Directory:</strong> <?php echo htmlspecialchars(ucwords(str_replace(['_', '-'], ' ', $submission_details['form_directory']))); ?></p>
                <p><strong>Submitted At:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($submission_details['submission_timestamp']))); ?></p>
                <p><strong>Submission ID:</strong> <?php echo htmlspecialchars($submission_id); ?></p>
            </div>
        </div>

        <h4 class="mt-4 mb-3">Submitted Form Data</h4>
        <?php if ($form_data_array && !empty($form_data_array)): ?>
            <table class="table table-bordered table-striped">
                <thead class="thead-light">
                    <tr>
                        <th>Field Name</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($form_data_array as $key => $value): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $key))); ?></td>
                            <td>
                                <?php 
                                if (is_array($value)) {
                                    echo nl2br(htmlspecialchars(print_r($value, true))); // Basic array display
                                } else {
                                    echo nl2br(htmlspecialchars($value)); 
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif (empty($error_message)): // No specific error message, but form data array is empty or null
            echo "<div class='alert alert-info'>No detailed form data to display or data was empty.</div>";
        endif; ?>
        
        <div class="mt-4">
            <a href="view_patient_history.php?patient_id=<?php echo htmlspecialchars($patient_details['id']); ?>" class="btn btn-secondary">Back to Patient History</a>
            <a href="dashboard.php" class="btn btn-info">Back to Dashboard</a>
        </div>

    <?php elseif (empty($error_message) && empty($db_error_message)): // Fallback if data is missing but no explicit error was set
        echo "<div class='alert alert-warning'>Could not retrieve submission details. Ensure the submission ID is correct and you are authorized.</div>";
        echo '<p><a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a></p>';
    endif; ?>
</div>

<?php
// $mysqli->close(); // Connection can be closed at the end by PHP or db_connect
include_once $path_to_root . 'includes/footer.php';
?>
