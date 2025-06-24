<?php
// Session management and authorization
require_once '../includes/SessionManager.php';
SessionManager::startSession();
SessionManager::ensureUserIsLoggedIn('login.php');
SessionManager::hasRole(['receptionist'], 'dashboard.php', "Unauthorized access. Only receptionists can perform this action.");

// Include ErrorHandler and register it
require_once '../includes/ErrorHandler.php';
ErrorHandler::register();

// Include Database and db_connect (for $pdo)
require_once '../includes/db_connect.php'; // Provides $pdo
require_once '../includes/Database.php';    // Provides Database class

$db = new Database($pdo); // Instantiate Database class

$page_title = "Assign Existing Patient";
$path_to_root = "../"; // Define $path_to_root for includes

$clinician_list_from_db = [];
$clinician_load_error = null;
$search_results = [];
$search_term = '';

// Load clinicians for assignment dropdown
try {
    $sql_clinicians = "SELECT id, username, first_name, last_name FROM users WHERE role = 'clinician' AND is_active = 1 ORDER BY last_name, first_name";
    $stmt_clinicians = $db->prepare($sql_clinicians);
    $db->execute($stmt_clinicians);
    $clinician_list_from_db = $db->fetchAll($stmt_clinicians);
} catch (PDOException $e) {
    error_log("Error fetching clinicians: " . $e->getMessage());
    $clinician_load_error = "Could not load clinician list due to a database error.";
}

// Handle patient search
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search_term'])) {
    $search_term = trim($_GET['search_term']);
    if (!empty($search_term)) {
        try {
            $params = [];
            $sql_base = "SELECT patients.id, patients.first_name, patients.last_name, patients.date_of_birth,
                                u_assigned.first_name as assigned_fn, u_assigned.last_name as assigned_ln,
                                u_assigned.username as assigned_username
                         FROM patients
                         LEFT JOIN users u_assigned ON patients.assigned_clinician_id = u_assigned.id";
            $where_clauses = [];

            if (is_numeric($search_term)) {
                $where_clauses[] = "patients.id = :search_id";
                $params[':search_id'] = $search_term;
            }

            // Always search by name regardless of whether it's numeric, as an ID could be part of a name or vice-versa.
            // Or, make it an OR condition if numeric, and also search names.
            // For simplicity, let's allow search term to match ID OR name fields.
            // The previous query `patients.id = :search_id OR patients.first_name LIKE :search_name OR patients.last_name LIKE :search_name`
            // is actually fine if :search_id is bound to the same $search_term. PDO handles type casting.
            // The issue is more likely data not existing or a different subtle problem.

            // Reverting to a slightly modified version of the original for less disruptive change first.
            // Ensure parameter names are distinct if they were to hold different values.
            $sql_search = "SELECT patients.id, patients.first_name, patients.last_name, patients.date_of_birth,
                                  u_assigned.first_name as assigned_fn, u_assigned.last_name as assigned_ln,
                                  u_assigned.username as assigned_username
                           FROM patients
                           LEFT JOIN users u_assigned ON patients.assigned_clinician_id = u_assigned.id
                           WHERE patients.id = :search_term_id OR patients.first_name LIKE :search_term_name_first OR patients.last_name LIKE :search_term_name_last
                           ORDER BY patients.last_name, patients.first_name";

            $stmt_search = $db->prepare($sql_search);
            $execute_params = [
                ':search_term_id' => $search_term,
                ':search_term_name_first' => "%" . $search_term . "%",
                ':search_term_name_last' => "%" . $search_term . "%"
            ];
            $db->execute($stmt_search, $execute_params);
            $search_results = $db->fetchAll($stmt_search);

            // For debugging, one might add:
            // if (empty($search_results)) {
            //     error_log("No results for search term: " . $search_term . " with params: " . json_encode($execute_params));
            // }

        } catch (PDOException $e) {
            error_log("Error searching patients: " . $e->getMessage()); // Log the detailed error
            // Provide the detailed error message to the user for debugging if not in production
            // For production, the generic message is better.
            // For this debugging phase with the user, let's show a more detailed (but safe) error.
            // $exception_message = htmlspecialchars($e->getMessage());
            // SessionManager::set('error_message', "Database search error. Please check logs. Message: " . $exception_message);
            SessionManager::set('error_message', "An error occurred while searching for patients. Please check system logs."); // Keep it generic for user
        }
    } else {
        SessionManager::set('message', "Please enter a term to search for patients.");
    }
}


// Generate CSRF token for the assignment form
$csrf_token = SessionManager::generateCsrfToken();

// Retrieve and clear session messages
$page_message = SessionManager::get('message');
SessionManager::remove('message');
$page_error_message = SessionManager::get('error_message');
SessionManager::remove('error_message');

// Include header
require_once $path_to_root . 'includes/header.php';
?>

<div class="container mt-5">
    <h2><?php echo htmlspecialchars($page_title); ?></h2>

    <?php if ($page_message): ?>
        <div class="alert alert-info"><?php echo htmlspecialchars($page_message); ?></div>
    <?php endif; ?>
    <?php if ($page_error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($page_error_message); ?></div>
    <?php endif; ?>
    <?php if ($clinician_load_error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($clinician_load_error); ?></div>
    <?php endif; ?>

    <!-- Patient Search Form -->
    <form method="GET" action="assign_existing_patient.php" class="mb-4">
        <div class="row">
            <div class="col-md-8">
                <input type="text" name="search_term" class="form-control" placeholder="Search by Patient ID, First Name, or Last Name" value="<?php echo htmlspecialchars($search_term); ?>" required>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">Search Patients</button>
            </div>
        </div>
    </form>

    <?php if (!empty($search_term) && empty($search_results) && $_SERVER["REQUEST_METHOD"] == "GET"): ?>
        <div class="alert alert-warning">No patients found matching your search term "<?php echo htmlspecialchars($search_term); ?>".</div>
    <?php endif; ?>

    <?php if (!empty($search_results)): ?>
        <h3 class="mt-4">Search Results</h3>
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>DOB</th>
                    <th>Currently Assigned To</th>
                    <th>Assign to New Clinician</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($search_results as $patient): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($patient['id']); ?></td>
                        <td><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($patient['date_of_birth']); ?></td>
                        <td>
                            <?php if ($patient['assigned_clinician_id']): ?>
                                <?php echo htmlspecialchars($patient['assigned_fn'] . ' ' . $patient['assigned_ln'] . ' (@' . $patient['assigned_username'] . ')'); ?>
                            <?php else: ?>
                                <span class="text-muted">Not Assigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (empty($clinician_load_error) && !empty($clinician_list_from_db)): ?>
                            <form action="<?php echo $path_to_root; ?>php/handle_assign_existing_patient.php" method="POST" class="d-flex">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($patient['id']); ?>">
                                <input type="hidden" name="search_term_hidden" value="<?php echo htmlspecialchars($search_term); ?>"> <!-- Pass search term back -->
                                <select class="form-select form-select-sm me-2" name="assigned_clinician_id" required>
                                    <option value="">Select Clinician</option>
                                    <?php foreach ($clinician_list_from_db as $clinician): ?>
                                        <option value="<?php echo htmlspecialchars($clinician['id']); ?>"
                                            <?php echo ($patient['assigned_clinician_id'] == $clinician['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($clinician['first_name'] . ' ' . $clinician['last_name'] . ' (@' . $clinician['username'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-success">Assign</button>
                            </form>
                            <?php else: ?>
                                <span class="text-danger">Clinician list unavailable.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="mt-4">
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
</div>

<?php
// Include footer
require_once $path_to_root . 'includes/footer.php';
?>
