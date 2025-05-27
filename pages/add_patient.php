<?php
// Session check must come before any HTML output.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in and is a clinician or receptionist
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"], ['clinician', 'receptionist'])) {
    $_SESSION['message'] = "Unauthorized access. Only clinicians or receptionists can add patients.";
    header("location: dashboard.php"); 
    exit;
}

$page_title = "Add Patient";
$path_to_root = "../"; // Define $path_to_root for includes

// Include database connection
require_once $path_to_root . 'includes/db_connect.php';

$clinician_list_from_db = [];
$clinician_load_error = null;

if ($_SESSION["role"] === 'receptionist') {
    // Fetch active clinicians from the database
    $sql_clinicians = "SELECT id, first_name, last_name FROM users WHERE role = 'clinician' AND is_active = 1 ORDER BY last_name, first_name";
    $result_clinicians = $mysqli->query($sql_clinicians);

    if ($result_clinicians) {
        while ($row = $result_clinicians->fetch_assoc()) {
            $clinician_list_from_db[] = $row;
        }
        $result_clinicians->free();
    } else {
        // Handle query error
        error_log("Error fetching clinicians: " . $mysqli->error);
        $clinician_load_error = "Could not load clinician list. Please try again or contact support.";
        // Optionally, set a session message if preferred, but local variable is fine for display here.
        // $_SESSION['message'] = $clinician_load_error;
    }
}
// No need to close $mysqli here if header.php or footer.php might use it or close it globally.
// Assuming connection is managed per script or closed by footer. For now, let it be open for header.

require_once $path_to_root . 'includes/header.php'; 
// header.php includes navigation.php, which handles displaying $_SESSION['message']
?>

    <div class="form-container">
        <h2 class="mb-4">Add New Patient</h2>
        <?php
        // Display clinician load error if any, before the form
        if ($clinician_load_error):
        ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($clinician_load_error); ?>
            </div>
        <?php
        endif;

        // The specific session message display that was here has been removed.
        // Global messages are now handled by navigation.php (via header.php).
        ?>
        <form action="../php/handle_add_patient.php" method="POST">
            <div class="mb-3">
                <label for="first_name" class="form-label">First Name:</label>
                <input type="text" id="first_name" name="first_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="last_name" class="form-label">Last Name:</label>
                <input type="text" id="last_name" name="last_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="date_of_birth" class="form-label">Date of Birth:</label>
                <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" required>
            </div>
            <?php if ($_SESSION["role"] === 'receptionist'): ?>
            <div class="mb-3">
                <label for="assigned_clinician_id" class="form-label">Assign to Clinician:</label>
                <select id="assigned_clinician_id" name="assigned_clinician_id" class="form-select" required <?php if ($clinician_load_error) echo 'disabled'; ?>>
                    <option value="">Select a Clinician</option>
                    <?php 
                    if (!empty($clinician_list_from_db)) {
                        foreach ($clinician_list_from_db as $clinician): 
                    ?>
                        <option value="<?php echo htmlspecialchars($clinician['id']); ?>">
                            <?php echo htmlspecialchars($clinician['first_name'] . ' ' . $clinician['last_name']); ?>
                        </option>
                    <?php 
                        endforeach; 
                    } elseif (!$clinician_load_error) { // No error, but list is empty
                        echo '<option value="" disabled>No clinicians available</option>';
                    }
                    ?>
                </select>
            </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary" <?php if ($clinician_load_error && $_SESSION["role"] === 'receptionist') echo 'disabled'; ?>>Add Patient</button>
        </form>
        <p class="mt-3"><a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a></p> <!-- Stays as is, relative to current dir (pages/) -->
    </div>

<?php require_once $path_to_root . 'includes/footer.php'; ?>
