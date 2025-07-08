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
    header("Location: " . $path_to_root . "pages/admin_manage_procedures.php");
    exit;
}

SessionManager::hasRole('admin', $path_to_root . 'pages/dashboard.php', 'You do not have permission to perform this action.');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    SessionManager::set('message', "Invalid request method.");
    header("Location: " . $path_to_root . "pages/admin_manage_procedures.php");
    exit;
}

$procedure_id = filter_input(INPUT_POST, 'procedure_id', FILTER_VALIDATE_INT);

if (!$procedure_id) {
    SessionManager::set('message', "Invalid procedure ID provided for deletion.");
    header("Location: " . $path_to_root . "pages/admin_manage_procedures.php");
    exit;
}

$db = new Database($pdo);

try {
    // Check if the procedure exists before attempting to delete
    $stmt_check_id = $db->prepare("SELECT name FROM procedures WHERE id = :id");
    $db->execute($stmt_check_id, [':id' => $procedure_id]);
    $procedure = $db->fetch($stmt_check_id);

    if (!$procedure) {
        SessionManager::set('message', "Procedure not found or ID is invalid. Cannot delete.");
        header("Location: " . $path_to_root . "pages/admin_manage_procedures.php");
        exit;
    }
    $procedure_name = $procedure['name'];


    // Attempt to delete the procedure
    // Note: If this procedure_id is used in `patient_procedures`, this will fail due to FK constraint `ON DELETE RESTRICT`.
    // This is intentional to prevent data inconsistency. A more advanced implementation might check `patient_procedures` first.
    $sql_delete = "DELETE FROM procedures WHERE id = :id";
    $stmt_delete_procedure = $db->prepare($sql_delete);
    $db->execute($stmt_delete_procedure, [':id' => $procedure_id]);

    if ($stmt_delete_procedure->rowCount() > 0) {
        SessionManager::set('message', "Procedure '" . htmlspecialchars($procedure_name) . "' deleted successfully.");
    } else {
        // This case might not be reached if the check above is thorough,
        // but it's a fallback. Or, it could mean the ID was valid but deletion failed for other reasons (though FK is most likely).
        SessionManager::set('message', "Could not delete procedure '" . htmlspecialchars($procedure_name) . "'. It might have been deleted already or an unknown error occurred.");
    }
    header("Location: " . $path_to_root . "pages/admin_manage_procedures.php");
    exit;

} catch (PDOException $e) {
    // Check for foreign key constraint violation (MySQL error code 1451)
    if ($e->getCode() == '23000' || strpos($e->getMessage(), '1451') !== false || strpos(strtolower($e->getMessage()), 'foreign key constraint fails') !== false) {
        error_log("Attempt to delete procedure ID {$procedure_id} failed due to foreign key constraint: " . $e->getMessage());
        SessionManager::set('message', "Cannot delete procedure '" . htmlspecialchars($procedure_name) . "' because it is currently assigned to one or more patients. Please remove its assignments first.");
    } else {
        error_log("Database error during procedure deletion: " . $e->getMessage());
        SessionManager::set('message', "Failed to delete procedure '" . htmlspecialchars($procedure_name) . "' due to a database error. Please try again or contact support.");
    }
    header("Location: " . $path_to_root . "pages/admin_manage_procedures.php");
    exit;
} catch (Exception $e) {
    error_log("General error during procedure deletion: " . $e->getMessage());
    SessionManager::set('message', "An unexpected error occurred while trying to delete procedure '" . htmlspecialchars($procedure_name) . "'. Please try again or contact support.");
    header("Location: " . $path_to_root . "pages/admin_manage_procedures.php");
    exit;
}
?>
