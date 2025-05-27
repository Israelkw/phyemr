<?php
// header.php will start the session if not already started.
// For login page, we usually don't need session access before header.php.
$page_title = "Login";
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/header.php'; 
?>

    <form action="../php/handle_login.php" method="POST" class="form-container">
        <h2 class="mb-4">User Login</h2>
        <?php
        // The global message display in navigation.php (included by header.php)
        // will handle displaying $_SESSION['message'].
        // If handle_login.php sets $_SESSION['message'] instead of $_SESSION['login_error'],
        // it will be shown by the header's navigation include.
        // For now, specific error display here is removed.
        ?>
        <div class="mb-3">
            <label for="username" class="form-label">Username:</label>
            <input type="text" id="username" name="username" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password:</label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Login</button>
    </form>

<?php require_once $path_to_root . 'includes/footer.php'; ?>
