<?php
// session_start() should be called in header.php or before including this file.
// Ensure $path_to_root is defined in the calling script for correct link paths.
$base_path = isset($path_to_root) ? $path_to_root : '';
?>
<nav class="main-navigation">
    <ul>
        <li><a href="<?php echo $base_path; ?>index.php">Home</a></li>
        <?php if (isset($_SESSION["user_id"]) && isset($_SESSION["role"])): ?>
            <li><a href="<?php echo $base_path; ?>dashboard.php">Dashboard</a></li>

            <?php // Role-specific navigation links ?>
            <?php if ($_SESSION["role"] === 'receptionist'): ?>
                <li><a href="<?php echo $base_path; ?>add_patient.php">Register & Assign Patient</a></li>
            <?php elseif ($_SESSION["role"] === 'nurse'): ?>
                <li><a href="<?php echo $base_path; ?>nurse_select_patient.php">Select Patient for Vitals/Info</a></li>
            <?php elseif ($_SESSION["role"] === 'clinician'): ?>
                <li><a href="<?php echo $base_path; ?>add_patient.php">Add Patient</a></li>
                <li><a href="<?php echo $base_path; ?>view_my_patients.php">My Assigned Patients</a></li>
                <li><a href="<?php echo $base_path; ?>select_patient_for_form.php">Select Patient for Form</a></li>
                <li><a href="<?php echo $base_path; ?>view_patient_history.php">View Patient History</a></li>
            <?php elseif ($_SESSION["role"] === 'admin'): ?>
                <li><a href="<?php echo $base_path; ?>manage_clinicians.php">Manage Clinicians</a></li>
                <?php // Add other admin links here if they exist ?>
            <?php endif; ?>

            <li><a href="<?php echo $base_path; ?>logout.php">Logout (<?php echo htmlspecialchars(isset($_SESSION["username"]) ? $_SESSION["username"] : ''); ?>)</a></li>
        <?php else: ?>
            <li><a href="<?php echo $base_path; ?>login.php">Login</a></li>
        <?php endif; ?>
    </ul>
</nav>
<div class="session-message-area container"> <!-- Area for global messages if needed -->
    <?php
    if (isset($_SESSION['message'])) {
        echo '<p class="session-message global-message">' . htmlspecialchars($_SESSION['message']) . '</p>';
        unset($_SESSION['message']); 
    }
    ?>
</div>
