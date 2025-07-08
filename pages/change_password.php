<?php
$path_to_root = "../";
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();

// Ensure user is logged in
if (!SessionManager::isLoggedIn()) {
    SessionManager::set('message', 'Please login to change your password.');
    header("Location: " . $path_to_root . "pages/login.php");
    exit;
}

require_once $path_to_root . 'includes/db_connect.php';
require_once $path_to_root . 'includes/Database.php';
$db = new Database($pdo); // Will be used for processing the form

$page_title = "Change Password";

// Form Processing Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionManager::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        SessionManager::set('message', 'Invalid or missing CSRF token.');
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';
    $user_id = SessionManager::get('user_id');

    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
        SessionManager::set('message', 'All password fields are required.');
    } elseif ($new_password !== $confirm_new_password) {
        SessionManager::set('message', 'New passwords do not match.');
    } elseif (strlen($new_password) < 8) { // Basic complexity: min length
        SessionManager::set('message', 'New password must be at least 8 characters long.');
    } else {
        // Verify current password
        $stmt_user = $db->prepare("SELECT password_hash FROM users WHERE id = :user_id");
        $db->execute($stmt_user, [':user_id' => $user_id]);
        $user = $db->fetch($stmt_user);

        if ($user && password_verify($current_password, $user['password_hash'])) {
            // Current password is correct, proceed to update
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

            $stmt_update_pass = $db->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :user_id");
            try {
                $db->execute($stmt_update_pass, [':password_hash' => $new_password_hash, ':user_id' => $user_id]);
                SessionManager::set('message', 'Password changed successfully.');
                // Regenerate CSRF token after successful operation if staying on the same form page,
                // or redirect. Here we redirect to dashboard.
                header("Location: " . $path_to_root . "pages/dashboard.php");
                exit;
            } catch (PDOException $e) {
                error_log("Error updating password for user ID {$user_id}: " . $e->getMessage());
                SessionManager::set('message', 'An error occurred while changing your password. Please try again.');
            }
        } else {
            SessionManager::set('message', 'Current password incorrect.');
        }
    }
    // If any validation error or failed DB op (not resulting in redirect yet), redirect back to form
    if (SessionManager::has('message') && strpos(strtolower(SessionManager::get('message')), 'success') === false) {
         header("Location: " . $_SERVER['PHP_SELF']);
         exit;
    }
}

$csrf_token = SessionManager::generateCsrfToken(); // Regenerate for form display

require_once $path_to_root . 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><?php echo $page_title; ?></h4>
                </div>
                <div class="card-body">
                    <?php if (SessionManager::has('message')): ?>
                        <div class="alert <?php echo strpos(strtolower(SessionManager::get('message')), 'success') !== false ? 'alert-success' : 'alert-danger'; ?>">
                            <?php echo htmlspecialchars(SessionManager::get('message')); SessionManager::remove('message'); ?>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_new_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once $path_to_root . 'includes/footer.php';
?>
