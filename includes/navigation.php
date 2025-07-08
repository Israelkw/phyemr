<?php
// session_start() should be called in header.php or before including this file.
// Ensure $path_to_root is defined in the calling script for correct link paths.
$base_path = isset($path_to_root) ? $path_to_root : '';
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <img class="navbar-brand" src=" <?php echo $base_path; ?>.//includes/Easelogoicon.png" alt="Ease logo"
            style="width: 7vw; height: 7vw;">
        <a class="navbar-brand" href="<?php echo $base_path; ?>index.php">EMR system</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown"
            aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNavDropdown">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link<?php if ($current_page == 'index.php') echo ' active'; ?>"
                        href="<?php echo $base_path; ?>index.php">Home</a>
                </li>
                <?php if (isset($_SESSION["user_id"]) && isset($_SESSION["role"])):
                ?>
                <li class="nav-item">
                    <a class="nav-link<?php if ($current_page == 'dashboard.php') echo ' active'; ?>"
                        href="<?php echo $base_path; ?>pages/dashboard.php">Dashboard</a>
                </li>

                <?php // Role-specific navigation links ?>
                <?php if ($_SESSION["role"] === 'receptionist'): ?>
                <li class="nav-item">
                    <a class="nav-link<?php if ($current_page == 'add_patient.php') echo ' active'; ?>"
                        href="<?php echo $base_path; ?>pages/add_patient.php">Register & Assign Patient</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php if ($current_page == 'receptionist_view_patient_billing.php') echo ' active'; ?>"
                        href="<?php echo $base_path; ?>pages/receptionist_view_patient_billing.php">Patient Billing</a>
                </li>
                <?php elseif ($_SESSION["role"] === 'nurse'): ?>
                <li class="nav-item">
                    <a class="nav-link<?php if ($current_page == 'nurse_select_patient.php') echo ' active'; ?>"
                        href="<?php echo $base_path; ?>pages/nurse_select_patient.php">Select Patient for
                        Vitals/Info</a>
                </li>
                <?php elseif ($_SESSION["role"] === 'clinician'): ?>
                <li class="nav-item">
                    <a class="nav-link<?php if ($current_page == 'add_patient.php') echo ' active'; ?>"
                        href="<?php echo $base_path; ?>pages/add_patient.php">Add Patient</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php if ($current_page == 'view_my_patients.php') echo ' active'; ?>"
                        href="<?php echo $base_path; ?>pages/view_my_patients.php">My Assigned Patients</a>
                </li>
                <!-- <li class="nav-item">
                    <a class="nav-link<?php if ($current_page == 'view_patient_history.php') echo ' active'; ?>"
                        href="<?php echo $base_path; ?>pages/view_patient_history.php">View Patient History</a>
                </li> -->
                <?php elseif ($_SESSION["role"] === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link<?php if ($current_page == 'manage_clinicians.php') echo ' active'; ?>"
                        href="<?php echo $base_path; ?>pages/manage_clinicians.php">Manage Users</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php if ($current_page == 'admin_manage_procedures.php') echo ' active'; ?>"
                        href="<?php echo $base_path; ?>pages/admin_manage_procedures.php">Manage Procedures</a>
                </li>
                <?php // Add other admin links here if they exist ?>
                <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <?php if (isset($_SESSION["user_id"]) && isset($_SESSION["role"])): ?>
                <li class="nav-item">
                    <a class="nav-link<?php if ($current_page == 'logout.php') echo ' active'; ?>"
                        href="<?php echo $base_path; ?>pages/logout.php">Logout
                        (<?php echo htmlspecialchars(isset($_SESSION["username"]) ? $_SESSION["username"] : ''); ?>)</a>
                </li>
                <?php else:
                ?>
                <li class="nav-item">
                    <a class="nav-link<?php if ($current_page == 'login.php') echo ' active'; ?>"
                        href="<?php echo $base_path; ?>pages/login.php">Login</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<div class="session-message-area container">
    <!-- Area for global messages if needed -->
    <?php
    if (isset($_SESSION['message'])) {
        echo '<p class="session-message global-message">' . htmlspecialchars($_SESSION['message']) . '</p>';
        unset($_SESSION['message']);
    }
    ?>
</div>
<?php ?>