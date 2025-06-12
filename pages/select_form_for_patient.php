<?php
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();
require_once $path_to_root . 'includes/db_connect.php'; // Added for database connection

// 1. Check if user is logged in and is a clinician
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clinician') {
    $_SESSION['message'] = "Unauthorized access. Please login as a clinician.";
    header("Location: dashboard.php"); // Sibling page
    exit();
}

// 2. Retrieve patient_id from URL
if (!isset($_GET['patient_id']) || empty($_GET['patient_id'])) {
    $_SESSION['message'] = "No patient selected.";
    header("Location: select_patient_for_form.php"); // Sibling page
    exit();
}

$patient_id = $_GET['patient_id'];

// 3. Store patient_id in session
$_SESSION['selected_patient_id_for_form'] = $patient_id;

// 4. Fetch patient details from database
require_once $path_to_root . 'includes/Database.php'; // Ensure Database class is included
$db = new Database($pdo); // $pdo is from db_connect.php already included

$patient_name = "Selected Patient"; // Default name
$db_error_message = null;

try {
    $sql_patient = "SELECT first_name, last_name FROM patients WHERE id = :patient_id";
    $stmt_patient = $db->prepare($sql_patient);
    $db->execute($stmt_patient, [':patient_id' => $patient_id]);
    $patient_db = $db->fetch($stmt_patient);

    if ($patient_db) {
        $patient_name = htmlspecialchars($patient_db['first_name'] . " " . $patient_db['last_name']);
    } else {
        // Patient not found in database
        $_SESSION['message'] = "Selected patient not found in the database (ID: " . htmlspecialchars($patient_id) . ").";
        header("Location: select_patient_for_form.php"); // Redirect to self or patient selection
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching patient name (select_form_for_patient): " . $e->getMessage());
    $_SESSION['message'] = "An error occurred while fetching patient details. Please try again or contact support.";
    // $db_error_message = $_SESSION['message']; // Optionally set for display on current page if not redirecting
    header("Location: select_patient_for_form.php"); // Redirect on error
    exit();
}

// 5. Set page title
$page_title = "Select Form for " . $patient_name; // $patient_name is now from DB

// 6. Include header
include_once $path_to_root . 'includes/header.php';
?>

<div class="container">
    <!-- 7. Display heading -->
    <h2 class="mb-4">Select Evaluation Form for <?php echo $patient_name; ?></h2>

    <?php
    // 8. Scan the patient_evaluation_form/ directory
    $form_directory = $path_to_root . 'patient_evaluation_form/'; // Adjusted path
    $form_files = [];
    if (is_dir($form_directory)) {
        $files = scandir($form_directory);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'html') {
                $form_files[] = $file;
            }
        }
    }

    // 9. If forms are found
    if (!empty($form_files)) :
    ?>
        <div class="list-group">
            <?php foreach ($form_files as $form_file) :
                // Derive user-friendly name
                $form_display_name = str_replace(['_', '-'], ' ', pathinfo($form_file, PATHINFO_FILENAME));
                $form_display_name = ucwords($form_display_name) . " Assessment Form";
            ?>
                <a href="fill_patient_form.php?form_name=<?php echo htmlspecialchars($form_file); ?>&form_directory=patient_evaluation_form" class="list-group-item list-group-item-action">
                    <?php echo htmlspecialchars($form_display_name); ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <!-- 10. If no forms are found -->
        <div class="alert alert-warning" role="alert">
            No evaluation forms found in the '<?php echo htmlspecialchars($form_directory); ?>' directory.
        </div>
    <?php endif; ?>

    <!-- 11. Navigation links -->
    <div class="mt-3">
        <a href="select_patient_for_form.php" class="btn btn-secondary">Back to Patient Selection</a> <!-- Sibling page -->
        <a href="dashboard.php" class="btn btn-info">Back to Dashboard</a> <!-- Sibling page -->
    </div>
</div>

<?php
// 12. Include footer
include_once $path_to_root . 'includes/footer.php';
?>
