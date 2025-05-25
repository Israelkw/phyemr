<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Authorization: Check if user is logged in and is a nurse
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'nurse') {
    $_SESSION['message'] = "Unauthorized access. Only nurses can select forms.";
    header("location: dashboard.php");
    exit;
}

// Get and validate patient_id
if (!isset($_GET['patient_id']) || empty($_GET['patient_id'])) {
    $_SESSION['message'] = "Patient ID is missing.";
    header("location: nurse_select_patient.php");
    exit;
}
$patient_id = $_GET['patient_id'];

// Ensure patient exists (basic check using $_SESSION['patients'])
if (!isset($_SESSION['patients'][$patient_id])) {
    $_SESSION['message'] = "Invalid Patient ID selected.";
    header("location: nurse_select_patient.php");
    exit;
}

// Store patient_id in session for the form filling process
$_SESSION['selected_patient_id_for_form'] = $patient_id;
$patient = $_SESSION['patients'][$patient_id];
$patient_name = htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']);

$page_title = "Select Form for " . $patient_name;
$path_to_root = "";
require_once 'includes/header.php';

$forms_directory = 'patient_general_info/';
$available_forms = [];
if (is_dir($forms_directory)) {
    if ($handle = opendir($forms_directory)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != ".." && pathinfo($entry, PATHINFO_EXTENSION) == 'html') {
                $available_forms[] = $entry;
            }
        }
        closedir($handle);
    }
}

?>

<div class="container">
    <h2>Select Form for Patient: <?php echo $patient_name; ?> (ID: <?php echo htmlspecialchars($patient_id); ?>)</h2>
    
    <?php
    // Message display is handled by header.php
    ?>

    <?php if (!empty($available_forms)): ?>
        <ul class="form-list">
            <?php foreach ($available_forms as $form_file): ?>
                <?php 
                    // Create a display name from the filename
                    $display_name = ucwords(str_replace(['_', '.html'], [' ', ''], $form_file));
                ?>
                <li>
                    <a href="fill_patient_form.php?form_name=<?php echo htmlspecialchars($form_file); ?>&form_directory=<?php echo htmlspecialchars(rtrim($forms_directory, '/')); ?>" class="btn-form-select">
                        <?php echo htmlspecialchars($display_name); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No general information forms found in the '<?php echo htmlspecialchars($forms_directory); ?>' directory.</p>
        <p>Please ensure forms like 'vital_signs.html' or 'general_patient_overview.html' are present.</p>
    <?php endif; ?>

    <p><a href="nurse_select_patient.php">Back to Patient Selection</a></p>
    <p><a href="dashboard.php">Back to Dashboard</a></p>
</div>

<?php require_once 'includes/footer.php'; ?>
