<?php
// This assumes Twig is installed via Composer or otherwise available in the include path.
// For the purpose of this environment, we simulate the autoloader's presence.
// In a real setup: require_once __DIR__ . '/../vendor/autoload.php';

// Simulate Twig classes being available for type hinting and structure.
// In a real environment, these would be loaded by Composer's autoloader.
if (!class_exists('Twig_Loader_Filesystem')) {
    class Twig_Loader_Filesystem {}
}
if (!class_exists('Twig_Environment')) {
    class Twig_Environment {
        private $loader;
        private $options;
        private $globals = [];

        public function __construct($loader, $options = []) {
            $this->loader = $loader;
            $this->options = $options;
            // Simulate adding $_SESSION as a global for now, as per subtask item 5
            // Proper session access should be handled more carefully in real apps.
            if (session_status() == PHP_SESSION_NONE) {
                // Starting session here if not already started, to make $_SESSION available.
                // Ideally, SessionManager::startSession() should be called before twig_init.php.
                session_start();
            }
            $this->addGlobal('session', isset($_SESSION) ? $_SESSION : []);
        }

        public function render($templateName, $context = []) {
            // This is a mock render. A real Twig instance would load and parse templates.
            $templatePath = $this->loader->getTemplatePath($templateName); // Mock method
            if (!file_exists($templatePath)) {
                return "Error: Template '$templateName' not found at '$templatePath'.";
            }
            $content = file_get_contents($templatePath);

            // Basic placeholder replacement for context variables (very simplified)
            foreach ($context as $key => $value) {
                if (is_string($value) || is_numeric($value)) {
                    $content = str_replace("{{ {$key} }}", htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'), $content);
                    $content = str_replace("{{ {$key}|raw }}", (string)$value, $content); // For raw output
                }
            }
            // Simulate block rendering (ultra-simplified)
            $content = preg_replace('/{% block title %}(.*?){% endblock %}/s', $context['page_title'] ?? 'My Application', $content);
            $content = preg_replace('/{% block content %}(.*?){% endblock %}/s', '$1', $content); // Keep content within block
            $content = preg_replace('/{%.*?%}/s', '', $content); // Remove other Twig tags

            // Simulate displaying session messages (very basic)
            $sessionMessages = '';
            if (isset($this->globals['session']['message'])) {
                $sessionMessages .= "<div class='alert alert-success'>" . htmlspecialchars($this->globals['session']['message'], ENT_QUOTES, 'UTF-8') . "</div>";
            }
            if (isset($this->globals['session']['error_message'])) {
                $sessionMessages .= "<div class='alert alert-danger'>" . htmlspecialchars($this->globals['session']['error_message'], ENT_QUOTES, 'UTF-8') . "</div>";
            }
            if (isset($this->globals['session']['login_error'])) {
                 $sessionMessages .= "<div class='alert alert-warning'>" . htmlspecialchars($this->globals['session']['login_error'], ENT_QUOTES, 'UTF-8') . "</div>";
            }
            // This is a crude way to inject session messages.
            // A proper base template would have a dedicated area for messages.
            // For now, prepend it to the content.
            $content = $sessionMessages . $content;

            return $content;
        }
        public function addGlobal($name, $value) {
            $this->globals[$name] = $value;
        }
    }
    // Mock for Twig_Loader_Filesystem
    class Twig_Loader_Filesystem_Mock {
        private $templateDir;
        public function __construct($templateDir) {
            $this->templateDir = $templateDir;
        }
        public function getTemplatePath($templateName) {
            return rtrim($this->templateDir, '/') . '/' . $templateName;
        }
    }
}


// Initialize Twig environment
try {
    // The path to the templates directory, relative to this file's parent directory (includes/)
    $loader = new Twig_Loader_Filesystem_Mock(__DIR__ . '/../templates');

    // The path to the cache directory, relative to this file's parent directory (includes/)
    $twig_options = [
        'cache' => __DIR__ . '/../cache',
        'debug' => true, // Enable debug mode for development
    ];

    $twig = new Twig_Environment($loader, $twig_options);

    // Add $_SESSION as a global variable to Twig (as per subtask item 5)
    // Ensure session is started before accessing $_SESSION
    // Note: SessionManager::startSession() should ideally be called before this script.
    // This is a simplified approach for now.
    if (class_exists('SessionManager') && method_exists('SessionManager', 'startSession')) {
         // SessionManager::startSession(); // Let's assume session is started by the calling script
    } elseif (session_status() == PHP_SESSION_NONE) {
        session_start(); // Fallback if SessionManager not used by caller yet
    }
    $twig->addGlobal('session', isset($_SESSION) ? $_SESSION : []);


} catch (Exception $e) {
    // Handle any exceptions during Twig initialization
    // Log the error and display a user-friendly message or die.
    error_log("Twig Initialization Error: " . $e->getMessage());
    die("An error occurred during template system initialization. Please check server logs.");
}

// The $twig variable is now available for use in PHP scripts that include this file.
?>
