<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'My Application'; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css"> <!-- Using absolute path from web root as per Twig template -->
    <?php if (isset($head_extra)) { echo $head_extra; } ?>
</head>
<body>
    <header>
        <?php
        // This assumes navigation.php handles its own session checks and HTML output.
        // It also assumes SessionManager::startSession() has been called by the including page.
        require_once __DIR__ . '/../navigation.php';
        ?>
    </header>

    <main class="container mt-4">
        <?php /* Display session messages. Assumes SessionManager::startSession() called by main page. */ ?>
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['message']); // Clear after displaying ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); // Clear after displaying ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['login_error'])): /* Specifically for login page errors */ ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['login_error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['login_error']); // Clear after displaying ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['logout_message'])): /* Specifically for logout message on login page */ ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['logout_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['logout_message']); // Clear after displaying ?>
        <?php endif; ?>

        <?php
        // Content block
        if (isset($content_template_path) && file_exists($content_template_path)) {
            include $content_template_path;
        } elseif (isset($content)) { // Allow passing direct content too
            echo $content;
        } else {
            echo '<p>Error: Content block not set. Please define $content_template_path or $content.</p>';
        }
        ?>
    </main>

    <footer class="text-center mt-4 py-3 bg-light">
        <p>&copy; <?php echo date("Y"); ?> My Application. All rights reserved.</p>
    </footer>

    <!-- Optional: Bootstrap JS bundle -->
    <script src="https_cdn.jsdelivr.net_npm_bootstrap@5.1.3_dist_js_bootstrap.bundle.min.js"></script>
    <?php if (isset($scripts_extra)) { echo $scripts_extra; } ?>
</body>
</html>
