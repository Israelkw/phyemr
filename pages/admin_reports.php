<?php
$path_to_root = "../";
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();
// Ensure user is admin
SessionManager::hasRole(['admin'], $path_to_root . 'pages/dashboard.php', 'Access Denied: You do not have permission to view this page.');

require_once $path_to_root . 'includes/db_connect.php'; // May not be needed immediately, but good for future
require_once $path_to_root . 'includes/Database.php';   // Same as above
$db = new Database($pdo); // Initialize Database class

$page_title = "Admin Reports";

// --- Report Logic Handling ---
$report_output_total_collected = null;
$report_output_detailed_payment_log = null;
$report_output_new_patients = null;
$report_output_active_patients = null;

// Values for Total Collected Report form
$tc_start_date_val = $_GET['tc_start_date'] ?? date('Y-m-01');
$tc_end_date_val = $_GET['tc_end_date'] ?? date('Y-m-d');

// Values for Detailed Payment Log form
$dpl_start_date_val = $_GET['dpl_start_date'] ?? date('Y-m-01');
$dpl_end_date_val = $_GET['dpl_end_date'] ?? date('Y-m-d');

// Values for New Patients Registered form
$npr_start_date_val = $_GET['npr_start_date'] ?? date('Y-m-01');
$npr_end_date_val = $_GET['npr_end_date'] ?? date('Y-m-d');

// Values for Active Patients by Assignment & Activity form
$apa_start_date_val = $_GET['apa_start_date'] ?? date('Y-m-01');
$apa_end_date_val = $_GET['apa_end_date'] ?? date('Y-m-d');


