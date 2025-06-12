<?php
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();

// Check if user is logged in and is a clinician
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clinician') {
    $_SESSION['message'] = "Unauthorized access.";
    header("Location: dashboard.php"); // Sibling page
    exit();
}

require_once $path_to_root . 'includes/db_connect.php'; // Provides $pdo
require_once $path_to_root . 'includes/Database.php';    // Provides Database class
$db = new Database($pdo); // Instantiate Database class

$page_title = "Select Patient for Form";
include_once $path_to_root . 'includes/header.php';

$clinician_patients = []; // Initialize to ensure it's an array
$db_error_message = '';   // To store any database error messages

if (isset($_SESSION['user_id'])) {
    $clinician_id = $_SESSION['user_id'];
    try {
        $sql = "SELECT id, first_name, last_name, date_of_birth FROM patients WHERE assigned_clinician_id = :clinician_id ORDER BY last_name, first_name";
        $stmt = $db->prepare($sql);
        $db->execute($stmt, [':clinician_id' => $clinician_id]);
        $clinician_patients = $db->fetchAll($stmt);
    } catch (PDOException $e) {
        error_log("Error fetching clinician's patients (select_patient_for_form): " . $e->getMessage());
        $db_error_message = "An error occurred while fetching patient data. Please try again later.";
        // Optional: ErrorHandler::handleException($e);
    }
} else {
    // This case should ideally not be reached due to the session check at the top
    // However, if it is, SessionManager::ensureUserIsLoggedIn would have already handled it.
    // For robustness, ensure a message if somehow bypassed.
    $db_error_message = "User session not found. Please log in again.";
}

?>

<div class="container mt-4"> <!-- Added mt-4 for spacing -->
    <h2 class="mb-3">Select Patient to Assign Form</h2>

    <?php if (!empty($db_error_message)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($db_error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($db_error_message)): ?>
        <?php // The main database error is already displayed further up. ?>
        <?php // This block can be used if there's specific content to show when the patient list can't be displayed due to the error. ?>
        <?php // For now, we can omit an additional message here if the main error message is sufficient, or add a simple one. ?>
        <div class="alert alert-warning mt-3">Could not display the patient list due to an issue (see message above if applicable).</div>
    <?php elseif (!empty($clinician_patients)) : ?>
        <table class="table table-striped table-hover table-bordered">
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
    <?php else : // This case means $db_error_message is empty AND $clinician_patients is empty ?>
        <div class="alert alert-info">
            No patients are currently assigned to you. If a patient has been recently assigned, they should appear here. You can also add new patients via your dashboard.
        </div>
    <?php endif; ?>

    <p class="mt-3"><a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a></p> <!-- Sibling page -->
</div>

<?php include_once $path_to_root . 'includes/footer.php'; ?>
