<?php

class SessionManager {
    private static $sessionStarted = false;
    private static $csrfTokenName = 'csrf_token';

    /**
     * Starts a session with secure settings if not already started.
     */
    public static function startSession() {
        if (self::$sessionStarted) {
            return;
        }

        // Use secure cookie parameters
        $cookieParams = [
            'lifetime' => 0, // Expires when browser closes
            'path' => '/',
            'domain' => '', // Current domain
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // Only send over HTTPS
            'httponly' => true, // Prevent JavaScript access
            'samesite' => 'Lax' // CSRF protection
        ];
        session_set_cookie_params($cookieParams);

        session_start();
        self::$sessionStarted = true;
    }

    /**
     * Regenerates the session ID to prevent session fixation.
     * Should be called after significant state changes like login or logout.
     */
    public static function regenerate() {
        if (!self::$sessionStarted) {
            self::startSession();
        }
        session_regenerate_id(true);
    }

    /**
     * Sets a session variable.
     * @param string $key The key of the session variable.
     * @param mixed $value The value to store.
     */
    public static function set($key, $value) {
        if (!self::$sessionStarted) {
            self::startSession();
        }
        $_SESSION[$key] = $value;
    }

    /**
     * Gets a session variable.
     * @param string $key The key of the session variable.
     * @param mixed $defaultValue The default value to return if the key is not found.
     * @return mixed The session variable's value or the default value.
     */
    public static function get($key, $defaultValue = null) {
        if (!self::$sessionStarted) {
            self::startSession();
        }
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $defaultValue;
    }

    /**
     * Checks if a session variable exists.
     * @param string $key The key of the session variable.
     * @return bool True if the key exists, false otherwise.
     */
    public static function has($key) {
        if (!self::$sessionStarted) {
            self::startSession();
        }
        return isset($_SESSION[$key]);
    }

    /**
     * Removes a session variable.
     * @param string $key The key of the session variable to remove.
     */
    public static function remove($key) {
        if (!self::$sessionStarted) {
            self::startSession();
        }
        unset($_SESSION[$key]);
    }

    /**
     * Destroys the current session.
     */
    public static function destroySession() {
        if (!self::$sessionStarted) {
            self::startSession();
        }
        $_SESSION = []; // Clear all session variables
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        self::$sessionStarted = false;
    }

    /**
     * Checks if a user is currently logged in.
     * @return bool True if user_id is set in session, false otherwise.
     */
    public static function isUserLoggedIn() {
        if (!self::$sessionStarted) {
            self::startSession();
        }
        return isset($_SESSION['user_id']);
    }

    /**
     * Ensures the user is logged in; otherwise, redirects.
     * @param string $redirectPath The path to redirect to if not logged in.
     */
    public static function ensureUserIsLoggedIn($redirectPath = '../pages/login.php') {
        if (!self::isUserLoggedIn()) {
            self::set('message', 'You must be logged in to access this page.');
            header("Location: " . $redirectPath);
            exit;
        }
    }

    /**
     * Checks if the logged-in user has one of the specified roles; otherwise, redirects.
     * @param string|array $roles A single role or an array of roles to check against.
     * @param string $redirectPath The path to redirect to if the user does not have the role.
     * @param string $message Custom message to set before redirecting.
     */
    public static function hasRole($roles, $redirectPath = '../pages/dashboard.php', $message = 'You do not have permission to access this page.') {
        if (!self::$sessionStarted) {
            self::startSession();
        }
        if (!self::isUserLoggedIn()) { // Should be logged in to check roles
            self::ensureUserIsLoggedIn(); // This will exit if not logged in
            return; // Should not be reached if ensureUserIsLoggedIn exits
        }

        $userRole = self::get('role');
        $allowed = is_array($roles) ? in_array($userRole, $roles) : ($userRole === $roles);

        if (!$allowed) {
            self::set('message', $message);
            header("Location: " . $redirectPath);
            exit;
        }
    }

    /**
     * Generates a CSRF token, stores it in the session, and returns it.
     * @return string The generated CSRF token.
     */
    public static function generateCsrfToken() {
        if (!self::$sessionStarted) {
            self::startSession();
        }
        $token = bin2hex(random_bytes(32));
        self::set(self::$csrfTokenName, $token);
        return $token;
    }

    /**
     * Validates the submitted CSRF token.
     * @param string $submittedToken The token submitted by the user (e.g., from $_POST).
     * @return bool True if the token is valid, false otherwise.
     */
    public static function validateCsrfToken($submittedToken) {
        if (!self::$sessionStarted) {
            self::startSession();
        }
        $sessionToken = self::get(self::$csrfTokenName);
        if ($sessionToken === null || $submittedToken === null) {
            return false;
        }
        $isValid = hash_equals($sessionToken, $submittedToken);
        // Optionally remove the token after first use to prevent reuse (depends on desired behavior)
        // self::remove(self::$csrfTokenName);
        return $isValid;
    }
}
?>
