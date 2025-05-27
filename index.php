<?php
$page_title = "Welcome";
$path_to_root = ""; // Root directory
// No session needed for index.php logic itself before header, header will start it.
require_once 'includes/header.php';
// The <main class="main-content container"> is already provided by header.php
?>
    <h1 class="mb-4">Welcome to the Patient Management System</h1>
    <p>This system allows authorized personnel to manage patient and clinician data.</p>
    
    <?php if (isset($_SESSION["user_id"])): ?>
        <p>You are logged in as <?php echo htmlspecialchars($_SESSION["first_name"]) . " " . htmlspecialchars($_SESSION["last_name"]); ?> (<?php echo htmlspecialchars($_SESSION["role"]); ?>).</p>
        <p><a href="pages/dashboard.php">Go to your Dashboard</a> or use the navigation menu.</p>
    <?php else: ?>
        <p>Please <a href="pages/login.php">login</a> to access the system features.</p>
    <?php endif; ?>
    
    <p>If you are looking for specific patient evaluation forms, they can be found under these (currently static) links:</p>
    <ul>
        <li><a href="patient_evaluation_form/cervical.html">Cervical Evaluation Form</a></li>
        <li><a href="patient_evaluation_form/lumbar.html">Lumbar Evaluation Form</a></li>
        <li><a href="patient_evaluation_form/thoracic.html">Thoracic Evaluation Form</a></li>
        <li><a href="patient_evaluation_form/neuro.html">Neurological Evaluation Form</a></li>
        <li><a href="patient_evaluation_form/pediatric_assesment.html">Pediatric Assessment Form</a></li>
        <li><a href="patient_evaluation_form/general_assesment_form.html">General Assessment Form</a></li>
    </ul>
     <p>General patient information forms (currently static):</p>
    <ul>
        <li><a href="patient_general_info/general-information.html">General Information Form</a></li>
        <li><a href="patient_general_info/basic_info.html">Basic Information Form</a></li>
        <li><a href="patient_general_info/demo.html">Demographics Form</a></li>
    </ul>

<?php require_once 'includes/footer.php'; ?>
