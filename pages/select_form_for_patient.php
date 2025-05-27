<?php
$path_to_root = "../"; // Define $path_to_root for includes
session_start();
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
$patient_name = "Selected Patient"; // Default name

$stmt = $mysqli->prepare("SELECT first_name, last_name FROM patients WHERE id = ?");
if ($stmt === false) {
    error_log("Error preparing statement to fetch patient name: " . $mysqli->error);
    $_SESSION['message'] = "An error occurred while fetching patient details.";
    header("Location: select_patient_for_form.php"); // Sibling page
    exit();
}

$stmt->bind_param("i", $patient_id); // Assuming patient_id is an integer

if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($patient_db = $result->fetch_assoc()) {
        $patient_name = htmlspecialchars($patient_db['first_name'] . " " . $patient_db['last_name']);
    } else {
        // Patient not found in database
        $_SESSION['message'] = "Selected patient not found in the database (ID: " . htmlspecialchars($patient_id) . ").";
        $stmt->close();
        header("Location: select_patient_for_form.php");
        exit();
    }
    $stmt->close();
} else {
    error_log("Error executing statement to fetch patient name: " . $stmt->error);
    $_SESSION['message'] = "An error occurred while retrieving patient information.";
    $stmt->close();
    header("Location: select_patient_for_form.php"); // Sibling page
    exit();
}
// $mysqli->close(); // Connection closed at end of script

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
                <a href="fill_patient_form.php?form_name=<?php echo htmlspecialchars($form_file); ?>" class="list-group-item list-group-item-action">
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
