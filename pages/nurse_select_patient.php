<?php
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();

// Authorization: Check if user is logged in and is a nurse
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'nurse') {
    $_SESSION['message'] = "Unauthorized access. Only nurses can select patients for forms.";
    header("location: dashboard.php"); // Sibling page
    exit;
}

$page_title = "Select Patient";
require_once $path_to_root . 'includes/header.php';
require_once $path_to_root . 'includes/db_connect.php'; // $mysqli connection object

$patients = []; // Initialize to ensure it's an array
$db_error_message = '';   // To store any database error messages

$sql = "SELECT id, first_name, last_name, date_of_birth FROM patients ORDER BY last_name, first_name";
$stmt = $mysqli->prepare($sql);

if ($stmt === false) {
    error_log("Error preparing statement to fetch all patients: " . $mysqli->error);
    $db_error_message = "An error occurred while preparing to fetch patient data. Please try again later.";
} else {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $patients[] = $row;
        }
        $stmt->close();
    } else {
        error_log("Error executing statement to fetch all patients: " . $stmt->error);
        $db_error_message = "An error occurred while fetching patient data. Please try again later.";
        $stmt->close();
    }
}
// $mysqli->close(); // Connection closed at end of script by PHP or db_connect.php

?>

<div class="container mt-4"> <!-- Added mt-4 for spacing -->
    <h2 class="mb-3">Select a Patient</h2>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-info"> <!-- Or appropriate class based on message type -->
            <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($db_error_message)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($db_error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($db_error_message) && !empty($patients)): ?>
        <table class="table table-striped table-hover table-bordered"> <!-- Ensured consistency and added table-hover -->
            <thead>
                <tr>
                    <th>ID</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Date of Birth</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($patients as $patient): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($patient['id']); ?></td>
                        <td><?php echo htmlspecialchars($patient['first_name']); ?></td>
                        <td><?php echo htmlspecialchars($patient['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($patient['date_of_birth']); ?></td>
                        <td>
                            <a href="nurse_select_form.php?patient_id=<?php echo htmlspecialchars($patient['id']); ?>" class="btn btn-primary btn-sm">Select</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif (empty($db_error_message) && empty($patients)) : ?>
        <div class="alert alert-info">No patients found in the system.</div>
    <?php endif; ?>

    <p class="mt-3"><a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a></p> <!-- Sibling page -->
</div>

<?php require_once $path_to_root . 'includes/footer.php'; ?>
