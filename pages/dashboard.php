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
// The <main class="main-content container"> is already provided by header.php
?>
    <?php
    $user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Guest';
    $user_first_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : '';
    ?>
    <h2 class="mb-4">Welcome, <?php echo htmlspecialchars(ucfirst($user_role)) . " " . htmlspecialchars($user_first_name); ?>!</h2>

    <p class="mb-4">This is your dashboard. From here, you can quickly access features relevant to your role.</p>

    <div class="row mt-4">
        <?php if ($user_role === 'receptionist'): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">Receptionist Actions</h5>
                        <p class="card-text flex-grow-1">As a receptionist, your primary role is to register new patients and assign them to available clinicians. You can also manage patient appointments and basic records.</p>
                        <a href="add_patient.php" class="btn btn-primary mt-auto align-self-start">Register & Assign Patient</a>
                    </div>
                </div>
            </div>
        <?php elseif ($user_role === 'nurse'): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">Nurse Actions</h5>
                        <p class="card-text flex-grow-1">As a nurse, you are responsible for taking patient vitals, recording general patient overview information, and assisting clinicians. Use the link above to select a patient and enter their data.</p>
                        <a href="nurse_select_patient.php" class="btn btn-primary mt-auto align-self-start">Select Patient for Vitals/Info</a>
                    </div>
                </div>
            </div>
        <?php elseif ($user_role === 'clinician'): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">Clinician Actions</h5>
                        <p class="card-text flex-grow-1">As a clinician, you can manage your assigned patients, add new patients (assigning them to yourself), fill out clinical evaluation forms, and view comprehensive patient history.</p>
                        <div class="mt-auto">
                            <a href="add_patient.php" class="btn btn-primary d-block mb-2">Add Patient</a>
                            <a href="view_my_patients.php" class="btn btn-info d-block mb-2">My Assigned Patients</a>
                            <a href="select_patient_for_form.php" class="btn btn-success d-block mb-2">Select Patient for Clinical Form</a>
                            <a href="view_patient_history.php" class="btn btn-secondary d-block">View Patient History</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($user_role === 'admin'): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">Admin Actions</h5>
                        <p class="card-text flex-grow-1">As an administrator, you have oversight of the system, including managing user accounts (like clinicians) and ensuring the smooth operation of the application.</p>
                        <a href="manage_clinicians.php" class="btn btn-danger mt-auto align-self-start">Manage Clinicians</a>
                        <?php // Add other admin links here if they exist ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-warning" role="alert">
                    Your role is not currently configured for specific quick actions. Please contact an administrator if you believe this is an error.
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php require_once $path_to_root . 'includes/footer.php'; ?>
