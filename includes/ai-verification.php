<?php
/**
 * AI Verification Module
 * Phase 2: AI-Powered Proof Verification
 * 
 * This is a simulated AI verification system.
 * In production, integrate with actual OCR/AI services like:
 * - Google Cloud Vision API
 * - AWS Rekognition
 * - Tesseract OCR
 * - OpenAI GPT Vision
 */

if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

/**
 * Perform AI verification on proof image
 */
function performAIVerification($file_path) {
    $full_path = __DIR__ . '/../' . $file_path;
    
    // Check if file exists
    if (!file_exists($full_path)) {
        return [
            'success' => false,
            'confidence' => 0,
            'details' => ['error' => 'File not found']
        ];
    }
    
    // Simulate AI processing
    $result = [
        'success' => true,
        'confidence' => 0,
        'details' => []
    ];
    
    // Basic image analysis
    $image_info = getimagesize($full_path);
    if ($image_info) {
        $result['details']['image_width'] = $image_info[0];
        $result['details']['image_height'] = $image_info[1];
        $result['details']['image_type'] = $image_info['mime'];
        
        // Check minimum image quality
        if ($image_info[0] < 400 || $image_info[1] < 400) {
            $result['confidence'] = 30;
            $result['details']['warning'] = 'Low resolution image';
        } else {
            $result['confidence'] = 50; // Base confidence
        }
    }
    
    // Simulate OCR text extraction
    $extracted_text = simulateOCR($full_path);
    $result['details']['extracted_text'] = $extracted_text;
    
    // Analyze extracted text for keywords
    $keywords = ['amazon', 'flipkart', 'review', 'order', 'delivered', 'rating', 'stars'];
    $keyword_matches = 0;
    foreach ($keywords as $keyword) {
        if (stripos($extracted_text, $keyword) !== false) {
            $keyword_matches++;
        }
    }
    
    // Adjust confidence based on keyword matches
    $confidence_boost = min($keyword_matches * 10, 40);
    $result['confidence'] += $confidence_boost;
    $result['details']['keyword_matches'] = $keyword_matches;
    
    // Simulate screenshot detection
    if (detectScreenshot($full_path)) {
        $result['confidence'] += 10;
        $result['details']['is_screenshot'] = true;
    } else {
        $result['details']['is_screenshot'] = false;
    }
    
    // Cap confidence at 100
    $result['confidence'] = min($result['confidence'], 100);
    
    // Determine verification status
    if ($result['confidence'] >= 80) {
        $result['status'] = 'auto_approved';
        $result['details']['recommendation'] = 'Auto-approve';
    } elseif ($result['confidence'] >= 50) {
        $result['status'] = 'manual_review';
        $result['details']['recommendation'] = 'Manual review recommended';
    } else {
        $result['status'] = 'manual_review';
        $result['details']['recommendation'] = 'Manual review required';
    }
    
    return $result;
}

/**
 * Simulate OCR text extraction
 * In production, use actual OCR service
 */
function simulateOCR($file_path) {
    // This is a simulation
    // In production, integrate with:
    // - Tesseract OCR
    // - Google Cloud Vision API
    // - AWS Textract
    
    $sample_texts = [
        'Order delivered successfully. Thank you for shopping with Amazon.',
        'Your review has been submitted. Rating: 5 stars',
        'Flipkart order #123456789 delivered on ' . date('Y-m-d'),
        'Product review posted successfully',
        'Thank you for your feedback. Your review is now live.',
    ];
    
    // Return random sample text for simulation
    return $sample_texts[array_rand($sample_texts)];
}

/**
 * Detect if image is a screenshot
 * In production, use image analysis to detect screenshot characteristics
 */
