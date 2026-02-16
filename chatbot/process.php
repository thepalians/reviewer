<?php
/**
 * Chatbot Message Processing Endpoint - Version 2.0
 * Handles chatbot messages from the widget
 */

session_start();

// Try to load config, but handle failures gracefully
$pdo = null;
$configLoaded = false;

try {
    require_once __DIR__ . '/../includes/config.php';
    $configLoaded = true;
} catch (Exception $e) {
    error_log('Chatbot: Failed to load config: ' . $e->getMessage());
    // We'll handle this below
}

header('Content-Type: application/json');

// Get input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['message'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$message = trim($data['message']);
$userType = $data['userType'] ?? 'guest';
$userId = intval($data['userId'] ?? 0);

if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
    exit;
}

try {
    // Check if config loaded and database is available
    if (!$configLoaded || !isset($pdo) || !($pdo instanceof PDO)) {
        error_log('Chatbot: Database not available, using fallback responses');
        $response = generateContextualResponse($message, $userType);
        echo json_encode([
            'success' => true,
            'response' => $response
        ]);
        exit;
    }
    
    // Ensure database tables exist
    ensureTablesExist($pdo);
    
    // Log the question to chatbot_unanswered table (optional, continue on failure)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO chatbot_unanswered (question, user_type, user_id, is_resolved, created_at)
            VALUES (?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$message, $userType, $userId > 0 ? $userId : null]);
        $loggedId = $pdo->lastInsertId();
    } catch (PDOException $logError) {
        error_log('Chatbot logging error (non-fatal): ' . $logError->getMessage());
        $loggedId = null;
    }
    
    // Try to find answer in FAQ
    $response = findFAQAnswer($message, $pdo);
    
    if ($response) {
        // Mark as resolved if answer found and logging succeeded
        if ($loggedId) {
            try {
                $stmt = $pdo->prepare("UPDATE chatbot_unanswered SET is_resolved = 1 WHERE id = ?");
                $stmt->execute([$loggedId]);
            } catch (PDOException $updateError) {
                error_log('Chatbot update error (non-fatal): ' . $updateError->getMessage());
            }
        }
    } else {
        // Generate contextual response based on user type
        $response = generateContextualResponse($message, $userType);
    }
    
    // Always return a response
    echo json_encode([
        'success' => true,
        'response' => $response
    ]);
    
} catch (PDOException $e) {
    error_log('Chatbot critical error: ' . $e->getMessage() . ' | Stack: ' . $e->getTraceAsString());
    // Return a helpful response even on error
    $fallbackResponse = generateContextualResponse($message, $userType);
    echo json_encode([
        'success' => true,
        'response' => $fallbackResponse
    ]);
} catch (Exception $e) {
    error_log('Chatbot unexpected error: ' . $e->getMessage() . ' | Stack: ' . $e->getTraceAsString());
    // Return a generic helpful response
    $fallbackResponse = generateContextualResponse('help', $userType);
    echo json_encode([
        'success' => true,
        'response' => $fallbackResponse
    ]);
}

/**
 * Find answer in FAQ database
 */
function findFAQAnswer($question, $pdo) {
    try {
        // Simple keyword matching
        $keywords = extractKeywords($question);
        
        if (empty($keywords)) {
            return null;
        }
        
        // Build LIKE query for each keyword
        $conditions = [];
        $params = [];
        foreach ($keywords as $keyword) {
            $conditions[] = "(question LIKE ? OR answer LIKE ?)";
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
        }
        
        $sql = "SELECT answer FROM chatbot_faq WHERE is_active = 1 AND (" . implode(' OR ', $conditions) . ") ORDER BY id DESC LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchColumn();
        
        return $result ?: null;
        
    } catch (PDOException $e) {
        error_log('FAQ search error (non-fatal): ' . $e->getMessage());
        return null; // Return null to fall back to contextual responses
    } catch (Exception $e) {
        error_log('FAQ search unexpected error (non-fatal): ' . $e->getMessage());
        return null;
    }
}

/**
 * Extract keywords from question
 */
