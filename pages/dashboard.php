<?php
$path_to_root = "../"; // Define $path_to_root for includes, moved up for SessionManager
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"])) {
    // Set a message for the login page if desired
    $_SESSION['message'] = "You must be logged in to view the dashboard.";
    header("location: login.php");
    exit;
}

$page_title = "Dashboard";
// $path_to_root is already defined above
require_once $path_to_root . 'includes/header.php'; 
// The header will include navigation.php which displays $_SESSION['message']
// The <main class="main-content container"> is already provided by header.php
?>
<?php
    $user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Guest';
    $user_first_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : '';
    $user_id = $_SESSION['user_id'];

    // Initialize variables for stats
    $active_patient_count = 0; // For clinician
    $stat_total_patients = 0;
    $stat_total_payments_value = 0;
    $stat_total_users = 0;
    $stat_total_invoices = 0;

    // Database connection is needed for any role that fetches data
    require_once $path_to_root . 'includes/db_connect.php'; // Provides $pdo
    require_once $path_to_root . 'includes/Database.php';    // Provides Database class
    $db = new Database($pdo);

    if ($user_role === 'clinician') {
        try {
            $sql_count = "SELECT COUNT(*) as count FROM patients WHERE assigned_clinician_id = :clinician_id";
            $stmt_count = $db->prepare($sql_count);
            $db->execute($stmt_count, [':clinician_id' => $user_id]);
            $result = $db->fetch($stmt_count);
            if ($result) {
                $active_patient_count = $result['count'];
            }
        } catch (PDOException $e) {
            error_log("Error fetching clinician's active patient count: " . $e->getMessage());
        }
    } elseif ($user_role === 'admin') {
        try {
            // a. Total Registered Patients
            $stmt_total_patients = $db->prepare("SELECT COUNT(*) AS total_patients FROM patients");
            $db->execute($stmt_total_patients);
            $stat_total_patients = ($db->fetch($stmt_total_patients)['total_patients']) ?? 0;

            // b. Total Payments Received (sum from payments table)
            $stmt_total_payments = $db->prepare("SELECT SUM(amount_paid) AS total_revenue FROM payments");
            $db->execute($stmt_total_payments);
            $stat_total_payments_value = ($db->fetch($stmt_total_payments)['total_revenue']) ?? 0;

            // c. Total Number of Users
            $stmt_total_users = $db->prepare("SELECT COUNT(*) AS total_users FROM users");
            $db->execute($stmt_total_users);
            $stat_total_users = ($db->fetch($stmt_total_users)['total_users']) ?? 0;

            // d. Total Invoices Generated
            $stmt_total_invoices = $db->prepare("SELECT COUNT(*) AS total_invoices FROM invoices");
            $db->execute($stmt_total_invoices);
            $stat_total_invoices = ($db->fetch($stmt_total_invoices)['total_invoices']) ?? 0;

        } catch (PDOException $e) {
            error_log("Error fetching admin dashboard statistics: " . $e->getMessage());
            // Optionally set a general error message for admin stats area
            SessionManager::set('dashboard_admin_error', 'Could not load all statistics.');
        }
    }
    ?>
<h2 class="mb-4">Welcome,
    <?php echo htmlspecialchars(ucfirst($user_role)) . " " . htmlspecialchars($user_first_name); ?>!</h2>

<p class="mb-4">This is your dashboard. From here, you can quickly access features relevant to your role.</p>

<div class="row mt-4">
    <?php if ($user_role === 'receptionist'): ?>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <h5 class="card-title">Receptionist Actions</h5>
                <p class="card-text flex-grow-1">As a receptionist, your primary role is to register new patients and
                    assign them to available clinicians. You can also manage patient appointments and basic records.</p>
                <div class="mt-auto align-self-start">
                    <a href="add_patient.php" class="btn btn-primary d-block mb-2">Register New Patient</a>
                    <a href="assign_existing_patient.php" class="btn btn-info d-block">Assign Existing Patient</a>
                </div>
            </div>
        </div>
    </div>
    <?php elseif ($user_role === 'nurse'): ?>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <h5 class="card-title">Nurse Actions</h5>
                <p class="card-text flex-grow-1">As a nurse, you are responsible for taking patient vitals, recording
                    general patient overview information, and assisting clinicians. Use the link above to select a
                    patient and enter their data.</p>
                <a href="nurse_select_patient.php" class="btn btn-primary mt-auto align-self-start">Select Patient for
                    Vitals/Info</a>
            </div>
        </div>
    </div>
    <?php elseif ($user_role === 'clinician'): ?>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <h5 class="card-title">Clinician Actions</h5>
                <p class="lead">You currently have <strong><?php echo $active_patient_count; ?></strong> active
                    patient(s) assigned.</p>
                <p class="card-text flex-grow-1">As a clinician, you can manage your assigned patients, add new patients
                    (assigning them to yourself), fill out clinical evaluation forms, and view comprehensive patient
                    history.</p>
                <div class="mt-auto">
                    <a href="add_patient.php" class="btn btn-primary d-block mb-2">Add Patient</a>
                    <a href="view_my_patients.php" class="btn btn-info d-block mb-2">My Assigned Patients
                        (<?php echo $active_patient_count; ?>)</a>
                    <!-- <a href="select_patient_for_form.php" class="btn btn-success d-block mb-2">Select Patient for Clinical Form</a>
                            <a href="view_patient_history.php" class="btn btn-secondary d-block">View Patient History</a> -->
                </div>
            </div>
        </div>
    </div>
    <?php elseif ($user_role === 'admin'): ?>
    <div class="col-lg-12"> <!-- Changed to full width for admin -->
        <div class="row">
            <!-- Statistics Section -->
            <div class="col-12 mb-4">
                <h4>System Statistics</h4>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-primary h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $stat_total_patients; ?></h5>
                                <p class="card-text">Total Registered Patients</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-success h-100">
                            <div class="card-body">
                                <h5 class="card-title">$<?php echo number_format($stat_total_payments_value, 2); ?></h5>
                                <p class="card-text">Total Payments Received</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-info h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $stat_total_users; ?></h5>
                                <p class="card-text">Total System Users</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-warning h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $stat_total_invoices; ?></h5>
                                <p class="card-text">Total Invoices Generated</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions/Links Section -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Admin Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">Quickly access key management areas of the system.</p>
                        <div class="d-grid gap-2 d-md-block">
                            <a href="<?php echo $path_to_root; ?>pages/manage_clinicians.php" class="btn btn-primary me-md-2 mb-2"><i class="fas fa-users-cog"></i> Manage Users</a>
                            <a href="<?php echo $path_to_root; ?>pages/admin_manage_procedures.php" class="btn btn-info me-md-2 mb-2"><i class="fas fa-cogs"></i> Manage Procedures</a>
                            <a href="<?php echo $path_to_root; ?>pages/admin_reports.php" class="btn btn-secondary me-md-2 mb-2"><i class="fas fa-chart-line"></i> View Reports</a>
                            <a href="<?php echo $path_to_root; ?>pages/generate_invoice.php" class="btn btn-success mb-2"><i class="fas fa-file-invoice-dollar"></i> Generate Invoice</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="col-12">
        <div class="alert alert-warning" role="alert">
            Your role is not currently configured for specific quick actions. Please contact an administrator if you
            believe this is an error.
        </div>
    </div>
    <?php endif; ?>
</div>
<?php
require_once $path_to_root . 'includes/footer.php';
?>