function detectScreenshot($file_path) {
    // Simulate screenshot detection
    // In production, analyze:
    // - Image metadata (EXIF data)
    // - Image dimensions (common screen resolutions)
    // - UI elements detection
    // - Color patterns typical of screenshots
    
    $image_info = getimagesize($file_path);
    if (!$image_info) {
        return false;
    }
    
    $width = $image_info[0];
    $height = $image_info[1];
    
    // Common mobile screenshot dimensions
    $common_resolutions = [
        [1080, 1920], [1080, 2340], [1080, 2400],
        [720, 1280], [1440, 2560], [1440, 3200]
    ];
    
    foreach ($common_resolutions as $res) {
        if (($width == $res[0] && $height == $res[1]) || 
            ($width == $res[1] && $height == $res[0])) {
            return true;
        }
    }
    
    // Check for common aspect ratios
    $aspect_ratio = $width / $height;
    $common_ratios = [9/16, 9/18, 9/19, 9/20, 16/9];
    
    foreach ($common_ratios as $ratio) {
        if (abs($aspect_ratio - $ratio) < 0.1) {
            return true;
        }
    }
    
    return false;
}

/**
 * Extract order details from text
 */
function extractOrderDetails($text) {
    $details = [
        'order_id' => null,
        'platform' => null,
        'date' => null,
        'rating' => null
    ];
    
    // Extract order ID patterns
    if (preg_match('/order[#\s:]*([A-Z0-9-]{8,})/i', $text, $matches)) {
        $details['order_id'] = $matches[1];
    }
    
    // Detect platform
    $platforms = ['amazon', 'flipkart', 'myntra', 'meesho', 'ajio'];
    foreach ($platforms as $platform) {
        if (stripos($text, $platform) !== false) {
            $details['platform'] = ucfirst($platform);
            break;
        }
    }
    
    // Extract rating
    if (preg_match('/(\d+)\s*(?:star|stars|\*)/i', $text, $matches)) {
        $details['rating'] = intval($matches[1]);
    }
    
    // Extract date
    if (preg_match('/\d{4}-\d{2}-\d{2}/', $text, $matches)) {
        $details['date'] = $matches[0];
    }
    
    return $details;
}

/**
 * Verify review content
 */
function verifyReviewContent($text, $required_keywords = []) {
    $matches = 0;
    $total = count($required_keywords);
    
    if ($total === 0) {
        return 100; // No specific requirements
    }
    
    foreach ($required_keywords as $keyword) {
        if (stripos($text, $keyword) !== false) {
            $matches++;
        }
    }
    
    return ($matches / $total) * 100;
}

/**
 * Analyze image quality
 */
function analyzeImageQuality($file_path) {
    $full_path = __DIR__ . '/../' . $file_path;
    
    if (!file_exists($full_path)) {
        return ['score' => 0, 'issues' => ['File not found']];
    }
    
    $image_info = getimagesize($full_path);
    if (!$image_info) {
        return ['score' => 0, 'issues' => ['Invalid image']];
    }
    
    $score = 100;
    $issues = [];
    
    // Check resolution
    $width = $image_info[0];
    $height = $image_info[1];
    
    if ($width < 400 || $height < 400) {
        $score -= 30;
        $issues[] = 'Low resolution (minimum 400x400 recommended)';
    }
    
    // Check file size
    $file_size = filesize($full_path);
    if ($file_size < 50000) { // Less than 50KB
        $score -= 20;
        $issues[] = 'File size too small (may indicate low quality)';
    }
    
    if ($file_size > 5000000) { // More than 5MB
        $issues[] = 'Large file size (consider compression)';
    }
    
    return [
        'score' => max($score, 0),
        'issues' => $issues,
        'width' => $width,
        'height' => $height,
        'file_size' => $file_size
    ];
}

/**
 * Batch verification for multiple proofs
 */
function batchVerifyProofs($db, $proof_ids) {
    $results = [];
    
    foreach ($proof_ids as $proof_id) {
        $stmt = $db->prepare("SELECT proof_file FROM task_proofs WHERE id = ?");
        $stmt->execute([$proof_id]);
        $proof_file = $stmt->fetchColumn();
        
        if ($proof_file) {
            $result = performAIVerification($proof_file);
            $results[$proof_id] = $result;
            
            // Update proof with results
            $update = $db->prepare("
                UPDATE task_proofs 
                SET ai_score = ?, ai_result = ?, status = ?
                WHERE id = ?
            ");
            $status = $result['confidence'] >= 80 ? 'auto_approved' : 'manual_review';
            $update->execute([
                $result['confidence'],
                json_encode($result['details']),
                $status,
                $proof_id
            ]);
        }
    }
    
    return $results;
}
