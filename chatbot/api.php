<?php
/**
 * ReviewFlow - AI Chatbot API
 * Intelligent chatbot with FAQ matching, learning capability, and context awareness
 */

declare(strict_types=1);

// Include dependencies first (config.php will start session and set security headers)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Set API-specific headers after includes
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$message = sanitizeInput($input['message'] ?? '');
$context = $input['context'] ?? [];
$session_id = $input['session_id'] ?? session_id();

if (empty($message)) {
    echo json_encode(['error' => 'Message is required']);
    exit;
}

// Rate limiting
if (!checkRateLimit('chatbot_' . $session_id, 30, 1)) {
    echo json_encode([
        'response' => "You're sending messages too fast! Please wait a moment and try again.",
        'type' => 'error'
    ]);
    exit;
}

// Get user info if logged in
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Guest';

// Initialize Chatbot and get response with error handling
try {
    $chatbot = new Chatbot($pdo, $user_id, $user_name);
    $response = $chatbot->getResponse($message, $context);
    echo json_encode($response);
} catch (Exception $e) {
    error_log("Chatbot API Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'response' => "I'm having trouble processing your request. Please try again in a moment.",
        'type' => 'error'
    ]);
}

/**
 * Chatbot Class
 */
class Chatbot {
    private $pdo;
    private $user_id;
    private $user_name;
    private $confidence_threshold = 0.6;
    
    // Common greetings
    private $greetings = [
        'hi', 'hello', 'hey', 'hii', 'hiii', 'namaste', 'good morning', 
        'good afternoon', 'good evening', 'good night', 'sup', 'yo'
    ];
    
    // Common thanks
    private $thanks = [
        'thank', 'thanks', 'thanku', 'thank you', 'thankyou', 'dhanyawad', 'shukriya', 'ty'
    ];
    
    // Common goodbyes
    private $goodbyes = [
        'bye', 'goodbye', 'good bye', 'see you', 'cya', 'tata', 'alvida'
    ];
    
    // Intent keywords mapping
    private $intents = [
        'wallet' => ['wallet', 'balance', 'money', 'paisa', 'amount', 'earning', 'earned'],
        'withdrawal' => ['withdraw', 'withdrawal', 'payout', 'cash out', 'nikalna', 'payment'],
        'task' => ['task', 'work', 'job', 'kaam', 'assignment', 'order'],
        'referral' => ['refer', 'referral', 'invite', 'friend', 'code', 'bonus'],
        'account' => ['account', 'profile', 'password', 'email', 'mobile', 'login', 'register'],
        'support' => ['help', 'support', 'problem', 'issue', 'contact', 'complaint'],
        'how_it_works' => ['how', 'work', 'kaise', 'process', 'steps', 'start'],
        'payment_method' => ['upi', 'bank', 'paytm', 'gpay', 'phonepe', 'payment method'],
    ];
    
    public function __construct($pdo, $user_id = null, $user_name = 'Guest') {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
        $this->user_name = $user_name;
    }
    
    /**
     * Get chatbot response
     */
    public function getResponse(string $message, array $context = []): array {
        $message_lower = strtolower(trim($message));
        
        // Check for greetings
        if ($this->isGreeting($message_lower)) {
            return $this->getGreetingResponse();
        }
        
        // Check for thanks
        if ($this->isThanks($message_lower)) {
            return $this->getThanksResponse();
        }
        
        // Check for goodbye
        if ($this->isGoodbye($message_lower)) {
            return $this->getGoodbyeResponse();
        }
        
        // Check for specific queries (user data)
        if ($this->user_id) {
            $user_response = $this->checkUserQueries($message_lower);
            if ($user_response) {
                return $user_response;
            }
        }
        
        // Search FAQ database
        $faq_response = $this->searchFAQ($message_lower);
        if ($faq_response) {
            return $faq_response;
        }
        
        // Check intent and provide generic response
        $intent_response = $this->getIntentResponse($message_lower);
        if ($intent_response) {
            return $intent_response;
        }
        
        // No match found - log and return fallback
        $this->logUnanswered($message);
        return $this->getFallbackResponse();
    }
    
