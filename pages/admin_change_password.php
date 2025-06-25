<?php
$path_to_root = "../"; // Relative path to the root directory
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();
require_once $path_to_root . 'includes/db_connect.php'; // Provides $pdo
require_once $path_to_root . 'includes/Database.php';    // Provides Database class

$db = new Database($pdo);
$page_title = "Admin: Change User Password";

// 1. Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    SessionManager::set('message', 'Unauthorized access. Only admins can change user passwords.');
    header("Location: " . $path_to_root . "pages/login.php");
    exit();
}

// 2. Get user_id from GET parameter
$user_id_to_change = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

if (!$user_id_to_change) {
    SessionManager::set('message', 'Invalid or missing user ID.');
    header("Location: manage_clinicians.php"); // Assuming it's in the same 'pages' directory
    exit();
}

// 3. Fetch user details to display username (and ensure user exists)
$user_info = null;
try {
    $sql_user = "SELECT id, username, first_name, last_name FROM users WHERE id = :user_id";
    $stmt_user = $db->prepare($sql_user);
    $db->execute($stmt_user, [':user_id' => $user_id_to_change]);
    $user_info = $db->fetch($stmt_user);

    if (!$user_info) {
        SessionManager::set('message', "User with ID {$user_id_to_change} not found.");
        header("Location: manage_clinicians.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching user for password change: " . $e->getMessage());
    SessionManager::set('message', 'A database error occurred while fetching user details.');
    header("Location: manage_clinicians.php");
    exit();
}

// Include header
require_once $path_to_root . 'includes/header.php';
?>

<div class="container mt-4">
    <h2><?php echo htmlspecialchars($page_title); ?></h2>

    <?php
    // Display messages if any (e.g., from previous attempts on this page if not redirected)
    if (SessionManager::has('message')) {
        echo '<div class="alert alert-info">' . htmlspecialchars(SessionManager::get('message')) . '</div>';
        SessionManager::remove('message'); // Clear message after displaying
    }
    if (SessionManager::has('error_message')) {
        echo '<div class="alert alert-danger">' . htmlspecialchars(SessionManager::get('error_message')) . '</div>';
        SessionManager::remove('error_message');
    }
    ?>

    <div class="card">
        <div class="card-header">
            Change Password for User: <strong><?php echo htmlspecialchars($user_info['username']); ?> (<?php echo htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']); ?>)</strong>
        </div>
        <div class="card-body">
            <form action="<?php echo $path_to_root; ?>php/handle_admin_change_password.php" method="POST">
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_info['id']); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionManager::generateCsrfToken()); ?>">

                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit" class="btn btn-primary">Change Password</button>
                <a href="manage_clinicians.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
require_once $path_to_root . 'includes/footer.php';
?>
