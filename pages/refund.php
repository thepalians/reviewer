<?php
/**
 * Refund & Cancellation Policy Page
 * ReviewFlow - Reviewer Task Management System
 */

declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Get content from database if available
$custom_content = getSetting('refund_content', '');
$last_updated = getSetting('refund_updated', date('F d, Y'));

$page_title = 'Refund & Cancellation Policy';
$page_description = 'Refund and cancellation policy for ReviewFlow platform - terms and conditions for refund requests and cancellations.';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <meta name="description" content="<?php echo $page_description; ?>">
    <meta name="keywords" content="refund, cancellation, policy, reviewer, payment">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo $page_title; ?>">
    <meta property="og:description" content="<?php echo $page_description; ?>">
    <meta property="og:type" content="website">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        .legal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0 40px;
            margin-top: -70px;
            padding-top: 130px;
        }
        .legal-content {
            padding: 40px 0;
        }
        .legal-section {
            margin-bottom: 40px;
        }
        .legal-section h2 {
            color: #667eea;
            margin-top: 30px;
            margin-bottom: 20px;
            font-size: 1.8rem;
            font-weight: 600;
        }
        .legal-section h3 {
            color: #764ba2;
            margin-top: 25px;
            margin-bottom: 15px;
            font-size: 1.4rem;
            font-weight: 600;
        }
        .legal-section p, .legal-section li {
            font-size: 1.05rem;
            line-height: 1.8;
            color: #333;
            margin-bottom: 15px;
        }
        .breadcrumb {
            background: transparent;
            padding: 15px 0;
        }
        .last-updated {
            background: #f8f9fa;
            padding: 15px 20px;
            border-left: 4px solid #667eea;
            margin-bottom: 30px;
            border-radius: 5px;
        }
        .table-of-contents {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 40px;
        }
        .table-of-contents ul {
            list-style: none;
            padding-left: 0;
        }
        .table-of-contents li {
            margin: 10px 0;
        }
        .table-of-contents a {
            text-decoration: none;
            color: #667eea;
            font-weight: 500;
        }
        .table-of-contents a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        .highlight-box {
            background: #d1ecf1;
            border-left: 4px solid #0dcaf0;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .process-step {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }
        .process-step .step-number {
            display: inline-block;
            width: 35px;
            height: 35px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 35px;
            font-weight: bold;
            margin-right: 15px;
        }
        [data-bs-theme="dark"] .legal-section p,
        [data-bs-theme="dark"] .legal-section li {
            color: #e9ecef;
        }
        [data-bs-theme="dark"] .last-updated,
        [data-bs-theme="dark"] .table-of-contents,
        [data-bs-theme="dark"] .process-step {
            background: #1e1e1e;
        }
        [data-bs-theme="dark"] .highlight-box {
            background: #1a4d5c;
        }
    </style>
</head>
<body>
    <?php 
    // Set BASE_URL for header
    if (!defined('BASE_URL')) {
        define('BASE_URL', APP_URL);
    }
    include __DIR__ . '/../includes/header.php'; 
    ?>

    <!-- Header Section -->
    <div class="legal-header">
        <div class="container">
            <h1 class="display-4 mb-3"><i class="bi bi-cash-coin"></i> Refund & Cancellation Policy</h1>
            <p class="lead">Understanding refunds and cancellations on our platform</p>
        </div>
    </div>

    <!-- Breadcrumb -->
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Refund & Cancellation Policy</li>
            </ol>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="legal-content">
        <div class="container">
            <div class="row">
                <div class="col-lg-10 mx-auto">
                    
                    <!-- Last Updated -->
                    <div class="last-updated">
                        <strong><i class="bi bi-calendar-check"></i> Last Updated:</strong> <?php echo htmlspecialchars($last_updated); ?>
                    </div>

                    <?php if (!empty($custom_content)): ?>
                        <!-- Custom Content from Database -->
                        <div class="legal-section">
                            <?php echo $custom_content; ?>
                        </div>
                    <?php else: ?>
                        <!-- Default Content -->
                        
                        <!-- Table of Contents -->
                        <div class="table-of-contents">
                            <h4 class="mb-3"><i class="bi bi-list-ul"></i> Table of Contents</h4>
                            <ul>
                                <li><a href="#overview">1. Policy Overview</a></li>
                                <li><a href="#refund-eligibility">2. Refund Eligibility</a></li>
                                <li><a href="#cancellation-policy">3. Cancellation Policy</a></li>
                                <li><a href="#refund-process">4. Refund Request Process</a></li>
                                <li><a href="#processing-time">5. Processing Time</a></li>
                                <li><a href="#non-refundable">6. Non-Refundable Items</a></li>
                                <li><a href="#partial-refunds">7. Partial Refunds</a></li>
                                <li><a href="#dispute-resolution">8. Dispute Resolution</a></li>
                                <li><a href="#contact">9. Contact Information</a></li>
                            </ul>
                        </div>

                        <div class="legal-section">
                            <h2 id="overview">1. Policy Overview</h2>
                            <p>At <?php echo APP_NAME; ?>, we strive to ensure a fair and transparent experience for all users. This Refund & Cancellation Policy outlines the circumstances under which refunds may be issued and how cancellations are handled on our platform.</p>
                            
                            <div class="highlight-box">
                                <strong><i class="bi bi-info-circle"></i> Important Note:</strong>
                                <p class="mb-0"><?php echo APP_NAME; ?> operates as a review task management platform. Refund eligibility depends on whether you are a <strong>reviewer</strong> (earning for completed reviews) or a <strong>business</strong> (paying for review services). This policy covers both scenarios.</p>
                            </div>
                        </div>

                        <div class="legal-section">
                            <h2 id="refund-eligibility">2. Refund Eligibility</h2>
                            
                            <h3>2.1 For Reviewers (Service Providers)</h3>
                            <p>As a reviewer, you may be eligible for a refund in the following situations:</p>
                            <ul>
                                <li><strong>Task Assignment Error:</strong> If you were assigned a task by mistake or duplicate task</li>
                                <li><strong>Payment Discrepancy:</strong> If the payment amount doesn't match the agreed-upon rate</li>
                                <li><strong>Incorrect Task Details:</strong> If the task requirements were misrepresented</li>
                                <li><strong>Technical Issues:</strong> If platform technical issues prevented task completion</li>
                                <li><strong>Unfair Rejection:</strong> If your submitted review was rejected without valid reason</li>
                            </ul>

                            <h3>2.2 For Businesses (Service Buyers)</h3>
                            <p>Businesses may request refunds in these scenarios:</p>
                            <ul>
                                <li><strong>Service Not Delivered:</strong> Review task was not completed within the agreed timeframe</li>
                                <li><strong>Quality Issues:</strong> Submitted reviews don't meet specified quality standards</li>
                                <li><strong>Fraudulent Activity:</strong> Evidence of fake or bot-generated reviews</li>
                                <li><strong>Platform Malfunction:</strong> Technical issues prevented proper task assignment</li>
                                <li><strong>Duplicate Charges:</strong> You were charged multiple times for the same service</li>
                            </ul>

                            <h3>2.3 General Eligibility Criteria</h3>
                            <p>All refund requests must meet these criteria:</p>
                            <ul>
                                <li>Request must be submitted within <strong>7 days</strong> of the transaction</li>
                                <li>Valid reason must be provided with supporting evidence</li>
                                <li>Account must be in good standing (no violations)</li>
                                <li>Transaction must be verifiable in our system</li>
                            </ul>
                        </div>

                        <div class="legal-section">
                            <h2 id="cancellation-policy">3. Cancellation Policy</h2>
                            
                            <h3>3.1 Task Cancellation by Reviewers</h3>
                            <p>Reviewers can cancel assigned tasks under these conditions:</p>
                            <ul>
                                <li><strong>Before Starting:</strong> Cancel anytime before beginning the review (no penalty)</li>
                                <li><strong>Valid Reasons:</strong> Medical emergency, personal circumstances, technical issues</li>
                                <li><strong>Penalty:</strong> Multiple cancellations may affect your account rating and future task assignments</li>
                                <li><strong>Notice Period:</strong> Minimum 24 hours notice recommended</li>
                            </ul>

                            <div class="process-step">
                                <span class="step-number">1</span>
                                <strong>Navigate to "My Tasks"</strong> - Go to your dashboard and select the task you wish to cancel
                            </div>
                            <div class="process-step">
                                <span class="step-number">2</span>
                                <strong>Click "Cancel Task"</strong> - Select the cancellation option and provide a reason
                            </div>
                            <div class="process-step">
                                <span class="step-number">3</span>
                                <strong>Confirmation</strong> - You'll receive confirmation once the cancellation is processed
                            </div>

                            <h3>3.2 Task Cancellation by Businesses</h3>
                            <p>Businesses can cancel review tasks in these situations:</p>
                            <ul>
                                <li><strong>Before Assignment:</strong> Cancel anytime before assigning to reviewers (full refund)</li>
                                <li><strong>After Assignment:</strong> Partial refund based on task progress</li>
                                <li><strong>Non-Performance:</strong> If reviewer fails to complete within deadline</li>
                                <li><strong>Refund Amount:</strong> Depends on completion percentage and time elapsed</li>
                            </ul>

                            <h3>3.3 Platform-Initiated Cancellations</h3>
                            <p><?php echo APP_NAME; ?> may cancel tasks if:</p>
                            <ul>
                                <li>Violation of Terms & Conditions detected</li>
                                <li>Fraudulent activity is suspected</li>
                                <li>Technical issues prevent proper execution</li>
                                <li>Either party requests with valid reason</li>
                            </ul>
                            <p><strong>In such cases, full refunds will be processed automatically.</strong></p>
                        </div>

                        <div class="legal-section">
                            <h2 id="refund-process">4. Refund Request Process</h2>
                            
                            <p>To request a refund, follow these steps:</p>

                            <div class="process-step">
                                <span class="step-number">1</span>
                                <strong>Login to Your Account</strong> - Access your <?php echo APP_NAME; ?> dashboard
                            </div>

                            <div class="process-step">
                                <span class="step-number">2</span>
                                <strong>Navigate to Transaction History</strong> - Find the transaction you want to refund
                            </div>

                            <div class="process-step">
                                <span class="step-number">3</span>
                                <strong>Click "Request Refund"</strong> - Select the transaction and click the refund button
                            </div>

                            <div class="process-step">
                                <span class="step-number">4</span>
                                <strong>Provide Details</strong> - Fill out the refund request form with:
                                <ul class="mt-2 mb-0">
                                    <li>Reason for refund request</li>
                                    <li>Supporting evidence (screenshots, emails, etc.)</li>
                                    <li>Preferred refund method</li>
                                </ul>
                            </div>

                            <div class="process-step">
                                <span class="step-number">5</span>
                                <strong>Submit Request</strong> - Review your information and submit the request
                            </div>

                            <div class="process-step">
                                <span class="step-number">6</span>
                                <strong>Await Review</strong> - Our team will review your request within 2-3 business days
                            </div>

                            <div class="process-step">
                                <span class="step-number">7</span>
                                <strong>Receive Decision</strong> - You'll be notified via email about the refund decision
                            </div>

                            <p class="mt-3"><strong>Alternative:</strong> You can also request refunds by emailing <a href="mailto:<?php echo SMTP_FROM; ?>"><?php echo SMTP_FROM; ?></a> with your transaction details.</p>
                        </div>

                        <div class="legal-section">
                            <h2 id="processing-time">5. Processing Time</h2>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Stage</th>
                                            <th>Timeline</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>Request Acknowledgment</strong></td>
                                            <td>Within 24 hours</td>
                                            <td>You'll receive confirmation that we've received your request</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Request Review</strong></td>
                                            <td>2-3 business days</td>
                                            <td>Our team will evaluate your request and supporting evidence</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Decision Notification</strong></td>
                                            <td>Within 3 business days</td>
                                            <td>You'll be notified whether the refund is approved or denied</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Refund Processing</strong></td>
                                            <td>3-7 business days</td>
                                            <td>Approved refunds are processed to your original payment method</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Bank Credit</strong></td>
                                            <td>5-10 business days</td>
                                            <td>Time for refund to appear in your bank account (varies by bank)</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="alert alert-info">
                                <strong><i class="bi bi-clock-history"></i> Note:</strong> Total refund processing time from request to bank credit: <strong>10-20 business days</strong>. Processing times may vary during holidays or peak periods.
                            </div>

                            <h3>5.1 Refund Methods</h3>
                            <ul>
                                <li><strong>Original Payment Method:</strong> Refund to the card/account used for payment</li>
                                <li><strong>Wallet Credit:</strong> Instant credit to your <?php echo APP_NAME; ?> wallet</li>
                                <li><strong>Bank Transfer:</strong> Direct transfer to your registered bank account</li>
                                <li><strong>UPI:</strong> Refund to your registered UPI ID (if applicable)</li>
                            </ul>
                        </div>

                        <div class="legal-section">
                            <h2 id="non-refundable">6. Non-Refundable Items</h2>
                            
                            <p>The following are generally <strong>NOT eligible</strong> for refunds:</p>

                            <div class="highlight-box">
                                <ul class="mb-0">
                                    <li><strong>Completed & Approved Tasks:</strong> Reviews that have been completed and approved cannot be refunded</li>
                                    <li><strong>Paid Commissions:</strong> Platform commission fees are non-refundable once service is rendered</li>
                                    <li><strong>Withdrawal Fees:</strong> Payment gateway charges for withdrawals</li>
                                    <li><strong>Expired Tasks:</strong> Tasks that exceeded the deadline without completion</li>
                                    <li><strong>Referral Bonuses:</strong> Promotional bonuses and referral credits</li>
                                    <li><strong>Voluntary Cancellations:</strong> Tasks cancelled by user without valid reason (after starting)</li>
                                    <li><strong>Policy Violations:</strong> Transactions related to account violations or fraudulent activity</li>
                                    <li><strong>Change of Mind:</strong> Simple change of mind after task acceptance</li>
                                </ul>
                            </div>

                            <h3>6.1 GST & Tax Charges</h3>
                            <p>GST charges (<?php echo GST_RATE; ?>%) and other applicable taxes are non-refundable as they are remitted to the government as per legal requirements.</p>
                        </div>

                        <div class="legal-section">
                            <h2 id="partial-refunds">7. Partial Refunds</h2>
                            
                            <p>In certain situations, partial refunds may be issued:</p>

                            <h3>7.1 When Partial Refunds Apply</h3>
                            <ul>
                                <li><strong>Incomplete Tasks:</strong> If task is partially completed before cancellation</li>
                                <li><strong>Quality Issues:</strong> If submitted work meets some but not all requirements</li>
                                <li><strong>Late Cancellations:</strong> Cancellations after work has commenced</li>
                                <li><strong>Disputed Work:</strong> When there's disagreement on work quality</li>
                            </ul>

                            <h3>7.2 Partial Refund Calculation</h3>
                            <p>Partial refunds are calculated based on:</p>
                            <ul>
                                <li>Percentage of task completed</li>
                                <li>Time elapsed since task assignment</li>
                                <li>Resources utilized</li>
                                <li>Platform commission (non-refundable portion)</li>
                            </ul>

                            <div class="alert alert-warning">
                                <strong>Example:</strong> If a task is 60% complete when cancelled, you may receive approximately 40% refund, minus platform commission and processing fees.
                            </div>
                        </div>

                        <div class="legal-section">
                            <h2 id="dispute-resolution">8. Dispute Resolution</h2>
                            
                            <h3>8.1 Refund Denial</h3>
                            <p>If your refund request is denied, you will receive:</p>
                            <ul>
                                <li>Detailed explanation of the denial reason</li>
                                <li>Supporting evidence for the decision</li>
                                <li>Option to appeal the decision</li>
                            </ul>

                            <h3>8.2 Appeal Process</h3>
                            <p>To appeal a denied refund request:</p>
                            <ol>
                                <li>Submit an appeal within 7 days of denial notification</li>
                                <li>Provide additional evidence supporting your case</li>
                                <li>Wait for senior team review (3-5 business days)</li>
                                <li>Receive final decision via email</li>
                            </ol>

                            <h3>8.3 Mediation</h3>
                            <p>For unresolved disputes, we offer:</p>
                            <ul>
                                <li>Third-party mediation services</li>
                                <li>Direct communication with support team</li>
                                <li>Escalation to management for review</li>
                            </ul>

                            <p>We are committed to resolving all disputes fairly and promptly.</p>
                        </div>

                        <div class="legal-section">
                            <h2 id="contact">9. Contact Information</h2>
                            
                            <p>For questions, refund requests, or assistance with this policy, please contact us:</p>
                            
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Refund Support Team</h5>
                                    <p class="mb-2"><strong><i class="bi bi-building"></i> Company:</strong> <?php echo APP_NAME; ?></p>
                                    <p class="mb-2"><strong><i class="bi bi-envelope"></i> Email:</strong> <a href="mailto:<?php echo SMTP_FROM; ?>"><?php echo SMTP_FROM; ?></a></p>
                                    <p class="mb-2"><strong><i class="bi bi-telephone"></i> WhatsApp Support:</strong> <?php echo WHATSAPP_SUPPORT; ?></p>
                                    <p class="mb-2"><strong><i class="bi bi-globe"></i> Website:</strong> <a href="<?php echo APP_URL; ?>"><?php echo APP_URL; ?></a></p>
                                    <p class="mb-2"><strong><i class="bi bi-clock"></i> Support Hours:</strong> Monday - Friday, 10:00 AM - 6:00 PM IST</p>
                                    <p class="mb-0"><strong><i class="bi bi-reply"></i> Response Time:</strong> Within 24-48 hours</p>
                                </div>
                            </div>

                            <div class="alert alert-primary mt-4">
                                <strong><i class="bi bi-info-circle"></i> Pro Tip:</strong> Include your transaction ID, order number, and detailed description when contacting support for faster resolution.
                            </div>
                        </div>

                        <div class="alert alert-success mt-4">
                            <i class="bi bi-check-circle"></i> <strong>Commitment to Fairness:</strong> We are committed to processing all refund requests fairly and transparently. Your satisfaction is our priority.
                        </div>

                    <?php endif; ?>

                    <!-- Related Links -->
                    <div class="card mt-5">
                        <div class="card-body">
                            <h5 class="card-title">Related Legal Documents</h5>
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <a href="<?php echo APP_URL; ?>/pages/terms.php" class="text-decoration-none">
                                        <i class="bi bi-file-text"></i> Terms & Conditions
                                    </a>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <a href="<?php echo APP_URL; ?>/pages/privacy.php" class="text-decoration-none">
                                        <i class="bi bi-shield-check"></i> Privacy Policy
                                    </a>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <a href="<?php echo APP_URL; ?>/pages/disclaimer.php" class="text-decoration-none">
                                        <i class="bi bi-exclamation-triangle"></i> Disclaimer
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Smooth Scroll for TOC Links -->
    <script>
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
