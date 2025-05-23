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
$path_to_root = ""; 
require_once 'includes/header.php'; 
// The header will include navigation.php which displays $_SESSION['message']
?>

    <h2>Welcome to the Dashboard, <?php echo htmlspecialchars($_SESSION["first_name"]) . " " . htmlspecialchars($_SESSION["last_name"]); ?>!</h2>
    <p>Your Role: <?php echo htmlspecialchars($_SESSION["role"]); ?></p>

    <?php if (isset($_SESSION['role'])): ?>
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <h3>Admin Section</h3>
            <ul>
                <li><a href="add_clinician.php">Add Clinician</a></li>
                <li><a href="manage_clinicians.php">Manage Clinicians</a></li>
            </ul>
        <?php elseif ($_SESSION['role'] === 'clinician'): ?>
            <h3>Clinician Section</h3>
            <ul>
                <li><a href="add_patient.php">Add Patient</a></li>
                <li><a href="view_my_patients.php">View My Patients</a></li>
            </ul>
        <?php elseif ($_SESSION['role'] === 'nurse'): ?>
            <h3>Nurse Section</h3>
            <p>Welcome, valued member of our nursing team!</p>
            <!-- Nurse-specific links can be added here later -->
        <?php elseif ($_SESSION['role'] === 'receptionist'): ?>
            <h3>Receptionist Section</h3>
            <p>Welcome, essential part of our front-desk operations!</p>
            <!-- Receptionist-specific links can be added here later -->
        <?php else: ?>
            <p>Your role (<?php echo htmlspecialchars($_SESSION['role']); ?>) is not currently configured for specific dashboard actions. Please contact an administrator.</p>
        <?php endif; ?>
    <?php else: ?>
        <?php // This case should ideally not be reached due to the check at the top of the script ?>
        <p>Error: User role not set. Please try logging in again.</p>
    <?php endif; ?>

    <?php // The main navigation in header.php now includes the Logout link. ?>
    <?php // So, the <nav> block that was here previously has been removed. ?>

<?php require_once 'includes/footer.php'; ?>
