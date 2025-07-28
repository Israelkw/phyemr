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
$contextual_general_info_data = null; // For contextual general info
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

                    // ---- START: Fetch Contextual General Info ----
                    // This should only run if the main submission is successfully fetched and authorized
                    if ($submission_details && $patient_details && empty($error_message) &&
                        $submission_details['form_name'] !== 'general-information.html') {

                        try {
                            $sql_context_gen_info = "SELECT form_data
                                                     FROM patient_form_submissions
                                                     WHERE patient_id = :patient_id
                                                       AND form_name = 'general-information.html'
                                                       AND form_directory = 'patient_general_info'
                                                       AND submission_timestamp <= :current_submission_timestamp
                                                     ORDER BY submission_timestamp DESC
                                                     LIMIT 1";
                            $stmt_context_gen_info = $db->prepare($sql_context_gen_info);
                            $db->execute($stmt_context_gen_info, [
                                ':patient_id' => $patient_id_from_submission,
                                ':current_submission_timestamp' => $submission_details['submission_timestamp']
                            ]);
                            $context_row = $db->fetch($stmt_context_gen_info);
                            if ($context_row && !empty($context_row['form_data'])) {
                                $decoded_context_data = json_decode($context_row['form_data'], true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    $contextual_general_info_data = $decoded_context_data;
                                } else {
                                    error_log("JSON decode error for contextual general info (view_submission_detail): " . json_last_error_msg());
                                    // $contextual_general_info_data remains null
                                }
                            }
                        } catch (PDOException $e_context) {
                             error_log("DB error fetching contextual general info (view_submission_detail): " . $e_context->getMessage());
                             // $contextual_general_info_data remains null, don't break the page
                        }
                    }
                    // ---- END: Fetch Contextual General Info ----
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
    <div class="alert alert-danger">Database error: <?php echo htmlspecialchars($db_error_message); ?> Please contact
        support.</div>
    <?php endif; ?>

    <?php
    // ---- START: Display Contextual General Info ----
    // This should only display if the main submission is also being displayed successfully
    if ($submission_details && $patient_details && empty($error_message) && empty($db_error_message) && $contextual_general_info_data):
    ?>
    <div class="card mb-4 border-info">
        <div class="card-header bg-info text-white">
            <strong>Contextual General Information (Read-Only, from just before this submission)</strong>
        </div>
        <div class="card-body">
            <dl class="row">
                <?php
                if (is_array($contextual_general_info_data)) {
                    $has_content = false;
                    foreach ($contextual_general_info_data as $field) {
                        // Ensure field 'name' is checked and converted to lowercase for 'csrf_token' comparison
                        if (isset($field['label']) && isset($field['value']) && trim($field['value']) !== '' && (!isset($field['name']) || strtolower($field['name']) !== 'csrf_token') ) {
                            $has_content = true;
                ?>
                    <dt class="col-sm-4"><?php echo htmlspecialchars($field['label']); ?>:</dt>
                    <dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($field['value'])); ?></dd>
                <?php
                        }
                    }
                    if (!$has_content) {
                        echo "<dd class='col-sm-12'><em>No specific details recorded in this general info submission.</em></dd>";
                    }
                } else {
                    echo "<dd class='col-sm-12'><em>General information data is not in the expected format.</em></dd>";
                }
                ?>
            </dl>
        </div>
    </div>
    <?php
    endif;
    // ---- END: Display Contextual General Info ----
    ?>

    <?php if ($submission_details && $patient_details && empty($error_message) && empty($db_error_message)): ?>
    <div class="card mb-4">
        <div class="card-header">
            Submission Overview
        </div>
        <div class="card-body">
            <p><strong>Patient:</strong>
                <?php echo htmlspecialchars($patient_details['first_name'] . ' ' . $patient_details['last_name']); ?>
                (ID: <?php echo htmlspecialchars($patient_details['id']); ?>)</p>
            <p><strong>Examination form:</strong>
                <?php echo htmlspecialchars(ucwords(str_replace(['_', '-'], ' ', pathinfo($submission_details['form_name'], PATHINFO_FILENAME)))); ?>
            </p>
            <!-- <p><strong>Form Directory:</strong>
                <?php echo htmlspecialchars(ucwords(str_replace(['_', '-'], ' ', $submission_details['form_directory']))); ?>
            </p> -->
            <p><strong>Data entry Date and time:</strong>
                <?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($submission_details['submission_timestamp']))); ?>
            </p>
            <p><strong>Submission ID:</strong> <?php echo htmlspecialchars($submission_id); ?></p>
        </div>
    </div>

    <fieldset class="mt-4 mb-3">
        <legend>Patient clinical Data</legend>
        <?php if ($form_data_array && !empty($form_data_array)): ?>
        <table class="table table-bordered table-striped">
            <thead class="thead-light">
                <tr>
                    <th>Clinical Examination type</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($form_data_array as $field_object): ?>
                <?php
                        // $field_object is expected to be an array like ['name' => ..., 'value' => ..., 'label' => ...]
                        $displayLabel = htmlspecialchars($field_object['label'] ?? ucwords(str_replace('_', ' ', $field_object['name'] ?? 'Unknown Field')));
                        $rawValue = $field_object['value'] ?? null;
                        $displayValue = "";
                        $shouldDisplayRow = true;

                        if (is_array($rawValue)) {
                            // For arrays (e.g., multi-selects)
                            if (!empty($rawValue)) {
                                $filtered_values = array_filter($rawValue, function($item) { return $item !== null && $item !== ''; });
                                if (!empty($filtered_values)) {
                                    $displayValue = nl2br(htmlspecialchars(implode(', ', $filtered_values)));
                                } else {
                                    $shouldDisplayRow = false; // Don't display if array was all empty/null
                                }
                            } else {
                                $shouldDisplayRow = false; // Don't display empty arrays
                            }
                        } elseif ($rawValue === null) {
                            $shouldDisplayRow = false; // Don't display if value is null
                        }
                        else {
                            // For scalar values (text, select-one, radio, checked checkbox value etc.)
                            $trimmedValue = trim((string)$rawValue);

                            // Define falsey and truthy values for checkboxes/binary states
                            $falseyValues = ["0", "false", "no", "off"]; // Not typically sent by current JS for unchecked
                            $truthyValues = ["1", "true", "yes", "on", "checked"]; // "checked" is a common value for checkboxes

                            if ($trimmedValue === '') {
                                 $shouldDisplayRow = false;
                            } elseif (in_array(strtolower($trimmedValue), $truthyValues, true)) {
                                // For values like "checked", "true", "yes", "on"
                                $displayValue = "<em>Yes</em>"; // Or "Checked", or use the label itself if appropriate
                                // Example: If label is "Is Active?" and value is "checked", "Yes" is good.
                                // If label is "Agree to Terms" and value is "checked", "Yes" is good.
                            } elseif (in_array(strtolower($trimmedValue), $falseyValues, true)) {
                                // This case might not be hit if form_handler.js doesn't send unchecked boxes
                                // or if they are not assigned these explicit falsey string values.
                                $displayValue = "<em>No</em>";
                            }
                             else {
                                // Regular text data
                                $displayValue = nl2br(htmlspecialchars($trimmedValue));
                            }
                        }
                        ?>
                <?php if ($shouldDisplayRow): ?>
                <tr>
                    <td><?php echo $displayLabel; ?></td>
                    <td><?php echo $displayValue; ?></td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif (empty($error_message)): // No specific error message, but form_data_array is empty or null
            echo "<div class='alert alert-info'>No detailed form data to display or data was empty.</div>";
        endif; ?>
    </fieldset>

    <div class="mt-4">
        <a href="view_patient_history.php?patient_id=<?php echo htmlspecialchars($patient_details['id']); ?>"
            class="btn btn-secondary">Back to Patient History</a>
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