<?php
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();

require_once $path_to_root . 'includes/ErrorHandler.php';
ErrorHandler::register();

require_once $path_to_root . 'includes/db_connect.php'; // Provides $pdo
require_once $path_to_root . 'includes/Database.php';    // Provides Database class

// CSRF Token Validation
$submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!SessionManager::validateCsrfToken($submittedToken)) {
    SessionManager::set('message', 'Invalid or missing CSRF token. Please try again.');
    // Redirect back to the edit page if possible, or manage procedures page
    $redirect_id = isset($_POST['procedure_id']) ? '?id=' . $_POST['procedure_id'] : '';
    header("Location: " . $path_to_root . "pages/admin_edit_procedure.php" . $redirect_id);
    exit;
}

SessionManager::hasRole('admin', $path_to_root . 'pages/dashboard.php', 'You do not have permission to perform this action.');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    SessionManager::set('message', "Invalid request method.");
    header("Location: " . $path_to_root . "pages/admin_manage_procedures.php");
    exit;
}

$procedure_id = filter_input(INPUT_POST, 'procedure_id', FILTER_VALIDATE_INT);
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$price = isset($_POST['price']) ? trim($_POST['price']) : '';

// Store old input in session in case of validation errors
$old_input = $_POST;
unset($old_input['csrf_token']);
SessionManager::set('form_old_input', $old_input);

$redirect_url_on_error = $path_to_root . "pages/admin_edit_procedure.php?id=" . $procedure_id;
if (!$procedure_id) {
    SessionManager::set('message', "Invalid procedure ID provided.");
    header("Location: " . $path_to_root . "pages/admin_manage_procedures.php"); // Fallback if ID is totally missing
    exit;
}


if (empty($name)) {
    SessionManager::set('message', "Procedure name is required.");
    header("Location: " . $redirect_url_on_error);
    exit;
}

if (!is_numeric($price) || floatval($price) < 0) {
    SessionManager::set('message', "Price must be a non-negative number.");
    header("Location: " . $redirect_url_on_error);
    exit;
}
$price_decimal = floatval($price);

$db = new Database($pdo);

try {
    // Check if the procedure exists
    $stmt_check_id = $db->prepare("SELECT id FROM procedures WHERE id = :id");
    $db->execute($stmt_check_id, [':id' => $procedure_id]);
    if (!$db->fetch($stmt_check_id)) {
        SessionManager::set('message', "Procedure not found or ID is invalid.");
        header("Location: " . $path_to_root . "pages/admin_manage_procedures.php");
        exit;
    }

    // Check if new name conflicts with an existing procedure (excluding itself)
    $stmt_check_name = $db->prepare("SELECT id FROM procedures WHERE name = :name AND id != :id");
    $db->execute($stmt_check_name, [':name' => $name, ':id' => $procedure_id]);
    if ($db->fetch($stmt_check_name)) {
        SessionManager::set('message', "Another procedure with the name '" . htmlspecialchars($name) . "' already exists.");
        header("Location: " . $redirect_url_on_error);
        exit;
    }

    // Prepare SQL UPDATE statement
    $sql_update = "UPDATE procedures SET name = :name, price = :price, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
    $params_update = [
        ':name' => $name,
        ':price' => $price_decimal,
        ':id' => $procedure_id
    ];
    $stmt_update_procedure = $db->prepare($sql_update);
    $db->execute($stmt_update_procedure, $params_update);

    SessionManager::set('message', "Procedure '" . htmlspecialchars($name) . "' updated successfully.");
    SessionManager::remove('form_old_input'); // Clear old input on success
    header("Location: " . $path_to_root . "pages/admin_manage_procedures.php");
    exit;

} catch (PDOException $e) {
    error_log("Database error during procedure update: " . $e->getMessage());
    SessionManager::set('message', "Failed to update procedure due to a database error. Please try again or contact support.");
    header("Location: " . $redirect_url_on_error);
    exit;
} catch (Exception $e) {
    error_log("General error during procedure update: " . $e->getMessage());
    SessionManager::set('message', "An unexpected error occurred. Please try again or contact support.");
    header("Location: " . $redirect_url_on_error);
    exit;
}
?>
