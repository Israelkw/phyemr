<?php
// Session check must come before any HTML output.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION["user_id"])) {
    header("location: login.php");
    exit;
}

$page_title = "Dashboard";
$path_to_root = ""; // This file is in the root.
require_once 'includes/header.php'; 
// header.php includes navigation.php, which handles displaying $_SESSION['message']
?>

    <?php // The main content div class="container" is provided by header.php for the <main> element ?>
    <h2>Welcome to Your Dashboard, <?php echo htmlspecialchars($_SESSION["first_name"]); ?>!</h2>
    <p>Your Role: <?php echo htmlspecialchars(ucfirst($_SESSION["role"])); ?></p>

    <?php
    // Global messages are handled by navigation.php (via header.php).
    ?>

    <div class="dashboard-sections">
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <h3>Admin Section</h3>
            <p>Manage system settings, user accounts, and overall application health.</p>
            <ul>
                <li><a href="add_user.php">Add New User (General)</a></li>
                <li><a href="manage_users.php">Manage All Users</a></li>
                <li><a href="system_logs.php">View System Logs (Placeholder)</a></li>
                <li><a href="add_patient.php">Register & Assign Patient</a></li> {/* Admin can also add patients */}
            </ul>

        <?php elseif ($_SESSION['role'] === 'clinician'): ?>
            <h3>Clinician Section</h3>
            <p>Access patient records, manage appointments, and view your schedule.</p>
            <ul>
                <li><a href="view_my_patients.php">View My Assigned Patients</a></li>
                <li><a href="add_patient.php">Add New Patient</a></li>
                <li><a href="schedule.php">My Schedule (Placeholder)</a></li>
            </ul>

        <?php elseif ($_SESSION['role'] === 'receptionist'): ?>
            <h3>Receptionist Section</h3>
            <p>Welcome, essential part of our front-desk operations!</p>
            <ul>
                <li><a href="add_patient.php">Register & Assign Patient</a></li>
            </ul>
            {/* Receptionist-specific links can be added here later, e.g., manage appointments */}

        <?php elseif ($_SESSION['role'] === 'nurse'): ?>
            <h3>Nurse Section</h3>
            <p>View patient vital signs, update records, and assist clinicians.</p>
            <ul>
                <li><a href="view_assigned_patients_nurse.php">View Patients for Today (Placeholder)</a></li>
                <li><a href="record_vitals.php">Record Patient Vitals (Placeholder)</a></li>
            </ul>
            
        <?php else: ?>
            <p>Your role is not fully configured for specific actions. Please contact an administrator.</p>
        <?php endif; ?>
    </div>

    <p><a href="php/handle_logout.php" class="button-logout">Logout</a></p>

<?php require_once 'includes/footer.php'; ?>
