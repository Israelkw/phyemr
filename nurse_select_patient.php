<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Authorization: Check if user is logged in and is a nurse
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'nurse') {
    $_SESSION['message'] = "Unauthorized access. Only nurses can select patients for forms.";
    header("location: dashboard.php");
    exit;
}

$page_title = "Select Patient";
$path_to_root = "";
require_once 'includes/header.php';

// Fetch patients from session
$patients = isset($_SESSION['patients']) ? $_SESSION['patients'] : [];

?>

<div class="container">
    <h2>Select a Patient</h2>
    <?php
    // Message display is handled by header.php
    // if (isset($_SESSION['message'])) {
    //     echo "<p class='message'>" . htmlspecialchars($_SESSION['message']) . "</p>";
    //     unset($_SESSION['message']);
    // }
    ?>

    <?php if (!empty($patients)): ?>
        <table class="styled-table">
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
                <?php foreach ($patients as $patient_id => $patient): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($patient_id); ?></td>
                        <td><?php echo htmlspecialchars($patient['first_name']); ?></td>
                        <td><?php echo htmlspecialchars($patient['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($patient['date_of_birth']); ?></td>
                        <td>
                            <a href="nurse_select_form.php?patient_id=<?php echo htmlspecialchars($patient_id); ?>" class="btn-select">Select</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No patients found in the system.</p>
    <?php endif; ?>

    <p><a href="dashboard.php">Back to Dashboard</a></p>
</div>

<?php require_once 'includes/footer.php'; ?>
