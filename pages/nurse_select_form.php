<?php
$path_to_root = "../"; // Define $path_to_root for includes
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Authorization: Check if user is logged in and is a nurse
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'nurse') {
    $_SESSION['message'] = "Unauthorized access. Only nurses can select forms.";
    header("location: dashboard.php"); // Sibling page
    exit;
}

// Get and validate patient_id
if (!isset($_GET['patient_id']) || empty($_GET['patient_id'])) {
    $_SESSION['message'] = "Patient ID is missing.";
    header("location: nurse_select_patient.php"); // Sibling page
    exit;
}
$patient_id = $_GET['patient_id'];

// DB connection
require_once $path_to_root . 'includes/db_connect.php'; // $mysqli connection object

// Fetch patient details from database
$patient_name = "Selected Patient"; // Default name

$stmt = $mysqli->prepare("SELECT first_name, last_name FROM patients WHERE id = ?");
if ($stmt === false) {
    error_log("Error preparing statement to fetch patient name (nurse_select_form): " . $mysqli->error);
    $_SESSION['message'] = "An error occurred while fetching patient details.";
    header("location: nurse_select_patient.php");
    exit();
}

$stmt->bind_param("i", $patient_id); // Assuming patient_id is an integer

if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($patient_db = $result->fetch_assoc()) {
        $patient_name = htmlspecialchars($patient_db['first_name'] . " " . $patient_db['last_name']);
        // Store patient_id in session for the form filling process ONLY if patient is found
        $_SESSION['selected_patient_id_for_form'] = $patient_id;
    } else {
        // Patient not found in database
        $_SESSION['message'] = "Selected patient not found in the database (ID: " . htmlspecialchars($patient_id) . ").";
        $stmt->close();
        header("location: nurse_select_patient.php");
        exit();
    }
    $stmt->close();
} else {
    error_log("Error executing statement to fetch patient name (nurse_select_form): " . $stmt->error);
    $_SESSION['message'] = "An error occurred while retrieving patient information.";
    $stmt->close();
    header("location: nurse_select_patient.php");
    exit();
}
// $mysqli->close(); // Connection closed at end of script

$page_title = "Select Form for " . $patient_name;
require_once $path_to_root . 'includes/header.php';

$forms_directory = $path_to_root . 'patient_general_info/'; // Adjusted path
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

    <p><a href="nurse_select_patient.php">Back to Patient Selection</a></p> <!-- Sibling page -->
    <p><a href="dashboard.php">Back to Dashboard</a></p> <!-- Sibling page -->
</div>

<?php require_once $path_to_root . 'includes/footer.php'; ?>
