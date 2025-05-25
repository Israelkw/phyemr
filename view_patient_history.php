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
            foreach ($all_patients as $patient) {
                // Assuming 'added_by_clinician_id' links patient to clinician
                if (isset($patient['added_by_clinician_id']) && $patient['added_by_clinician_id'] == $current_clinician_id) {
                    $clinician_patients[] = $patient;
                }
            }
        }

        if (!empty($clinician_patients)) :
        ?>
            <table class="table table-bordered table-striped">
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
            <div class="alert alert-info">You have not added any patients yet, or no patients are assigned to you. <a href="add_patient.php">Add a patient</a>.</div>
        <?php endif; ?>

    <?php else : // 6.2. If patient_id IS provided in the URL
        $patient_id_from_url = trim($_GET['patient_id']);
        $patient_index = findPatientIndexById($patient_id_from_url, isset($_SESSION['patients']) ? $_SESSION['patients'] : []);
        
        // Validate patient_id and ensure patient belongs to the clinician
        if ($patient_index === -1 || 
            !isset($_SESSION['patients'][$patient_index]['added_by_clinician_id']) ||
            $_SESSION['patients'][$patient_index]['added_by_clinician_id'] != $_SESSION['user_id']) {
            
            $_SESSION['message'] = "Invalid patient selection or patient not assigned to you.";
            // Redirect back to the selection list to avoid showing an empty/error page for wrong ID
            // header("Location: view_patient_history.php"); 
            // Or show message directly:
            echo "<div class='alert alert-danger'>Invalid patient selection or patient not assigned to you. Please <a href='view_patient_history.php'>select a patient from the list</a>.</div>";
            // exit(); // If redirecting
        } else {
            $selected_patient = $_SESSION['patients'][$patient_index];
            $patient_name = htmlspecialchars($selected_patient['first_name'] . " " . $selected_patient['last_name']);
    ?>
        <h3>History for <?php echo $patient_name; ?></h3>
        <?php
            $submitted_forms = isset($selected_patient['submitted_forms']) && is_array($selected_patient['submitted_forms']) ? $selected_patient['submitted_forms'] : [];

            if (!empty($submitted_forms)) :
        ?>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Form Name</th>
                        <th>Submission Date</th>
                        <th>View/Download JSON</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submitted_forms as $form_submission) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($form_submission['form_name']); // e.g., "cervical.html" ?></td>
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
                                // The path stored is 'submitted_forms/[patient_id]/[json_filename]'
                                // which is relative to the project root.
                                // view_patient_history.php is at the root, so no ../ needed.
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
        <p><a href="view_patient_history.php" class="btn btn-secondary">Select Another Patient</a></p>
    <?php 
        } // End of valid patient_id processing
    endif; // End of patient_id check 
    ?>

    <!-- 7. Common Navigation -->
    <div class="mt-3">
        <a href="dashboard.php" class="btn btn-success">Back to Dashboard</a>
    </div>
</div>

<?php
// 8. Include footer
include_once 'includes/footer.php';
?>