    /**
     * Check if message is a greeting
     */
    private function isGreeting(string $message): bool {
        foreach ($this->greetings as $greeting) {
            if (strpos($message, $greeting) !== false || $message === $greeting) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if message is thanks
     */
    private function isThanks(string $message): bool {
        foreach ($this->thanks as $thank) {
            if (strpos($message, $thank) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if message is goodbye
     */
    private function isGoodbye(string $message): bool {
        foreach ($this->goodbyes as $bye) {
            if (strpos($message, $bye) !== false || $message === $bye) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get greeting response
     */
    private function getGreetingResponse(): array {
        $hour = (int)date('H');
        $time_greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
        
        $greetings = [
            "$time_greeting, {$this->user_name}! 👋 How can I help you today?",
            "Hello {$this->user_name}! 😊 Welcome to " . APP_NAME . ". What would you like to know?",
            "Hi there, {$this->user_name}! 🎉 I'm here to assist you. Ask me anything!",
            "Hey {$this->user_name}! 👋 Great to see you. How may I assist you today?"
        ];
        
        $response = !empty($greetings) ? $greetings[array_rand($greetings)] : "Hello {$this->user_name}! 👋";
        
        return [
            'response' => $response,
            'type' => 'greeting',
            'suggestions' => [
                'How do I complete a task?',
                'Check my wallet balance',
                'How to withdraw money?',
                'Tell me about referral bonus'
            ]
        ];
    }
    
    /**
     * Get thanks response
     */
    private function getThanksResponse(): array {
        $responses = [
            "You're welcome, {$this->user_name}! 😊 Is there anything else I can help you with?",
            "Happy to help! 🎉 Let me know if you need anything else.",
            "Anytime! 😊 Feel free to ask if you have more questions.",
            "My pleasure! 💪 Don't hesitate to reach out again."
        ];
        
        return [
            'response' => !empty($responses) ? $responses[array_rand($responses)] : "You're welcome!",
            'type' => 'thanks'
        ];
    }
    
    /**
     * Get goodbye response
     */
    private function getGoodbyeResponse(): array {
        $responses = [
            "Goodbye {$this->user_name}! 👋 Have a great day! Come back anytime.",
            "See you later! 😊 Happy earning!",
            "Bye! 👋 If you need help later, I'm always here.",
            "Take care, {$this->user_name}! 🎉 Good luck with your tasks!"
        ];
        
        return [
            'response' => !empty($responses) ? $responses[array_rand($responses)] : "Goodbye!",
            'type' => 'goodbye'
        ];
    }
    
    /**
     * Check user-specific queries
     */
    private function checkUserQueries(string $message): ?array {
        try {
            // Wallet balance query
            if (preg_match('/(my|mera|check|show|what).*(balance|wallet|paisa|money|earning)/i', $message) ||
                preg_match('/(balance|wallet|earning).*(kitna|how much|kya)/i', $message)) {
                
                $balance = getWalletBalance($this->user_id);
                $wallet = getWalletDetails($this->user_id);
                
                if (!is_array($wallet)) {
                    error_log("Chatbot: getWalletDetails returned non-array for user_id: {$this->user_id}");
                    $wallet = ['total_earned' => 0, 'total_withdrawn' => 0];
                }
                
                return [
                    'response' => "💰 **Your Wallet Summary:**\n\n" .
                                 "• Current Balance: **₹" . number_format($balance, 2) . "**\n" .
                                 "• Total Earned: ₹" . number_format($wallet['total_earned'] ?? 0, 2) . "\n" .
                                 "• Total Withdrawn: ₹" . number_format($wallet['total_withdrawn'] ?? 0, 2) . "\n\n" .
                                 "Would you like to withdraw or check your transactions?",
                    'type' => 'wallet_info',
                    'data' => ['balance' => $balance],
                    'suggestions' => ['How to withdraw?', 'Show my transactions', 'Minimum withdrawal amount?']
                ];
            }
        
        // Task status query
        if (preg_match('/(my|mera|show|check).*(task|work|job|order)/i', $message) ||
            preg_match('/(pending|complete|active).*(task)/i', $message)) {
            
            $stats = getUserStats($this->user_id);
            
            if (!is_array($stats)) {
                error_log("Chatbot: getUserStats returned non-array for user_id: {$this->user_id}");
                $stats = ['tasks_completed' => 0, 'tasks_pending' => 0, 'total_earnings' => 0, 'level' => 1];
            }
            
            return [
                'response' => "📋 **Your Task Summary:**\n\n" .
                             "• Tasks Completed: **" . ($stats['tasks_completed'] ?? 0) . "**\n" .
                             "• Tasks Pending: **" . ($stats['tasks_pending'] ?? 0) . "**\n" .
                             "• Total Earnings: ₹" . number_format($stats['total_earnings'] ?? 0, 2) . "\n" .
                             "• Your Level: Level " . ($stats['level'] ?? 1) . "\n\n" .
                             "Keep completing tasks to earn more! 💪",
                'type' => 'task_info',
                'data' => $stats,
                'suggestions' => ['How to complete a task?', 'When will I get paid?', 'Check wallet balance']
            ];
        }
        
        // Referral query
        if (preg_match('/(my|mera|show).*(referral|refer).*(code|link)/i', $message)) {
            $code = getReferralCode($this->user_id);
            $referral_bonus = getSetting('referral_bonus', 50);
            $link = APP_URL . "/index.php?ref=$code";
            
            return [
                'response' => "🎁 **Your Referral Details:**\n\n" .
                             "• Your Code: **$code**\n" .
                             "• Referral Link: $link\n" .
                             "• Bonus per Referral: ₹$referral_bonus\n\n" .
                             "Share your code with friends. When they complete their first task, you both earn bonus! ���",
                'type' => 'referral_info',
                'data' => ['code' => $code, 'link' => $link],
                'suggestions' => ['How does referral work?', 'Check my referral earnings', 'Share on WhatsApp']
            ];
        }
        
        // Referral stats
        if (preg_match('/(referral|refer).*(earning|bonus|status|stat)/i', $message)) {
            $stats = getReferralStats($this->user_id);
            
            if (!is_array($stats)) {
                error_log("Chatbot: getReferralStats returned non-array for user_id: {$this->user_id}");
                $stats = ['total' => 0, 'completed' => 0, 'pending' => 0, 'earnings' => 0];
            }
            
            return [
                'response' => "📊 **Your Referral Stats:**\n\n" .
                             "• Total Referrals: **" . ($stats['total'] ?? 0) . "**\n" .
                             "• Completed: " . ($stats['completed'] ?? 0) . "\n" .
                             "• Pending: " . ($stats['pending'] ?? 0) . "\n" .
                             "• Total Earned: ₹" . number_format($stats['earnings'] ?? 0, 2) . "\n\n" .
                             "Keep sharing to earn more! 🚀",
                'type' => 'referral_stats',
                'data' => $stats
            ];
        }
        } catch (Exception $e) {
            error_log("Chatbot checkUserQueries Error: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Search FAQ database
     */
    private function searchFAQ(string $message): ?array {
        try {
            // Get all active FAQs
            $stmt = $this->pdo->query("SELECT * FROM chatbot_faq WHERE is_active = 1");
            $faqs = $stmt->fetchAll();
            
            $best_match = null;
            $best_score = 0;
            
            foreach ($faqs as $faq) {
                // Skip if essential fields are NULL or empty
                if (empty($faq['question']) || empty($faq['answer'])) {
                    continue;
                }
                
                // Check exact keyword match
                if (!empty($faq['keywords'])) {
                    $keywords = array_map('trim', explode(',', strtolower((string)$faq['keywords'])));
                    foreach ($keywords as $keyword) {
                        if (!empty($keyword) && !empty($message) && strpos($message, $keyword) !== false) {
                            $score = strlen($keyword) / max(1, strlen($message));
                            if ($score > $best_score) {
                                $best_score = $score;
                                $best_match = $faq;
                            }
                        }
                    }
                }
                
                // Check question similarity
                $question_lower = strtolower((string)$faq['question']);
                $similarity = $this->calculateSimilarity($message, $question_lower);
                if ($similarity > $best_score) {
                    $best_score = $similarity;
                    $best_match = $faq;
                }
            }
            
            if ($best_match && $best_score >= $this->confidence_threshold) {
                // Increment usage count
                $stmt = $this->pdo->prepare("UPDATE chatbot_faq SET usage_count = usage_count + 1 WHERE id = ?");
                $stmt->execute([$best_match['id']]);
                
                // Parse response for variables
                $response = $this->parseResponse((string)$best_match['answer']);
                
                return [
                    'response' => $response,
                    'type' => 'faq',
                    'faq_id' => $best_match['id'],
                    'confidence' => round($best_score, 2),
                    'category' => $best_match['category'] ?? 'general'
                ];
            }
            
        } catch (PDOException $e) {
            error_log("Chatbot FAQ Error: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Chatbot FAQ Unexpected Error: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Calculate text similarity
     */
    private function calculateSimilarity(string $str1, string $str2): float {
        // Remove common words
        $stopwords = ['a', 'an', 'the', 'is', 'are', 'was', 'were', 'what', 'how', 'when', 'where', 'why', 'can', 'i', 'my', 'me', 'do', 'does', 'to', 'in', 'on', 'for', 'of', 'and', 'or'];
        
        $words1 = array_diff(explode(' ', $str1), $stopwords);
        $words2 = array_diff(explode(' ', $str2), $stopwords);
        
        if (empty($words1) || empty($words2)) {
            return 0;
        }
        
        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));
        
        return count($intersection) / count($union);
    }
    
    /**
     * Parse response for variables
     */
    private function parseResponse(string $response): string {
        $variables = [
            '{user_name}' => $this->user_name,
            '{app_name}' => APP_NAME,
            '{min_withdrawal}' => '₹' . getSetting('min_withdrawal', 100),
            '{referral_bonus}' => '₹' . getSetting('referral_bonus', 50),
            '{support_email}' => getSetting('support_email', 'support@example.com'),
            '{support_whatsapp}' => getSetting('support_whatsapp', ''),
        ];
        
        if ($this->user_id) {
            $variables['{balance}'] = '₹' . number_format(getWalletBalance($this->user_id), 2);
            $variables['{referral_code}'] = getReferralCode($this->user_id);
        }
        
        return str_replace(array_keys($variables), array_values($variables), $response);
    }
    
    /**
     * Get intent-based response
     */
    private function getIntentResponse(string $message): ?array {
        $detected_intent = null;
        $max_matches = 0;
        
        foreach ($this->intents as $intent => $keywords) {
            $matches = 0;
            foreach ($keywords as $keyword) {
                if (strpos($message, $keyword) !== false) {
                    $matches++;
                }
            }
            if ($matches > $max_matches) {
                $max_matches = $matches;
                $detected_intent = $intent;
            }
        }
        
        if (!$detected_intent || $max_matches === 0) {
            return null;
        }
        
        $responses = $this->getIntentResponses();
        
        if (isset($responses[$detected_intent])) {
            return [
                'response' => $responses[$detected_intent]['response'],
                'type' => 'intent',
                'intent' => $detected_intent,
                'suggestions' => $responses[$detected_intent]['suggestions'] ?? []
            ];
        }
        
        return null;
    }
    
    /**
     * Get intent responses
     */
    private function getIntentResponses(): array {
        $min_withdrawal = getSetting('min_withdrawal', 100);
        $referral_bonus = getSetting('referral_bonus', 50);
        
        return [
            'wallet' => [
                'response' => "💰 **About Wallet:**\n\n" .
                             "Your wallet stores all your earnings from completed tasks and referral bonuses.\n\n" .
                             "• View balance anytime from Dashboard\n" .
                             "• Track all transactions\n" .
                             "• Withdraw when balance reaches ₹$min_withdrawal\n\n" .
                             "Would you like to check your current balance?",
                'suggestions' => ['Check my balance', 'How to withdraw?', 'Show transactions']
            ],
            
            'withdrawal' => [
                'response' => "💸 **Withdrawal Process:**\n\n" .
                             "1. Minimum withdrawal: **₹$min_withdrawal**\n" .
                             "2. Go to Wallet → Request Withdrawal\n" .
                             "3. Choose payment method (UPI/Bank/Paytm)\n" .
                             "4. Enter amount and payment details\n" .
                             "5. Submit request\n\n" .
                             "⏱️ Processing time: 24-48 hours\n\n" .
                             "Make sure your payment details are correct!",
                'suggestions' => ['Check my balance', 'Withdrawal status', 'Payment methods']
            ],
            
            'task' => [
                'response' => "📋 **How Tasks Work:**\n\n" .
                             "Each task has 4 simple steps:\n\n" .
                             "**Step 1:** Order the product (we provide link)\n" .
                             "**Step 2:** Upload order screenshot\n" .
                             "**Step 3:** Give 5-star review after delivery\n" .
                             "**Step 4:** Upload review screenshot & request refund\n\n" .
                             "✅ Once verified, you get:\n" .
                             "• Full product refund\n" .
                             "• Commission for your work\n\n" .
                             "Easy peasy! 🎉",
                'suggestions' => ['Show my tasks', 'How much can I earn?', 'Task deadline']
            ],
            
            'referral' => [
                'response' => "🎁 **Referral Program:**\n\n" .
                             "Earn **₹$referral_bonus** for each friend you refer!\n\n" .
                             "**How it works:**\n" .
                             "1. Share your unique referral code/link\n" .
                             "2. Friend signs up using your code\n" .
                             "3. Friend completes their first task\n" .
                             "4. You get ₹$referral_bonus bonus! 🎉\n\n" .
                             "No limit on referrals - earn unlimited!",
                'suggestions' => ['Show my referral code', 'My referral earnings', 'Share on WhatsApp']
            ],
            
            'account' => [
                'response' => "👤 **Account Help:**\n\n" .
                             "**Profile Settings:**\n" .
                             "• Update name, mobile, address\n" .
                             "• Save payment details for faster withdrawals\n\n" .
                             "**Security:**\n" .
                             "• Change password anytime\n" .
                             "• View login history\n\n" .
                             "**Forgot Password?**\n" .
                             "Click 'Forgot Password' on login page to reset.\n\n" .
                             "What would you like to do?",
                'suggestions' => ['Update profile', 'Change password', 'Payment settings']
            ],
            
            'support' => [
                'response' => "🆘 **Need Help?**\n\n" .
                             "I'm here to assist you! But if you need human support:\n\n" .
                             "📧 Email: " . getSetting('support_email', 'support@example.com') . "\n" .
                             "💬 In-app: Go to Messages → New Message\n" .
                             "📱 WhatsApp: " . (getSetting('support_whatsapp') ?: 'Available in app') . "\n\n" .
                             "Response time: Within 24 hours\n\n" .
                             "Can you tell me more about your issue?",
                'suggestions' => ['Task not showing', 'Payment issue', 'Account problem']
            ],
            
            'how_it_works' => [
                'response' => "🚀 **How " . APP_NAME . " Works:**\n\n" .
                             "**1. Sign Up** - Create free account\n\n" .
                             "**2. Get Tasks** - Admin assigns product review tasks\n\n" .
                             "**3. Complete Steps:**\n" .
                             "   • Order product (you pay initially)\n" .
                             "   • Upload order proof\n" .
                             "   • Write review after delivery\n" .
                             "   • Request refund\n\n" .
                             "**4. Get Paid:**\n" .
                             "   • Full refund + Commission\n" .
                             "   • Withdraw to UPI/Bank\n\n" .
                             "Start earning today! 💰",
                'suggestions' => ['Start my first task', 'How much can I earn?', 'Is it safe?']
            ],
            
            'payment_method' => [
                'response' => "💳 **Supported Payment Methods:**\n\n" .
                             "**1. UPI (Recommended)**\n" .
                             "   • GPay, PhonePe, Paytm, etc.\n" .
                             "   • Instant transfer\n\n" .
                             "**2. Bank Transfer**\n" .
                             "   • Any Indian bank account\n" .
                             "   • 1-2 business days\n\n" .
                             "**3. Paytm Wallet**\n" .
                             "   • Direct to Paytm\n" .
                             "   • Instant transfer\n\n" .
                             "Save your details in Profile for faster withdrawals!",
                'suggestions' => ['Withdraw now', 'Update payment details', 'Minimum withdrawal?']
            ]
        ];
    }
    
    /**
     * Log unanswered question
     */
    private function logUnanswered(string $message): void {
        try {
            // Check if similar question already logged
            $stmt = $this->pdo->prepare("
                SELECT id, asked_count FROM chatbot_unanswered 
                WHERE question = ? OR SOUNDEX(question) = SOUNDEX(?)
                LIMIT 1
            ");
            $stmt->execute([$message, $message]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Increment count and update timestamps
                $stmt = $this->pdo->prepare("
                    UPDATE chatbot_unanswered 
                    SET asked_count = asked_count + 1, 
                        occurrence_count = asked_count + 1,
                        last_asked_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$existing['id']]);
            } else {
                // Insert new unanswered question
                $stmt = $this->pdo->prepare("
                    INSERT INTO chatbot_unanswered 
                    (question, user_id, user_name, occurrence_count, asked_count, 
                     is_resolved, first_asked_at, last_asked_at, created_at, updated_at)
                    VALUES (?, ?, ?, 1, 1, 0, NOW(), NOW(), NOW(), NOW())
                ");
                $stmt->execute([$message, $this->user_id, $this->user_name]);
            }
        } catch (PDOException $e) {
            error_log("Chatbot Log Error: " . $e->getMessage());
        }
    }
    
    /**
     * Get fallback response
     */
    private function getFallbackResponse(): array {
        $responses = [
            "I'm not sure I understand that. Could you rephrase your question? 🤔",
            "Hmm, I don't have information about that yet. Try asking something else or contact support.",
            "I'm still learning! Your question has been noted. Meanwhile, try these options:",
            "Sorry, I couldn't find an answer for that. Can you try asking differently?"
        ];
        
        return [
            'response' => !empty($responses) ? $responses[array_rand($responses)] : "I'm not sure I understand that.",
            'type' => 'fallback',
            'suggestions' => [
                'How do tasks work?',
                'Check my wallet',
                'How to withdraw?',
                'Contact support'
            ]
        ];
    }
}
