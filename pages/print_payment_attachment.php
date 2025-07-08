<?php
$path_to_root = "../"; // Define $path_to_root for includes
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();
// Allow admin for testing/oversight, primarily for receptionists
SessionManager::hasRole(['receptionist', 'admin'], $path_to_root . 'pages/login.php', 'Access denied. Please log in with appropriate credentials.');

require_once $path_to_root . 'includes/db_connect.php'; // Provides $pdo
require_once $path_to_root . 'includes/Database.php';    // Provides Database class
$db = new Database($pdo);

$patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);

if (!$patient_id) {
    // Instead of redirecting (which might close a popup), display an error or a blank page.
    // Or redirect back to a safe page if not opened in a new tab.
    // For simplicity here, just exit with a message.
    die("Error: No patient ID provided. This page should be accessed with a patient_id parameter.");
}

// Fetch patient details
$stmt_patient = $db->prepare("SELECT id, first_name, last_name, date_of_birth FROM patients WHERE id = :patient_id");
$db->execute($stmt_patient, [':patient_id' => $patient_id]);
$patient_details = $db->fetch($stmt_patient);

if (!$patient_details) {
    die("Error: Patient not found for ID: " . htmlspecialchars($patient_id));
}

// Fetch procedures assigned to the selected patient
$sql_patient_procedures = "
    SELECT
        pp.date_performed,
        p.name AS procedure_name,
        p.price AS procedure_price,
        u.first_name AS clinician_first_name,
        u.last_name AS clinician_last_name
    FROM patient_procedures pp
    JOIN procedures p ON pp.procedure_id = p.id
    JOIN users u ON pp.clinician_id = u.id
    WHERE pp.patient_id = :patient_id
    ORDER BY pp.date_performed ASC, p.name ASC";

$stmt_procedures = $db->prepare($sql_patient_procedures);
$db->execute($stmt_procedures, [':patient_id' => $patient_id]);
$assigned_procedures = $db->fetchAll($stmt_procedures);

$total_cost = 0;
foreach ($assigned_procedures as $proc) {
    $total_cost += floatval($proc['procedure_price']);
}

// Basic Clinic Information (Consider making this configurable)
$clinic_name = "Physio Clinic XYZ";
$clinic_address = "123 Wellness Ave, Health City, HC 45678";
$clinic_phone = "(123) 456-7890";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Attachment - <?php echo htmlspecialchars($patient_details['last_name'] . ', ' . $patient_details['first_name']); ?></title>
    <link href="<?php echo $path_to_root; ?>css/bootstrap.min.css" rel="stylesheet"> <!-- Optional: for basic styling if needed -->
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        .container {
            width: 100%;
            max-width: 800px; /* Adjust as needed */
            margin: 0 auto;
            border: 1px solid #ccc;
            padding: 20px;
        }
        .header, .footer {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0;
            font-size: 12px;
        }
        .patient-info, .procedure-details {
            margin-bottom: 20px;
        }
        .patient-info h3, .procedure-details h3 {
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
            margin-bottom: 10px;
            font-size: 18px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f8f8f8;
        }
        .text-end {
            text-align: right;
        }
        .total-row th, .total-row td {
            font-weight: bold;
            font-size: 14px;
        }
        .print-button-area {
            text-align: center;
            margin-top: 20px;
        }
        @media print {
            body {
                margin: 0;
                font-size: 10pt; /* Adjust for print */
            }
            .container {
                border: none;
                box-shadow: none;
                max-width: 100%;
                padding: 0;
            }
            .print-button-area {
                display: none;
            }
            /* Ensure bootstrap styles don't interfere too much if used */
            .navbar, .footer-content, button, a.btn { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo htmlspecialchars($clinic_name); ?></h1>
            <p><?php echo htmlspecialchars($clinic_address); ?></p>
            <p>Phone: <?php echo htmlspecialchars($clinic_phone); ?></p>
            <h2>Payment Attachment</h2>
        </div>

        <div class="patient-info">
            <h3>Patient Information</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($patient_details['first_name'] . ' ' . $patient_details['last_name']); ?></p>
            <p><strong>Patient ID:</strong> <?php echo htmlspecialchars($patient_details['id']); ?></p>
            <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars(date("m/d/Y", strtotime($patient_details['date_of_birth']))); ?></p>
            <p><strong>Date Printed:</strong> <?php echo date("m/d/Y H:i:s"); ?></p>
        </div>

        <div class="procedure-details">
            <h3>Procedures Performed</h3>
            <?php if (!empty($assigned_procedures)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date Performed</th>
                            <th>Procedure</th>
                            <th>Clinician</th>
                            <th class="text-end">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assigned_procedures as $procedure): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(date("m/d/Y", strtotime($procedure['date_performed']))); ?></td>
                                <td><?php echo htmlspecialchars($procedure['procedure_name']); ?></td>
                                <td><?php echo htmlspecialchars($procedure['clinician_first_name'] . ' ' . $procedure['clinician_last_name']); ?></td>
                                <td class="text-end"><?php echo htmlspecialchars(number_format($procedure['procedure_price'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="3" class="text-end"><strong>Total Amount Due:</strong></td>
                            <td class="text-end"><strong><?php echo htmlspecialchars(number_format($total_cost, 2)); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            <?php else: ?>
                <p>No procedures recorded for this patient.</p>
            <?php endif; ?>
        </div>

        <div class="footer">
            <p>Thank you for choosing <?php echo htmlspecialchars($clinic_name); ?>.</p>
            <p>Please make payments to [Payment Details/Instructions].</p>
        </div>
    </div>

    <div class="print-button-area">
        <button class="btn btn-primary" onclick="window.print();">Print This Page</button>
        <button class="btn btn-secondary" onclick="window.close();">Close</button>
    </div>

</body>
</html>
