<?php
// Ensure this path is correct, pointing to the Composer autoload file
// from your project's root vendor directory.
require_once __DIR__ . '/../vendor/autoload.php';

// Initialize Twig environment - MINIMAL TEST
try {
    error_log("JULES DEBUG: About to attempt new Twig\Environment(null, ['cache' => false]);");
    $twig = new \Twig\Environment(null, ['cache' => false, 'autoescape' => false]); // No loader, no cache for this basic test
    error_log("JULES DEBUG: Successfully instantiated Twig\Environment.");
    // The rest of the original Twig setup (FilesystemLoader, options, globals) can remain commented out or removed for this test.
    // For now, let's just see if the Environment class itself can be new'd up.
    // The original code for loader, options, globals, etc. will be put back later.

    // For this minimal test, we won't use $loader or $twig_options from before.
    // We'll just see if the class can be found.
    // $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
    // $twig_options = [
    //     'cache' => __DIR__ . '/../cache_new',
    //     'debug' => true,
    //     'auto_reload' => true,
    // ];
    // $twig = new \Twig\Environment($loader, $twig_options); // Original instantiation

    // if (session_status() == PHP_SESSION_NONE) {
    //     session_start();
    // }
    // $twig->addGlobal('session', isset($_SESSION) ? $_SESSION : []);
    // if ($twig_options['debug']) { // This would cause error if $twig_options not defined
    //     $twig->addExtension(new \Twig\Extension\DebugExtension());
    // }

} catch (Throwable $e) { // Catch Throwable for broader error catching, including Error
    error_log("JULES DEBUG: Twig Initialization Error (Minimal Test): " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
    error_log("JULES DEBUG: Stack Trace: " . $e->getTraceAsString());
    die("An error occurred during minimal Twig Environment test. Please check server logs. Details: " . $e->getMessage());
}

// The $twig variable is now available for use in PHP scripts that include this file.
// However, for this minimal test, it's only useful if the Environment class was found.
?>
