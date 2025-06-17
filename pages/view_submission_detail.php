<?php
$path_to_root = "../";
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();

require_once $path_to_root . 'includes/db_connect.php'; // Provides $pdo
require_once $path_to_root . 'includes/Database.php';    // Provides Database class

$db = new Database($pdo); // Instantiate Database class

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

    try {
        // 3. Fetch Submission Details from patient_form_submissions
        $sql_submission = "SELECT patient_id, form_name, form_directory, submission_timestamp, form_data FROM patient_form_submissions WHERE id = :submission_id";
        $stmt_submission = $db->prepare($sql_submission);
        $db->execute($stmt_submission, [':submission_id' => $submission_id]);
        $submission_details = $db->fetch($stmt_submission);

        if ($submission_details) {
            $patient_id_from_submission = $submission_details['patient_id'];

            // 4. Fetch Patient Details for Authorization
            $sql_patient = "SELECT id, first_name, last_name, date_of_birth, assigned_clinician_id FROM patients WHERE id = :patient_id";
            $stmt_patient = $db->prepare($sql_patient);
            $db->execute($stmt_patient, [':patient_id' => $patient_id_from_submission]);
            $patient_details = $db->fetch($stmt_patient);

            if ($patient_details) {
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
            $error_message = "Submission not found with ID: " . htmlspecialchars($submission_id);
        }
    } catch (PDOException $e) {
        error_log("Database error on view_submission_detail.php: " . $e->getMessage());
        $db_error_message = "A database error occurred. Please try again or contact support.";
        // Optional: ErrorHandler::handleException($e);
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
                        <?php
                        // Normalize key for display
                        $displayKey = htmlspecialchars(ucwords(str_replace('_', ' ', $key)));
                        $displayValue = "";
                        $shouldDisplayRow = true;

                        if (is_array($value)) {
                            // For arrays (e.g., checkbox groups, multi-selects)
                            // Display if the array is not empty.
                            // Join values with a comma for a cleaner look than print_r.
                            if (!empty($value)) {
                                $filtered_values = array_filter($value, function($item) { return $item !== null && $item !== ''; });
                                if (!empty($filtered_values)) {
                                    $displayValue = nl2br(htmlspecialchars(implode(', ', $filtered_values)));
                                } else {
                                    $shouldDisplayRow = false; // Don't display if array was all empty/null
                                }
                            } else {
                                $shouldDisplayRow = false; // Don't display empty arrays
                            }
                        } else {
                            // For scalar values
                            $trimmedValue = trim((string)$value); // Trim and cast to string

                            // Define falsey and truthy values (lowercase for case-insensitive comparison)
                            $falseyValues = ["0", "false", "no", "off"];
                            // Empty string is handled separately to mean "don't display row"
                            $truthyValues = ["1", "true", "yes", "on"];

                            if ($trimmedValue === '') {
                                 $shouldDisplayRow = false; // Don't display row for empty strings
                            } elseif (in_array(strtolower($trimmedValue), $falseyValues, true)) {
                                 $shouldDisplayRow = false; // Don't display row for "0", "false", "no", "off"
                            } elseif (in_array(strtolower($trimmedValue), $truthyValues, true)) {
                                // For "checked" or "true" representations, display a generic confirmation or just the label.
                                // The key itself acts as the "corresponding text".
                                $displayValue = "<em>Checked</em>"; // Or "Yes", or even left empty if the key is sufficient.
                            } else {
                                // Regular text data
                                $displayValue = nl2br(htmlspecialchars($trimmedValue));
                            }
                        }
                        ?>
                        <?php if ($shouldDisplayRow): ?>
                            <tr>
                                <td><?php echo $displayKey; ?></td>
                                <td><?php echo $displayValue; ?></td>
                            </tr>
                        <?php endif; ?>
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
// No explicit $mysqli->close() or $stmt->close() needed with PDO and Database class
include_once $path_to_root . 'includes/footer.php';
?>
