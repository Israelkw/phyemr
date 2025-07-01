<?php
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();

// 1. Authorization: Ensure user is logged in and is a clinician
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'clinician') {
    $_SESSION['message'] = "Unauthorized access. Only clinicians can view this page.";
    header("location: " . $path_to_root . "pages/dashboard.php");
    exit;
}

// 2. Get patient_id from URL
if (!isset($_GET['patient_id']) || empty($_GET['patient_id'])) {
    $_SESSION['message'] = "No patient selected.";
    header("location: " . $path_to_root . "pages/view_my_patients.php");
    exit;
}
$patient_id = htmlspecialchars($_GET['patient_id']); // Sanitize patient_id

// 3. Database connection and fetching patient details
require_once $path_to_root . 'includes/db_connect.php'; // Provides $pdo
require_once $path_to_root . 'includes/Database.php';    // Provides Database class
$db = new Database($pdo);

$patient = null;
$db_error_message = '';

try {
    // Verify patient is assigned to this clinician
    $sql = "SELECT id, first_name, last_name, date_of_birth
            FROM patients
            WHERE id = :patient_id AND assigned_clinician_id = :clinician_id";
    $stmt = $db->prepare($sql);
    $db->execute($stmt, [':patient_id' => $patient_id, ':clinician_id' => $_SESSION['user_id']]);
    $patient = $db->fetch($stmt);

    if (!$patient) {
        $_SESSION['message'] = "Patient not found or not assigned to you.";
        header("location: " . $path_to_root . "pages/view_my_patients.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching patient details for dashboard: " . $e->getMessage());
    $db_error_message = "An error occurred while fetching patient data.";
    // For critical errors, redirecting might be better, or displaying error on page
}

$page_title = "Patient Dashboard - " . htmlspecialchars($patient['first_name'] ?? '') . " " . htmlspecialchars($patient['last_name'] ?? '');
$csrf_token = SessionManager::generateCsrfToken(); // For the remove form

require_once $path_to_root . 'includes/header.php';
?>

<div class="container mt-4">
    <?php if (!empty($db_error_message)): ?>
        <div class="alert alert-danger"><?php echo $db_error_message; ?></div>
    <?php endif; ?>

    <?php if ($patient): ?>
        <h2>Patient: <?php echo htmlspecialchars($patient['first_name'] . " " . $patient['last_name']); ?></h2>
        <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($patient['date_of_birth']); ?></p>
        <p><strong>Patient ID:</strong> <?php echo htmlspecialchars($patient['id']); ?></p>

        <hr>
        <h4 class="mb-3">Actions</h4>
        <div class="list-group">
            <a href="view_patient_history.php?patient_id=<?php echo $patient_id; ?>" class="list-group-item list-group-item-action">
                <i class="fas fa-history"></i> View Full Patient History
            </a>
            <a href="select_form_for_patient.php?patient_id=<?php echo $patient_id; ?>" class="list-group-item list-group-item-action">
                <i class="fas fa-file-alt"></i> Add New Evaluation Form
            </a>
            <a href="fill_patient_form.php?patient_id=<?php echo $patient_id; ?>&form_name=general-information.html&form_directory=patient_general_info" class="list-group-item list-group-item-action">
                <i class="fas fa-notes-medical"></i> Fill General Information
            </a>
        </div>

        <hr>
        <h4 class="mt-4 mb-3">Administrative Actions</h4>
        <form action="<?php echo $path_to_root; ?>php/handle_remove_patient_from_list.php" method="POST" class="mt-3"
              onsubmit="return confirm('Are you sure you want to remove this patient from your active list? This patient will become unassigned.');">
            <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-user-minus"></i> Remove Patient from My List
            </button>
        </form>

        <div class="mt-4">
            <a href="view_my_patients.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to My Patients List
            </a>
        </div>

    <?php elseif (empty($db_error_message)) : ?>
        <div class="alert alert-warning">Patient data could not be loaded.</div>
    <?php endif; ?>
</div>

<?php
// Font Awesome script if you want to use icons like <i class="fas fa-history"></i>
// Add this to your footer.php or header.php if not already present globally
// echo '<script defer src="https://use.fontawesome.com/releases/v5.15.4/js/all.js"></script>';
require_once $path_to_root . 'includes/footer.php';
?>
