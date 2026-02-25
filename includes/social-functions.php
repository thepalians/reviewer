<?php
/**
 * Social Media Hub Helper Functions
 * Phase 9 — Social Media Hub
 */

if (!function_exists('extractVideoId')) {
    /**
     * Extract YouTube video ID from various URL formats
     */
    function extractVideoId(string $url): string {
        $patterns = [
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
            '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/v\/([a-zA-Z0-9_-]{11})/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $m)) {
                return $m[1];
            }
        }
        return '';
    }
}

if (!function_exists('generateEmbed')) {
    /**
     * Generate platform-specific embed HTML from a content URL
     */
    function generateEmbed(string $platform_slug, string $content_url): string {
        switch ($platform_slug) {
            case 'youtube':
                $vid = extractVideoId($content_url);
                if ($vid) {
                    return '<iframe id="yt-player" width="100%" height="400" '
                        . 'src="https://www.youtube.com/embed/' . htmlspecialchars($vid, ENT_QUOTES, 'UTF-8')
                        . '?enablejsapi=1&rel=0" frameborder="0" allowfullscreen '
                        . 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture">'
                        . '</iframe>';
                }
                return '';

            case 'instagram':
                $url_enc = htmlspecialchars($content_url, ENT_QUOTES, 'UTF-8');
                return '<blockquote class="instagram-media" data-instgrm-permalink="' . $url_enc . '" '
                    . 'style="width:100%;max-width:540px;margin:auto;">'
                    . '</blockquote>'
                    . '<script async src="//www.instagram.com/embed.js"></script>';

            case 'facebook':
                $url_enc = urlencode($content_url);
                return '<div class="fb-video" data-href="' . htmlspecialchars($content_url, ENT_QUOTES, 'UTF-8') . '" '
                    . 'data-width="auto" data-allowfullscreen="true"></div>'
                    . '<div id="fb-root"></div>'
                    . '<script async defer crossorigin="anonymous" '
                    . 'src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v18.0"></script>';

            case 'twitter':
                $url_enc = htmlspecialchars($content_url, ENT_QUOTES, 'UTF-8');
                return '<blockquote class="twitter-tweet" data-width="550">'
                    . '<a href="' . $url_enc . '"></a>'
                    . '</blockquote>'
                    . '<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>';

            case 'pinterest':
                $url_enc = htmlspecialchars($content_url, ENT_QUOTES, 'UTF-8');
                return '<a data-pin-do="embedPin" href="' . $url_enc . '"></a>'
                    . '<script async defer src="//assets.pinterest.com/js/pinit.js"></script>';

            case 'telegram':
                // Telegram posts can be embedded via widget
                $url_enc = htmlspecialchars($content_url, ENT_QUOTES, 'UTF-8');
                return '<a href="' . $url_enc . '" target="_blank" rel="noopener noreferrer" '
                    . 'class="btn btn-info text-white">Open on Telegram</a>';

            default:
                return '<a href="' . htmlspecialchars($content_url, ENT_QUOTES, 'UTF-8') . '" '
                    . 'target="_blank" rel="noopener noreferrer">View Content</a>';
        }
    }
}

