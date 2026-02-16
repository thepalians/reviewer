<?php
/**
 * Terms & Conditions Page
 * ReviewFlow - Reviewer Task Management System
 */

declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Get content from database if available
$custom_content = getSetting('terms_content', '');
$last_updated = getSetting('terms_updated', date('F d, Y'));

$page_title = 'Terms & Conditions';
$page_description = 'Terms and conditions for using ReviewFlow platform - rules, obligations, and policies governing our service.';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <meta name="description" content="<?php echo $page_description; ?>">
    <meta name="keywords" content="terms, conditions, reviewer, platform, policy">
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
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        [data-bs-theme="dark"] .legal-section p,
        [data-bs-theme="dark"] .legal-section li {
            color: #e9ecef;
        }
        [data-bs-theme="dark"] .last-updated,
        [data-bs-theme="dark"] .table-of-contents {
            background: #1e1e1e;
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
            <h1 class="display-4 mb-3"><i class="bi bi-file-text"></i> Terms & Conditions</h1>
            <p class="lead">Please read these terms carefully before using our service</p>
        </div>
    </div>

    <!-- Breadcrumb -->
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Terms & Conditions</li>
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
                                <li><a href="#acceptance">1. Acceptance of Terms</a></li>
                                <li><a href="#service-description">2. Service Description</a></li>
                                <li><a href="#user-obligations">3. User Obligations</a></li>
                                <li><a href="#payment-terms">4. Payment Terms</a></li>
                                <li><a href="#intellectual-property">5. Intellectual Property</a></li>
                                <li><a href="#limitation-liability">6. Limitation of Liability</a></li>
                                <li><a href="#termination">7. Termination</a></li>
                                <li><a href="#governing-law">8. Governing Law</a></li>
                                <li><a href="#changes">9. Changes to Terms</a></li>
                                <li><a href="#contact">10. Contact Information</a></li>
                            </ul>
                        </div>

                        <div class="legal-section">
                            <h2 id="acceptance">1. Acceptance of Terms</h2>
                            <p>Welcome to <?php echo APP_NAME; ?>. By accessing or using our platform, you agree to be bound by these Terms and Conditions ("Terms"). If you do not agree to these Terms, please do not use our service.</p>
                            <p>These Terms constitute a legally binding agreement between you and <?php echo APP_NAME; ?> regarding your use of our reviewer task management platform and related services.</p>
                        </div>

                        <div class="legal-section">
                            <h2 id="service-description">2. Service Description</h2>
                            <p><?php echo APP_NAME; ?> is a platform that connects product reviewers with businesses seeking honest feedback. Our service includes:</p>
                            <ul>
                                <li><strong>Task Assignment:</strong> Businesses can assign review tasks to registered reviewers</li>
                                <li><strong>Review Management:</strong> Track and manage review submissions and approvals</li>
                                <li><strong>Payment Processing:</strong> Secure payment processing for completed reviews</li>
                                <li><strong>Wallet System:</strong> Integrated digital wallet for managing earnings</li>
                                <li><strong>Referral Program:</strong> Earn bonuses by referring new reviewers</li>
                            </ul>
                            <p>We reserve the right to modify, suspend, or discontinue any aspect of the service at any time without prior notice.</p>
                        </div>

                        <div class="legal-section">
                            <h2 id="user-obligations">3. User Obligations</h2>
                            
                            <h3>3.1 Account Registration</h3>
                            <p>To use our service, you must:</p>
                            <ul>
                                <li>Provide accurate, current, and complete registration information</li>
                                <li>Be at least 18 years of age or the age of majority in your jurisdiction</li>
                                <li>Maintain the security of your account credentials</li>
                                <li>Promptly update your information if it changes</li>
                                <li>Accept full responsibility for all activities under your account</li>
                            </ul>

                            <h3>3.2 Review Guidelines</h3>
                            <p>When submitting reviews, you must:</p>
                            <ul>
                                <li>Provide honest, unbiased, and genuine feedback</li>
                                <li>Actually use or experience the product/service being reviewed</li>
                                <li>Not submit fake, fraudulent, or misleading reviews</li>
                                <li>Follow all applicable laws and platform policies</li>
                                <li>Include required proof (screenshots, photos, etc.) as specified</li>
                                <li>Meet all deadlines and submission requirements</li>
                            </ul>

                            <h3>3.3 Prohibited Activities</h3>
                            <div class="highlight-box">
                                <strong>You must NOT:</strong>
                                <ul class="mb-0">
                                    <li>Create multiple accounts or use fake identities</li>
                                    <li>Share your account with others</li>
                                    <li>Engage in any fraudulent activities</li>
                                    <li>Violate any laws or regulations</li>
                                    <li>Attempt to manipulate ratings or reviews</li>
                                    <li>Use automated tools or bots</li>
                                    <li>Harass, abuse, or harm other users</li>
                                    <li>Copy or misuse intellectual property</li>
                                </ul>
                            </div>
                        </div>

                        <div class="legal-section">
                            <h2 id="payment-terms">4. Payment Terms</h2>
                            
                            <h3>4.1 Earning Payments</h3>
                            <p>Payments for completed reviews are subject to:</p>
                            <ul>
                                <li>Successful completion and approval of assigned tasks</li>
                                <li>Compliance with all review guidelines and requirements</li>
                                <li>Verification of submitted proof and documentation</li>
                                <li>Platform commission deduction as disclosed at task assignment</li>
                            </ul>

                            <h3>4.2 Withdrawal</h3>
                            <p>To withdraw earnings from your wallet:</p>
                            <ul>
                                <li>Minimum withdrawal amount: ₹<?php echo MIN_WITHDRAWAL; ?></li>
                                <li>Valid bank account or payment method required</li>
                                <li>Processing time: 3-7 business days</li>
                                <li>Withdrawal fees may apply as per payment gateway charges</li>
                            </ul>

                            <h3>4.3 Taxes</h3>
                            <p>You are responsible for determining and paying all applicable taxes on your earnings. GST at <?php echo GST_RATE; ?>% may be applicable on certain transactions as per Indian tax laws.</p>
                        </div>

                        <div class="legal-section">
                            <h2 id="intellectual-property">5. Intellectual Property</h2>
                            
                            <h3>5.1 Platform Content</h3>
                            <p>All content on <?php echo APP_NAME; ?>, including but not limited to text, graphics, logos, images, software, and design, is the property of <?php echo APP_NAME; ?> or its licensors and is protected by intellectual property laws.</p>

                            <h3>5.2 User Content</h3>
                            <p>By submitting reviews and content to our platform:</p>
                            <ul>
                                <li>You retain ownership of your original content</li>
                                <li>You grant us a worldwide, non-exclusive, royalty-free license to use, display, and distribute your content for platform operations</li>
                                <li>You represent that you have all necessary rights to the content you submit</li>
                                <li>You agree not to submit content that infringes on third-party rights</li>
                            </ul>
                        </div>

                        <div class="legal-section">
                            <h2 id="limitation-liability">6. Limitation of Liability</h2>
                            
                            <p><strong>TO THE MAXIMUM EXTENT PERMITTED BY LAW:</strong></p>
                            
                            <div class="highlight-box">
                                <ul class="mb-0">
                                    <li><?php echo APP_NAME; ?> is provided "as is" without warranties of any kind</li>
                                    <li>We do not guarantee uninterrupted or error-free service</li>
                                    <li>We are not liable for any indirect, incidental, or consequential damages</li>
                                    <li>Our total liability is limited to the amount you paid to us in the past 12 months</li>
                                    <li>We are not responsible for disputes between users and businesses</li>
                                    <li>We do not guarantee approval or payment for all submitted reviews</li>
                                </ul>
                            </div>

                            <h3>6.1 Third-Party Services</h3>
                            <p>Our platform may integrate with third-party payment gateways and services. We are not responsible for any issues, losses, or damages arising from the use of these third-party services.</p>
                        </div>

                        <div class="legal-section">
                            <h2 id="termination">7. Termination</h2>
                            
                            <h3>7.1 By You</h3>
                            <p>You may terminate your account at any time by contacting our support team. Upon termination:</p>
                            <ul>
                                <li>You must complete all pending tasks or forfeit associated payments</li>
                                <li>You may withdraw your remaining wallet balance (subject to minimum withdrawal amount)</li>
                                <li>Your account data may be retained as per our Privacy Policy</li>
                            </ul>

                            <h3>7.2 By Us</h3>
                            <p>We reserve the right to suspend or terminate your account immediately if:</p>
                            <ul>
                                <li>You violate these Terms or any applicable laws</li>
                                <li>You engage in fraudulent or abusive behavior</li>
                                <li>Your account is inactive for an extended period</li>
                                <li>We discontinue the service</li>
                            </ul>
                            <p>In case of termination for violations, any pending payments may be forfeited.</p>
                        </div>

                        <div class="legal-section">
                            <h2 id="governing-law">8. Governing Law</h2>
                            <p>These Terms are governed by and construed in accordance with the laws of India. Any disputes arising from these Terms or your use of <?php echo APP_NAME; ?> shall be subject to the exclusive jurisdiction of the courts located in [Your City/State], India.</p>
                            
                            <h3>8.1 Dispute Resolution</h3>
                            <p>Before initiating legal proceedings, we encourage you to:</p>
                            <ul>
                                <li>Contact our support team to resolve the issue amicably</li>
                                <li>Participate in good faith negotiations</li>
                                <li>Consider alternative dispute resolution methods</li>
                            </ul>
                        </div>

                        <div class="legal-section">
                            <h2 id="changes">9. Changes to Terms</h2>
                            <p>We reserve the right to modify these Terms at any time. When we make changes:</p>
                            <ul>
                                <li>We will update the "Last Updated" date at the top of this page</li>
                                <li>For significant changes, we may notify you via email or platform notification</li>
                                <li>Continued use of the service after changes constitutes acceptance of the new Terms</li>
                                <li>You should review these Terms periodically</li>
                            </ul>
                        </div>

                        <div class="legal-section">
                            <h2 id="contact">10. Contact Information</h2>
                            <p>If you have any questions, concerns, or feedback regarding these Terms, please contact us:</p>
                            <div class="card">
                                <div class="card-body">
                                    <p class="mb-2"><strong><i class="bi bi-building"></i> Company:</strong> <?php echo APP_NAME; ?></p>
                                    <p class="mb-2"><strong><i class="bi bi-envelope"></i> Email:</strong> <a href="mailto:<?php echo SMTP_FROM; ?>"><?php echo SMTP_FROM; ?></a></p>
                                    <p class="mb-2"><strong><i class="bi bi-telephone"></i> Support:</strong> <?php echo WHATSAPP_SUPPORT; ?></p>
                                    <p class="mb-0"><strong><i class="bi bi-globe"></i> Website:</strong> <a href="<?php echo APP_URL; ?>"><?php echo APP_URL; ?></a></p>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-4">
                            <i class="bi bi-info-circle"></i> <strong>Note:</strong> By using <?php echo APP_NAME; ?>, you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions.
                        </div>

                    <?php endif; ?>

                    <!-- Related Links -->
                    <div class="card mt-5">
                        <div class="card-body">
                            <h5 class="card-title">Related Legal Documents</h5>
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <a href="<?php echo APP_URL; ?>/pages/privacy.php" class="text-decoration-none">
                                        <i class="bi bi-shield-check"></i> Privacy Policy
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
