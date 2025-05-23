<?php
// Session check must come before any HTML output.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in and is an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'admin') {
    $_SESSION['message'] = "Unauthorized access. Only admins can add clinicians.";
    // Redirect to login page or an unauthorized page. Dashboard is better if they were logged in but not admin.
    // If session might not exist at all, login.php is safer.
    header("location: dashboard.php"); 
    exit;
}

$page_title = "Add Clinician";
$path_to_root = ""; 
require_once 'includes/header.php'; 
// header.php includes navigation.php, which handles displaying $_SESSION['message']
?>

    <div class="form-container">
        <h2>Add New Clinician</h2>
        <?php
        // The specific session message display that was here has been removed.
        // Global messages are now handled by navigation.php (via header.php).
        ?>
        <form action="php/handle_add_clinician.php" method="POST">
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" required>
            </div>
            <div>
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" required>
            </div>
            <div>
                <button type="submit">Add Clinician</button>
            </div>
        </form>
        <p><a href="dashboard.php">Back to Dashboard</a></p>
    </div>

<?php require_once 'includes/footer.php'; ?>