if (isset($_GET['report_name']) && $_GET['report_name'] === 'total_collected') {
    // Validate dates (basic validation)
    if (strtotime($tc_start_date_val) && strtotime($tc_end_date_val)) {
        $start_date = date('Y-m-d 00:00:00', strtotime($tc_start_date_val));
        // For end date, include the whole day
        $end_date = date('Y-m-d 23:59:59', strtotime($tc_end_date_val));

        if ($end_date < $start_date) {
            $report_output_total_collected = "<p class='text-danger'>Error: End date cannot be before start date.</p>";
        } else {
            try {
                $sql = "SELECT SUM(amount_paid) AS total_collected
                        FROM payments
                        WHERE payment_date >= :start_date AND payment_date <= :end_date";
                $stmt = $db->prepare($sql);
                $db->execute($stmt, [':start_date' => $start_date, ':end_date' => $end_date]);
                $result = $db->fetch($stmt);

                $total_collected_amount = $result['total_collected'] ?? 0;
                $report_output_total_collected = "<h5 class='mt-3'>Total Collected from " . htmlspecialchars($tc_start_date_val) . " to " . htmlspecialchars($tc_end_date_val) . ":</h5>";
                $report_output_total_collected .= "<p class='fs-4'><strong>$" . htmlspecialchars(number_format($total_collected_amount, 2)) . "</strong></p>";

            } catch (PDOException $e) {
                error_log("Error generating Total Collected report: " . $e->getMessage());
                $report_output_total_collected = "<p class='text-danger'>Error generating report. Please try again.</p>";
            }
        }
    } else {
        $report_output_total_collected = "<p class='text-danger'>Error: Invalid date format provided.</p>";
    }
} elseif (isset($_GET['report_name']) && $_GET['report_name'] === 'detailed_payment_log') {
    if (strtotime($dpl_start_date_val) && strtotime($dpl_end_date_val)) {
        $start_date = date('Y-m-d 00:00:00', strtotime($dpl_start_date_val));
        $end_date = date('Y-m-d 23:59:59', strtotime($dpl_end_date_val));

        if ($end_date < $start_date) {
            $report_output_detailed_payment_log = "<p class='text-danger'>Error: End date cannot be before start date.</p>";
        } else {
            try {
                $sql = "SELECT
                            p.payment_date,
                            p.amount_paid,
                            p.payment_method,
                            p.manual_receipt_number,
                            i.invoice_number,
                            i.id AS invoice_id,
                            pat.first_name AS patient_first_name,
                            pat.last_name AS patient_last_name,
                            pat.id AS patient_id,
                            u.username AS recorded_by_username
                        FROM payments p
                        JOIN invoices i ON p.invoice_id = i.id
                        JOIN patients pat ON i.patient_id = pat.id
                        JOIN users u ON p.recorded_by_user_id = u.id
                        WHERE p.payment_date >= :start_date AND p.payment_date <= :end_date
                        ORDER BY p.payment_date DESC";
                $stmt = $db->prepare($sql);
                $db->execute($stmt, [':start_date' => $start_date, ':end_date' => $end_date]);
                $payments_log = $db->fetchAll($stmt);

                $report_output_detailed_payment_log = "<h5 class='mt-3'>Detailed Payment Log from " . htmlspecialchars($dpl_start_date_val) . " to " . htmlspecialchars($dpl_end_date_val) . ":</h5>";
                if (empty($payments_log)) {
                    $report_output_detailed_payment_log .= "<p>No payments found in this period.</p>";
                } else {
                    $report_output_detailed_payment_log .= "<table class='table table-striped table-sm mt-2'>";
                    $report_output_detailed_payment_log .= "<thead><tr><th>Payment Date</th><th>Patient</th><th>Invoice #</th><th class='text-end'>Amount Paid</th><th>Method</th><th>Receipt #</th><th>Recorded By</th></tr></thead><tbody>";
                    foreach ($payments_log as $log_entry) {
                        $patient_name = htmlspecialchars($log_entry['patient_first_name'] . ' ' . $log_entry['patient_last_name']);
                        // Link to patient details if a page exists, for now just name
                        // $patient_link = $path_to_root . "pages/patient_details.php?patient_id=" . $log_entry['patient_id'];
                        $invoice_link = $path_to_root . "pages/view_invoice_details.php?invoice_id=" . $log_entry['invoice_id'];

                        $report_output_detailed_payment_log .= "<tr>";
                        $report_output_detailed_payment_log .= "<td>" . htmlspecialchars(date('M d, Y H:i', strtotime($log_entry['payment_date']))) . "</td>";
                        $report_output_detailed_payment_log .= "<td>" . $patient_name . " (ID: " . $log_entry['patient_id'] . ")</td>";
                        $report_output_detailed_payment_log .= "<td><a href='" . $invoice_link . "'>" . htmlspecialchars($log_entry['invoice_number']) . "</a></td>";
                        $report_output_detailed_payment_log .= "<td class='text-end'>" . htmlspecialchars(number_format($log_entry['amount_paid'], 2)) . "</td>";
                        $report_output_detailed_payment_log .= "<td>" . htmlspecialchars($log_entry['payment_method']) . "</td>";
                        $report_output_detailed_payment_log .= "<td>" . htmlspecialchars($log_entry['manual_receipt_number'] ?? 'N/A') . "</td>";
                        $report_output_detailed_payment_log .= "<td>" . htmlspecialchars($log_entry['recorded_by_username']) . "</td>";
                        $report_output_detailed_payment_log .= "</tr>";
                    }
                    $report_output_detailed_payment_log .= "</tbody></table>";
                }
            } catch (PDOException $e) {
                error_log("Error generating Detailed Payment Log: " . $e->getMessage());
                $report_output_detailed_payment_log = "<p class='text-danger'>Error generating report. Please try again.</p>";
            }
        }
    } else {
        $report_output_detailed_payment_log = "<p class='text-danger'>Error: Invalid date format provided.</p>";
    }
} elseif (isset($_GET['report_name']) && $_GET['report_name'] === 'new_patients_registered') {
    if (strtotime($npr_start_date_val) && strtotime($npr_end_date_val)) {
        $start_date = date('Y-m-d 00:00:00', strtotime($npr_start_date_val));
        $end_date = date('Y-m-d 23:59:59', strtotime($npr_end_date_val));

        if ($end_date < $start_date) {
            $report_output_new_patients = "<p class='text-danger'>Error: End date cannot be before start date.</p>";
        } else {
            try {
                $sql = "SELECT
                            pat.id AS patient_id,
                            pat.first_name,
                            pat.last_name,
                            pat.created_at AS registration_date,
                            reg_u.username AS registered_by_username,
                            ac.first_name AS clinician_first_name,
                            ac.last_name AS clinician_last_name
                        FROM patients pat
                        LEFT JOIN users reg_u ON pat.registered_by_user_id = reg_u.id
                        LEFT JOIN users ac ON pat.assigned_clinician_id = ac.id
                        WHERE pat.created_at >= :start_date AND pat.created_at <= :end_date
                        ORDER BY pat.created_at DESC";
                $stmt = $db->prepare($sql);
                $db->execute($stmt, [':start_date' => $start_date, ':end_date' => $end_date]);
                $new_patients_log = $db->fetchAll($stmt);
                $new_patients_count = count($new_patients_log);

                $report_output_new_patients = "<h5 class='mt-3'>New Patients Registered from " . htmlspecialchars($npr_start_date_val) . " to " . htmlspecialchars($npr_end_date_val) . ":</h5>";
                $report_output_new_patients .= "<p><strong>Total New Patients: " . $new_patients_count . "</strong></p>";

                if (empty($new_patients_log)) {
                    $report_output_new_patients .= "<p>No new patients registered in this period.</p>";
                } else {
                    $report_output_new_patients .= "<table class='table table-striped table-sm mt-2'>";
                    $report_output_new_patients .= "<thead><tr><th>Registered On</th><th>Patient ID</th><th>Name</th><th>Registered By</th><th>Assigned Clinician</th></tr></thead><tbody>";
                    foreach ($new_patients_log as $patient_entry) {
                        $patient_name = htmlspecialchars($patient_entry['first_name'] . ' ' . $patient_entry['last_name']);
                        $assigned_clinician_name = "N/A";
                        if (!empty($patient_entry['clinician_first_name'])) {
                            $assigned_clinician_name = htmlspecialchars($patient_entry['clinician_first_name'] . ' ' . $patient_entry['clinician_last_name']);
                        }

                        // Consider linking to patient details if such a page exists and is appropriate
                        // $patient_link = $path_to_root . "pages/view_patient_details_general.php?patient_id=" . $patient_entry['patient_id'];

                        $report_output_new_patients .= "<tr>";
                        $report_output_new_patients .= "<td>" . htmlspecialchars(date('M d, Y H:i', strtotime($patient_entry['registration_date']))) . "</td>";
                        $report_output_new_patients .= "<td>" . htmlspecialchars($patient_entry['patient_id']) . "</td>";
                        $report_output_new_patients .= "<td>" . $patient_name . "</td>";
                        $report_output_new_patients .= "<td>" . htmlspecialchars($patient_entry['registered_by_username'] ?? 'N/A') . "</td>";
                        $report_output_new_patients .= "<td>" . $assigned_clinician_name . "</td>";
                        $report_output_new_patients .= "</tr>";
                    }
                    $report_output_new_patients .= "</tbody></table>";
                }
            } catch (PDOException $e) {
                error_log("Error generating New Patients Registered report: " . $e->getMessage());
                $report_output_new_patients = "<p class='text-danger'>Error generating report. Please try again.</p>";
            }
        }
    } else {
        $report_output_new_patients = "<p class='text-danger'>Error: Invalid date format provided.</p>";
    }
} elseif (isset($_GET['report_name']) && $_GET['report_name'] === 'active_patient_activity') {
    if (strtotime($apa_start_date_val) && strtotime($apa_end_date_val)) {
        $start_date = date('Y-m-d 00:00:00', strtotime($apa_start_date_val));
        $end_date = date('Y-m-d 23:59:59', strtotime($apa_end_date_val));

        if ($end_date < $start_date) {
            $report_output_active_patients = "<p class='text-danger'>Error: Activity End date cannot be before Activity Start date.</p>";
        } else {
            try {
                $sql = "SELECT DISTINCT
                            pat.id AS patient_id,
                            pat.first_name AS patient_first_name,
                            pat.last_name AS patient_last_name,
                            ac.first_name AS clinician_first_name,
                            ac.last_name AS clinician_last_name
                        FROM patients pat
                        JOIN users ac ON pat.assigned_clinician_id = ac.id
                        WHERE pat.assigned_clinician_id IS NOT NULL
                        AND EXISTS (
                            SELECT 1 FROM patient_procedures pp
                            WHERE pp.patient_id = pat.id
                            AND pp.date_performed >= :start_date AND pp.date_performed <= :end_date
                            UNION ALL
                            SELECT 1 FROM patient_form_submissions pfs
                            WHERE pfs.patient_id = pat.id
                            AND pfs.submission_timestamp >= :start_date AND pfs.submission_timestamp <= :end_date
                        )
                        ORDER BY pat.last_name, pat.first_name";

                $stmt = $db->prepare($sql);
                $db->execute($stmt, [':start_date' => $start_date, ':end_date' => $end_date]);
                $active_patients_list = $db->fetchAll($stmt);
                $active_patients_count = count($active_patients_list);

                $report_output_active_patients = "<h5 class='mt-3'>Active Patients (with assigned clinician & activity) from " . htmlspecialchars($apa_start_date_val) . " to " . htmlspecialchars($apa_end_date_val) . ":</h5>";
                $report_output_active_patients .= "<p><strong>Total Matching Patients: " . $active_patients_count . "</strong></p>";

                if (empty($active_patients_list)) {
                    $report_output_active_patients .= "<p>No patients found matching these criteria in this period.</p>";
                } else {
                    $report_output_active_patients .= "<table class='table table-striped table-sm mt-2'>";
                    $report_output_active_patients .= "<thead><tr><th>Patient ID</th><th>Patient Name</th><th>Assigned Clinician</th></tr></thead><tbody>";
                    foreach ($active_patients_list as $patient_entry) {
                        $patient_name = htmlspecialchars($patient_entry['patient_first_name'] . ' ' . $patient_entry['patient_last_name']);
                        $assigned_clinician_name = htmlspecialchars($patient_entry['clinician_first_name'] . ' ' . $patient_entry['clinician_last_name']);

                        $report_output_active_patients .= "<tr>";
                        $report_output_active_patients .= "<td>" . htmlspecialchars($patient_entry['patient_id']) . "</td>";
                        $report_output_active_patients .= "<td>" . $patient_name . "</td>";
                        $report_output_active_patients .= "<td>" . $assigned_clinician_name . "</td>";
                        $report_output_active_patients .= "</tr>";
                    }
                    $report_output_active_patients .= "</tbody></table>";
                }
            } catch (PDOException $e) {
                error_log("Error generating Active Patients report: " . $e->getMessage());
                $report_output_active_patients = "<p class='text-danger'>Error generating report. Please try again. Details: " . $e->getMessage() . "</p>";
            }
        }
    } else {
        $report_output_active_patients = "<p class='text-danger'>Error: Invalid date format provided for activity period.</p>";
    }
}


