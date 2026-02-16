<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// Check if seller is logged in
if (!isset($_SESSION['seller_id'])) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Access denied</div>';
    exit;
}

$seller_id = $_SESSION['seller_id'];
$invoice_id = (int) ($_GET['id'] ?? 0);

if ($invoice_id <= 0) {
    echo '<div class="alert alert-danger">Invalid invoice ID</div>';
    exit;
}

try {
    // Get invoice details
    $stmt = $pdo->prepare("
        SELECT ti.*, rr.product_name, rr.brand_name, rr.product_link,
               s.name as seller_name, s.email as seller_email, s.company_name,
               s.gst_number as seller_gst, s.billing_address as seller_address
        FROM tax_invoices ti
        JOIN review_requests rr ON ti.review_request_id = rr.id
        JOIN sellers s ON ti.seller_id = s.id
        WHERE ti.id = ? AND ti.seller_id = ?
    ");
    $stmt->execute([$invoice_id, $seller_id]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        echo '<div class="alert alert-danger">Invoice not found</div>';
        exit;
    }
    
} catch (PDOException $e) {
    error_log('Invoice view error: ' . $e->getMessage());
    echo '<div class="alert alert-danger">Failed to load invoice</div>';
    exit;
}
?>

<!-- GST Invoice Template -->
<div class="invoice-container p-4">
    <div class="text-center mb-4">
        <h2><?= APP_NAME ?></h2>
        <p class="text-muted">Tax Invoice</p>
    </div>
    
    <div class="row mb-4">
        <div class="col-6">
            <strong>Invoice Number:</strong> <?= htmlspecialchars($invoice['invoice_number']) ?><br>
            <strong>Invoice Date:</strong> <?= date('d-M-Y', strtotime($invoice['invoice_date'])) ?>
        </div>
        <div class="col-6 text-end">
            <strong>Order ID:</strong> #<?= $invoice['review_request_id'] ?>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-6">
            <h6>From:</h6>
            <strong><?= htmlspecialchars($invoice['platform_legal_name'] ?? APP_NAME) ?></strong><br>
            <?php if ($invoice['platform_gst']): ?>
                GST: <?= htmlspecialchars($invoice['platform_gst']) ?><br>
            <?php endif; ?>
            <?= nl2br(htmlspecialchars($invoice['platform_address'] ?? '')) ?>
        </div>
        <div class="col-6">
            <h6>To:</h6>
            <strong><?= htmlspecialchars($invoice['seller_name']) ?></strong><br>
            <?php if ($invoice['company_name']): ?>
                <?= htmlspecialchars($invoice['company_name']) ?><br>
            <?php endif; ?>
            <?php if ($invoice['seller_gst']): ?>
                GST: <?= htmlspecialchars($invoice['seller_gst']) ?><br>
            <?php endif; ?>
            <?= nl2br(htmlspecialchars($invoice['seller_address'])) ?>
        </div>
    </div>
    
    <table class="table table-bordered">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Description</th>
                <th>HSN/SAC</th>
                <th class="text-end">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>
                    Review Services<br>
                    <small class="text-muted">
                        <?= htmlspecialchars($invoice['product_name']) ?> - <?= htmlspecialchars($invoice['brand_name']) ?>
                    </small>
                </td>
                <td><?= htmlspecialchars($invoice['sac_code'] ?? SAC_CODE) ?></td>
                <td class="text-end">₹<?= number_format($invoice['base_amount'], 2) ?></td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                <td class="text-end">₹<?= number_format($invoice['base_amount'], 2) ?></td>
            </tr>
            <?php if ($invoice['cgst_amount'] > 0): ?>
            <tr>
                <td colspan="3" class="text-end">CGST (9%):</td>
                <td class="text-end">₹<?= number_format($invoice['cgst_amount'], 2) ?></td>
            </tr>
            <tr>
                <td colspan="3" class="text-end">SGST (9%):</td>
                <td class="text-end">₹<?= number_format($invoice['sgst_amount'], 2) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($invoice['igst_amount'] > 0): ?>
            <tr>
                <td colspan="3" class="text-end">IGST (18%):</td>
                <td class="text-end">₹<?= number_format($invoice['igst_amount'], 2) ?></td>
            </tr>
            <?php endif; ?>
            <tr class="table-primary">
                <td colspan="3" class="text-end"><strong>Grand Total:</strong></td>
                <td class="text-end"><strong>₹<?= number_format($invoice['grand_total'], 2) ?></strong></td>
            </tr>
        </tfoot>
    </table>
    
    <div class="mt-4">
        <p class="small text-muted">
            This is a computer generated invoice. No signature required.
        </p>
    </div>
</div>

<style>
.invoice-container {
    background: white;
    max-width: 800px;
    margin: 0 auto;
}

@media print {
    body { background: white; }
    .invoice-container { box-shadow: none; }
}
</style>