if (!function_exists('getActiveCampaigns')) {
    /**
     * Get available (active & approved) campaigns, optionally filtered by platform or excluding completed by user
     */
    function getActiveCampaigns(PDO $pdo, ?int $platform_id = null, ?int $user_id = null): array {
        $params = [];
        $sql = "SELECT sc.*, sp.name AS platform_name, sp.slug AS platform_slug,
                       sp.icon AS platform_icon, sp.color AS platform_color,
                       s.name AS seller_name
                FROM social_campaigns sc
                JOIN social_platforms sp ON sp.id = sc.platform_id
                JOIN sellers s ON s.id = sc.seller_id
                WHERE sc.status = 'active'
                  AND sc.admin_approved = 1
                  AND sc.tasks_completed < sc.total_tasks_needed";

        if ($platform_id) {
            $sql .= " AND sc.platform_id = ?";
            $params[] = $platform_id;
        }

        if ($user_id) {
            $sql .= " AND NOT EXISTS (
                SELECT 1 FROM social_task_completions stc
                WHERE stc.campaign_id = sc.id AND stc.user_id = ?
                  AND stc.status IN ('completed','verified')
            )";
            $params[] = $user_id;
        }

        $sql .= " ORDER BY sc.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getUserSocialStats')) {
    /**
     * Get social task stats for a user
     */
    function getUserSocialStats(PDO $pdo, int $user_id): array {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*) AS total_completed,
                    COALESCE(SUM(reward_amount), 0) AS total_earned
                FROM social_task_completions
                WHERE user_id = ? AND status IN ('completed','verified')
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_completed' => 0, 'total_earned' => 0];
        } catch (PDOException $e) {
            error_log("getUserSocialStats error: " . $e->getMessage());
            return ['total_completed' => 0, 'total_earned' => 0];
        }
    }
}

if (!function_exists('completeSocialTask')) {
    /**
     * Mark a social task as completed and credit user wallet
     */
    function completeSocialTask(PDO $pdo, int $campaign_id, int $user_id, array $watch_data): array {
        try {
            $pdo->beginTransaction();

            // Fetch campaign
            $stmt = $pdo->prepare("SELECT * FROM social_campaigns WHERE id = ? AND status = 'active' AND admin_approved = 1 FOR UPDATE");
            $stmt->execute([$campaign_id]);
            $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$campaign) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Campaign not found or not active.'];
            }

            if ($campaign['tasks_completed'] >= $campaign['total_tasks_needed']) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Campaign has reached its completion limit.'];
            }

            // Check watch percentage
            $watch_percent = (float)($watch_data['watch_percent'] ?? 0);
            if ($watch_percent < $campaign['watch_percent_required']) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Minimum watch requirement not met.'];
            }

            // Check duplicate
            $stmt = $pdo->prepare("SELECT id FROM social_task_completions WHERE campaign_id = ? AND user_id = ?");
            $stmt->execute([$campaign_id, $user_id]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'You have already completed this campaign.'];
            }

            $reward = (float)$campaign['reward_per_task'];
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

            // Insert completion
            $stmt = $pdo->prepare("
                INSERT INTO social_task_completions
                    (campaign_id, user_id, watch_duration, watch_percent, reward_amount, status, completed_at, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, 'completed', NOW(), ?, ?)
            ");
            $stmt->execute([
                $campaign_id,
                $user_id,
                (int)($watch_data['watch_seconds'] ?? 0),
                $watch_percent,
                $reward,
                substr($ip, 0, 45),
                substr($ua, 0, 500),
            ]);

            // Increment campaign counter
            $stmt = $pdo->prepare("UPDATE social_campaigns SET tasks_completed = tasks_completed + 1 WHERE id = ?");
            $stmt->execute([$campaign_id]);

            // Check if campaign is now complete
            if (($campaign['tasks_completed'] + 1) >= $campaign['total_tasks_needed']) {
                $pdo->prepare("UPDATE social_campaigns SET status = 'completed' WHERE id = ?")->execute([$campaign_id]);
            }

            // Credit user wallet using existing addToWallet function if available
            if (function_exists('addToWallet')) {
                addToWallet($pdo, $user_id, $reward, 'social_task', 'Reward for watching: ' . $campaign['title']);
            } else {
                // Fallback: direct wallet_transactions insert
                $stmt = $pdo->prepare("
                    INSERT INTO wallet_transactions (user_id, type, amount, description, created_at)
                    VALUES (?, 'credit', ?, ?, NOW())
                ");
                $stmt->execute([$user_id, $reward, 'Reward for watching: ' . $campaign['title']]);
                // Update wallet balance
                $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")->execute([$reward, $user_id]);
            }

            $pdo->commit();
            return ['success' => true, 'reward' => $reward];

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("completeSocialTask error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error. Please try again.'];
        }
    }
}

