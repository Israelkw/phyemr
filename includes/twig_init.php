<?php
// Ensure this path is correct, pointing to the Composer autoload file
// from your project's root vendor directory.
require_once __DIR__ . '/../vendor/autoload.php';

// -- START JULES DEBUG --
if (class_exists('Twig\Environment')) {
    error_log("JULES DEBUG: Twig\Environment class IS found after autoload.");
} else {
    error_log("JULES DEBUG: Twig\Environment class IS NOT found after autoload. Autoloader path: " . realpath(__DIR__ . '/../vendor/autoload.php'));
    // Check if the autoloader file itself is readable
    if (!is_readable(__DIR__ . '/../vendor/autoload.php')) {
        error_log("JULES DEBUG: vendor/autoload.php is NOT readable.");
    }
    // Check if the specific Twig class file is present
    $twigClassPath = __DIR__ . '/../vendor/twig/twig/src/Environment.php';
    if (file_exists($twigClassPath)) {
        error_log("JULES DEBUG: Twig Environment.php file IS present at: " . realpath($twigClassPath));
         if (!is_readable($twigClassPath)) {
            error_log("JULES DEBUG: Twig Environment.php IS NOT readable.");
         }
    } else {
        error_log("JULES DEBUG: Twig Environment.php file IS NOT present at expected path: " . realpath($twigClassPath));
    }
}
// -- END JULES DEBUG --

// Initialize Twig environment
try {
    // The path to the templates directory, relative to this file's parent directory (includes/)
    // So, if twig_init.php is in 'includes/', and templates are in 'templates/',
    // this path should resolve correctly.
    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');

    // The path to the cache directory, relative to this file's parent directory (includes/)
    // Ensure the 'cache' directory exists and is writable by the web server.
    // For development, you can disable caching or set auto_reload to true.
    $twig_options = [
        'cache' => __DIR__ . '/../cache_new', // Set to false to disable caching for development
        'debug' => true,                 // Enable debug mode (useful for development)
        'auto_reload' => true,           // Automatically recompile templates if source changes (good for dev)
    ];

    $twig = new \Twig\Environment($loader, $twig_options);

    // Add $_SESSION as a global variable to Twig.
    // It's generally better to handle session access more explicitly in your controllers
    // or services and pass only necessary data to templates, but for now, this maintains
    // the previous behavior of making the whole session available.
    if (session_status() == PHP_SESSION_NONE) {
        // This check is important. If SessionManager is used, it might have already started it.
        // If not, start it here to make $_SESSION available.
        // Consider using your SessionManager::startSession() if it's the standard way.
        session_start();
    }
    $twig->addGlobal('session', isset($_SESSION) ? $_SESSION : []);

    // If you use Twig's debug extension, you can add it here:
    if ($twig_options['debug']) {
        $twig->addExtension(new \Twig\Extension\DebugExtension());
    }

} catch (Exception $e) {
    // Handle any exceptions during Twig initialization
    error_log("Twig Initialization Error: " . $e->getMessage());
    // Consider a more user-friendly error page or message in production
    die("An error occurred during template system initialization. Please check server logs. Details: " . $e->getMessage());
}

// The $twig variable is now available for use in PHP scripts that include this file.
?>
