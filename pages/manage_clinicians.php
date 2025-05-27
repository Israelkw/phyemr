<?php
// Session check must come before any HTML output.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in and is an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'admin') {
    $_SESSION['message'] = "Unauthorized access. Only admins can manage clinicians.";
    header("location: dashboard.php"); 
    exit;
}

// Fetch clinicians from session (simulated data)
$clinicians = isset($_SESSION['clinicians']) ? $_SESSION['clinicians'] : [];

$page_title = "Manage Clinicians";
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/header.php'; 
// header.php includes navigation.php, which handles displaying $_SESSION['message']
?>
    <style>
        /* Styles specific to this page's table */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>

    <?php // The main content div class="container" is provided by header.php for the <main> element ?>
    <h2>Manage Clinicians</h2>

    <?php
    // The specific session message display that was here has been removed.
    // Global messages are now handled by navigation.php (via header.php).
    ?>

    <p><a href="add_clinician.php">Add New Clinician</a> | <a href="dashboard.php">Back to Dashboard</a></p> <!-- Links are to sibling pages -->

    <?php if (!empty($clinicians)): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clinicians as $clinician_id => $clinician): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($clinician['id']); ?></td>
                        <td><?php echo htmlspecialchars($clinician['username']); ?></td>
                        <td><?php echo htmlspecialchars($clinician['first_name']); ?></td>
                        <td><?php echo htmlspecialchars($clinician['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($clinician['role']); ?></td>
                        <td>
                            No actions available yet
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No clinicians found. <a href="add_clinician.php">Add one now</a>.</p> <!-- Link is to a sibling page -->
    <?php endif; ?>

<?php require_once $path_to_root . 'includes/footer.php'; ?>
