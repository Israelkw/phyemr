<?php
session_start(); // Ensure session is started to use $_SESSION variables

class ErrorHandler {

    /**
     * Handles exceptions, logs them, sets a user-friendly error message, and redirects.
     *
     * @param Exception $e The exception object.
     * @param string $logFilePath Path to the log file.
     */
    public static function handleException(Exception $e, $logFilePath = __DIR__ . '/../error.log') {
        // Log the detailed error message
        $errorMessage = "[" . date("Y-m-d H:i:s") . "] Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . PHP_EOL;
        error_log($errorMessage, 3, $logFilePath);

        // Set user-friendly error message
        $_SESSION['error_message'] = 'An unexpected error occurred. Please try again later or contact support.';

        // Determine redirection path
        // For this subtask, we will redirect to a generic error page.
        // A more sophisticated approach might check the context (e.g., current page or type of exception)
        // For now, all exceptions handled by this will go to pages/error.php
        header('Location: ../pages/error.php');
        exit;
    }

    /**
     * Handles PHP errors (non-exception), converts them into ErrorExceptions, and then calls handleException.
     *
     * @param int $errno The error number.
     * @param string $errstr The error message.
     * @param string $errfile The file where the error occurred.
     * @param int $errline The line number where the error occurred.
     * @param string $logFilePath Path to the log file for handleException.
     * @throws ErrorException
     */
    public static function handleError($errno, $errstr, $errfile, $errline, $logFilePath = __DIR__ . '/../error.log') {
        // Do not handle suppressed errors (@ operator)
        if (!(error_reporting() & $errno)) {
            return false;
        }

        // Convert error to ErrorException and throw it to be caught by the exception handler
        // This centralizes error logging and response through handleException.
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    /**
     * Registers the error and exception handlers.
     * To be called early in the application lifecycle.
     */
    public static function register() {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
    }
}

?>
