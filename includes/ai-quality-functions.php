<?php
/**
 * AI-Powered Review Quality Check Functions
 * Analyzes review content for quality, plagiarism, and spam detection
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Calculate quality score for a review proof
 * 
 * @param int $proofId
 * @param string $reviewText
 * @param array $metadata Additional metadata
 * @return array Quality analysis results
 */
function analyzeReviewQuality(int $proofId, string $reviewText, array $metadata = []): array {
    $scores = [
        'quality_score' => 0,
        'plagiarism_score' => 0,
        'spam_probability' => 0,
        'ai_flags' => [],
        'is_flagged' => false
    ];
    
    // 1. Text Length Analysis (20 points)
    $textLength = strlen(trim($reviewText));
    if ($textLength < 20) {
        $scores['ai_flags']['short_text'] = true;
        $scores['quality_score'] += 0;
    } elseif ($textLength < 50) {
        $scores['quality_score'] += 10;
    } elseif ($textLength < 100) {
        $scores['quality_score'] += 15;
    } else {
        $scores['quality_score'] += 20;
    }
    
    // 2. Word Count Analysis (15 points)
    $wordCount = str_word_count($reviewText);
    if ($wordCount < 5) {
        $scores['ai_flags']['too_few_words'] = true;
        $scores['quality_score'] += 0;
    } elseif ($wordCount < 10) {
        $scores['quality_score'] += 8;
    } else {
        $scores['quality_score'] += 15;
    }
    
    // 3. Spam Pattern Detection (25 points)
    $spamScore = detectSpamPatterns($reviewText);
    $scores['spam_probability'] = $spamScore;
    if ($spamScore > 70) {
        $scores['ai_flags']['spam_detected'] = true;
        $scores['quality_score'] += 0;
        $scores['is_flagged'] = true;
    } elseif ($spamScore > 40) {
        $scores['quality_score'] += 10;
    } else {
        $scores['quality_score'] += 25;
    }
    
    // 4. Duplicate Content Check (20 points)
    $plagiarismScore = checkPlagiarism($reviewText, $proofId);
    $scores['plagiarism_score'] = $plagiarismScore;
    if ($plagiarismScore > 50) {
        $scores['ai_flags']['duplicate_content'] = true;
        $scores['quality_score'] += 0;
        $scores['is_flagged'] = true;
    } elseif ($plagiarismScore > 25) {
        $scores['quality_score'] += 10;
    } else {
        $scores['quality_score'] += 20;
    }
    
    // 5. Content Quality Analysis (20 points)
    $contentQuality = analyzeContentQuality($reviewText);
    $scores['quality_score'] += $contentQuality;
    if ($contentQuality < 5) {
        $scores['ai_flags']['poor_quality'] = true;
    }
    
    // Cap at 100
    $scores['quality_score'] = min(100, $scores['quality_score']);
    
    // Flag if quality score is too low
    if ($scores['quality_score'] < 40) {
        $scores['is_flagged'] = true;
        $scores['ai_flags']['low_quality_score'] = true;
    }
    
    return $scores;
}

/**
 * Detect spam patterns in review text
 * 
 * @param string $text
 * @return float Spam probability (0-100)
 */
function detectSpamPatterns(string $text): float {
    $spamScore = 0.0;
    $text = strtolower($text);
    
    // Common spam keywords
    $spamKeywords = [
        'click here', 'buy now', 'limited offer', 'act now', 'hurry up',
        'free money', 'earn money', 'make money', 'get rich', 'guaranteed',
        'no risk', '100% free', 'call now', 'order now', 'special promotion'
    ];
    
    foreach ($spamKeywords as $keyword) {
        if (stripos($text, $keyword) !== false) {
            $spamScore += 20;
        }
    }
    
    // Excessive capitalization
    $capsCount = preg_match_all('/[A-Z]/', $text);
    $totalChars = strlen(preg_replace('/\s/', '', $text));
    if ($totalChars > 0 && ($capsCount / $totalChars) > 0.4) {
        $spamScore += 15;
    }
    
    // Excessive punctuation
    $punctCount = preg_match_all('/[!?]{2,}/', $text);
    if ($punctCount > 3) {
        $spamScore += 10;
    }
    
    // Repetitive words
    $words = explode(' ', $text);
    $wordCounts = array_count_values($words);
    foreach ($wordCounts as $count) {
        if ($count > 5) {
            $spamScore += 15;
            break;
        }
    }
    
    return min(100.0, $spamScore);
}