function extractKeywords($text) {
    $text = strtolower($text);
    $stopWords = ['how', 'do', 'i', 'the', 'a', 'an', 'to', 'is', 'can', 'what', 'where', 'when'];
    
    $words = preg_split('/\s+/', $text);
    $keywords = array_filter($words, function($word) use ($stopWords) {
        return strlen($word) > 3 && !in_array($word, $stopWords);
    });
    
    return array_values($keywords);
}

/**
 * Generate contextual response based on user type
 */
function generateContextualResponse($message, $userType) {
    $message = strtolower($message);
    
    // Admin responses
    if ($userType === 'admin') {
        if (strpos($message, 'approve') !== false || strpos($message, 'request') !== false) {
            return "To approve review requests:\n1. Go to 'Review Requests' in the sidebar\n2. Click on a pending request\n3. Review the details\n4. Click 'Approve' button\n\nYou can also approve wallet recharge requests from the 'Wallet Requests' page.";
        }
        if (strpos($message, 'assign') !== false || strpos($message, 'task') !== false) {
            return "To assign tasks:\n1. Go to 'Assign Task' in the sidebar\n2. Select users from the list\n3. Enter product link and commission\n4. Click 'Assign Task'\n\nYou can assign tasks to multiple users at once.";
        }
        if (strpos($message, 'export') !== false || strpos($message, 'data') !== false) {
            return "To export data:\n1. Go to 'Export Data' in the sidebar\n2. Select a brand from the dropdown\n3. Choose date range (optional)\n4. Click 'Export to CSV'\n\nThe file will download automatically with all review data.";
        }
    }
    
    // Seller responses
    if ($userType === 'seller') {
        // Review requests
        if (strpos($message, 'review') !== false || strpos($message, 'request') !== false) {
            return "**How to Request Reviews:**\n\n" .
                   "1. Click 'New Request' in the sidebar\n" .
                   "2. Enter product details:\n" .
                   "   • Product link (Amazon/Flipkart)\n" .
                   "   • Product name and brand\n" .
                   "   • Product price\n" .
                   "   • Number of reviews needed\n" .
                   "3. Review the cost calculation\n" .
                   "4. Make payment securely\n" .
                   "5. Wait for admin approval\n\n" .
                   "Once approved, reviewers will be assigned to your product automatically!";
        }
        
        // Wallet and recharge
        if (strpos($message, 'wallet') !== false || strpos($message, 'recharge') !== false || strpos($message, 'balance') !== false) {
            return "**Wallet & Recharge Guide:**\n\n" .
                   "To recharge your wallet:\n" .
                   "1. Go to 'Wallet' in the sidebar\n" .
                   "2. Click 'Recharge Wallet' button\n" .
                   "3. Enter the amount you want to add\n" .
                   "4. Choose payment method\n" .
                   "5. Complete the payment\n\n" .
                   "Your wallet balance will be updated instantly!\n\n" .
                   "You can also add money during checkout when creating a new review request.";
        }
        
        // Invoices
        if (strpos($message, 'invoice') !== false || strpos($message, 'bill') !== false || strpos($message, 'receipt') !== false) {
            return "**View & Download Invoices:**\n\n" .
                   "1. Go to 'Invoices' in the sidebar\n" .
                   "2. You'll see all your invoices listed\n" .
                   "3. Click 'View' to see invoice details\n" .
                   "4. Click 'Download' to save PDF\n\n" .
                   "Invoices include:\n" .
                   "• Order details\n" .
                   "• GST breakdown (18%)\n" .
                   "• Payment information\n" .
                   "• SAC code for services\n\n" .
                   "Invoices are generated automatically after payment.";
        }
        
        // Payment and pricing
        if (strpos($message, 'payment') !== false || strpos($message, 'pay') !== false || strpos($message, 'cost') !== false || strpos($message, 'price') !== false) {
            return "**Payment & Pricing:**\n\n" .
                   "Review pricing:\n" .
                   "• ₹50 per review (base commission)\n" .
                   "• Plus 18% GST\n" .
                   "• Example: 10 reviews = ₹500 + ₹90 GST = ₹590\n\n" .
                   "Payment methods:\n" .
                   "• Razorpay (UPI, Cards, Net Banking)\n" .
                   "• Wallet balance\n\n" .
                   "All payments are secure and encrypted!";
        }
        
        // Order status and tracking
        if (strpos($message, 'order') !== false || strpos($message, 'status') !== false || strpos($message, 'track') !== false) {
            return "**Track Your Orders:**\n\n" .
                   "1. Go to 'Orders' in the sidebar\n" .
                   "2. See all your review requests\n" .
                   "3. Filter by status:\n" .
                   "   • Pending - Awaiting admin approval\n" .
                   "   • Approved - In progress\n" .
                   "   • Completed - All reviews done\n" .
                   "   • Rejected - See reason in details\n\n" .
                   "Click 'View' on any order to see:\n" .
                   "• Product details\n" .
                   "• Review progress\n" .
                   "• Payment status\n" .
                   "• Timeline";
        }
        
        // Getting started / help
        if (strpos($message, 'start') !== false || strpos($message, 'begin') !== false || strpos($message, 'first') !== false || strpos($message, 'new') !== false) {
            return "**Getting Started as a Seller:**\n\n" .
                   "Welcome! Here's how to get reviews for your products:\n\n" .
                   "1. **Create a Review Request**\n" .
                   "   • Click 'New Request'\n" .
                   "   • Enter your product details\n" .
                   "   • Choose number of reviews\n\n" .
                   "2. **Make Payment**\n" .
                   "   • Review the cost\n" .
                   "   • Pay securely via Razorpay\n\n" .
                   "3. **Wait for Approval**\n" .
                   "   • Admin reviews your request (usually within 24 hours)\n\n" .
                   "4. **Track Progress**\n" .
                   "   • Monitor reviews in 'Orders'\n" .
                   "   • Get notifications on completion\n\n" .
                   "Need help? Contact support anytime!";
        }
    }
    
    // User/Reviewer responses
    if ($userType === 'user') {
        if (strpos($message, 'task') !== false || strpos($message, 'complete') !== false) {
            return "To complete a task:\n1. Check 'My Tasks' on dashboard\n2. Click on a pending task\n3. Follow the 4 steps:\n   - Place order\n   - Confirm delivery\n   - Submit review\n   - Request refund\n4. Upload required screenshots\n\nYou'll earn commission after admin approval.";
        }
        if (strpos($message, 'withdraw') !== false || strpos($message, 'money') !== false) {
            return "To withdraw money:\n1. Go to 'Wallet' page\n2. Click 'Withdraw'\n3. Enter amount (min ₹100)\n4. Provide bank/UPI details\n5. Submit request\n\nAdmin will process within 24-48 hours. Check your tier limits.";
        }
        if (strpos($message, 'refer') !== false || strpos($message, 'friend') !== false) {
            return "To refer friends:\n1. Go to 'Referrals' page\n2. Copy your unique referral link\n3. Share with friends\n4. Earn ₹50 when they complete their first task\n\nYou can track all your referrals on the referrals page.";
        }
    }
    
    // Generic helpful response - dynamic based on user type
    $topics = [];
    if ($userType === 'seller') {
        $topics = [
            '• How to request reviews',
            '• Wallet and recharges',
            '• Order status and tracking',
            '• Payment and pricing',
            '• Invoices and receipts',
            '• Getting started guide'
        ];
    } elseif ($userType === 'admin') {
        $topics = [
            '• How to approve requests',
            '• Assign tasks to users',
            '• Export data',
            '• Manage settings',
            '• View reports'
        ];
    } elseif ($userType === 'user') {
        $topics = [
            '• How to complete tasks',
            '• Withdrawals and payments',
            '• Referral program',
            '• Account management'
        ];
    } else {
        $topics = [
            '• How to register',
            '• Platform features',
            '• Getting started'
        ];
    }
    
    return "Thank you for your message! I'm here to help. Could you please be more specific about what you need assistance with?\n\n" .
           "**Common topics I can help with:**\n" .
           implode("\n", $topics) . "\n\n" .
           "Just ask me anything about these topics, or contact our support team for personalized assistance!";
}

