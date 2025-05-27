<?php
$path_to_root = "../"; // Define $path_to_root for includes
session_start();

// Check if user is logged in
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"])) {
    $_SESSION['message'] = "Please log in to access this page.";
    header("Location: login.php"); // Stays as is, relative to current dir (pages/)
    exit;
}

// Check if user is admin
if ($_SESSION["role"] !== 'admin') {
    $_SESSION['message'] = "Unauthorized access. Only administrators can add users.";
    header("Location: dashboard.php"); // Stays as is, relative to current dir (pages/)
    exit;
}

// Include header
require_once $path_to_root . 'includes/header.php';
?>

<div class="container mt-5">
    <h2>Add New User</h2>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert <?php echo strpos(strtolower($_SESSION['message']), 'successfully') !== false ? 'alert-success' : 'alert-danger'; ?>">
            <?php 
                echo htmlspecialchars($_SESSION['message']); 
                unset($_SESSION['message']); 
            ?>
        </div>
    <?php endif; ?>

    <form action="../php/handle_add_clinician.php" method="POST">
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <div class="mb-3">
            <label for="first_name" class="form-label">First Name</label>
            <input type="text" class="form-control" id="first_name" name="first_name">
        </div>
        <div class="mb-3">
            <label for="last_name" class="form-label">Last Name</label>
            <input type="text" class="form-control" id="last_name" name="last_name">
        </div>
        <div class="mb-3">
            <label for="role" class="form-label">Role</label>
            <select class="form-select" id="role" name="role" required>
                <option value="">Select Role</option>
                <option value="receptionist">Receptionist</option>
                <option value="nurse">Nurse</option>
                <option value="clinician">Clinician</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Add User</button>
    </form>
</div>

<?php
// Include footer
require_once $path_to_root . 'includes/footer.php';
?>
