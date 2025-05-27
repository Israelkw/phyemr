<?php
// header.php will start the session if not already started.
// For login page, we usually don't need session access before header.php.
$page_title = "Login";
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/header.php'; 
?>

    <form action="../php/handle_login.php" method="POST" class="form-container">
        <h2>User Login</h2>
        <?php
        // The global message display in navigation.php (included by header.php)
        // will handle displaying $_SESSION['message'].
        // If handle_login.php sets $_SESSION['message'] instead of $_SESSION['login_error'],
        // it will be shown by the header's navigation include.
        // For now, specific error display here is removed.
        ?>
        <div>
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div>
            <button type="submit">Login</button>
        </div>
    </form>

<?php require_once $path_to_root . 'includes/footer.php'; ?>
