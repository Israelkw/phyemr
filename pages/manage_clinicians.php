<?php
$path_to_root = "../"; // Define $path_to_root for includes, ensure it's at the top
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();

// Check if the user is logged in and is an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'admin') {
    $_SESSION['message'] = "Unauthorized access. Only admins can manage clinicians.";
    header("location: dashboard.php"); 
    exit;
}

// $path_to_root is already defined above
require_once $path_to_root . 'includes/db_connect.php'; // Database connection

$users_from_db = [];
$db_error_message = '';

// Fetch users from the database
$sql_users = "SELECT id, username, first_name, last_name, role, is_active FROM users ORDER BY role, username";
$result_users = $mysqli->query($sql_users);

if ($result_users) {
    while ($row = $result_users->fetch_assoc()) {
        $users_from_db[] = $row;
    }
    $result_users->free();
} else {
    // Handle query error
    error_log("Error fetching users: " . $mysqli->error);
    $db_error_message = "Could not load user list. Please try again or contact support.";
}
// $mysqli->close(); // Connection might be used by footer or closed by PHP automatically

$page_title = "Manage Users"; // Changed title to reflect it manages all users
require_once $path_to_root . 'includes/header.php'; 
?>
    <style>
        /* Styles specific to this page's table */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .status-active { color: green; }
        .status-inactive { color: red; }
    </style>

    <?php // The main content div class="container" is provided by header.php for the <main> element ?>
    <h2>Manage Users</h2> 

    <?php if (!empty($db_error_message)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($db_error_message); ?></div>
    <?php endif; ?>

    <p><a href="add_clinician.php" class="btn btn-primary">Add New User</a> | <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a></p>

    <?php if (!empty($users_from_db)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users_from_db as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['last_name']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                            <td class="<?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </td>
                            <td>
                                No actions available yet
                                <?php /* Example for future actions:
                                <a href="edit_user.php?id=<?php echo htmlspecialchars($user['id']); ?>" class="btn btn-sm btn-warning">Edit</a>
                                <a href="delete_user.php?id=<?php echo htmlspecialchars($user['id']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?');">Delete</a>
                                */ ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif (empty($db_error_message)): // Only show "No users found" if there wasn't a DB error
        echo "<p class='alert alert-info'>No users found. <a href='add_clinician.php'>Add one now</a>.</p>";
    endif; ?>

<?php require_once $path_to_root . 'includes/footer.php'; ?>