if (!function_exists('createCampaign')) {
    /**
     * Create a new social campaign and deduct seller wallet
     */
    function createCampaign(PDO $pdo, int $seller_id, array $data): array {
        try {
            $pdo->beginTransaction();

            $reward        = (float)$data['reward_per_task'];
            $total_tasks   = (int)$data['total_tasks_needed'];
            $platform_fee  = round($reward * $total_tasks * 0.20, 2);
            $budget        = round($reward * $total_tasks + $platform_fee, 2);

            // Check seller wallet balance
            $stmt = $pdo->prepare("SELECT balance FROM seller_wallet WHERE seller_id = ?");
            $stmt->execute([$seller_id]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
            $balance = $wallet ? (float)$wallet['balance'] : 0;

            if ($balance < $budget) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Insufficient wallet balance. Required: ₹' . number_format($budget, 2)];
            }

            // Extract embed code and thumbnail
            $platform_slug = $data['platform_slug'] ?? '';
            $embed_code    = generateEmbed($platform_slug, $data['content_url']);
            $thumbnail_url = $data['thumbnail_url'] ?? '';

            if ($platform_slug === 'youtube' && !$thumbnail_url) {
                $vid = extractVideoId($data['content_url']);
                if ($vid) {
                    $thumbnail_url = 'https://img.youtube.com/vi/' . $vid . '/mqdefault.jpg';
                }
            }

            // Insert campaign
            $stmt = $pdo->prepare("
                INSERT INTO social_campaigns
                    (seller_id, platform_id, title, description, content_url, embed_code, thumbnail_url,
                     task_type, reward_per_task, total_tasks_needed, budget, platform_fee,
                     watch_percent_required, category, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $seller_id,
                (int)$data['platform_id'],
                $data['title'],
                $data['description'] ?? '',
                $data['content_url'],
                $embed_code,
                $thumbnail_url,
                $data['task_type'] ?? 'watch',
                $reward,
                $total_tasks,
                $budget,
                $platform_fee,
                (int)($data['watch_percent_required'] ?? 50),
                $data['category'] ?? 'general',
            ]);
            $campaign_id = (int)$pdo->lastInsertId();

            // Deduct seller wallet
            $pdo->prepare("UPDATE seller_wallet SET balance = balance - ? WHERE seller_id = ?")->execute([$budget, $seller_id]);

            // Log transaction
            $stmt = $pdo->prepare("
                INSERT INTO seller_wallet_transactions (seller_id, type, amount, description, created_at)
                VALUES (?, 'debit', ?, ?, NOW())
            ");
            $stmt->execute([$seller_id, $budget, 'Social campaign budget: ' . $data['title']]);

            $pdo->commit();
            return ['success' => true, 'campaign_id' => $campaign_id, 'budget' => $budget];

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("createCampaign error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create campaign. Please try again.'];
        }
    }
}

if (!function_exists('getCampaignStats')) {
    /**
     * Get analytics for a campaign
     */
    function getCampaignStats(PDO $pdo, int $campaign_id): array {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    sc.*,
                    sp.name AS platform_name,
                    sp.slug AS platform_slug,
                    sp.icon AS platform_icon,
                    sp.color AS platform_color,
                    COUNT(stc.id) AS total_completions,
                    COALESCE(SUM(stc.reward_amount), 0) AS total_paid,
                    COALESCE(AVG(stc.watch_percent), 0) AS avg_watch_percent
                FROM social_campaigns sc
                JOIN social_platforms sp ON sp.id = sc.platform_id
                LEFT JOIN social_task_completions stc ON stc.campaign_id = sc.id AND stc.status IN ('completed','verified')
                WHERE sc.id = ?
                GROUP BY sc.id
            ");
            $stmt->execute([$campaign_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("getCampaignStats error: " . $e->getMessage());
            return [];
        }
    }
}