require_once $path_to_root . 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4"><?php echo $page_title; ?></h1>
            <p>Welcome to the Admin Reports section. Select a report to generate.</p>
            <hr>
        </div>
    </div>

    <!-- Report sections will be added here in subsequent steps -->

    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Payment Reports</h5>
                </div>
                <div class="card-body">
                    <p>Generate reports related to payments and revenue.</p>

                    <div id="total-collected-report-section" class="report-section mb-4 p-3 border rounded">
                        <h6>Total Collected Payments</h6>
                        <form method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>#total-collected-report-section">
                            <input type="hidden" name="report_name" value="total_collected">
                            <div class="row">
                                <div class="col-md-5 mb-2">
                                    <label for="tc_start_date" class="form-label">Start Date:</label>
                                    <input type="date" class="form-control" id="tc_start_date" name="tc_start_date" value="<?php echo htmlspecialchars($_GET['tc_start_date'] ?? date('Y-m-01')); ?>" required>
                                </div>
                                <div class="col-md-5 mb-2">
                                    <label for="tc_end_date" class="form-label">End Date:</label>
                                    <input type="date" class="form-control" id="tc_end_date" name="tc_end_date" value="<?php echo htmlspecialchars($_GET['tc_end_date'] ?? date('Y-m-d')); ?>" required>
                                </div>
                                <div class="col-md-2 align-self-end mb-2">
                                    <button type="submit" class="btn btn-primary w-100">Generate</button>
                                </div>
                            </div>
                        </form>
                        <div id="total-collected-results" class="mt-3 report-results-area">
                            <?php if ($report_output_total_collected): ?>
                                <?php echo $report_output_total_collected; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <hr>
                    <div id="detailed-payment-log-section" class="report-section mb-4 p-3 border rounded">
                        <h6>Detailed Payment Log</h6>
                        <form method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>#detailed-payment-log-section">
                            <input type="hidden" name="report_name" value="detailed_payment_log">
                            <div class="row">
                                <div class="col-md-5 mb-2">
                                    <label for="dpl_start_date" class="form-label">Start Date:</label>
                                    <input type="date" class="form-control" id="dpl_start_date" name="dpl_start_date" value="<?php echo htmlspecialchars($_GET['dpl_start_date'] ?? date('Y-m-01')); ?>" required>
                                </div>
                                <div class="col-md-5 mb-2">
                                    <label for="dpl_end_date" class="form-label">End Date:</label>
                                    <input type="date" class="form-control" id="dpl_end_date" name="dpl_end_date" value="<?php echo htmlspecialchars($_GET['dpl_end_date'] ?? date('Y-m-d')); ?>" required>
                                </div>
                                <div class="col-md-2 align-self-end mb-2">
                                    <button type="submit" class="btn btn-primary w-100">Generate</button>
                                </div>
                            </div>
                        </form>
                        <div id="detailed-payment-log-results" class="mt-3 report-results-area table-responsive">
                            <?php if ($report_output_detailed_payment_log): ?>
                                <?php echo $report_output_detailed_payment_log; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <hr>
                    <div id="active-patients-report-section" class="report-section mb-4 p-3 border rounded">
                        <h6>Active Patients by Clinician Assignment & Activity</h6>
                        <form method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>#active-patients-report-section">
                            <input type="hidden" name="report_name" value="active_patient_activity">
                            <div class="row">
                                <div class="col-md-5 mb-2">
                                    <label for="apa_start_date" class="form-label">Activity Start Date:</label>
                                    <input type="date" class="form-control" id="apa_start_date" name="apa_start_date" value="<?php echo htmlspecialchars($_GET['apa_start_date'] ?? date('Y-m-01')); ?>" required>
                                </div>
                                <div class="col-md-5 mb-2">
                                    <label for="apa_end_date" class="form-label">Activity End Date:</label>
                                    <input type="date" class="form-control" id="apa_end_date" name="apa_end_date" value="<?php echo htmlspecialchars($_GET['apa_end_date'] ?? date('Y-m-d')); ?>" required>
                                </div>
                                <div class="col-md-2 align-self-end mb-2">
                                    <button type="submit" class="btn btn-primary w-100">Generate</button>
                                </div>
                            </div>
                        </form>
                        <div id="active-patients-report-results" class="mt-3 report-results-area table-responsive">
                            <?php if ($report_output_active_patients): ?>
                                <?php echo $report_output_active_patients; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Patient Reports</h5>
                </div>
                <div class="card-body">
                    <p>Generate reports related to patient statistics and activity.</p>
                    <!-- Links or forms for patient reports will go here -->
                    <div id="new-patients-report-section" class="report-section mb-4 p-3 border rounded">
                        <h6>New Patients Registered</h6>
                        <form method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>#new-patients-report-section">
                            <input type="hidden" name="report_name" value="new_patients_registered">
                            <div class="row">
                                <div class="col-md-5 mb-2">
                                    <label for="npr_start_date" class="form-label">Start Date:</label>
                                    <input type="date" class="form-control" id="npr_start_date" name="npr_start_date" value="<?php echo htmlspecialchars($_GET['npr_start_date'] ?? date('Y-m-01')); ?>" required>
                                </div>
                                <div class="col-md-5 mb-2">
                                    <label for="npr_end_date" class="form-label">End Date:</label>
                                    <input type="date" class="form-control" id="npr_end_date" name="npr_end_date" value="<?php echo htmlspecialchars($_GET['npr_end_date'] ?? date('Y-m-d')); ?>" required>
                                </div>
                                <div class="col-md-2 align-self-end mb-2">
                                    <button type="submit" class="btn btn-primary w-100">Generate</button>
                                </div>
                            </div>
                        </form>
                        <div id="new-patients-report-results" class="mt-3 report-results-area table-responsive">
                            <?php if ($report_output_new_patients): ?>
                                <?php echo $report_output_new_patients; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php
require_once $path_to_root . 'includes/footer.php';
?>
