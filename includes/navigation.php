<?php
// session_start() should be called in header.php or before including this file.
// Ensure $path_to_root is defined in the calling script for correct link paths.
$base_path = isset($path_to_root) ? $path_to_root : '';
?>
<nav class="main-navigation">
    <ul>
        <li><a href="<?php echo $base_path; ?>index.php">Home</a></li>
        <?php if (isset($_SESSION["user_id"])): ?>
            <li><a href="<?php echo $base_path; ?>dashboard.php">Dashboard</a></li>

            <?php // Role-specific navigation can be added here if not already on dashboard ?>
            <?php if ($_SESSION["role"] === 'admin'): ?>
                <!-- Example: <li><a href="<?php echo $base_path; ?>admin_specific_page.php">Admin Tools</a></li> -->
            <?php endif; ?>

            <?php if ($_SESSION["role"] === 'clinician'): ?>
                <li><a href="<?php echo $base_path; ?>select_patient_for_form.php">Assign Form to Patient</a></li>
                <li><a href="<?php echo $base_path; ?>view_patient_history.php">View Patient Form History</a></li>
            <?php endif; ?>

            <li><a href="<?php echo $base_path; ?>logout.php">Logout (<?php echo htmlspecialchars($_SESSION["username"]); ?>)</a></li>
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
