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
require_once $path_to_root . 'includes/db_connect.php'; // Provides $pdo
require_once $path_to_root . 'includes/Database.php';    // Provides Database class

$db = new Database($pdo); // Instantiate Database class

$users_from_db = []; // Ensure it's initialized
$db_error_message = ''; // Ensure it's initialized

try {
    $sql_users = "SELECT id, username, first_name, last_name, role, is_active FROM users ORDER BY role, username";
    $stmt_users = $db->prepare($sql_users);
    $db->execute($stmt_users);
    $users_from_db = $db->fetchAll($stmt_users);
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $db_error_message = "Could not load user list due to a database error. Please try again or contact support.";
    // Optionally, call ErrorHandler::handleException($e) if general error page redirection is preferred.
    // For this page, displaying the error message on the page itself is acceptable.
}

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
                                <form action="<?php echo $path_to_root; ?>php/handle_user_status.php" method="POST" style="display: inline-block; margin-right: 5px;">
                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo SessionManager::generateCsrfToken(); ?>">
                                    <?php if ($user['is_active']): ?>
                                        <input type="hidden" name="action" value="deactivate">
                                        <button type="submit" class="btn btn-sm btn-warning" <?php echo ($user['id'] == $_SESSION['user_id']) ? 'disabled title="Cannot deactivate yourself"' : ''; ?>>Deactivate</button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="activate">
                                        <button type="submit" class="btn btn-sm btn-success">Activate</button>
                                    <?php endif; ?>
                                </form>
                                <a href="admin_change_password.php?user_id=<?php echo htmlspecialchars($user['id']); ?>" class="btn btn-sm btn-info">Change Password</a>
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
