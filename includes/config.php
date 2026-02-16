<?php
declare(strict_types=1);

// Load environment variables
require_once __DIR__ . '/env-loader.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Debug mode (set to false in production)
define('DEBUG', env('DEBUG_MODE', false));

// Database Configuration
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USER', 'reviewflow_user'));
define('DB_PASS', env('DB_PASS', 'Malik@241123'));
define('DB_NAME', env('DB_NAME', 'reviewflow'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// Application Settings
define('APP_NAME', env('APP_NAME', 'ReviewFlow'));
define('APP_URL', env('APP_URL', 'https://palians.com/reviewer'));
define('BASE_URL', APP_URL); // Alias for shared header/footer templates (legacy usage).
define('ADMIN_URL', env('ADMIN_URL', 'https://palians.com/reviewer/admin'));
define('SELLER_URL', env('SELLER_URL', 'https://palians.com/reviewer/seller'));
define('APP_VERSION', env('APP_VERSION', '3.0.0'));

// Security Settings
const SESSION_TIMEOUT = 3600;
const PASSWORD_HASH_ALGO = PASSWORD_BCRYPT;
const PASSWORD_HASH_OPTIONS = ['cost' => 12];

// File Upload Settings
const UPLOAD_DIR = __DIR__ . '/../uploads/';
const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
const MAX_FILE_SIZE = 5 * 1024 * 1024;

// Wallet Settings
const MIN_WITHDRAWAL = 100;
const REFERRAL_BONUS = 50;
const FIRST_TASK_BONUS = 25;
const DEFAULT_ADMIN_COMMISSION_PER_REVIEW = 5;

// Task Steps Configuration
const TASK_STEPS = ['Order Placed', 'Delivery Received', 'Review Submitted', 'Refund Requested'];

// WhatsApp Settings
const WHATSAPP_API_URL = 'https://api.whatsapp.com/send';
const WHATSAPP_SUPPORT = '917379162377';

// Email Settings
define('SMTP_HOST', env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int)env('SMTP_PORT', 587));
define('SMTP_USER', env('SMTP_USER', ''));
define('SMTP_PASS', env('SMTP_PASS', ''));
define('SMTP_FROM', env('SMTP_FROM', 'noreply@palians.com'));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'ReviewFlow'));

// Payment Gateway Settings (Override with database settings)
define('RAZORPAY_KEY_ID', env('RAZORPAY_KEY_ID', ''));
define('RAZORPAY_KEY_SECRET', env('RAZORPAY_KEY_SECRET', ''));
define('PAYUMONEY_MERCHANT_KEY', env('PAYU_MERCHANT_KEY', ''));
define('PAYUMONEY_MERCHANT_SALT', env('PAYU_MERCHANT_SALT', ''));

// GST Settings
const GST_RATE = 18;
const SAC_CODE = '998371';

// Create directories
$dirs = [
    UPLOAD_DIR, 
    UPLOAD_DIR . 'qr/', 
    UPLOAD_DIR . 'profiles/', 
    UPLOAD_DIR . 'invoices/',
    __DIR__ . '/../logs'
];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
}

ini_set('error_log', __DIR__ . '/../logs/error.log');

// PDO Connection
try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);
} catch (PDOException $e) {
    // Enhanced error logging with more details
    $error_message = sprintf(
        'Database Connection Failed: %s | DSN: mysql:host=%s;dbname=%s | User: %s | Time: %s',
        $e->getMessage(),
        DB_HOST,
        DB_NAME,
        DB_USER,
        date('Y-m-d H:i:s')
    );
    error_log($error_message);
    
    // Set HTTP 500 status
    http_response_code(500);
    
    // Show detailed error in debug mode, generic error in production
    if (DEBUG) {
        die('<h1>Database Connection Error</h1><p>Details: ' . htmlspecialchars($e->getMessage()) . '</p><p>Check the error log for more information.</p>');
    } else {
        // User-friendly error page
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Service Unavailable - <?php echo APP_NAME; ?></title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    margin: 0;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: #333;
                }
                .error-container {
                    background: white;
                    padding: 3rem;
                    border-radius: 12px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    text-align: center;
                    max-width: 500px;
                }
                .error-icon {
                    font-size: 4rem;
                    margin-bottom: 1rem;
                }
                h1 {
                    color: #e74c3c;
                    margin: 0 0 1rem 0;
                    font-size: 1.8rem;
                }
                p {
                    color: #666;
                    line-height: 1.6;
                    margin: 1rem 0;
                }
                .btn {
                    display: inline-block;
                    margin-top: 1.5rem;
                    padding: 0.8rem 2rem;
                    background: #667eea;
                    color: white;
                    text-decoration: none;
                    border-radius: 6px;
                    transition: background 0.3s;
                }
                .btn:hover {
                    background: #5568d3;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">⚠️</div>
                <h1>Service Temporarily Unavailable</h1>
                <p>We're experiencing technical difficulties. Our team has been notified and is working to resolve the issue.</p>
                <p>Please try again in a few moments.</p>
                <a href="<?php echo APP_URL; ?>" class="btn">Return to Home</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Error Handler
set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): bool {
    error_log("[$errno] $errstr in $errfile:$errline");
    return true;
});

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path' => '/',
        'domain' => 'palians.com',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Session timeout check
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > SESSION_TIMEOUT)) {
    session_destroy();
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

if (isset($_SESSION['user_id']) || isset($_SESSION['admin_name'])) {
    $_SESSION['login_time'] = time();
}

// Helper function to get settings
function getSetting(string $key, $default = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    } catch (PDOException $e) {
        return $default;
    }
} 
?>
