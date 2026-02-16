<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// Check if seller is logged in
if (!isset($_SESSION['seller_id'])) {
    http_response_code(403);
    die('Access denied');
}

$seller_id = $_SESSION['seller_id'];
$invoice_id = (int) ($_GET['id'] ?? 0);

if ($invoice_id <= 0) {
    die('Invalid invoice ID');
}

try {
    // Get invoice details
    $stmt = $pdo->prepare("
        SELECT ti.*, rr.product_name, rr.brand_name,
               s.name as seller_name, s.company_name, s.gst_number as seller_gst,
               s.billing_address as seller_address
        FROM tax_invoices ti
        JOIN review_requests rr ON ti.review_request_id = rr.id
        JOIN sellers s ON ti.seller_id = s.id
        WHERE ti.id = ? AND ti.seller_id = ?
    ");
    $stmt->execute([$invoice_id, $seller_id]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        die('Invoice not found');
    }
    
    // TODO: Implement PDF generation using a library like TCPDF or mPDF
    // For now, provide a simple HTML download
    
    $filename = 'invoice_' . $invoice['invoice_number'] . '.html';
    
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Generate HTML invoice
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice <?= htmlspecialchars($invoice['invoice_number']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .invoice-header { text-align: center; margin-bottom: 30px; }
        .invoice-details { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background-color: #f4f4f4; }
        .text-end { text-align: right; }
        .total-row { font-weight: bold; background-color: #f9f9f9; }
    </style>
</head>
<body>
    <div class="invoice-header">
        <h1><?= APP_NAME ?></h1>
        <h2>TAX INVOICE</h2>
    </div>
    
    <div class="invoice-details">
        <div style="float: left; width: 48%;">
            <strong>Invoice Number:</strong> <?= htmlspecialchars($invoice['invoice_number']) ?><br>
            <strong>Invoice Date:</strong> <?= date('d-M-Y', strtotime($invoice['invoice_date'])) ?><br>
            <strong>Order ID:</strong> #<?= $invoice['review_request_id'] ?>
        </div>
        <div style="float: right; width: 48%;">
            <strong>Billed To:</strong><br>
            <?= htmlspecialchars($invoice['seller_name']) ?><br>
            <?php if ($invoice['company_name']): ?>
                <?= htmlspecialchars($invoice['company_name']) ?><br>
            <?php endif; ?>
            <?php if ($invoice['seller_gst']): ?>
                GST: <?= htmlspecialchars($invoice['seller_gst']) ?><br>
            <?php endif; ?>
            <?= nl2br(htmlspecialchars($invoice['seller_address'])) ?>
        </div>
        <div style="clear: both;"></div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Description</th>
                <th>HSN/SAC</th>
                <th class="text-end">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>
                    Review Services<br>
                    <small><?= htmlspecialchars($invoice['product_name']) ?> - <?= htmlspecialchars($invoice['brand_name']) ?></small>
                </td>
                <td><?= htmlspecialchars($invoice['sac_code'] ?? SAC_CODE) ?></td>
                <td class="text-end"><?= number_format($invoice['base_amount'], 2) ?></td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                <td class="text-end"><?= number_format($invoice['base_amount'], 2) ?></td>
            </tr>
            <?php if ($invoice['cgst_amount'] > 0): ?>
            <tr>
                <td colspan="3" class="text-end">CGST (9%):</td>
                <td class="text-end"><?= number_format($invoice['cgst_amount'], 2) ?></td>
            </tr>
            <tr>
                <td colspan="3" class="text-end">SGST (9%):</td>
                <td class="text-end"><?= number_format($invoice['sgst_amount'], 2) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($invoice['igst_amount'] > 0): ?>
            <tr>
                <td colspan="3" class="text-end">IGST (18%):</td>
                <td class="text-end"><?= number_format($invoice['igst_amount'], 2) ?></td>
            </tr>
            <?php endif; ?>
            <tr class="total-row">
                <td colspan="3" class="text-end"><strong>Grand Total:</strong></td>
                <td class="text-end"><strong>₹<?= number_format($invoice['grand_total'], 2) ?></strong></td>
            </tr>
        </tfoot>
    </table>
    
    <div style="margin-top: 40px;">
        <p><small>This is a computer generated invoice.</small></p>
        <p><small>For queries, contact: support@reviewflow.com</small></p>
    </div>
</body>
</html>
    <?php
    
} catch (PDOException $e) {
    error_log('Invoice download error: ' . $e->getMessage());
    http_response_code(500);
    die('Failed to generate invoice');
}
?>
