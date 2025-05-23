<?php
// Session check must come before any HTML output.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is logged in and is a clinician
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'clinician') {
    $_SESSION['message'] = "Unauthorized access. Only clinicians can view this page.";
    header("location: dashboard.php"); 
    exit;
}

// Fetch all patients from session
$all_patients = isset($_SESSION['patients']) ? $_SESSION['patients'] : [];
$my_patients = [];
$clinician_id = $_SESSION['user_id'];

// Filter patients for the current clinician
if (!empty($all_patients)) {
    foreach ($all_patients as $patient_id => $patient) {
        if (isset($patient['added_by_clinician_id']) && $patient['added_by_clinician_id'] == $clinician_id) {
            $my_patients[$patient_id] = $patient;
        }
    }
}

$page_title = "My Patients";
$path_to_root = ""; 
require_once 'includes/header.php'; 
// header.php includes navigation.php, which handles displaying $_SESSION['message']
?>
    <style>
        /* Styles specific to this page's table */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>

    <?php // The main content div class="container" is provided by header.php for the <main> element ?>
    <h2>My Patients</h2>

    <?php
    // The specific session message display that was here has been removed.
    // Global messages are now handled by navigation.php (via header.php).
    ?>

    <p><a href="add_patient.php">Add New Patient</a> | <a href="dashboard.php">Back to Dashboard</a></p>

    <?php if (!empty($my_patients)): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Date of Birth</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($my_patients as $patient): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($patient['id']); ?></td>
                        <td><?php echo htmlspecialchars($patient['first_name']); ?></td>
                        <td><?php echo htmlspecialchars($patient['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($patient['date_of_birth']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>You have not added any patients yet. <a href="add_patient.php">Add one now</a>.</p>
    <?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
