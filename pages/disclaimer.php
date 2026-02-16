<?php
/**
 * Disclaimer Page
 * ReviewFlow - Reviewer Task Management System
 */

declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Get content from database if available
$custom_content = getSetting('disclaimer_content', '');
$last_updated = getSetting('disclaimer_updated', date('F d, Y'));

$page_title = 'Disclaimer';
$page_description = 'Disclaimer for ReviewFlow platform - important limitations and disclaimers regarding our services.';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <meta name="description" content="<?php echo $page_description; ?>">
    <meta name="keywords" content="disclaimer, legal, reviewer, platform, limitations">
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
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .important-box {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
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
        [data-bs-theme="dark"] .warning-box {
            background: #3d3420;
        }
        [data-bs-theme="dark"] .important-box {
            background: #3d2022;
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
            <h1 class="display-4 mb-3"><i class="bi bi-exclamation-triangle"></i> Disclaimer</h1>
            <p class="lead">Important limitations and disclaimers regarding our services</p>
        </div>
    </div>

    <!-- Breadcrumb -->
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Disclaimer</li>
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
                                <li><a href="#service-disclaimer">2. Service Disclaimer</a></li>
                                <li><a href="#accuracy-disclaimer">3. Accuracy & Completeness</a></li>
                                <li><a href="#third-party-links">4. Third-Party Links & Content</a></li>
                                <li><a href="#review-disclaimer">5. Review Content Disclaimer</a></li>
                                <li><a href="#earnings-disclaimer">6. Earnings & Income Disclaimer</a></li>
                                <li><a href="#professional-advice">7. Professional Advice Disclaimer</a></li>
                                <li><a href="#technical-limitations">8. Technical Limitations</a></li>
                                <li><a href="#liability-limitation">9. Limitation of Liability</a></li>
                                <li><a href="#changes">10. Changes to Disclaimer</a></li>
                            </ul>
                        </div>

                        <div class="important-box">
                            <h5><i class="bi bi-exclamation-octagon"></i> IMPORTANT NOTICE</h5>
                            <p class="mb-0">Please read this disclaimer carefully before using <?php echo APP_NAME; ?>. By accessing or using our platform, you acknowledge and agree to the terms outlined in this disclaimer. If you do not agree with any part of this disclaimer, please do not use our services.</p>
                        </div>

                        <div class="legal-section">
                            <h2 id="introduction">1. Introduction</h2>
                            <p>This disclaimer governs your use of <?php echo APP_NAME; ?> and applies to all users, including reviewers, businesses, and visitors. The information and services provided on this platform are subject to the terms, conditions, and disclaimers outlined below.</p>
                            <p><?php echo APP_NAME; ?> is a reviewer task management platform that facilitates connections between product reviewers and businesses. We act as an intermediary and are not directly responsible for the actions, content, or transactions between users and businesses.</p>
                        </div>

                        <div class="legal-section">
                            <h2 id="service-disclaimer">2. Service Disclaimer</h2>
                            
                            <h3>2.1 "As Is" and "As Available" Basis</h3>
                            <div class="warning-box">
                                <p><strong><?php echo APP_NAME; ?> is provided on an "AS IS" and "AS AVAILABLE" basis without any warranties of any kind, either express or implied.</strong></p>
                                <p class="mb-0">We make no warranties or representations about:</p>
                                <ul class="mb-0">
                                    <li>The accuracy, reliability, or completeness of the service</li>
                                    <li>The availability or uninterrupted operation of the platform</li>
                                    <li>The quality or suitability of reviews and tasks</li>
                                    <li>The earnings or outcomes from using the platform</li>
                                </ul>
                            </div>

                            <h3>2.2 No Guarantee of Service</h3>
                            <p>We do not guarantee that:</p>
                            <ul>
                                <li>The platform will be available at all times or without interruption</li>
                                <li>Defects or errors will be corrected promptly</li>
                                <li>The platform is free from viruses or harmful components</li>
                                <li>All tasks will be completed as expected</li>
                                <li>All payments will be processed without issues</li>
                                <li>All reviews will be approved or accepted</li>
                            </ul>

                            <h3>2.3 System Maintenance</h3>
                            <p><?php echo APP_NAME; ?> may be temporarily unavailable due to:</p>
                            <ul>
                                <li>Scheduled maintenance and updates</li>
                                <li>Technical issues beyond our control</li>
                                <li>Third-party service disruptions</li>
                                <li>Security updates and patches</li>
                            </ul>
                            <p>We are not liable for any losses or damages arising from service interruptions.</p>
                        </div>

                        <div class="legal-section">
                            <h2 id="accuracy-disclaimer">3. Accuracy & Completeness Disclaimer</h2>
                            
                            <h3>3.1 Information Accuracy</h3>
                            <p>While we strive to provide accurate and up-to-date information:</p>
                            <ul>
                                <li>We do not warrant the accuracy, completeness, or currency of any information on the platform</li>
                                <li>Information may contain technical inaccuracies or typographical errors</li>
                                <li>Task details and requirements are provided by businesses and may be incomplete or inaccurate</li>
                                <li>We are not responsible for verifying all information posted by users</li>
                            </ul>

                            <h3>3.2 No Endorsement</h3>
                            <p>The presence of products, services, or businesses on <?php echo APP_NAME; ?> does not constitute:</p>
                            <ul>
                                <li>An endorsement or recommendation by us</li>
                                <li>A guarantee of quality or legitimacy</li>
                                <li>A warranty of any kind</li>
                                <li>A verification of claims made</li>
                            </ul>

                            <h3>3.3 User Verification</h3>
                            <p>Users are responsible for:</p>
                            <ul>
                                <li>Verifying the accuracy of task details before acceptance</li>
                                <li>Confirming payment terms and conditions</li>
                                <li>Validating product authenticity and legitimacy</li>
                                <li>Ensuring compliance with applicable laws</li>
                            </ul>
                        </div>

                        <div class="legal-section">
                            <h2 id="third-party-links">4. Third-Party Links & Content</h2>
                            
                            <h3>4.1 External Links</h3>
                            <p><?php echo APP_NAME; ?> may contain links to third-party websites, services, or products. Please note:</p>
                            <ul>
                                <li>We do not control or endorse third-party websites</li>
                                <li>We are not responsible for the content, privacy practices, or terms of third-party sites</li>
                                <li>Clicking external links is at your own risk</li>
                                <li>We recommend reviewing the terms and privacy policies of external sites</li>
                            </ul>

                            <h3>4.2 Third-Party Services</h3>
                            <p>We use third-party services including but not limited to:</p>
                            <ul>
                                <li><strong>Payment Gateways:</strong> Razorpay, PayU Money, and others</li>
                                <li><strong>Email Services:</strong> SMTP providers for notifications</li>
                                <li><strong>Hosting Services:</strong> Web hosting and infrastructure providers</li>
                            </ul>
                            <p>We are not responsible for any issues, errors, or losses arising from third-party services.</p>

                            <h3>4.3 E-commerce Platforms</h3>
                            <p>Review tasks may involve purchases from e-commerce platforms such as:</p>
                            <ul>
                                <li>Amazon, Flipkart, Meesho, and similar platforms</li>
                                <li>Independent business websites</li>
                                <li>Other online marketplaces</li>
                            </ul>
                            <p><strong>Important:</strong> We are not affiliated with these platforms and are not responsible for product quality, delivery, customer service, or disputes related to purchases made through them.</p>
                        </div>

                        <div class="legal-section">
                            <h2 id="review-disclaimer">5. Review Content Disclaimer</h2>
                            
                            <h3>5.1 User-Generated Content</h3>
                            <div class="warning-box">
                                <p><strong>All reviews, ratings, and feedback submitted on <?php echo APP_NAME; ?> are user-generated content.</strong></p>
                                <p class="mb-0">We do not:</p>
                                <ul class="mb-0">
                                    <li>Verify the accuracy of all reviews</li>
                                    <li>Guarantee the authenticity of user experiences</li>
                                    <li>Endorse opinions expressed in reviews</li>
                                    <li>Take responsibility for misleading or fraudulent reviews</li>
                                </ul>
                            </div>

                            <h3>5.2 Review Guidelines Compliance</h3>
                            <p>While we encourage honest reviews and have guidelines in place:</p>
                            <ul>
                                <li>We cannot guarantee that all reviews comply with our guidelines</li>
                                <li>Some inappropriate content may slip through our moderation</li>
                                <li>We rely on user reports and automated systems for monitoring</li>
                                <li>We will remove violating content upon discovery or report</li>
                            </ul>

                            <h3>5.3 Review Accuracy</h3>
                            <p>Reviews reflect individual user experiences and opinions. We are not responsible for:</p>
                            <ul>
                                <li>The accuracy or truthfulness of review content</li>
                                <li>Biased or prejudiced opinions</li>
                                <li>Product defects or issues mentioned in reviews</li>
                                <li>Consequences of relying on review content</li>
                            </ul>
                        </div>

                        <div class="legal-section">
                            <h2 id="earnings-disclaimer">6. Earnings & Income Disclaimer</h2>
                            
                            <div class="important-box">
                                <h5><i class="bi bi-exclamation-circle"></i> No Guaranteed Income</h5>
                                <p class="mb-0"><strong><?php echo APP_NAME; ?> DOES NOT GUARANTEE ANY SPECIFIC INCOME OR EARNINGS FROM USING THE PLATFORM.</strong></p>
                            </div>

                            <h3>6.1 Earnings Variability</h3>
                            <p>Your earnings on <?php echo APP_NAME; ?> depend on various factors:</p>
                            <ul>
                                <li>Number and quality of completed tasks</li>
                                <li>Task availability in your region</li>
                                <li>Your account rating and performance history</li>
                                <li>Competition from other reviewers</li>
                                <li>Business demand and budget</li>
                                <li>Seasonal fluctuations</li>
                            </ul>

                            <h3>6.2 Past Performance</h3>
                            <p>Any earnings figures, statistics, or testimonials mentioned on the platform:</p>
                            <ul>
                                <li>Are not representative of typical user earnings</li>
                                <li>Do not guarantee similar results for all users</li>
                                <li>May vary significantly based on individual circumstances</li>
                                <li>Should not be considered as income projections</li>
                            </ul>

                            <h3>6.3 Task Availability</h3>
                            <p>We do not guarantee:</p>
                            <ul>
                                <li>A minimum number of tasks per user</li>
                                <li>Consistent task availability</li>
                                <li>Equal distribution of tasks among reviewers</li>
                                <li>Specific payment amounts for tasks</li>
                            </ul>

                            <h3>6.4 Payment Processing</h3>
                            <p>While we strive for timely payments:</p>
                            <ul>
                                <li>Payment processing times may vary</li>
                                <li>Delays may occur due to verification or technical issues</li>
                                <li>Payment gateway issues are beyond our control</li>
                                <li>Bank processing times vary by institution</li>
                            </ul>
                        </div>

                        <div class="legal-section">
                            <h2 id="professional-advice">7. Professional Advice Disclaimer</h2>
                            
                            <h3>7.1 Not Professional Advice</h3>
                            <p>Information on <?php echo APP_NAME; ?> is for general informational purposes only and should not be considered as:</p>
                            <ul>
                                <li><strong>Legal Advice:</strong> We are not lawyers and do not provide legal advice</li>
                                <li><strong>Financial Advice:</strong> We do not provide investment or financial planning advice</li>
                                <li><strong>Tax Advice:</strong> We do not provide tax planning or filing advice</li>
                                <li><strong>Business Advice:</strong> We do not provide business consulting services</li>
                            </ul>

                            <h3>7.2 Consult Professionals</h3>
                            <p>For specific advice regarding:</p>
                            <ul>
                                <li><strong>Legal matters:</strong> Consult a qualified attorney</li>
                                <li><strong>Tax obligations:</strong> Consult a certified tax professional</li>
                                <li><strong>Financial decisions:</strong> Consult a financial advisor</li>
                                <li><strong>Business strategies:</strong> Consult a business consultant</li>
                            </ul>

                            <h3>7.3 Tax Responsibility</h3>
                            <div class="warning-box">
                                <p><strong>Important:</strong> Users are solely responsible for:</p>
                                <ul class="mb-0">
                                    <li>Determining their tax obligations on earnings</li>
                                    <li>Filing appropriate tax returns</li>
                                    <li>Paying applicable taxes (income tax, GST, etc.)</li>
                                    <li>Maintaining accurate financial records</li>
                                </ul>
                            </div>
                        </div>

                        <div class="legal-section">
                            <h2 id="technical-limitations">8. Technical Limitations</h2>
                            
                            <h3>8.1 Technology Risks</h3>
                            <p>Using <?php echo APP_NAME; ?> involves inherent technology risks:</p>
                            <ul>
                                <li><strong>Data Loss:</strong> Technical failures may result in data loss</li>
                                <li><strong>Security Breaches:</strong> Despite our security measures, no system is 100% secure</li>
                                <li><strong>Software Bugs:</strong> The platform may contain bugs or errors</li>
                                <li><strong>Compatibility Issues:</strong> The platform may not work on all devices or browsers</li>
                                <li><strong>Performance Variations:</strong> Speed and performance may vary</li>
                            </ul>

                            <h3>8.2 Internet Dependency</h3>
                            <p>Our platform requires internet connectivity:</p>
                            <ul>
                                <li>We are not responsible for poor internet connections</li>
                                <li>Network issues may affect functionality</li>
                                <li>ISP problems are beyond our control</li>
                            </ul>

                            <h3>8.3 Device Compatibility</h3>
                            <p>Platform functionality may vary across devices:</p>
                            <ul>
                                <li>Some features may not work on older browsers</li>
                                <li>Mobile experience may differ from desktop</li>
                                <li>Screen readers and accessibility tools may have limitations</li>
                            </ul>
                        </div>

                        <div class="legal-section">
                            <h2 id="liability-limitation">9. Limitation of Liability</h2>
                            
                            <div class="important-box">
                                <h5><i class="bi bi-shield-x"></i> MAXIMUM EXTENT PERMITTED BY LAW</h5>
                                <p><strong>TO THE FULLEST EXTENT PERMITTED BY APPLICABLE LAW, <?php echo APP_NAME; ?> SHALL NOT BE LIABLE FOR:</strong></p>
                                <ul class="mb-0">
                                    <li>Any indirect, incidental, special, consequential, or punitive damages</li>
                                    <li>Loss of profits, revenue, data, or business opportunities</li>
                                    <li>Damages arising from unauthorized access to accounts</li>
                                    <li>Errors, mistakes, or inaccuracies of content</li>
                                    <li>Personal injury or property damage</li>
                                    <li>Interruption or cessation of service</li>
                                    <li>Bugs, viruses, or malicious code</li>
                                    <li>Actions or content of third parties</li>
                                </ul>
                            </div>

                            <h3>9.1 Maximum Liability</h3>
                            <p>In no event shall our total liability to you exceed:</p>
                            <ul>
                                <li>The amount you paid to <?php echo APP_NAME; ?> in the 12 months preceding the claim, OR</li>
                                <li>₹1,000 (One Thousand Indian Rupees), whichever is less</li>
                            </ul>

                            <h3>9.2 User Responsibility</h3>
                            <p>You acknowledge and agree that:</p>
                            <ul>
                                <li>You use <?php echo APP_NAME; ?> at your own risk</li>
                                <li>You are responsible for your own actions and decisions</li>
                                <li>You should exercise caution and due diligence</li>
                                <li>You will not hold us liable for user-generated content or actions</li>
                            </ul>
                        </div>

                        <div class="legal-section">
                            <h2 id="changes">10. Changes to Disclaimer</h2>
                            
                            <p>We reserve the right to modify this disclaimer at any time. Changes will be:</p>
                            <ul>
                                <li>Effective immediately upon posting</li>
                                <li>Reflected in the "Last Updated" date</li>
                                <li>Binding on all users</li>
                            </ul>

                            <p>Your continued use of <?php echo APP_NAME; ?> after changes constitutes acceptance of the modified disclaimer.</p>

                            <h3>Review Regularly</h3>
                            <p>We encourage you to review this disclaimer periodically to stay informed about our policies and limitations.</p>
                        </div>

                        <div class="alert alert-info mt-4">
                            <h5><i class="bi bi-info-circle"></i> Questions About This Disclaimer?</h5>
                            <p class="mb-2">If you have any questions or concerns about this disclaimer, please contact us:</p>
                            <p class="mb-0">
                                <strong>Email:</strong> <a href="mailto:<?php echo SMTP_FROM; ?>"><?php echo SMTP_FROM; ?></a><br>
                                <strong>Website:</strong> <a href="<?php echo APP_URL; ?>"><?php echo APP_URL; ?></a>
                            </p>
                        </div>

                        <div class="alert alert-warning mt-4">
                            <strong><i class="bi bi-exclamation-triangle"></i> Final Note:</strong> This disclaimer is part of our Terms & Conditions. By using <?php echo APP_NAME; ?>, you agree to all terms, conditions, and disclaimers outlined in our legal documents.
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
                                    <a href="<?php echo APP_URL; ?>/pages/refund.php" class="text-decoration-none">
                                        <i class="bi bi-cash-coin"></i> Refund Policy
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
