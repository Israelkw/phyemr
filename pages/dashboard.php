<?php
// This session check MUST come before any HTML output, including the header.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"])) {
    // Set a message for the login page if desired
    $_SESSION['message'] = "You must be logged in to view the dashboard.";
    header("location: login.php");
    exit;
}

$page_title = "Dashboard";
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/header.php'; 
// The header will include navigation.php which displays $_SESSION['message']
?>
<div class="container">
    <?php
    $user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Guest';
    $user_first_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : '';
    ?>
    <h2>Welcome, <?php echo htmlspecialchars(ucfirst($user_role)) . " " . htmlspecialchars($user_first_name); ?>!</h2>

    <p>This is your dashboard. From here, you can quickly access features relevant to your role.</p>

    <div class="quick-links">
        <h3>Quick Actions:</h3>
        <ul>
            <?php if ($user_role === 'receptionist'): ?>
                <li><a href="add_patient.php" class="btn-dashboard-link">Register & Assign Patient</a></li> <!-- Stays as is, relative to current dir (pages/) -->
                <li><p>As a receptionist, your primary role is to register new patients and assign them to available clinicians. You can also manage patient appointments and basic records.</p></li>
            <?php elseif ($user_role === 'nurse'): ?>
                <li><a href="nurse_select_patient.php" class="btn-dashboard-link">Select Patient for Vitals/Info</a></li> <!-- Stays as is -->
                <li><p>As a nurse, you are responsible for taking patient vitals, recording general patient overview information, and assisting clinicians. Use the link above to select a patient and enter their data.</p></li>
            <?php elseif ($user_role === 'clinician'): ?>
                <li><a href="add_patient.php" class="btn-dashboard-link">Add Patient</a></li> <!-- Stays as is -->
                <li><a href="view_my_patients.php" class="btn-dashboard-link">My Assigned Patients</a></li> <!-- Stays as is -->
                <li><a href="select_patient_for_form.php" class="btn-dashboard-link">Select Patient for Clinical Form</a></li> <!-- Stays as is -->
                <li><a href="view_patient_history.php" class="btn-dashboard-link">View Patient History</a></li> <!-- Stays as is -->
                <li><p>As a clinician, you can manage your assigned patients, add new patients (assigning them to yourself), fill out clinical evaluation forms, and view comprehensive patient history.</p></li>
            <?php elseif ($user_role === 'admin'): ?>
                <li><a href="manage_clinicians.php" class="btn-dashboard-link">Manage Clinicians</a></li> <!-- Stays as is -->
                <?php // Add other admin links here if they exist ?>
                <li><p>As an administrator, you have oversight of the system, including managing user accounts (like clinicians) and ensuring the smooth operation of the application.</p></li>
            <?php else: ?>
                <li><p>Your role is not currently configured for specific quick actions. Please contact an administrator if you believe this is an error.</p></li>
            <?php endif; ?>
        </ul>
    </div>
</div>
<?php require_once $path_to_root . 'includes/footer.php'; ?>
