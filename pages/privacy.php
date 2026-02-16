<?php
/**
 * Privacy Policy Page
 * ReviewFlow - Reviewer Task Management System
 */

declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Get content from database if available
$custom_content = getSetting('privacy_content', '');
$last_updated = getSetting('privacy_updated', date('F d, Y'));

$page_title = 'Privacy Policy';
$page_description = 'Privacy policy for ReviewFlow platform - how we collect, use, and protect your personal information.';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <meta name="description" content="<?php echo $page_description; ?>">
    <meta name="keywords" content="privacy, policy, data protection, security, reviewer">
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
            background: #e7f3ff;
            border-left: 4px solid #0d6efd;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .data-table {
            margin: 20px 0;
        }
        [data-bs-theme="dark"] .legal-section p,
        [data-bs-theme="dark"] .legal-section li {
            color: #e9ecef;
        }
        [data-bs-theme="dark"] .last-updated,
        [data-bs-theme="dark"] .table-of-contents {
            background: #1e1e1e;
        }
        [data-bs-theme="dark"] .highlight-box {
            background: #1a3a52;
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
            <h1 class="display-4 mb-3"><i class="bi bi-shield-check"></i> Privacy Policy</h1>
            <p class="lead">Your privacy and data security are our top priorities</p>
        </div>
    </div>

    <!-- Breadcrumb -->
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Privacy Policy</li>
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
                                <li><a href="#introduction">1. Introduction</a></li>
                                <li><a href="#information-collection">2. Information We Collect</a></li>
                                <li><a href="#use-information">3. How We Use Your Information</a></li>
                                <li><a href="#data-protection">4. Data Protection & Security</a></li>
                                <li><a href="#cookies">5. Cookies & Tracking</a></li>
                                <li><a href="#third-party">6. Third-Party Services</a></li>
                                <li><a href="#user-rights">7. Your Rights</a></li>
                                <li><a href="#data-retention">8. Data Retention</a></li>
                                <li><a href="#changes">9. Changes to Privacy Policy</a></li>
                                <li><a href="#contact">10. Contact Us</a></li>
                            </ul>
                        </div>

                        <div class="legal-section">
                            <h2 id="introduction">1. Introduction</h2>
                            <p>Welcome to <?php echo APP_NAME; ?>. We respect your privacy and are committed to protecting your personal data. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our reviewer task management platform.</p>
                            <p>By using <?php echo APP_NAME; ?>, you agree to the collection and use of information in accordance with this Privacy Policy. If you do not agree with our policies and practices, please do not use our service.</p>
                            
                            <div class="highlight-box">
                                <strong><i class="bi bi-info-circle"></i> Key Points:</strong>
                                <ul class="mb-0">
                                    <li>We collect only necessary information to provide our services</li>
                                    <li>Your data is protected with industry-standard security measures</li>
                                    <li>We never sell your personal information to third parties</li>
                                    <li>You have full control over your data and can request deletion at any time</li>
                                </ul>
                            </div>
                        </div>

                        <div class="legal-section">
                            <h2 id="information-collection">2. Information We Collect</h2>
                            
                            <h3>2.1 Personal Information</h3>
                            <p>When you register and use <?php echo APP_NAME; ?>, we collect the following personal information:</p>
                            
                            <div class="table-responsive data-table">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Information Type</th>
                                            <th>Purpose</th>
                                            <th>Required/Optional</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Name</td>
                                            <td>Account identification and communication</td>
                                            <td><span class="badge bg-danger">Required</span></td>
                                        </tr>
                                        <tr>
                                            <td>Email Address</td>
                                            <td>Account login, notifications, and communication</td>
                                            <td><span class="badge bg-danger">Required</span></td>
                                        </tr>
                                        <tr>
                                            <td>Phone Number</td>
                                            <td>Account verification and support</td>
                                            <td><span class="badge bg-danger">Required</span></td>
                                        </tr>
                                        <tr>
                                            <td>Bank Account Details</td>
                                            <td>Payment processing and withdrawals</td>
                                            <td><span class="badge bg-warning">Required for withdrawals</span></td>
                                        </tr>
                                        <tr>
                                            <td>PAN/GST Information</td>
                                            <td>Tax compliance and documentation</td>
                                            <td><span class="badge bg-info">Optional</span></td>
                                        </tr>
                                        <tr>
                                            <td>Profile Photo</td>
                                            <td>Account personalization</td>
                                            <td><span class="badge bg-info">Optional</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <h3>2.2 Automatically Collected Information</h3>
                            <p>When you access our platform, we automatically collect:</p>
                            <ul>
                                <li><strong>Device Information:</strong> Browser type, operating system, device identifiers</li>
                                <li><strong>Usage Data:</strong> Pages visited, time spent, features used, interaction patterns</li>
                                <li><strong>IP Address:</strong> For security, fraud prevention, and geographic location</li>
                                <li><strong>Cookies:</strong> Session data, preferences, and authentication tokens</li>
                                <li><strong>Log Data:</strong> Error logs, access logs, and system performance data</li>
                            </ul>

                            <h3>2.3 Review & Task Information</h3>
                            <p>When you submit reviews, we collect:</p>
                            <ul>
                                <li>Review text, ratings, and feedback</li>
                                <li>Screenshots and proof of purchase/usage</li>
                                <li>Product links and order details</li>
                                <li>Submission timestamps and completion status</li>
                            </ul>

                            <h3>2.4 Financial Information</h3>
                            <p>For payment processing, we collect:</p>
                            <ul>
                                <li>Bank account details (for withdrawals)</li>
                                <li>UPI IDs (if applicable)</li>
                                <li>Payment gateway transaction IDs</li>
                                <li>Tax information (PAN, GST numbers)</li>
                            </ul>
                            <p><strong>Note:</strong> We do not store complete credit/debit card information. Payment processing is handled by secure third-party payment gateways.</p>
                        </div>

                        <div class="legal-section">
                            <h2 id="use-information">3. How We Use Your Information</h2>
                            <p>We use your information for the following purposes:</p>

                            <h3>3.1 Service Delivery</h3>
                            <ul>
                                <li>Create and manage your account</li>
                                <li>Assign review tasks and track completion</li>
                                <li>Process payments and manage your wallet</li>
                                <li>Facilitate communication between users and businesses</li>
                                <li>Provide customer support</li>
                            </ul>

                            <h3>3.2 Platform Improvement</h3>
                            <ul>
                                <li>Analyze usage patterns to improve user experience</li>
                                <li>Develop new features and services</li>
                                <li>Conduct research and analytics</li>
                                <li>Monitor and optimize platform performance</li>
                            </ul>

                            <h3>3.3 Communication</h3>
                            <ul>
                                <li>Send task notifications and updates</li>
                                <li>Provide customer support and respond to inquiries</li>
                                <li>Send important security and account alerts</li>
                                <li>Share promotional offers and referral bonuses (with consent)</li>
                            </ul>

                            <h3>3.4 Security & Compliance</h3>
                            <ul>
                                <li>Detect and prevent fraud, abuse, and violations</li>
                                <li>Verify user identity and review authenticity</li>
                                <li>Comply with legal obligations and regulations</li>
                                <li>Enforce our Terms and Conditions</li>
                                <li>Protect the rights and safety of all users</li>
                            </ul>

                            <h3>3.5 Legal Obligations</h3>
                            <ul>
                                <li>Comply with tax reporting requirements</li>
                                <li>Respond to legal requests and court orders</li>
                                <li>Maintain financial records as required by law</li>
                            </ul>
                        </div>

                        <div class="legal-section">
                            <h2 id="data-protection">4. Data Protection & Security</h2>
                            
                            <div class="highlight-box">
                                <strong><i class="bi bi-shield-lock"></i> Security Measures We Implement:</strong>
                            </div>

                            <h3>4.1 Technical Security</h3>
                            <ul>
                                <li><strong>Encryption:</strong> All data transmitted is encrypted using SSL/TLS protocols</li>
                                <li><strong>Password Protection:</strong> Passwords are hashed using bcrypt algorithm with cost factor 12</li>
                                <li><strong>Secure Sessions:</strong> Session data protected with secure, httponly, and samesite cookies</li>
                                <li><strong>Database Security:</strong> Parameterized queries to prevent SQL injection</li>
                                <li><strong>Input Validation:</strong> All user inputs are sanitized and validated</li>
                                <li><strong>Rate Limiting:</strong> Protection against brute force and DDoS attacks</li>
                            </ul>

                            <h3>4.2 Administrative Security</h3>
                            <ul>
                                <li>Role-based access control (admin vs. user permissions)</li>
                                <li>Regular security audits and vulnerability assessments</li>
                                <li>Employee training on data protection practices</li>
                                <li>Incident response procedures</li>
                                <li>Regular software updates and patches</li>
                            </ul>

                            <h3>4.3 Data Breach Notification</h3>
                            <p>In the unlikely event of a data breach affecting your personal information, we will:</p>
                            <ul>
                                <li>Notify affected users within 72 hours</li>
                                <li>Provide details about the breach and affected data</li>
                                <li>Explain steps we're taking to address the breach</li>
                                <li>Advise on protective measures you should take</li>
                                <li>Report to relevant authorities as required by law</li>
                            </ul>
                        </div>

                        <div class="legal-section">
                            <h2 id="cookies">5. Cookies & Tracking Technologies</h2>
                            
                            <h3>5.1 What Are Cookies?</h3>
                            <p>Cookies are small text files stored on your device that help us provide and improve our service. We use cookies for session management, authentication, and user preferences.</p>

                            <h3>5.2 Types of Cookies We Use</h3>
                            <div class="table-responsive data-table">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Cookie Type</th>
                                            <th>Purpose</th>
                                            <th>Duration</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Session Cookies</td>
                                            <td>Maintain your login session and authentication</td>
                                            <td><?php echo SESSION_TIMEOUT / 60; ?> minutes</td>
                                        </tr>
                                        <tr>
                                            <td>Preference Cookies</td>
                                            <td>Remember your settings (e.g., dark mode)</td>
                                            <td>30 days</td>
                                        </tr>
                                        <tr>
                                            <td>Referral Cookies</td>
                                            <td>Track referral codes and bonuses</td>
                                            <td>30 days</td>
                                        </tr>
                                        <tr>
                                            <td>Security Cookies</td>
                                            <td>Protect against unauthorized access</td>
                                            <td>Session</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <h3>5.3 Managing Cookies</h3>
                            <p>You can control cookies through your browser settings. However, disabling certain cookies may affect platform functionality and your user experience.</p>
                        </div>

                        <div class="legal-section">
                            <h2 id="third-party">6. Third-Party Services</h2>
                            
                            <p>We use third-party services to operate our platform. These services have access to your information only to perform specific tasks on our behalf and are obligated to protect your data.</p>

                            <h3>6.1 Payment Gateways</h3>
                            <ul>
                                <li><strong>Razorpay:</strong> For secure payment processing and withdrawals</li>
                                <li><strong>PayU Money:</strong> Alternative payment processing</li>
                                <li>These services have their own privacy policies governing data handling</li>
                            </ul>

                            <h3>6.2 Email Services</h3>
                            <ul>
                                <li>SMTP services for sending notifications and communications</li>
                                <li>Email addresses used only for platform-related communications</li>
                            </ul>

                            <h3>6.3 Hosting & Infrastructure</h3>
                            <ul>
                                <li>Web hosting providers for platform availability</li>
                                <li>Database hosting with security and backup measures</li>
                                <li>CDN services for content delivery</li>
                            </ul>

                            <h3>6.4 Analytics (If Applicable)</h3>
                            <p>We may use analytics services to understand platform usage and improve user experience. Such services collect aggregated, anonymized data.</p>

                            <div class="alert alert-warning">
                                <strong><i class="bi bi-exclamation-triangle"></i> Important:</strong> We do not sell or rent your personal information to third parties for marketing purposes.
                            </div>
                        </div>

                        <div class="legal-section">
                            <h2 id="user-rights">7. Your Rights</h2>
                            
                            <p>You have the following rights regarding your personal data:</p>

                            <h3>7.1 Access</h3>
                            <ul>
                                <li>Request a copy of all personal data we hold about you</li>
                                <li>Access your account information anytime through your profile</li>
                            </ul>

                            <h3>7.2 Correction</h3>
                            <ul>
                                <li>Update your profile information at any time</li>
                                <li>Request correction of inaccurate or incomplete data</li>
                            </ul>

                            <h3>7.3 Deletion</h3>
                            <ul>
                                <li>Request deletion of your account and associated data</li>
                                <li>Note: Some data may be retained for legal compliance (e.g., financial records)</li>
                            </ul>

                            <h3>7.4 Portability</h3>
                            <ul>
                                <li>Request your data in a structured, machine-readable format</li>
                                <li>Export your review history and transaction data</li>
                            </ul>

                            <h3>7.5 Objection</h3>
                            <ul>
                                <li>Opt-out of promotional communications (marketing emails)</li>
                                <li>Object to certain data processing activities</li>
                            </ul>

                            <h3>7.6 Withdrawal of Consent</h3>
                            <ul>
                                <li>Withdraw consent for data processing at any time</li>
                                <li>Note: This may limit your ability to use certain features</li>
                            </ul>

                            <p>To exercise these rights, please contact us at <a href="mailto:<?php echo SMTP_FROM; ?>"><?php echo SMTP_FROM; ?></a></p>
                        </div>

                        <div class="legal-section">
                            <h2 id="data-retention">8. Data Retention</h2>
                            
                            <p>We retain your personal data for as long as necessary to provide our services and comply with legal obligations:</p>

                            <div class="table-responsive data-table">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Data Type</th>
                                            <th>Retention Period</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Account Information</td>
                                            <td>Duration of account + 1 year after deletion</td>
                                        </tr>
                                        <tr>
                                            <td>Transaction Records</td>
                                            <td>7 years (tax compliance requirement)</td>
                                        </tr>
                                        <tr>
                                            <td>Review Content</td>
                                            <td>Duration of account + 6 months</td>
                                        </tr>
                                        <tr>
                                            <td>Support Communications</td>
                                            <td>3 years after resolution</td>
                                        </tr>
                                        <tr>
                                            <td>System Logs</td>
                                            <td>90 days (unless needed for investigation)</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <p>After the retention period, data is securely deleted or anonymized.</p>
                        </div>

                        <div class="legal-section">
                            <h2 id="changes">9. Changes to Privacy Policy</h2>
                            <p>We may update this Privacy Policy from time to time to reflect changes in our practices or legal requirements. When we make changes:</p>
                            <ul>
                                <li>We will update the "Last Updated" date</li>
                                <li>For significant changes, we will notify you via email or platform notification</li>
                                <li>Continued use after changes constitutes acceptance</li>
                                <li>You can review the previous version upon request</li>
                            </ul>
                        </div>

                        <div class="legal-section">
                            <h2 id="contact">10. Contact Us</h2>
                            <p>If you have questions, concerns, or requests regarding this Privacy Policy or your personal data, please contact us:</p>
                            
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Data Protection Officer</h5>
                                    <p class="mb-2"><strong><i class="bi bi-building"></i> Company:</strong> <?php echo APP_NAME; ?></p>
                                    <p class="mb-2"><strong><i class="bi bi-envelope"></i> Email:</strong> <a href="mailto:<?php echo SMTP_FROM; ?>"><?php echo SMTP_FROM; ?></a></p>
                                    <p class="mb-2"><strong><i class="bi bi-telephone"></i> Support:</strong> <?php echo WHATSAPP_SUPPORT; ?></p>
                                    <p class="mb-2"><strong><i class="bi bi-globe"></i> Website:</strong> <a href="<?php echo APP_URL; ?>"><?php echo APP_URL; ?></a></p>
                                    <p class="mb-0"><strong><i class="bi bi-clock"></i> Response Time:</strong> Within 48 hours</p>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-success mt-4">
                            <i class="bi bi-check-circle"></i> <strong>Your Privacy Matters:</strong> We are committed to protecting your personal information and maintaining transparency in our data practices. Thank you for trusting <?php echo APP_NAME; ?>.
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
                                    <a href="<?php echo APP_URL; ?>/pages/refund.php" class="text-decoration-none">
                                        <i class="bi bi-cash-coin"></i> Refund Policy
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
