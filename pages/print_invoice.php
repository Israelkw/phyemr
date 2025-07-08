<?php
$path_to_root = "../";
require_once $path_to_root . 'includes/SessionManager.php';
SessionManager::startSession();
SessionManager::hasRole(['receptionist', 'admin'], $path_to_root . 'pages/login.php', 'Access Denied.');

require_once $path_to_root . 'includes/db_connect.php';
require_once $path_to_root . 'includes/Database.php';
$db = new Database($pdo);

$invoice_id = filter_input(INPUT_GET, 'invoice_id', FILTER_VALIDATE_INT);

if (!$invoice_id) {
    die("Error: No Invoice ID provided.");
}

$invoice_details = null;
$invoice_items = [];

try {
    // Fetch invoice details
    $sql_invoice = "SELECT i.*, p.first_name AS p_first_name, p.last_name AS p_last_name, p.address AS p_address, p.phone_number AS p_phone,
                           u.first_name AS created_by_first, u.last_name AS created_by_last
                    FROM invoices i
                    JOIN patients p ON i.patient_id = p.id
                    JOIN users u ON i.created_by_user_id = u.id
                    WHERE i.id = :invoice_id";
    $stmt_invoice = $db->prepare($sql_invoice);
    $db->execute($stmt_invoice, [':invoice_id' => $invoice_id]);
    $invoice_details = $db->fetch($stmt_invoice);

    if (!$invoice_details) {
        die("Error: Invoice not found for ID: " . htmlspecialchars($invoice_id));
    }

    // Fetch invoice items
    $sql_items = "SELECT ii.procedure_name_snapshot, ii.price_snapshot
                  FROM invoice_items ii
                  WHERE ii.invoice_id = :invoice_id
                  ORDER BY ii.id ASC";
    $stmt_items = $db->prepare($sql_items);
    $db->execute($stmt_items, [':invoice_id' => $invoice_id]);
    $invoice_items = $db->fetchAll($stmt_items);

} catch (PDOException $e) {
    error_log("Error fetching invoice for printing: " . $e->getMessage());
    die("Database error fetching invoice details for printing.");
}

$balance_due = floatval($invoice_details['total_amount']) - floatval($invoice_details['amount_paid']);

