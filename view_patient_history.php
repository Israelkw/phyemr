<?php
session_start();

// 1. Helper function to find patient index (if patients are in a numeric array)
function findPatientIndexById($patient_id, $patients_array) {
    if (!is_array($patients_array)) return -1;
    foreach ($patients_array as $index => $patient) {
        if (isset($patient['id']) && $patient['id'] == $patient_id) {
            return $index;
        }
    }
    return -1;
}

// 2. Check if user is logged in and is a clinician
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clinician') {
    $_SESSION['message'] = "Unauthorized access. Please login as a clinician.";
    header("Location: login.php"); // Redirect to login if not authorized
    exit();
}

// 3. Set page title
$page_title = "View Patient Form History";

// 4. Include header
include_once 'includes/header.php';
?>

<div class="container">
    <!-- 5. Main heading -->
    <h2>View Patient Form History</h2>

    <?php
    // 6. Check if a specific patient_id is provided in the URL
    if (!isset($_GET['patient_id'])) :
        // 6.1. If patient_id is NOT provided, display list of clinician's patients
    ?>
        <h3>Select a Patient to View History</h3>
        <?php
        $all_patients = isset($_SESSION['patients']) ? $_SESSION['patients'] : [];
        $clinician_patients = [];
        $current_clinician_id = $_SESSION['user_id'];

        if (!empty($all_patients)) {
            foreach ($all_patients as $patient_key => $patient) { // Use key if $_SESSION['patients'] is associative by actual ID
                if (isset($patient['assigned_clinician_id']) && $patient['assigned_clinician_id'] == $current_clinician_id) {
                    $clinician_patients[$patient['id']] = $patient; // Use actual patient ID as key
                }
            }
        }

        if (!empty($clinician_patients)) :
        ?>
            <table class="table table-bordered table-striped styled-table">
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Date of Birth</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clinician_patients as $patient) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($patient['id']); ?></td>
                            <td><?php echo htmlspecialchars($patient['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($patient['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($patient['date_of_birth']); ?></td>
                            <td>
                                <a href="view_patient_history.php?patient_id=<?php echo htmlspecialchars($patient['id']); ?>" class="btn btn-primary btn-sm">View History</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <div class="alert alert-info">You have no patients assigned to you. If you believe this is an error, please contact administration.</div>
        <?php endif; ?>

    <?php else : // 6.2. If patient_id IS provided in the URL
        $patient_id_from_url = trim($_GET['patient_id']);
        // Assuming $_SESSION['patients'] is an associative array where keys are patient IDs.
        // If findPatientIndexById returns an index for a numerically indexed array, 
        // you might need to adjust how $selected_patient is fetched or use a different lookup.
        // For this change, let's assume $_SESSION['patients'] is keyed by patient ID for direct access.
        // If not, findPatientIndexById and subsequent access $_SESSION['patients'][$patient_index] is fine.
        
        $selected_patient = null;
        if (isset($_SESSION['patients'][$patient_id_from_url])) {
            $temp_patient = $_SESSION['patients'][$patient_id_from_url];
            // Crucial validation: check if this patient is assigned to the logged-in clinician
            if (isset($temp_patient['assigned_clinician_id']) && $temp_patient['assigned_clinician_id'] == $_SESSION['user_id']) {
                $selected_patient = $temp_patient;
            }
        }
        
        $all_users_map = isset($_SESSION['all_users_map']) ? $_SESSION['all_users_map'] : [];

        if (!$selected_patient) {
            $_SESSION['message'] = "Invalid patient selection or patient not assigned to you.";
            echo "<div class='alert alert-danger'>" . $_SESSION['message'] . " Please <a href='view_patient_history.php'>select a patient from the list</a>.</div>";
            unset($_SESSION['message']); // Clear message after displaying
        } else {
            $patient_name = htmlspecialchars($selected_patient['first_name'] . " " . $selected_patient['last_name']);
    ?>
        <h3>Form Submission History for <?php echo $patient_name; ?> (ID: <?php echo htmlspecialchars($selected_patient['id']); ?>)</h3>
        <?php
            $submitted_forms = isset($selected_patient['submitted_forms']) && is_array($selected_patient['submitted_forms']) ? $selected_patient['submitted_forms'] : [];
            // Sort forms by submission timestamp, most recent first
            if (!empty($submitted_forms)) {
                usort($submitted_forms, function($a, $b) {
                    return (isset($b['submission_timestamp']) ? $b['submission_timestamp'] : 0) <=> (isset($a['submission_timestamp']) ? $a['submission_timestamp'] : 0);
                });
            }

            if (!empty($submitted_forms)) :
        ?>
            <table class="table table-bordered table-striped styled-table">
                <thead>
                    <tr>
                        <th>Form Name</th>
                        <th>Category</th>
                        <th>Submitted By</th>
                        <th>Submission Date</th>
                        <th>View/Download JSON</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submitted_forms as $form_submission) : ?>
                        <?php
                            // Descriptive Form Name
                            $descriptive_form_name = "Unknown Form";
                            if (isset($form_submission['form_name'])) {
                                $base_name = pathinfo($form_submission['form_name'], PATHINFO_FILENAME);
                                $descriptive_form_name = ucwords(str_replace(['_', '-'], [' ', ' '], $base_name));
                            }

                            // Category
                            $category = "N/A";
                            if (isset($form_submission['form_directory'])) {
                                if ($form_submission['form_directory'] === 'patient_general_info') {
                                    if (strpos($form_submission['form_name'], 'vital_signs') !== false) {
                                        $category = "Vitals";
                                        $descriptive_form_name = "Vital Signs Record"; // More specific
                                    } elseif (strpos($form_submission['form_name'], 'general_patient_overview') !== false) {
                                        $category = "General Info";
                                        $descriptive_form_name = "General Patient Overview"; // More specific
                                    } else {
                                        $category = "General Info"; // Default for this directory
                                    }
                                } elseif ($form_submission['form_directory'] === 'patient_evaluation_form') {
                                    $category = "Clinical Evaluation";
                                    // Example for specific assessment names if needed
                                    // if (strpos($form_submission['form_name'], 'cervical') !== false) $descriptive_form_name = "Cervical Assessment";
                                } else {
                                    $category = ucfirst(str_replace(['_', '-'], [' ', ' '], $form_submission['form_directory']));
                                }
                            }

                            // Submitter Details
                            $submitter_details = "N/A";
                            if (isset($form_submission['submitted_by_user_id']) && isset($all_users_map[$form_submission['submitted_by_user_id']])) {
                                $submitter = $all_users_map[$form_submission['submitted_by_user_id']];
                                $submitter_name = htmlspecialchars($submitter['first_name'] . " " . $submitter['last_name']);
                                $submitter_role = htmlspecialchars(ucfirst($submitter['role']));
                                $submitter_details = $submitter_role . " " . $submitter_name;
                            } elseif (isset($form_submission['submitted_by_user_role'])) {
                                // Fallback if user not in map but role is known
                                $submitter_details = htmlspecialchars(ucfirst($form_submission['submitted_by_user_role'])) . " (ID: " . htmlspecialchars(isset($form_submission['submitted_by_user_id']) ? $form_submission['submitted_by_user_id'] : 'Unknown') . ")";
                            } elseif (isset($form_submission['submitted_by_user_id'])) {
                                $submitter_details = "User ID: " . htmlspecialchars($form_submission['submitted_by_user_id']);
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($descriptive_form_name); ?></td>
                            <td><?php echo htmlspecialchars($category); ?></td>
                            <td><?php echo $submitter_details; // Already escaped ?></td>
                            <td>
                                <?php 
                                if (isset($form_submission['submission_timestamp'])) {
                                    echo htmlspecialchars(date('Y-m-d H:i:s', $form_submission['submission_timestamp']));
                                } else {
                                    echo "N/A";
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                $file_link = htmlspecialchars($form_submission['file_path']); 
                                ?>
                                <a href="<?php echo $file_link; ?>" target="_blank" class="btn btn-info btn-sm">View JSON</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <div class="alert alert-info">No forms have been submitted yet for <?php echo $patient_name; ?>.</div>
        <?php endif; ?>
        <p><a href="view_patient_history.php" class="btn btn-secondary">Back to Patient Selection for History</a></p>
    <?php 
        } // End of valid patient_id processing
    endif; // End of patient_id check 
    ?>

    <!-- 7. Common Navigation -->
    <div class="mt-3">
        <a href="dashboard.php" class="btn btn-info">Back to Dashboard</a>
    </div>
</div>

<?php
// 8. Include footer
include_once 'includes/footer.php';
?>
