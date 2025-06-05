<?php
// Define path to root for includes, assuming this file is in pages/
$path_to_root = "../";

require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession(); // Start session management

// Session messages are now handled directly by base_layout.php by accessing $_SESSION.
// However, we still need to ensure they are cleared after being potentially set by other processes (like handle_login.php).
// The base_layout.php will display and then unset them.
// If they were set and not yet displayed, base_layout.php handles it.
// If they were displayed by a previous load of base_layout.php, they'd be unset already.
// This section can be simplified if base_layout.php is guaranteed to run and clear them.
// For now, let's ensure they are cleared if they exist, matching previous behavior of clearing after read.
if (SessionManager::get('login_error')) {
    // The value is retrieved by SessionManager::get but not used here.
    // base_layout.php will access $_SESSION['login_error'] directly.
    // We call remove here to ensure it's cleared if handle_login.php set it and redirected.
    SessionManager::remove('login_error');
}
if (SessionManager::get('logout_message')) {
    SessionManager::remove('logout_message');
}
if (SessionManager::get('message')) {
    SessionManager::remove('message');
}

// --- Page-specific variables for the layout and content files ---

// For base_layout.php:
$page_title = "Login";
// $head_extra = "<link rel='stylesheet' href='specific_login_styles.css'>"; // Example if needed for this page
// $scripts_extra = "<script src='specific_login_scripts.js'></script>";   // Example if needed for this page

// For login_content.php (which will be included by base_layout.php):
$csrf_token = SessionManager::generateCsrfToken();
$form_action_path = $path_to_root . 'php/handle_login.php';
// Example for pre-filling username, if you want to implement sticky forms:
// $username_value = SessionManager::get('form_input_username', '');
// if ($username_value) SessionManager::remove('form_input_username'); // Clear after retrieving for pre-fill
$register_path = '#'; // Placeholder for registration page path - update with actual path
$forgot_password_path = '#'; // Placeholder for forgot password path - update with actual path

// Define the path to the actual content specific to this page
// This path will be used by base_layout.php
$content_template_path = __DIR__ . '/views/login_content.php';

// --- Render the page by including the base layout ---
// The base_layout.php will then include the $content_template_path.
// All variables defined above ($page_title, $csrf_token, $form_action_path, etc.)
// will be available in the scope of base_layout.php and login_content.php.
require_once $path_to_root . 'includes/layout/base_layout.php';

?>
