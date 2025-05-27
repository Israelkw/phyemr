<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Start session if not already started
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : "User Management App"; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Open+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>css/style.css">
</head>
<body>
    <div class="page-container"> <!-- Wrapper for overall page content -->
        <?php include_once 'navigation.php'; // Include navigation ?>
        <main class="main-content container"> <!-- Main content area -->
