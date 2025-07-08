<?php
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();
SessionManager::hasRole('admin', $path_to_root . 'pages/dashboard.php', 'You do not have permission to access this page.');

require_once $path_to_root . 'includes/db_connect.php'; // Provides $pdo
require_once $path_to_root . 'includes/Database.php';    // Provides Database class
$db = new Database($pdo);

// Fetch all procedures
$stmt_procedures = $db->prepare("SELECT id, name, price, created_at, updated_at FROM procedures ORDER BY name ASC");
$db->execute($stmt_procedures);
$procedures = $db->fetchAll($stmt_procedures);

// Retrieve old form input (if any) and clear it from session
$old_input = SessionManager::get('form_old_input', []);
SessionManager::remove('form_old_input');

// Include header
require_once $path_to_root . 'includes/header.php';
?>

<div class="container mt-5">
    <h2 class="mb-4">Manage Procedures</h2>

    <?php if (SessionManager::has('message')): ?>
        <div class="alert <?php echo strpos(strtolower(SessionManager::get('message')), 'success') !== false ? 'alert-success' : 'alert-danger'; ?>">
            <?php
                echo htmlspecialchars(SessionManager::get('message'));
                SessionManager::remove('message');
            ?>
        </div>
    <?php endif; ?>

    <!-- Add New Procedure Form -->
    <div class="card mb-4">
        <div class="card-header">
            Add New Procedure
        </div>
        <div class="card-body">
            <form action="<?php echo $path_to_root; ?>php/handle_add_procedure.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo SessionManager::generateCsrfToken(); ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Procedure Name</label>
                        <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($old_input['name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="price" class="form-label">Price</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" required value="<?php echo htmlspecialchars($old_input['price'] ?? ''); ?>">
                    </div>
                    <div class="col-md-2 mb-3 align-self-end">
                        <button type="submit" class="btn btn-primary w-100">Add Procedure</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- List of Existing Procedures -->
    <div class="card">
        <div class="card-header">
            Existing Procedures
        </div>
        <div class="card-body">
            <?php if (empty($procedures)): ?>
                <p>No procedures found. Add some using the form above.</p>
            <?php else: ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Created At</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($procedures as $procedure): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($procedure['name']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($procedure['price'], 2)); ?></td>
                                <td><?php echo htmlspecialchars($procedure['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($procedure['updated_at']); ?></td>
                                <td>
                                    <a href="<?php echo $path_to_root; ?>pages/admin_edit_procedure.php?id=<?php echo $procedure['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                    <form action="<?php echo $path_to_root; ?>php/handle_delete_procedure.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this procedure?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo SessionManager::generateCsrfToken(); ?>">
                                        <input type="hidden" name="procedure_id" value="<?php echo $procedure['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
require_once $path_to_root . 'includes/footer.php';
?>