// Clinic Information
$clinic_logo_path = $path_to_root . "includes/Easelogoicon.png"; // Adjusted path
$clinic_name = "Ease Physiotherapy Speciality Clinic"; // Updated Name
$clinic_address_line1 = "Debrework Building, Mexico";
$clinic_address_line2 = "Addis Ababa, Ethiopia";
$clinic_phone1 = "+251973363738";
$clinic_phone2 = "+251978363738";
$clinic_email = "info@easepartners.et";
$clinic_website = "easepartners.et";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo htmlspecialchars($invoice_details['invoice_number']); ?></title>
    <!-- Optional: Link to Bootstrap for some basic styling, but print CSS will override much of it -->
    <!-- <link href="<?php echo $path_to_root; ?>css/bootstrap.min.css" rel="stylesheet"> -->
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; color: #333; font-size: 12px; }
        .invoice-box { width: 100%; max-width: 800px; margin: auto; padding: 20px; /* border: 1px solid #eee; box-shadow: 0 0 10px rgba(0, 0, 0, .15); */ }

        .header-section, .address-section, .items-section, .totals-section, .notes-section, .footer-section { margin-bottom: 20px; }
        .header-section { text-align: center; }
        .header-section h1 { margin: 0 0 5px 0; font-size: 24px; color: #000; }
        .header-section .clinic-address { font-size: 10px; margin-bottom: 2px;}

        .invoice-title { font-size: 28px; text-align: right; font-weight: bold; margin-bottom:5px; }
        .invoice-meta p { margin: 2px 0; text-align: right; font-size: 11px; }

        .address-section table { width: 100%; }
        .address-section td { padding: 5px; vertical-align: top; }
        .address-section .billed-to { font-weight: bold; margin-bottom: 3px; }

        .items-section table { width: 100%; border-collapse: collapse; }
        .items-section th, .items-section td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .items-section th { background-color: #f2f2f2; font-weight: bold; }
        .items-section td.amount, .items-section th.amount { text-align: right; }

        .totals-section { margin-top: 20px; }
        .totals-section table { width: 50%; float: right; border-collapse: collapse; }
        .totals-section td { padding: 5px 8px; text-align: right; }
        .totals-section tr.highlight td { font-weight: bold; border-top: 2px solid #333; font-size: 13px;}

        .notes-section h5 { margin-top: 20px; margin-bottom: 5px; font-size: 13px; }
        .notes-section p { font-size: 11px; white-space: pre-wrap; }

        .footer-section { text-align: center; font-size: 10px; margin-top: 30px; border-top: 1px solid #eee; padding-top: 10px; }
        .print-button-container { text-align: center; margin: 20px 0; } /* Changed from .print-button-area */

        @media screen { /* Styles for screen view */
            .print-button-container { display: block; }
        }

        @media print {
            body * { visibility: hidden; } /* Hide everything by default in print */
            #invoice-printable-area, #invoice-printable-area * { visibility: visible; } /* Show only the printable area and its children */
            #invoice-printable-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                padding: 0;
                border: 0;
                box-shadow: none;
            }
            .print-button-container { display: none !important; } /* Ensure button container is hidden when printing */
            /* Basic print styling for the invoice box content if needed, e.g., font size */
            body { margin: 0.5cm; font-size: 10pt; }
        }
    </style>
</head>
<body>
    <div class="print-button-container" id="print-controls"> <!-- Changed class, added ID -->
        <button id="print-button" onclick="window.print();" class="btn btn-primary">Print Invoice</button>
        <button onclick="window.close();" class="btn btn-secondary">Close</button>
    </div>

    <div class="invoice-box" id="invoice-printable-area"> <!-- Added ID here -->
        <table cellpadding="0" cellspacing="0" style="width:100%;">
            <tr class="top">
                <td colspan="2" style="padding:0;">
                    <table style="width:100%;">
                        <tr>
                            <td class="title" style="vertical-align:top; width:60%;">
                                <div class="header-section">
                                    <img src="<?php echo $clinic_logo_path; ?>" alt="Clinic Logo" style="max-width:150px; max-height:70px; margin-bottom:10px;">
                                    <h2><?php echo htmlspecialchars($clinic_name); ?></h2>
                                    <p class="clinic-address"><?php echo htmlspecialchars($clinic_address_line1); ?></p>
                                    <p class="clinic-address"><?php echo htmlspecialchars($clinic_address_line2); ?></p>
                                    <p class="clinic-address">
                                        Phone: <?php echo htmlspecialchars($clinic_phone1); ?> / <?php echo htmlspecialchars($clinic_phone2); ?>
                                    </p>
                                    <p class="clinic-address">
                                        Email: <?php echo htmlspecialchars($clinic_email); ?> | Website: <?php echo htmlspecialchars($clinic_website); ?>
                                    </p>
                                </div>
                            </td>
                            <td style="vertical-align:top; text-align:right; width:40%;">
                                <div class="invoice-title">INVOICE</div>
                                <div class="invoice-meta">
                                    <p><strong>Invoice #:</strong> <?php echo htmlspecialchars($invoice_details['invoice_number']); ?></p>
                                    <p><strong>Date:</strong> <?php echo htmlspecialchars(date('M d, Y', strtotime($invoice_details['invoice_date']))); ?></p>
                                    <?php if($invoice_details['due_date']): ?>
                                    <p><strong>Due Date:</strong> <?php echo htmlspecialchars(date('M d, Y', strtotime($invoice_details['due_date']))); ?></p>
                                    <?php endif; ?>
                                    <p><strong>Status:</strong> <?php echo ucfirst(str_replace('_', ' ', $invoice_details['payment_status'])); ?></p>
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="address-section">
            <table>
                <tr>
                    <td>
                        <div class="billed-to">Bill To:</div>
                        <?php echo htmlspecialchars($invoice_details['p_first_name'] . ' ' . $invoice_details['p_last_name']); ?><br>
                        <?php if(!empty($invoice_details['p_address'])): ?>
                        <?php echo nl2br(htmlspecialchars($invoice_details['p_address'])); ?><br>
                        <?php endif; ?>
                        <?php if(!empty($invoice_details['p_phone'])): ?>
                        <?php echo htmlspecialchars($invoice_details['p_phone']); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="items-section">
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="amount">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($invoice_items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['procedure_name_snapshot']); ?></td>
                        <td class="amount"><?php echo htmlspecialchars(number_format($item['price_snapshot'], 2)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="totals-section">
            <table>
                <tr>
                    <td>Subtotal:</td>
                    <td><?php echo htmlspecialchars(number_format($invoice_details['total_amount'], 2)); ?></td>
                </tr>
                <tr>
                    <td>Amount Paid:</td>
                    <td><?php echo htmlspecialchars(number_format($invoice_details['amount_paid'], 2)); ?></td>
                </tr>
                <tr class="highlight">
                    <td>Balance Due:</td>
                    <td><?php echo htmlspecialchars(number_format($balance_due, 2)); ?></td>
                </tr>
            </table>
            <div style="clear:both;"></div>
        </div>

        <?php if (!empty($invoice_details['payment_notes'])): ?>
        <div class="notes-section">
            <h5>Payment Notes:</h5>
            <p><?php echo nl2br(htmlspecialchars($invoice_details['payment_notes'])); ?></p>
        </div>
        <?php endif; ?>

        <div class="footer-section">
            <p>Thank you for your business!</p>
            <p>Generated by: <?php echo htmlspecialchars($invoice_details['created_by_first'] . ' ' . $invoice_details['created_by_last']); ?> on <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($invoice_details['created_at'])));?></p>
        </div>
    </div>
</body>
</html>
