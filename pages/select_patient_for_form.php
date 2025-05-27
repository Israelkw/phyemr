<?php
$path_to_root = "../"; // Define $path_to_root for includes
session_start();

// Check if user is logged in and is a clinician
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clinician') {
    $_SESSION['message'] = "Unauthorized access.";
    header("Location: dashboard.php"); // Sibling page
    exit();
}

require_once $path_to_root . 'includes/db_connect.php'; // $mysqli connection object

$page_title = "Select Patient for Form";
include_once $path_to_root . 'includes/header.php';

$clinician_patients = []; // Initialize to ensure it's an array
$db_error_message = '';   // To store any database error messages

if (isset($_SESSION['user_id'])) {
    $clinician_id = $_SESSION['user_id'];

    $sql = "SELECT id, first_name, last_name, date_of_birth FROM patients WHERE assigned_clinician_id = ? ORDER BY last_name, first_name";
    $stmt = $mysqli->prepare($sql);

    if ($stmt === false) {
        error_log("Error preparing statement to fetch clinician's patients: " . $mysqli->error);
        $db_error_message = "An error occurred while preparing to fetch patient data. Please try again later.";
    } else {
        $stmt->bind_param("i", $clinician_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $clinician_patients[] = $row;
            }
            $stmt->close();
        } else {
            error_log("Error executing statement to fetch clinician's patients: " . $stmt->error);
            $db_error_message = "An error occurred while fetching patient data. Please try again later.";
            $stmt->close();
        }
    }
} else {
    // This case should ideally not be reached due to the session check at the top
    $db_error_message = "User session not found. Please log in again.";
}
// $mysqli->close(); // Connection closed at end of script by PHP or db_connect.php

?>

<div class="container mt-4"> <!-- Added mt-4 for spacing -->
    <h2>Select Patient to Assign Form</h2>

    <?php if (!empty($db_error_message)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($db_error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($db_error_message) && !empty($clinician_patients)) : ?>
        <table class="table table-bordered table-striped"> <!-- Removed styled-table, assuming Bootstrap handles styling -->
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
            If a patient has been recently assigned, they should appear here.
            Contact administration if you believe this is an error or if issues persist.
        </div>
    <?php elseif (empty($db_error_message) && empty($clinician_patients)) : ?>
        <div class="alert alert-info">
            No patients are currently assigned to you.
        </div>
    <?php endif; ?>

    <p class="mt-3"><a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a></p> <!-- Sibling page -->
</div>

<?php include_once $path_to_root . 'includes/footer.php'; ?>
