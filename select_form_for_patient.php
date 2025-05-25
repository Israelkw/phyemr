<?php
session_start();

// 1. Check if user is logged in and is a clinician
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clinician') {
    $_SESSION['message'] = "Unauthorized access. Please login as a clinician.";
    header("Location: dashboard.php");
    exit();
}

// 2. Retrieve patient_id from URL
if (!isset($_GET['patient_id']) || empty($_GET['patient_id'])) {
    $_SESSION['message'] = "No patient selected.";
    header("Location: select_patient_for_form.php");
    exit();
}

$patient_id = $_GET['patient_id'];

// 3. Store patient_id in session
$_SESSION['selected_patient_id_for_form'] = $patient_id;

// 4. Fetch patient details
$patient_name = "Selected Patient"; // Default name
$patient_found = false;
if (isset($_SESSION['patients']) && is_array($_SESSION['patients'])) {
    foreach ($_SESSION['patients'] as $patient) {
        if (isset($patient['id']) && $patient['id'] == $patient_id) {
            $patient_name = htmlspecialchars($patient['first_name'] . " " . $patient['last_name']);
            $patient_found = true;
            break;
        }
    }
}

if (!$patient_found) {
    // This case might happen if session data is inconsistent or patient_id is invalid
    $_SESSION['message'] = "Selected patient not found.";
    // Redirect back to patient selection as we can't proceed without a valid patient
    header("Location: select_patient_for_form.php");
    exit();
}


// 5. Set page title
$page_title = "Select Form for " . $patient_name;

// 6. Include header
include_once 'includes/header.php';
?>

<div class="container">
    <!-- 7. Display heading -->
    <h2>Select Evaluation Form for <?php echo $patient_name; ?></h2>

    <?php
    // 8. Scan the patient_evaluation_form/ directory
    $form_directory = 'patient_evaluation_form/';
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
        <a href="select_patient_for_form.php" class="btn btn-secondary">Back to Patient Selection</a>
        <a href="dashboard.php" class="btn btn-info">Back to Dashboard</a>
    </div>
</div>

<?php
// 12. Include footer
include_once 'includes/footer.php';
?>
