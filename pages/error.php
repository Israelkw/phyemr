<?php
session_start();

$errorMessage = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : 'An unspecified error occurred.';
// Clear the error message after displaying it to prevent it from showing again on subsequent visits
unset($_SESSION['error_message']);

// Determine a safe page to return to
$homePage = 'login.php'; // Default to login page
if (isset($_SESSION['user_id'])) {
    // If user is logged in, dashboard is a better redirect
    $homePage = 'dashboard.php';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error</title>
    <link rel="stylesheet" href="../assets/css/style.css"> <!-- Assuming a general stylesheet exists -->
    <style>
        .error-container {
            text-align: center;
            margin-top: 50px;
            padding: 20px;
            border: 1px solid #ff0000;
            background-color: #ffecec;
            color: #cc0000;
        }
        .error-container a {
            color: #007bff;
            text-decoration: none;
        }
        .error-container a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Application Error</h1>
        </header>
        <main>
            <div class="error-container">
                <p><?php echo htmlspecialchars($errorMessage); ?></p>
                <p><a href="<?php echo htmlspecialchars($homePage); ?>">Go to Home Page</a></p>
            </div>
        </main>
        <?php include '../includes/footer.php'; // Assuming a common footer ?>
    </div>
</body>
</html>
