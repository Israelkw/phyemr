<?php
// DEBUGGING CODE BY JULES - START
$templatePath = __DIR__ . '/../templates/layout/base.html.twig';
if (file_exists($templatePath)) {
    $templateContent = file_get_contents($templatePath);
    echo "<pre>\nDEBUGGING base.html.twig (first 300 chars):\n";
    echo htmlspecialchars(substr($templateContent, 0, 300));
    echo "\n</pre>\n<hr>\n";
} else {
    echo "<pre>\nDEBUGGING: base.html.twig NOT FOUND at: " . htmlspecialchars($templatePath) . "</pre>\n<hr>\n";
}
die("Stopped by Jules' debug output.");
// DEBUGGING CODE BY JULES - END

// Define path to root for includes, assuming this file is in pages/
$path_to_root = "../";

require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession(); // Start session management

require_once $path_to_root . 'includes/twig_init.php'; // Initializes $twig

// Data for the template
$template_data = [];

// Get and then clear one-time messages
$login_error = SessionManager::get('login_error');
if ($login_error) {
    // $template_data['login_error'] = $login_error; // Pass to template
    // The base.html.twig now handles session.login_error directly.
    SessionManager::remove('login_error'); // Clear it after retrieving
}

$logout_message = SessionManager::get('logout_message');
if ($logout_message) {
    // $template_data['logout_message'] = $logout_message; // Pass to template
    // The base.html.twig now handles session.logout_message directly.
    SessionManager::remove('logout_message'); // Clear it after retrieving
}

// General message from other pages (if any)
$message = SessionManager::get('message');
if ($message) {
    // $template_data['message'] = $message; // Pass to template
    // The base.html.twig now handles session.message directly.
    SessionManager::remove('message');
}


// CSRF token for the form
$template_data['csrf_token'] = SessionManager::generateCsrfToken();
$template_data['form_action_path'] = $path_to_root . 'php/handle_login.php';

// Other potential variables for the form (e.g., pre-filled username, paths)
// $template_data['username_value'] = SessionManager::get('form_input_username', ''); // Example
// SessionManager::remove('form_input_username'); // Clear after use
$template_data['register_path'] = '#'; // Placeholder for registration page path
$template_data['forgot_password_path'] = '#'; // Placeholder for forgot password path

// Variables needed by base.html.twig if not using globals extensively in Twig
$template_data['page_title'] = "Login"; // For the {% block title %}
// $template_data['path_to_root'] = $path_to_root; // If base template needs it for asset paths

// Render the template
// Note: The mock Twig environment in twig_init.php is very basic.
// It won't process {% extends %} or complex blocks correctly.
// This is a structural step for the subtask.
try {
    echo $twig->render('pages/login.html.twig', $template_data);
} catch (Exception $e) {
    error_log("Error rendering login template: " . $e->getMessage());
    // Fallback or more robust error display
    echo "Error loading page. Please try again later.";
    // In a dev environment, you might want to show more details from $e->getMessage()
}

?>
