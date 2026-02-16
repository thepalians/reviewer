<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

class AIChatbot {
    private $db;
    private $trainingData;

    public function __construct($pdo) {
        // Expecting PDO instance
        $this->db = $pdo;
        $this->loadTrainingData();
    }

    private function loadTrainingData() {
        $dataFile = __DIR__ . '/train_data.json';
        if (file_exists($dataFile)) {
            $this->trainingData = json_decode(file_get_contents($dataFile), true);
        } else {
            $this->trainingData = [
                'intents' => [
                    [
                        'tag' => 'greeting',
                        'patterns' => ['Hi', 'Hello', 'Hey', 'Good morning', 'Good afternoon'],
                        'responses' => ['Hello! How can I help you with ReviewFlow?', 'Hi there! Need assistance?']
                    ],
                    [
                        'tag' => 'how_to_register',
                        'patterns' => ['How to register', 'Sign up process', 'Create account', 'Registration steps'],
                        'responses' => ['To register, click Register and follow the steps.']
                    ],
                    [
                        'tag' => 'contact_admin',
                        'patterns' => ['Contact admin', 'Help needed', 'Issue with task', 'Problem with order'],
                        'responses' => ['For specific issues, please contact the admin directly through the dashboard message system.']
                    ]
                ]
            ];
            file_put_contents($dataFile, json_encode($this->trainingData, JSON_PRETTY_PRINT));
        }
    }

    public function processMessage($message) {
        $message = strtolower(trim($message));

        // Check for exact matches
        foreach ($this->trainingData['intents'] as $intent) {
            foreach ($intent['patterns'] as $pattern) {
                if (strpos($message, strtolower($pattern)) !== false) {
                    $responses = $intent['responses'];
                    return $responses[array_rand($responses)];
                }
            }
        }

        // Default responses
        $defaultResponses = [
            "I'm not sure I understand. Could you rephrase your question?",
            "That's an interesting question. Let me connect you with a human agent for more specific help.",
            "I'm still learning about ReviewFlow. Please contact admin for detailed assistance.",
            "Can you provide more details about your query?"
        ];

        return $defaultResponses[array_rand($defaultResponses)];
    }

    public function learnFromInteraction($question, $response) {
        // Simple learning mechanism - log interactions for manual review
        $logFile = __DIR__ . '/learning_log.json';
        $log = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];

        $log[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'question' => $question,
            'response' => $response
        ];

        file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT));
    }
}

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message'])) {
    // Use the PDO instance created in config.php
    $chatbot = new AIChatbot($pdo);
    $message = sanitizeInput($_POST['message']);
    $response = $chatbot->processMessage($message);

    // Log the interaction
    $chatbot->learnFromInteraction($message, $response);

    // Return JSON response
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['response' => $response]);
    exit();
}
