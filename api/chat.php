<?php
/**
 * Chat API Endpoint
 * Handles AJAX requests for real-time chat functionality
 */

require_once '../includes/config.php';
require_once '../includes/chat-functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();

// Handle POST requests (send message)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['send_message'])) {
        $conversation_id = filter_input(INPUT_POST, 'conversation_id', FILTER_SANITIZE_NUMBER_INT);
        $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
        
        if (empty($message) || empty($conversation_id)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }
        
        $sender_type = $is_admin ? 'admin' : 'user';
        $attachment = isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK ? $_FILES['attachment'] : null;
        
        $result = sendChatMessage($db, $conversation_id, $user_id, $sender_type, $message, $attachment);
        echo json_encode($result);
        exit;
    }
    
    if (isset($_POST['update_typing'])) {
        $conversation_id = filter_input(INPUT_POST, 'conversation_id', FILTER_SANITIZE_NUMBER_INT);
        $is_typing = filter_input(INPUT_POST, 'is_typing', FILTER_VALIDATE_BOOLEAN);
        
        updateTypingStatus($db, $conversation_id, $user_id, $is_typing);
        echo json_encode(['success' => true]);
        exit;
    }
    
    if (isset($_POST['assign_conversation']) && $is_admin) {
        $conversation_id = filter_input(INPUT_POST, 'conversation_id', FILTER_SANITIZE_NUMBER_INT);
        $admin_id = filter_input(INPUT_POST, 'admin_id', FILTER_SANITIZE_NUMBER_INT);
        
        assignConversationToAdmin($db, $conversation_id, $admin_id);
        echo json_encode(['success' => true, 'message' => 'Conversation assigned']);
        exit;
    }
    
    if (isset($_POST['close_conversation'])) {
        $conversation_id = filter_input(INPUT_POST, 'conversation_id', FILTER_SANITIZE_NUMBER_INT);
        
        closeConversation($db, $conversation_id);
        echo json_encode(['success' => true, 'message' => 'Conversation closed']);
        exit;
    }
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    if ($action === 'get_messages') {
        $conversation_id = filter_input(INPUT_GET, 'conversation_id', FILTER_SANITIZE_NUMBER_INT);
        
        if (empty($conversation_id)) {
            echo json_encode(['success' => false, 'message' => 'Missing conversation_id']);
            exit;
        }
        
        $messages = getChatMessages($db, $conversation_id, 100);
        echo json_encode(['success' => true, 'messages' => $messages]);
        exit;
    }
    
    if ($action === 'get_conversations') {
        if ($is_admin) {
            $status = isset($_GET['status']) ? $_GET['status'] : 'open';
            $conversations = getAllConversations($db, $status);
        } else {
            $conversations = getUserConversations($db, $user_id);
        }
        
        echo json_encode(['success' => true, 'conversations' => $conversations]);
        exit;
    }
    
    if ($action === 'get_unread_count') {
        $user_type = $is_admin ? 'admin' : 'user';
        $count = getUnreadMessageCount($db, $user_id, $user_type);
        echo json_encode(['success' => true, 'count' => $count]);
        exit;
    }
    
    if ($action === 'get_typing') {
        $conversation_id = filter_input(INPUT_GET, 'conversation_id', FILTER_SANITIZE_NUMBER_INT);
        $typing = getTypingStatus($db, $conversation_id);
        echo json_encode(['success' => true, 'typing' => $typing]);
        exit;
    }
    
    if ($action === 'get_canned_responses' && $is_admin) {
        $responses = getCannedResponses($db);
        echo json_encode(['success' => true, 'responses' => $responses]);
        exit;
    }
}

// Default response
echo json_encode(['success' => false, 'message' => 'Invalid request']);
