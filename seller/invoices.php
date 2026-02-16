<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/header.php';

try {
    // Get all invoices for this seller
    $stmt = $pdo->prepare("
        SELECT ti.*, rr.product_name, rr.brand_name 
        FROM tax_invoices ti
        JOIN review_requests rr ON ti.review_request_id = rr.id
        WHERE ti.seller_id = ?
        ORDER BY ti.created_at DESC
    ");
    $stmt->execute([$seller_id]);
    $invoices = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Invoices fetch error: ' . $e->getMessage());
    $invoices = [];
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Invoices</li>
                </ol>
            </nav>
            <h3 class="mb-0">Tax Invoices</h3>
            <p class="text-muted">View and download your GST invoices</p>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($invoices)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-receipt" style="font-size: 3rem; color: #cbd5e1;"></i>
                    <p class="text-muted mt-3 mb-0">No invoices generated yet</p>
                    <p class="small text-muted">Invoices are generated after successful payment</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice No.</th>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Order ID</th>
                                <th>Base Amount</th>
                                <th>GST</th>
                                <th>Total Amount</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong>
                                    </td>
                                    <td><?= date('d M Y', strtotime($invoice['invoice_date'])) ?></td>
                                    <td>
                                        <div><?= htmlspecialchars($invoice['product_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($invoice['brand_name']) ?></small>
                                    </td>
                                    <td>#<?= $invoice['review_request_id'] ?></td>
                                    <td>₹<?= number_format($invoice['base_amount'], 2) ?></td>
                                    <td>₹<?= number_format($invoice['total_gst'], 2) ?></td>
                                    <td><strong>₹<?= number_format($invoice['grand_total'], 2) ?></strong></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" 
                                                    onclick="viewInvoice(<?= $invoice['id'] ?>)">
                                                <i class="bi bi-eye"></i> View
                                            </button>
                                            <button type="button" class="btn btn-outline-success" 
                                                    onclick="downloadInvoice(<?= $invoice['id'] ?>)">
                                                <i class="bi bi-download"></i> Download
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Invoice Preview Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Invoice Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="invoiceContent">
                <!-- Invoice content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" onclick="printInvoice()">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function viewInvoice(invoiceId) {
    // Invoice preview functionality
    // NOTE: Requires invoice-view.php to be implemented
    const modal = new bootstrap.Modal(document.getElementById('invoiceModal'));
    document.getElementById('invoiceContent').innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading invoice...</p>
        </div>
    `;
    modal.show();
    
    // In production, fetch invoice HTML via AJAX
    fetch('invoice-view.php?id=' + invoiceId)
        .then(response => response.text())
        .then(html => {
            document.getElementById('invoiceContent').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('invoiceContent').innerHTML = `
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    Invoice preview feature is under development. Please use download option.
                </div>
            `;
        });
}

function downloadInvoice(invoiceId) {
    // NOTE: Requires invoice-download.php to be implemented
    window.location.href = 'invoice-download.php?id=' + invoiceId;
    
}

function printInvoice() {
    const content = document.getElementById('invoiceContent').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Invoice</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                @media print {
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            ${content}
            <scr" + "ipt>
                window.onload = function() {
                    window.print();
                    setTimeout(function() { window.close(); }, 100);
                };
            </scr" + "ipt>
        </body>
        </html>
    `);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
