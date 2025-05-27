<?php
// session_start() should be called in header.php or before including this file.
// Ensure $path_to_root is defined in the calling script for correct link paths.
$base_path = isset($path_to_root) ? $path_to_root : '';
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<nav class="main-navigation">
    <ul>
        <li><a href="<?php echo $base_path; ?>index.php" <?php if ($current_page == 'index.php') echo 'class="active"'; ?>>Home</a></li>
        <?php if (isset($_SESSION["user_id"]) && isset($_SESSION["role"])): ?>
            <li><a href="<?php echo $base_path; ?>dashboard.php" <?php if ($current_page == 'dashboard.php') echo 'class="active"'; ?>>Dashboard</a></li>

            <?php // Role-specific navigation links ?>
            <?php if ($_SESSION["role"] === 'receptionist'): ?>
                <li><a href="<?php echo $base_path; ?>add_patient.php" <?php if ($current_page == 'add_patient.php') echo 'class="active"'; ?>>Register & Assign Patient</a></li>
            <?php elseif ($_SESSION["role"] === 'nurse'): ?>
                <li><a href="<?php echo $base_path; ?>nurse_select_patient.php" <?php if ($current_page == 'nurse_select_patient.php') echo 'class="active"'; ?>>Select Patient for Vitals/Info</a></li>
            <?php elseif ($_SESSION["role"] === 'clinician'): ?>
                <li><a href="<?php echo $base_path; ?>add_patient.php" <?php if ($current_page == 'add_patient.php') echo 'class="active"'; ?>>Add Patient</a></li>
                <li><a href="<?php echo $base_path; ?>view_my_patients.php" <?php if ($current_page == 'view_my_patients.php') echo 'class="active"'; ?>>My Assigned Patients</a></li>
                <li><a href="<?php echo $base_path; ?>select_patient_for_form.php" <?php if ($current_page == 'select_patient_for_form.php') echo 'class="active"'; ?>>Select Patient for Form</a></li>
                <li><a href="<?php echo $base_path; ?>view_patient_history.php" <?php if ($current_page == 'view_patient_history.php') echo 'class="active"'; ?>>View Patient History</a></li>
            <?php elseif ($_SESSION["role"] === 'admin'): ?>
                <li><a href="<?php echo $base_path; ?>manage_clinicians.php" <?php if ($current_page == 'manage_clinicians.php') echo 'class="active"'; ?>>Manage Clinicians</a></li>
                <?php // Add other admin links here if they exist ?>
            <?php endif; ?>

            <li><a href="<?php echo $base_path; ?>logout.php" <?php if ($current_page == 'logout.php') echo 'class="active"'; ?>>Logout (<?php echo htmlspecialchars(isset($_SESSION["username"]) ? $_SESSION["username"] : ''); ?>)</a></li>
        <?php else: ?>
            <li><a href="<?php echo $base_path; ?>login.php" <?php if ($current_page == 'login.php') echo 'class="active"'; ?>>Login</a></li>
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
