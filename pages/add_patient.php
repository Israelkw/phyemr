<?php
// Session management and authorization
require_once '../includes/SessionManager.php';
SessionManager::startSession();
SessionManager::ensureUserIsLoggedIn('login.php');
SessionManager::hasRole(['clinician', 'receptionist'], 'dashboard.php', "Unauthorized access. Only clinicians or receptionists can add patients.");

// Include ErrorHandler and register it
require_once '../includes/ErrorHandler.php';
ErrorHandler::register();

// Include Database and db_connect (for $pdo)
require_once '../includes/db_connect.php'; // Provides $pdo
require_once '../includes/Database.php';    // Provides Database class

$db = new Database($pdo); // Instantiate Database class

$page_title = "Add Patient";
$path_to_root = "../"; // Define $path_to_root for includes

$clinician_list_from_db = [];
$clinician_load_error = null;
$current_user_role = SessionManager::get("role");

if ($current_user_role === 'receptionist') {
    try {
        $sql_clinicians = "SELECT id, username, first_name, last_name FROM users WHERE role = 'clinician' AND is_active = 1 ORDER BY last_name, first_name";
        $stmt_clinicians = $db->prepare($sql_clinicians);
        $db->execute($stmt_clinicians);
        $clinician_list_from_db = $db->fetchAll($stmt_clinicians);
    } catch (PDOException $e) {
        // ErrorHandler::handleException($e); // This would typically redirect. For rendering form with error, set a message.
        error_log("Error fetching clinicians: " . $e->getMessage()); // Log the error
        $clinician_load_error = "Could not load clinician list due to a database error. Please try again or contact support.";
        // SessionManager::set('message', $clinician_load_error); // Optionally set as a general message
    }
}

// Generate CSRF token
$csrf_token = SessionManager::generateCsrfToken();

// Retrieve old form input (if any)
$old_input = SessionManager::get('form_old_input', []);
SessionManager::remove('form_old_input'); // Clear after use

// Retrieve and clear session messages
$page_message = SessionManager::get('message');
SessionManager::remove('message');
$page_error_message = SessionManager::get('error_message'); // ErrorHandler might set this
SessionManager::remove('error_message');


// Include header
require_once $path_to_root . 'includes/header.php';
?>

<div class="container mt-5">
    <h2><?php echo htmlspecialchars($page_title); ?></h2>

    <?php if ($page_message): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($page_message); ?></div>
    <?php endif; ?>
    <?php if ($page_error_message): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($page_error_message); ?></div>
    <?php endif; ?>
    <?php if ($clinician_load_error && $current_user_role === 'receptionist'): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($clinician_load_error); ?></div>
    <?php endif; ?>


    <form action="<?php echo $path_to_root; ?>php/handle_add_patient.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

        <div class="mb-3">
            <label for="first_name" class="form-label">First Name</label>
            <input type="text" class="form-control" id="first_name" name="first_name"
                value="<?php echo htmlspecialchars($old_input['first_name'] ?? ''); ?>" required>
        </div>

        <div class="mb-3">
            <label for="last_name" class="form-label">Last Name</label>
            <input type="text" class="form-control" id="last_name" name="last_name"
                value="<?php echo htmlspecialchars($old_input['last_name'] ?? ''); ?>" required>
        </div>

        <div class="mb-3">
            <label for="date_of_birth" class="form-label">Date of Birth</label>
            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                value="<?php echo htmlspecialchars($old_input['date_of_birth'] ?? ''); ?>" required>
        </div>

        <!-- Optional fields not explicitly required by form but good to have -->
        <div class="mb-3">
            <label for="sex" class="form-label">Sex</label>
            <select class="form-select" id="sex" name="sex">
                <option value="">Select Sex</option>
                <option value="Male" <?php echo (($old_input['sex'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male
                </option>
                <option value="Female" <?php echo (($old_input['sex'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female
                </option>

            </select>
        </div>

        <div class="mb-3">
            <label for="address" class="form-label">Address</label>
            <textarea class="form-control" id="address" name="address"
                rows="3"><?php echo htmlspecialchars($old_input['address'] ?? ''); ?></textarea>
        </div>

        <div class="mb-3">
            <label for="phone_number" class="form-label">Phone Number</label>
            <input type="tel" class="form-control" id="phone_number" name="phone_number"
                value="<?php echo htmlspecialchars($old_input['phone_number'] ?? ''); ?>">
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" class="form-control" id="email" name="email"
                value="<?php echo htmlspecialchars($old_input['email'] ?? ''); ?>">
        </div>

        <div class="mb-3">
            <label for="insurance_details" class="form-label">Insurance Details</label>
            <textarea class="form-control" id="insurance_details" name="insurance_details"
                rows="3"><?php echo htmlspecialchars($old_input['insurance_details'] ?? ''); ?></textarea>
        </div>

        <div class="mb-3">
            <label for="reason_for_visit" class="form-label">Reason for Visit</label>
            <textarea class="form-control" id="reason_for_visit" name="reason_for_visit"
                rows="3"><?php echo htmlspecialchars($old_input['reason_for_visit'] ?? ''); ?></textarea>
        </div>

        <?php if ($current_user_role === 'receptionist'): ?>
        <div class="mb-3">
            <label for="assigned_clinician_id" class="form-label">Assign to Clinician</label>
            <select class="form-select" id="assigned_clinician_id" name="assigned_clinician_id" required>
                <option value="">Select Clinician</option>
                <?php if (!empty($clinician_list_from_db)): ?>
                <?php foreach ($clinician_list_from_db as $clinician): ?>
                <option value="<?php echo htmlspecialchars($clinician['id']); ?>"
                    <?php echo (isset($old_input['assigned_clinician_id']) && $old_input['assigned_clinician_id'] == $clinician['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($clinician['first_name'] . ' ' . $clinician['last_name'] . ' (@' . $clinician['username'] . ')'); ?>
                </option>
                <?php endforeach; ?>
                <?php else: ?>
                <option value="" disabled>No clinicians available or error loading list.</option>
                <?php endif; ?>
            </select>
            <?php if ($clinician_load_error): ?>
            <div class="form-text text-danger"><?php echo htmlspecialchars($clinician_load_error); ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">Add Patient</button>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </form>
</div>

<?php
// Include footer
require_once $path_to_root . 'includes/footer.php';
?>