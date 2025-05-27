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
    header("location: dashboard.php"); // Stays as is, relative to current dir (pages/)
    exit;
}
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/header.php'; 
// header.php includes navigation.php, which handles displaying $_SESSION['message']
?>

    <div class="form-container">
        <h2 class="mb-4">Add New Patient</h2>
        <?php
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
                <select id="assigned_clinician_id" name="assigned_clinician_id" class="form-select" required>
                    <option value="">Select a Clinician</option>
                    <?php 
                    // Ensure clinician_list is available and is an array before looping
                    if (isset($_SESSION['clinician_list']) && is_array($_SESSION['clinician_list'])) {
                        foreach ($_SESSION['clinician_list'] as $clinician): 
                    ?>
                        <option value="<?php echo htmlspecialchars($clinician['id']); ?>">
                            <?php echo htmlspecialchars($clinician['first_name'] . ' ' . $clinician['last_name']); ?>
                        </option>
                    <?php 
                        endforeach; 
                    }
                    ?>
                </select>
            </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Add Patient</button>
        </form>
        <p class="mt-3"><a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a></p> <!-- Stays as is, relative to current dir (pages/) -->
    </div>

<?php require_once $path_to_root . 'includes/footer.php'; ?>
