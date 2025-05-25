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
// Ensure clinician_list is available, otherwise redirect with an error or handle appropriately
if ($_SESSION['role'] === 'receptionist' && !isset($_SESSION['clinician_list'])) {
    // This might happen if the session was started before the clinician_list was added
    // Or if the logged-in user is a receptionist but the list wasn't populated for some reason.
    $_SESSION['message'] = "Clinician list not available. Please log out and log back in.";
    header("location: dashboard.php");
    exit;
}
$path_to_root = ""; 
require_once 'includes/header.php'; 
// header.php includes navigation.php, which handles displaying $_SESSION['message']
?>

    <div class="form-container">
        <h2>Add New Patient</h2>
        <?php
        // The specific session message display that was here has been removed.
        // Global messages are now handled by navigation.php (via header.php).
        ?>
        <form action="php/handle_add_patient.php" method="POST">
            <div>
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" required>
            </div>
            <div>
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" required>
            </div>
            <div>
                <label for="date_of_birth">Date of Birth:</label>
                <input type="date" id="date_of_birth" name="date_of_birth" required>
            </div>
            <?php if ($_SESSION["role"] === 'receptionist'): ?>
            <div>
                <label for="assigned_clinician_id">Assign to Clinician:</label>
                <select id="assigned_clinician_id" name="assigned_clinician_id" required>
                    <option value="">Select a Clinician</option>
                    <?php foreach ($_SESSION['clinician_list'] as $clinician): ?>
                        <option value="<?php echo htmlspecialchars($clinician['id']); ?>">
                            <?php echo htmlspecialchars($clinician['first_name'] . ' ' . $clinician['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <button type="submit">Add Patient</button>
            </div>
        </form>
        <p><a href="dashboard.php">Back to Dashboard</a></p>
    </div>

<?php require_once 'includes/footer.php'; ?>
