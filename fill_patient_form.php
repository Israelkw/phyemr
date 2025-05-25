<?php
session_start();

// 1. Check if user is logged in and is a clinician or nurse
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['clinician', 'nurse'])) {
    $_SESSION['message'] = "Unauthorized access. Please login as a clinician or nurse.";
    header("Location: dashboard.php");
    exit();
}

// Determine appropriate redirect paths based on role
$select_patient_page = ($_SESSION['role'] === 'nurse') ? 'nurse_select_patient.php' : 'select_patient_for_form.php';
$select_form_page = ($_SESSION['role'] === 'nurse') ? 'nurse_select_form.php' : 'select_form_for_patient.php';


// 2. Retrieve form_name and form_directory from URL
if (!isset($_GET['form_name']) || empty(trim($_GET['form_name']))) {
    $_SESSION['message'] = "No form selected.";
    header("Location: " . $select_form_page . (isset($_SESSION['selected_patient_id_for_form']) ? '?patient_id=' . $_SESSION['selected_patient_id_for_form'] : ''));
    exit();
}
$form_name_from_url = trim($_GET['form_name']);

if (!isset($_GET['form_directory']) || empty(trim($_GET['form_directory']))) {
    $_SESSION['message'] = "Form directory not specified.";
    header("Location: " . $select_form_page . (isset($_SESSION['selected_patient_id_for_form']) ? '?patient_id=' . $_SESSION['selected_patient_id_for_form'] : ''));
    exit();
}
$form_directory_from_url = trim($_GET['form_directory']);

// 3. Retrieve patient_id from session
if (!isset($_SESSION['selected_patient_id_for_form']) || empty($_SESSION['selected_patient_id_for_form'])) {
    $_SESSION['message'] = "Patient not selected or session expired. Please select a patient first.";
    header("Location: " . $select_patient_page);
    exit();
}
$patient_id_from_session = $_SESSION['selected_patient_id_for_form'];

// 4. Validate form_directory and form_name, and file existence
$allowed_directories = ['patient_evaluation_form', 'patient_general_info'];
if (!in_array($form_directory_from_url, $allowed_directories)) {
    $_SESSION['message'] = "Invalid form directory specified.";
    header("Location: " . $select_form_page . '?patient_id=' . htmlspecialchars($patient_id_from_session));
    exit();
}

$form_file_basename = basename($form_name_from_url); // Prevent directory traversal
$form_file_path = rtrim($form_directory_from_url, '/') . '/' . $form_file_basename;

if ($form_file_basename !== $form_name_from_url || !file_exists($form_file_path) || pathinfo($form_file_path, PATHINFO_EXTENSION) !== 'html') {
    $_SESSION['message'] = "Selected form '" . htmlspecialchars($form_name_from_url) . "' is invalid or not found in the specified directory.";
    header("Location: " . $select_form_page . '?patient_id=' . htmlspecialchars($patient_id_from_session));
    exit();
}

// 5. Fetch patient details
$patient_full_name = "Patient"; // Default
$patient_found = false;
if (isset($_SESSION['patients']) && is_array($_SESSION['patients'])) {
    foreach ($_SESSION['patients'] as $patient) {
        if (isset($patient['id']) && $patient['id'] == $patient_id_from_session) {
            $patient_full_name = htmlspecialchars($patient['first_name'] . " " . $patient['last_name']);
            $patient_found = true;
            break;
        }
    }
}

if (!$patient_found) {
    $_SESSION['message'] = "Patient details not found. Please re-select the patient.";
    header("Location: " . $select_patient_page);
    exit();
}

// 6. Set page title
$form_display_name = ucwords(str_replace(['_', '-'], ' ', pathinfo($form_file_basename, PATHINFO_FILENAME)));
$page_title = "Fill Form: " . htmlspecialchars($form_display_name) . " for " . $patient_full_name; // patient_full_name is already escaped

// 7. Include header
include_once 'includes/header.php';

// 8. Display heading
?>
<div class="container">
    <h2>
        <?php echo htmlspecialchars($form_display_name); ?>
        <small class="text-muted">for <?php echo $patient_full_name; // Already escaped ?></small>
    </h2>

    <?php
    // 9. Read the content of the selected HTML form file
    $form_content = file_get_contents($form_file_path);
    if ($form_content === false) {
        echo "<div class='alert alert-danger'>Error: Could not read the form content.</div>";
    } else {
        // 10. Modify the <form> tag
        // Using regex to be more flexible with attributes that might exist on the form tag
        $form_tag_pattern = '/<form(.*?)>/i'; // Case-insensitive search for <form ...>
        $replacement_form_tag = '<form$1 action="php/handle_submit_patient_form.php" method="POST">';
        
        $form_content_modified = preg_replace($form_tag_pattern, $replacement_form_tag, $form_content, 1, $count);

        if ($count > 0) { // Check if a form tag was actually found and replaced
            // 11. Add hidden input fields
            $hidden_fields = "\n" .
                '    <input type="hidden" name="patient_id" value="' . htmlspecialchars($patient_id_from_session) . '">' . "\n" .
                '    <input type="hidden" name="form_name" value="' . htmlspecialchars($form_file_basename) . '">' . "\n" .
                '    <input type="hidden" name="form_directory" value="' . htmlspecialchars($form_directory_from_url) . '">' . "\n";
            
            // Insert hidden fields immediately after the opening form tag
            // The $replacement_form_tag already contains the new attributes.
            // We need to insert hidden_fields after the *entire* opening tag.
            // A simple way is to find the first ">" after "<form" and insert after it.
            $form_open_tag_pos = stripos($form_content_modified, '<form');
            if ($form_open_tag_pos !== false) {
                $form_open_tag_end_pos = strpos($form_content_modified, '>', $form_open_tag_pos);
                if ($form_open_tag_end_pos !== false) {
                    $form_content_modified = substr_replace($form_content_modified, $hidden_fields, $form_open_tag_end_pos + 1, 0);
                } else {
                     // Fallback if form tag is malformed (e.g. no closing ">")
                     // This is less precise but better than nothing
                    $form_content_modified = preg_replace($form_tag_pattern, $replacement_form_tag . $hidden_fields, $form_content, 1);
                }
            } else {
                 //This case should ideally not be reached if $count > 0
                echo "<div class='alert alert-warning'>Could not properly inject hidden fields even though form tag was identified.</div>";
            }
        } else {
            echo "<div class='alert alert-warning'>Warning: No &lt;form&gt; tag was found in the selected file. Submission will not work as expected.</div>";
        }


        // 12. Display the (modified) content of the form
        echo $form_content_modified;
    }
    ?>

    <!-- 13. Navigation links -->
    <div class="mt-4 mb-4">
        <a href="<?php echo $select_form_page; ?>?patient_id=<?php echo htmlspecialchars($patient_id_from_session); ?>" class="btn btn-secondary">Back to Form Selection</a>
        <a href="dashboard.php" class="btn btn-info">Back to Dashboard</a>
    </div>
</div>

<?php
// 14. Include footer
include_once 'includes/footer.php';
?>
