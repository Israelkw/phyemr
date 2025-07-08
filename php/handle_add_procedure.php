<?php
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();

// Include ErrorHandler and register it (optional, but good practice)
require_once $path_to_root . 'includes/ErrorHandler.php';
ErrorHandler::register();

// Include Database and db_connect (for $pdo)
require_once $path_to_root . 'includes/db_connect.php'; // Provides $pdo
require_once $path_to_root . 'includes/Database.php';    // Provides Database class

// --- DEBUG START for CSRF ---
echo "<pre style='background-color: #f0f0f0; border: 1px solid #ccc; padding: 10px; margin: 10px; white-space: pre-wrap;'><strong>DEBUG (handle_add_procedure.php)</strong>\n";
echo "SESSION at start of handler:\n";
var_dump($_SESSION);
echo "POST data:\n";
var_dump($_POST);
$submittedToken_debug = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : 'NOT SET IN POST';
echo "Submitted CSRF Token (from POST): " . htmlspecialchars($submittedToken_debug);
echo "</pre>";
// --- DEBUG END for CSRF ---

// CSRF Token Validation
$submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!SessionManager::validateCsrfToken($submittedToken)) {
    SessionManager::set('message', 'Invalid or missing CSRF token. Please try again.');
    header("Location: " . $path_to_root . "pages/admin_manage_procedures.php");
    exit;
}

// Role check: Ensure user is admin
SessionManager::hasRole('admin', $path_to_root . 'pages/dashboard.php', 'You do not have permission to perform this action.');

// Ensure the script only processes POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    SessionManager::set('message', "Invalid request method.");
    header("Location: " . $path_to_root . "pages/admin_manage_procedures.php");
    exit;
}

// Retrieve and trim data from POST
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$price = isset($_POST['price']) ? trim($_POST['price']) : '';

// Store old input in session in case of validation errors
$old_input = $_POST;
unset($old_input['csrf_token']);
SessionManager::set('form_old_input', $old_input);

// Validate input
if (empty($name)) {
    SessionManager::set('message', "Procedure name is required.");
    header("Location: " . $path_to_root . "pages/admin_manage_procedures.php");
    exit;
}

if (!is_numeric($price) || floatval($price) < 0) {
    SessionManager::set('message', "Price must be a non-negative number.");
    header("Location: " . $path_to_root . "pages/admin_manage_procedures.php");
    exit;
}
$price_decimal = floatval($price);

$db = new Database($pdo);

try {
    // Check if procedure name already exists (optional, but good for usability)
    $stmt_check = $db->prepare("SELECT id FROM procedures WHERE name = :name");
    $db->execute($stmt_check, [':name' => $name]);
    if ($db->fetch($stmt_check)) {
        SessionManager::set('message', "A procedure with the name '" . htmlspecialchars($name) . "' already exists.");
        header("Location: " . $path_to_root . "pages/admin_manage_procedures.php");
        exit;
    }

    // Prepare SQL INSERT statement
    $sql_insert = "INSERT INTO procedures (name, price) VALUES (:name, :price)";
    $params_insert = [
        ':name' => $name,
        ':price' => $price_decimal
    ];
    $stmt_insert_procedure = $db->prepare($sql_insert);
    $db->execute($stmt_insert_procedure, $params_insert);

    SessionManager::set('message', "Procedure '" . htmlspecialchars($name) . "' added successfully.");
    SessionManager::remove('form_old_input'); // Clear old input on success
    header("Location: " . $path_to_root . "pages/admin_manage_procedures.php");
    exit;

} catch (PDOException $e) {
    error_log("Database error during procedure creation: " . $e->getMessage());
    SessionManager::set('message', "Failed to add procedure due to a database error. Please try again or contact support.");
    header("Location: " . $path_to_root . "pages/admin_manage_procedures.php");
    exit;
} catch (Exception $e) {
    error_log("General error during procedure creation: " . $e->getMessage());
    SessionManager::set('message', "An unexpected error occurred. Please try again or contact support.");
    header("Location: " . $path_to_root . "pages/admin_manage_procedures.php");
    exit;
}
?>
