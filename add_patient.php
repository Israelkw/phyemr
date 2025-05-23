<?php
// Session check must come before any HTML output.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in and is a clinician
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'clinician') {
    $_SESSION['message'] = "Unauthorized access. Only clinicians can add patients.";
    header("location: dashboard.php"); 
    exit;
}

$page_title = "Add Patient";
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
            <div>
                <button type="submit">Add Patient</button>
            </div>
        </form>
        <p><a href="dashboard.php">Back to Dashboard</a></p>
    </div>

<?php require_once 'includes/footer.php'; ?>
