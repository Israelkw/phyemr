<?php
$path_to_root = "../"; // Define $path_to_root for includes
// Session check must come before any HTML output.
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();

// Ensure user is logged in and is a clinician
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'clinician') {
    $_SESSION['message'] = "Unauthorized access. Only clinicians can view this page.";
    header("location: dashboard.php"); // Sibling page
    exit;
}

// The require_once paths below will use __DIR__ for robustness of file inclusion itself.
require_once __DIR__ . '/../includes/db_connect.php'; // Provides $pdo
require_once __DIR__ . '/../includes/Database.php';    // Provides Database class

$db = new Database($pdo); // Instantiate Database class

$my_patients = [];
$db_error_message = '';
$clinician_id = $_SESSION['user_id'];

try {
    $sql = "SELECT id, first_name, last_name, date_of_birth FROM patients WHERE assigned_clinician_id = :clinician_id ORDER BY last_name, first_name";
    $stmt = $db->prepare($sql);
    $db->execute($stmt, [':clinician_id' => $clinician_id]);
    $my_patients = $db->fetchAll($stmt);
} catch (PDOException $e) {
    error_log("Error fetching clinician's patients: " . $e->getMessage());
    $db_error_message = "An error occurred while fetching your patient data. Please try again later.";
    // Optional: ErrorHandler::handleException($e);
}

$page_title = "My Assigned Patients";
// $path_to_root must be defined before including header.php for its internal use.

// Generate a CSRF token for forms on this page
$csrf_token = SessionManager::generateCsrfToken();

require_once __DIR__ . '/../includes/header.php'; 
// header.php includes navigation.php, which handles displaying $_SESSION['message'] (global messages)
?>
<?php // The main content div class="container" is provided by header.php for the <main> element ?>
<h2 class="mt-4 mb-3">My Patients</h2>

<?php if (!empty($db_error_message)): ?>
<div class="alert alert-danger">
    <?php echo htmlspecialchars($db_error_message); ?>
</div>
<?php endif; ?>

<p><a href="add_patient.php" class="btn btn-success mb-3">Add New Patient</a> <a href="dashboard.php"
        class="btn btn-secondary mb-3">Back to Dashboard</a></p> <!-- Sibling pages -->

<?php if (empty($db_error_message) && !empty($my_patients)): ?>
<table class="table table-striped table-hover table-bordered">
    <!-- Ensured consistency and added table-hover -->
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
            <td class="text-nowrap">
                <a href="view_patient_history.php?patient_id=<?php echo htmlspecialchars($patient['id']); ?>"
                    class="btn btn-info btn-sm mb-1 me-1">View History</a>
                <a href="select_form_for_patient.php?patient_id=<?php echo htmlspecialchars($patient['id']); ?>"
                    class="btn btn-success btn-sm mb-1 me-1">Add Form</a>

                <a href="fill_patient_form.php?patient_id=<?php echo htmlspecialchars($patient['id']); ?>&form_name=general-information.html&form_directory=patient_general_info"
                    class="btn btn-primary btn-sm">fill general info</a>

                <form action="<?php echo $path_to_root; ?>php/handle_remove_patient_from_list.php" method="POST"
                    style="display: inline-block;"
                    onsubmit="return confirm('Are you sure you want to remove this patient from your active list? This patient will become unassigned.');">
                    <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($patient['id']); ?>">
                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($csrf_token); // Use page-level token ?>">
                    <button type="submit" class="btn btn-danger btn-sm mb-1">Remove from List</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php elseif (empty($db_error_message) && empty($my_patients)) : ?>
<div class="alert alert-info">
    You have no patients assigned to you yet.
    If a patient has been recently assigned to you by a receptionist, they will appear here.
    You can also <a href="add_patient.php">add a new patient</a>, and they will be automatically assigned to you.
    <!-- Sibling page -->
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>