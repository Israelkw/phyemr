<?php
session_start();

// Check if user is logged in and is a clinician
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clinician') {
    $_SESSION['message'] = "Unauthorized access.";
    header("Location: dashboard.php");
    exit();
}

$page_title = "Select Patient for Form";
include_once 'includes/header.php';
?>

<div class="container">
    <h2>Select Patient to Assign Form</h2>

    <?php
    // Fetch patients from session
    $all_patients = isset($_SESSION['patients']) ? $_SESSION['patients'] : [];
    $clinician_patients = [];

    // Filter patients for the current clinician
    if (!empty($all_patients)) {
        foreach ($all_patients as $patient_id_key => $patient) { // Use key from $_SESSION['patients']
            if (isset($patient['assigned_clinician_id']) && $patient['assigned_clinician_id'] == $_SESSION['user_id']) {
                // Ensure the patient_id used for links is the actual patient ID.
                // If $all_patients is an associative array with patient ID as key, $patient_id_key is it.
                // If it's numerically indexed, $patient['id'] is it. Assuming $patient['id'] is reliable.
                $clinician_patients[$patient['id']] = $patient; 
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
                            <a href="select_form_for_patient.php?patient_id=<?php echo htmlspecialchars($patient['id']); ?>" class="btn btn-primary btn-sm">Select</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <div class="alert alert-info">
            You have no patients assigned to you for whom forms can be filled. 
            If a patient has been assigned to you, they should appear here. 
            Contact administration if you believe this is an error.
        </div>
    <?php endif; ?>

    <p><a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a></p>
</div>

<?php include_once 'includes/footer.php'; ?>
