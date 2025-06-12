<?php
error_log("DEBUG: Navigation - Execution Start");
// session_start() should be called in header.php or before including this file.
// Ensure $path_to_root is defined in the calling script for correct link paths.
$base_path = isset($path_to_root) ? $path_to_root : '';
error_log("DEBUG: Navigation - Base path: '" . $base_path . "' (derived from path_to_root: '" . (isset($path_to_root) ? $path_to_root : 'Not Set') . "')");
$current_page = basename($_SERVER['SCRIPT_NAME']);
error_log("DEBUG: Navigation - Current page: '" . $current_page . "'");
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo $base_path; ?>index.php">Patient System</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNavDropdown">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link<?php if ($current_page == 'index.php') echo ' active'; ?>" href="<?php echo $base_path; ?>index.php">Home</a>
                </li>
                <?php if (isset($_SESSION["user_id"]) && isset($_SESSION["role"])):
                    error_log("DEBUG: Navigation - User is logged in. User ID: " . ($_SESSION["user_id"] ?? 'N/A') . ", Role: " . ($_SESSION["role"] ?? 'N/A'));
                ?>
                    <li class="nav-item">
                        <a class="nav-link<?php if ($current_page == 'dashboard.php') echo ' active'; ?>" href="<?php echo $base_path; ?>pages/dashboard.php">Dashboard</a>
                    </li>

                    <?php // Role-specific navigation links ?>
                    <?php if ($_SESSION["role"] === 'receptionist'): ?>
                        <li class="nav-item">
                            <a class="nav-link<?php if ($current_page == 'add_patient.php') echo ' active'; ?>" href="<?php echo $base_path; ?>pages/add_patient.php">Register & Assign Patient</a>
                        </li>
                    <?php elseif ($_SESSION["role"] === 'nurse'): ?>
                        <li class="nav-item">
                            <a class="nav-link<?php if ($current_page == 'nurse_select_patient.php') echo ' active'; ?>" href="<?php echo $base_path; ?>pages/nurse_select_patient.php">Select Patient for Vitals/Info</a>
                        </li>
                    <?php elseif ($_SESSION["role"] === 'clinician'): ?>
                        <li class="nav-item">
                            <a class="nav-link<?php if ($current_page == 'add_patient.php') echo ' active'; ?>" href="<?php echo $base_path; ?>pages/add_patient.php">Add Patient</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?php if ($current_page == 'view_my_patients.php') echo ' active'; ?>" href="<?php echo $base_path; ?>pages/view_my_patients.php">My Assigned Patients</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?php if ($current_page == 'view_patient_history.php') echo ' active'; ?>" href="<?php echo $base_path; ?>pages/view_patient_history.php">View Patient History</a>
                        </li>
                    <?php elseif ($_SESSION["role"] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link<?php if ($current_page == 'manage_clinicians.php') echo ' active'; ?>" href="<?php echo $base_path; ?>pages/manage_clinicians.php">Manage Clinicians</a>
                        </li>
                        <?php // Add other admin links here if they exist ?>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <?php if (isset($_SESSION["user_id"]) && isset($_SESSION["role"])): ?>
                    <li class="nav-item">
                        <a class="nav-link<?php if ($current_page == 'logout.php') echo ' active'; ?>" href="<?php echo $base_path; ?>pages/logout.php">Logout (<?php echo htmlspecialchars(isset($_SESSION["username"]) ? $_SESSION["username"] : ''); ?>)</a>
                    </li>
                <?php else:
                    error_log("DEBUG: Navigation - User is not logged in (showing Login link).");
                ?>
                    <li class="nav-item">
                        <a class="nav-link<?php if ($current_page == 'login.php') echo ' active'; ?>" href="<?php echo $base_path; ?>pages/login.php">Login</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<div class="session-message-area container"> <!-- Area for global messages if needed -->
    <?php
    if (isset($_SESSION['message'])) {
        error_log("DEBUG: Navigation - Displaying session message: '" . ($_SESSION['message'] ?? 'No message set') . "'");
        echo '<p class="session-message global-message">' . htmlspecialchars($_SESSION['message']) . '</p>';
        unset($_SESSION['message']);
    }
    ?>
</div>
<?php error_log("DEBUG: Navigation - Execution End"); ?>
