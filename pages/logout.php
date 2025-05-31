<?php
require_once '../includes/SessionManager.php';

// Ensure SessionManager starts its own session context if needed, though destroy will handle it.
// No explicit startSession() call is strictly needed before destroy if destroy handles it.
// However, to be absolutely clear and consistent:
SessionManager::startSession(); // Ensures session system is active with our settings

// Optional: Log the logout action here if auditing is required.
// Logger::log('User ' . SessionManager::get('user_id', 'Unknown') . ' logged out.');

SessionManager::destroySession();

// To set a message for the login page after session destruction,
// we need to start a new, minimal session or pass it via query parameter.
// Starting a new session is cleaner if the login page is set up to display it.
session_start(); // This starts a new, fresh session
SessionManager::set('logout_message', 'You have been successfully logged out.'); // Uses the new session

header("Location: login.php");
exit;
?>
