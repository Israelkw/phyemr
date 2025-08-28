<?php
$page_title = "Welcome";
$path_to_root = ""; // Root directory
// No session needed for index.php logic itself before header, header will start it.
require_once 'includes/header.php';
// The <main class="main-content container"> is already provided by header.php
?>
<h1 class="mb-4">Welcome to the EASE Physiotherapy Patient Management System</h1>
<p>This system allows authorized personnel to manage patient, clinician data and payment.</p>

<?php if (isset($_SESSION["user_id"])): ?>
<p>You are logged in as
    <?php echo htmlspecialchars($_SESSION["first_name"]) . " " . htmlspecialchars($_SESSION["last_name"]); ?>
    (<?php echo htmlspecialchars($_SESSION["role"]); ?>).</p>
<p><a href="pages/dashboard.php">Go to your Dashboard</a> or use the navigation menu.</p>
<?php else: ?>
<p>Please <a href="pages/login.php">login</a> to access the system features.</p>
<?php endif; ?>


<p>Patient-specific forms should be accessed through the patient selection and dashboard functionalities after logging
    in. Direct linking to forms from this page is not supported as they require patient context.</p>

<?php require_once 'includes/footer.php'; ?>