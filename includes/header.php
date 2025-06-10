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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>css/style.css">
</head>
<body>
    <div class="page-container"> <!-- Wrapper for overall page content -->
        <?php include_once 'navigation.php'; // Include navigation ?>
        <main class="main-content container"> <!-- Main content area -->
