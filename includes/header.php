<?php
require_once __DIR__ . '/SessionManager.php'; // Ensure SessionManager is available
SessionManager::startSession(); // Centralized session start
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : "User Management App"; ?></title>
    <!-- Bootstrap 5 CSS CDN -->
    <link rel="stylesheet" href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>css/fonts.css">


    <link rel="stylesheet" href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>css/style.css">
</head>

<body>
    <div class="page-container">
        <!-- Wrapper for overall page content -->
        <?php
        include_once 'navigation.php'; // Include navigation
        ?>
        <main class="main-content container">
            <!-- Main content area -->