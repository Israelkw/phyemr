<?php
$path_to_root = "../"; // Define $path_to_root for includes
// Session check must come before any HTML output.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is logged in and is a clinician
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'clinician') {
    $_SESSION['message'] = "Unauthorized access. Only clinicians can view this page.";
    header("location: dashboard.php"); // Sibling page
    exit;
}

require_once $path_to_root . 'includes/db_connect.php'; // $mysqli connection object

$my_patients = [];
$db_error_message = '';
$clinician_id = $_SESSION['user_id'];

$sql = "SELECT id, first_name, last_name, date_of_birth FROM patients WHERE assigned_clinician_id = ? ORDER BY last_name, first_name";
$stmt = $mysqli->prepare($sql);

if ($stmt === false) {
    error_log("Error preparing statement to fetch clinician's patients: " . $mysqli->error);
    $db_error_message = "An error occurred while preparing to fetch your patient data. Please try again later.";
} else {
    $stmt->bind_param("i", $clinician_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $my_patients[] = $row;
        }
    } else {
        error_log("Error executing statement to fetch clinician's patients: " . $stmt->error);
        $db_error_message = "An error occurred while fetching your patient data. Please try again later.";
    }
    $stmt->close();
}
// $mysqli->close(); // Connection usually closed at end of script by PHP or db_connect.php

$page_title = "My Assigned Patients";
require_once $path_to_root . 'includes/header.php'; 
// header.php includes navigation.php, which handles displaying $_SESSION['message'] (global messages)
?>
    <?php // The main content div class="container" is provided by header.php for the <main> element ?>
    <h2 class="mt-4 mb-3">My Patients</h2>

    <?php if (!empty($db_error_message)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($db_error_message); ?>
        </div>
    <?php endif; ?>

    <p><a href="add_patient.php" class="btn btn-success mb-3">Add New Patient</a> <a href="dashboard.php" class="btn btn-secondary mb-3">Back to Dashboard</a></p> <!-- Sibling pages -->

    <?php if (empty($db_error_message) && !empty($my_patients)): ?>
        <table class="table table-striped table-hover table-bordered"> <!-- Ensured consistency and added table-hover -->
            <thead>
                <tr>
                    <th>Patient ID</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Date of Birth</th>
                    <th>Action</th> <!-- Changed from View History for consistency if other actions are added -->
                </tr>
            </thead>
            <tbody>
                <?php foreach ($my_patients as $patient): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($patient['id']); ?></td>
                        <td><?php echo htmlspecialchars($patient['first_name']); ?></td>
                        <td><?php echo htmlspecialchars($patient['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($patient['date_of_birth']); ?></td>
                        <td>
                            <a href="view_patient_history.php?patient_id=<?php echo htmlspecialchars($patient['id']); ?>" class="btn btn-info btn-sm">View History</a>
                            <!-- Add other actions here if needed, e.g., Edit Patient -->
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif (empty($db_error_message) && empty($my_patients)) : ?>
        <div class="alert alert-info">
            You have no patients assigned to you yet. 
            If a patient has been recently assigned to you by a receptionist, they will appear here.
            You can also <a href="add_patient.php">add a new patient</a>, and they will be automatically assigned to you. <!-- Sibling page -->
        </div>
    <?php endif; ?>

<?php require_once $path_to_root . 'includes/footer.php'; ?>