/**
 * Check for plagiarism/duplicate content
 * 
 * @param string $text
 * @param int $currentProofId
 * @return float Plagiarism score (0-100)
 */
function checkPlagiarism(string $text, int $currentProofId): float {
    global $conn;
    
    $plagiarismScore = 0.0;
    $text = trim(strtolower($text));
    
    // Get similar reviews from database
    $stmt = $conn->prepare("
        SELECT id, review_text 
        FROM task_proofs 
        WHERE id != ? 
        AND review_text IS NOT NULL 
        AND LENGTH(review_text) > 20
        ORDER BY created_at DESC 
        LIMIT 100
    ");
    $stmt->bind_param('i', $currentProofId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $maxSimilarity = 0.0;
    while ($row = $result->fetch_assoc()) {
        $existingText = trim(strtolower($row['review_text']));
        $similarity = calculateTextSimilarity($text, $existingText);
        $maxSimilarity = max($maxSimilarity, $similarity);
    }
    
    $plagiarismScore = $maxSimilarity * 100;
    
    return $plagiarismScore;
}

/**
 * Calculate similarity between two texts using Levenshtein distance
 * 
 * @param string $text1
 * @param string $text2
 * @return float Similarity (0-1)
 */
function calculateTextSimilarity(string $text1, string $text2): float {
    // For very long texts, use simple word overlap
    if (strlen($text1) > 255 || strlen($text2) > 255) {
        $words1 = array_unique(explode(' ', $text1));
        $words2 = array_unique(explode(' ', $text2));
        $intersection = count(array_intersect($words1, $words2));
        $union = count(array_unique(array_merge($words1, $words2)));
        return $union > 0 ? $intersection / $union : 0.0;
    }
    
    $levDistance = levenshtein($text1, $text2);
    $maxLen = max(strlen($text1), strlen($text2));
    
    if ($maxLen === 0) return 1.0;
    
    return 1 - ($levDistance / $maxLen);
}

/**
 * Analyze content quality
 * 
 * @param string $text
 * @return int Quality points (0-20)
 */
function analyzeContentQuality(string $text): int {
    $points = 0;
    
    // Contains specific product details
    if (preg_match('/product|quality|delivery|price|value/i', $text)) {
        $points += 5;
    }
    
    // Balanced sentiment (not all positive or negative)
    $positiveWords = preg_match_all('/good|great|excellent|amazing|perfect|love/i', $text);
    $negativeWords = preg_match_all('/bad|poor|terrible|awful|worst|hate/i', $text);
    
    if ($positiveWords > 0 && $negativeWords > 0) {
        $points += 5; // Balanced review
    } elseif ($positiveWords > 0 || $negativeWords > 0) {
        $points += 3;
    }
    
    // Proper sentence structure
    $sentences = preg_split('/[.!?]+/', $text);
    $validSentences = 0;
    foreach ($sentences as $sentence) {
        if (strlen(trim($sentence)) > 10) {
            $validSentences++;
        }
    }
    
    if ($validSentences >= 3) {
        $points += 5;
    } elseif ($validSentences >= 1) {
        $points += 3;
    }
    
    // Uses descriptive adjectives
    if (preg_match_all('/\b\w{5,}\b/', $text) > 3) {
        $points += 5;
    }
    
    return min(20, $points);
}

/**
 * Save quality score to database
 * 
 * @param int $proofId
 * @param array $scores
 * @return bool
 */
function saveQualityScore(int $proofId, array $scores): bool {
    global $conn;
    
    $aiFlags = json_encode($scores['ai_flags']);
    
    $stmt = $conn->prepare("
        INSERT INTO review_quality_scores 
        (proof_id, quality_score, ai_flags, plagiarism_score, spam_probability, is_flagged)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        quality_score = VALUES(quality_score),
        ai_flags = VALUES(ai_flags),
        plagiarism_score = VALUES(plagiarism_score),
        spam_probability = VALUES(spam_probability),
        is_flagged = VALUES(is_flagged),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->bind_param(
        'iisddi',
        $proofId,
        $scores['quality_score'],
        $aiFlags,
        $scores['plagiarism_score'],
        $scores['spam_probability'],
        $scores['is_flagged']
    );
    
    return $stmt->execute();
}

/**
 * Get quality score for a proof
 * 
 * @param int $proofId
 * @return array|null
 */
function getQualityScore(int $proofId): ?array {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT * FROM review_quality_scores WHERE proof_id = ?
    ");
    $stmt->bind_param('i', $proofId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $row['ai_flags'] = json_decode($row['ai_flags'], true);
        return $row;
    }
    
    return null;
}

/**
 * Get flagged reviews for admin review
 * 
 * @param int $limit
 * @param int $offset
 * @return array
 */
function getFlaggedReviews(int $limit = 20, int $offset = 0): array {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            rqs.*,
            tp.task_id,
            tp.user_id,
            tp.review_text,
            tp.screenshot,
            u.name as user_name,
            t.title as task_title
        FROM review_quality_scores rqs
        JOIN task_proofs tp ON rqs.proof_id = tp.id
        JOIN users u ON tp.user_id = u.id
        JOIN tasks t ON tp.task_id = t.id
        WHERE rqs.is_flagged = 1
        ORDER BY rqs.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        $row['ai_flags'] = json_decode($row['ai_flags'], true);
        $reviews[] = $row;
    }
    
    return $reviews;
}

/**
 * Mark review as manually reviewed
 * 
 * @param int $qualityScoreId
 * @param int $reviewerId
 * @param bool $approved
 * @return bool
 */
function markReviewAsReviewed(int $qualityScoreId, int $reviewerId, bool $approved = true): bool {
    global $conn;
    
    $flagStatus = $approved ? 0 : 1;
    
    $stmt = $conn->prepare("
        UPDATE review_quality_scores 
        SET is_flagged = ?, reviewed_by = ?, reviewed_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->bind_param('iii', $flagStatus, $reviewerId, $qualityScoreId);
    
    return $stmt->execute();
}

/**
 * Get quality statistics
 * 
 * @return array
 */
function getQualityStatistics(): array {
    global $conn;
    
    $stats = [
        'total_reviews' => 0,
        'flagged_reviews' => 0,
        'avg_quality_score' => 0,
        'high_quality' => 0,
        'medium_quality' => 0,
        'low_quality' => 0
    ];
    
    // Total and flagged reviews
    $result = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(is_flagged) as flagged,
            AVG(quality_score) as avg_score,
            SUM(CASE WHEN quality_score >= 70 THEN 1 ELSE 0 END) as high,
            SUM(CASE WHEN quality_score >= 40 AND quality_score < 70 THEN 1 ELSE 0 END) as medium,
            SUM(CASE WHEN quality_score < 40 THEN 1 ELSE 0 END) as low
        FROM review_quality_scores
    ");
    
    if ($row = $result->fetch_assoc()) {
        $stats['total_reviews'] = (int)$row['total'];
        $stats['flagged_reviews'] = (int)$row['flagged'];
        $stats['avg_quality_score'] = round($row['avg_score'], 1);
        $stats['high_quality'] = (int)$row['high'];
        $stats['medium_quality'] = (int)$row['medium'];
        $stats['low_quality'] = (int)$row['low'];
    }
    
    return $stats;
}
