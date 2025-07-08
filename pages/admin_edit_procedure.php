<?php
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();
SessionManager::hasRole('admin', $path_to_root . 'pages/dashboard.php', 'You do not have permission to access this page.');

require_once $path_to_root . 'includes/db_connect.php'; // Provides $pdo
require_once $path_to_root . 'includes/Database.php';    // Provides Database class
$db = new Database($pdo);

$procedure_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$procedure = null;

if (!$procedure_id) {
    SessionManager::set('message', 'Invalid procedure ID.');
    header('Location: admin_manage_procedures.php');
    exit;
}

// Fetch the procedure
$stmt_procedure = $db->prepare("SELECT id, name, price FROM procedures WHERE id = :id");
$db->execute($stmt_procedure, [':id' => $procedure_id]);
$procedure = $db->fetch($stmt_procedure);

if (!$procedure) {
    SessionManager::set('message', 'Procedure not found.');
    header('Location: admin_manage_procedures.php');
    exit;
}

// Retrieve old form input (if any) and clear it from session
$old_input = SessionManager::get('form_old_input', []);
SessionManager::remove('form_old_input');

// If old input exists, use it; otherwise, use data from DB
$name_value = $old_input['name'] ?? $procedure['name'];
$price_value = $old_input['price'] ?? $procedure['price'];


// Include header
require_once $path_to_root . 'includes/header.php';
?>

<div class="container mt-5">
    <h2 class="mb-4">Edit Procedure</h2>

    <?php if (SessionManager::has('message')): ?>
        <div class="alert <?php echo strpos(strtolower(SessionManager::get('message')), 'success') !== false ? 'alert-success' : 'alert-danger'; ?>">
            <?php
                echo htmlspecialchars(SessionManager::get('message'));
                SessionManager::remove('message');
            ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo $path_to_root; ?>php/handle_edit_procedure.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo SessionManager::generateCsrfToken(); ?>">
        <input type="hidden" name="procedure_id" value="<?php echo htmlspecialchars($procedure['id']); ?>">

        <div class="mb-3">
            <label for="name" class="form-label">Procedure Name</label>
            <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($name_value); ?>">
        </div>
        <div class="mb-3">
            <label for="price" class="form-label">Price</label>
            <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" required value="<?php echo htmlspecialchars($price_value); ?>">
        </div>

        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="admin_manage_procedures.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php
// Include footer
require_once $path_to_root . 'includes/footer.php';
?>
