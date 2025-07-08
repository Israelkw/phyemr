<?php
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();
SessionManager::hasRole(['clinician', 'admin'], $path_to_root . 'pages/dashboard.php', 'You do not have permission to access this page.'); // Allow admin for testing

require_once $path_to_root . 'includes/db_connect.php'; // Provides $pdo
require_once $path_to_root . 'includes/Database.php';    // Provides Database class
$db = new Database($pdo);

// Get current clinician ID
$clinician_id = SessionManager::get('user_id');

// Fetch all patients for selection
$stmt_patients = $db->prepare("SELECT id, first_name, last_name FROM patients ORDER BY last_name, first_name ASC");
$db->execute($stmt_patients);
$patients = $db->fetchAll($stmt_patients);

// Fetch all available procedures
$stmt_procedures = $db->prepare("SELECT id, name, price FROM procedures ORDER BY name ASC");
$db->execute($stmt_procedures);
$procedures = $db->fetchAll($stmt_procedures);

// Get selected patient_id from GET request if available
$selected_patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);
if (!$selected_patient_id) {
    $selected_patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT); // Check POST if not in GET
}


// Retrieve old form input (if any) and clear it from session
$old_input = SessionManager::get('form_old_input', []);
SessionManager::remove('form_old_input');

// Include header
require_once $path_to_root . 'includes/header.php';
?>

<div class="container mt-5">
    <h2 class="mb-4">Assign Procedures to Patient</h2>

    <?php if (SessionManager::has('message')): ?>
        <div class="alert <?php echo strpos(strtolower(SessionManager::get('message')), 'success') !== false ? 'alert-success' : 'alert-danger'; ?>">
            <?php
                echo htmlspecialchars(SessionManager::get('message'));
                SessionManager::remove('message');
            ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo $path_to_root; ?>php/handle_assign_patient_procedures.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo SessionManager::generateCsrfToken(); ?>">

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="patient_id" class="form-label">Select Patient</label>
                <select class="form-select" id="patient_id" name="patient_id" required>
                    <option value="">-- Select a Patient --</option>
                    <?php foreach ($patients as $patient): ?>
                        <option value="<?php echo htmlspecialchars($patient['id']); ?>"
                                <?php echo (($selected_patient_id == $patient['id']) || ($old_input['patient_id'] ?? '') == $patient['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name'] . ' (ID: ' . $patient['id'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="date_performed" class="form-label">Date Performed</label>
                <input type="date" class="form-control" id="date_performed" name="date_performed"
                       value="<?php echo htmlspecialchars($old_input['date_performed'] ?? date('Y-m-d')); ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Select Procedures</label>
            <?php if (empty($procedures)): ?>
                <p>No procedures available. Please ask an administrator to add some.</p>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                    <?php foreach ($procedures as $procedure): ?>
                        <div class="col">
                            <div class="form-check card card-body">
                                <input class="form-check-input" type="checkbox"
                                       name="procedure_ids[]"
                                       value="<?php echo htmlspecialchars($procedure['id']); ?>"
                                       id="procedure_<?php echo htmlspecialchars($procedure['id']); ?>"
                                       <?php echo (isset($old_input['procedure_ids']) && is_array($old_input['procedure_ids']) && in_array($procedure['id'], $old_input['procedure_ids'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="procedure_<?php echo htmlspecialchars($procedure['id']); ?>">
                                    <?php echo htmlspecialchars($procedure['name']); ?>
                                    (<?php echo htmlspecialchars(number_format($procedure['price'], 2)); ?>)
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label for="notes" class="form-label">Notes (Optional)</label>
            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($old_input['notes'] ?? ''); ?></textarea>
        </div>

        <?php if (!empty($procedures)): // Only show button if there are procedures to select ?>
        <button type="submit" class="btn btn-primary">Assign Procedures</button>
        <?php endif; ?>
        <a href="<?php echo $path_to_root; ?>pages/dashboard.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php
// Include footer
require_once $path_to_root . 'includes/footer.php';
?>