/**
 * Ensure required database tables exist
 */
function ensureTablesExist($pdo) {
    try {
        // Check if chatbot_unanswered table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'chatbot_unanswered'");
        if ($stmt->rowCount() === 0) {
            // Create chatbot_unanswered table
            $pdo->exec("
                CREATE TABLE chatbot_unanswered (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    question TEXT NOT NULL,
                    user_type ENUM('guest', 'user', 'seller', 'admin') DEFAULT 'guest',
                    user_id INT NULL,
                    is_resolved TINYINT(1) DEFAULT 0,
                    admin_answer TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_user_type (user_type),
                    INDEX idx_is_resolved (is_resolved),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            error_log('Chatbot: Created chatbot_unanswered table');
        }
        
        // Check if faq table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'faq'");
        if ($stmt->rowCount() === 0) {
            // Create faq table
            $pdo->exec("
                CREATE TABLE faq (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    question TEXT NOT NULL,
                    answer TEXT NOT NULL,
                    category VARCHAR(50) DEFAULT 'general',
                    user_type ENUM('guest', 'user', 'seller', 'admin', 'all') DEFAULT 'all',
                    is_active TINYINT(1) DEFAULT 1,
                    view_count INT DEFAULT 0,
                    helpful_count INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_category (category),
                    INDEX idx_user_type (user_type),
                    INDEX idx_is_active (is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            error_log('Chatbot: Created faq table');
            
            // Default FAQs for sellers
            $defaultFAQs = [
                [
                    'question' => 'How do I request reviews?',
                    'answer' => 'To request reviews: 1. Click "New Request" in the sidebar, 2. Enter product details (link, name, price), 3. Choose number of reviews needed, 4. Make payment, 5. Wait for admin approval. Once approved, reviewers will be assigned automatically!',
                    'category' => 'reviews',
                    'user_type' => 'seller'
                ],
                [
                    'question' => 'How do I recharge my wallet?',
                    'answer' => 'To recharge wallet: 1. Go to "Wallet" in sidebar, 2. Click "Recharge Wallet", 3. Enter amount, 4. Choose payment method (Razorpay supports UPI, Cards, Net Banking), 5. Complete payment. Your balance updates instantly!',
                    'category' => 'wallet',
                    'user_type' => 'seller'
                ],
                [
                    'question' => 'How do I view my invoices?',
                    'answer' => 'To view invoices: 1. Go to "Invoices" in sidebar, 2. See all your invoices listed, 3. Click "View" for details, 4. Click "Download" to save PDF. Invoices include GST breakdown and are generated automatically after payment.',
                    'category' => 'billing',
                    'user_type' => 'seller'
                ],
                [
                    'question' => 'What is the cost per review?',
                    'answer' => 'Review pricing: Base commission is ₹50 per review, plus 18% GST. Example: 10 reviews = ₹500 + ₹90 GST = ₹590 total. You can pay via Razorpay (UPI/Cards/Net Banking) or wallet balance.',
                    'category' => 'pricing',
                    'user_type' => 'seller'
                ],
                [
                    'question' => 'How long does admin approval take?',
                    'answer' => 'Admin typically reviews and approves requests within 24 hours. You will receive a notification once your request is approved. You can track the status in the "Orders" section.',
                    'category' => 'reviews',
                    'user_type' => 'seller'
                ]
            ];
            
            // Insert default FAQs
            $stmt = $pdo->prepare("
                INSERT INTO faq (question, answer, category, user_type) 
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($defaultFAQs as $faq) {
                try {
                    $stmt->execute([
                        $faq['question'],
                        $faq['answer'],
                        $faq['category'],
                        $faq['user_type']
                    ]);
                } catch (PDOException $insertError) {
                    error_log('Chatbot: Error inserting FAQ: ' . $insertError->getMessage());
                }
            }
            
            error_log('Chatbot: Inserted default FAQs');
        }
    } catch (PDOException $e) {
        error_log('Chatbot table creation error: ' . $e->getMessage());
        // Don't throw - allow chatbot to continue with contextual responses
    }
}
?>
