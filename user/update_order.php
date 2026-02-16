<?php
require_once '../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Only regular logged-in users
if (!isLoggedIn() || isAdmin()) {
    redirect('../index.php');
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Check if order_id is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    redirect('tasks.php');
}

$order_id = sanitizeInput($_GET['order_id']);
$step = isset($_GET['step']) ? intval($_GET['step']) : 0;

// Fetch task details
try {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE order_id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        die("Task not found!");
    }

    // Determine which step to show
    if ($step == 0) {
        // Auto-determine current step based on status
        switch ($task['status']) {
            case 'step1_completed': $step = 2; break;
            case 'step2_completed': $step = 3; break;
            case 'step3_completed': $step = 4; break;
            default: $step = 2; break;
        }
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle form submission based on step
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_step = intval($_POST['current_step'] ?? 0);

    switch ($current_step) {
        case 2: // Step 2: Review
            $review_text = sanitizeInput($_POST['review_text'] ?? '');
            $review_rating = intval($_POST['review_rating'] ?? 0);
            $review_date = sanitizeInput($_POST['review_date'] ?? '');

            if (empty($review_text) || empty($review_rating) || empty($review_date)) {
                $error = "Please fill all required fields for review!";
            } elseif ($review_rating < 1 || $review_rating > 5) {
                $error = "Rating must be between 1 and 5 stars!";
            } else {
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("UPDATE tasks SET 
                                          review_text = ?, 
                                          review_rating = ?, 
                                          review_date = ?, 
                                          status = 'step2_completed', 
                                          step2_completed_at = NOW() 
                                          WHERE order_id = ? AND user_id = ?");
                    $stmt->execute([$review_text, $review_rating, $review_date, $order_id, $user_id]);

                    // Log in history
                    $stmt = $pdo->prepare("INSERT INTO order_history 
                                          (task_id, order_id, step, details, created_at) 
                                          VALUES (?, ?, 'step2', ?, NOW())");
                    $details = json_encode([
                        'review_rating' => $review_rating,
                        'review_date' => $review_date
                    ]);
                    $stmt->execute([$task['id'], $order_id, $details]);

                    $pdo->commit();
                    $success = "Review submitted successfully! You can now proceed to Step 3.";
                    $step = 3;

                    // Refresh task data
                    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE order_id = ? AND user_id = ?");
                    $stmt->execute([$order_id, $user_id]);
                    $task = $stmt->fetch(PDO::FETCH_ASSOC);

                    logActivity('Submit Review', "User {$user_id} submitted review for order {$order_id}");
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Failed to submit review: " . $e->getMessage();
                }
            }
            break;

        // Additional steps (3: screenshots, 4: refund request) should be handled similarly...
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Order - User Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container mt-4">
        <h2 class="mb-4">
            <?php 
            echo $step == 2 ? '📝 Step 2: Submit Review' : 
                 ($step == 3 ? '🖼️ Step 3: Upload Screenshots' : 
                 '💰 Step 4: Request Refund');
            ?>
        </h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- The page content (forms) follows... -->
        <?php include '../includes/footer.php'; ?>
    </div>
</body>
</html>
