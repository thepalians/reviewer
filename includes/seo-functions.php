<?php
/**
 * SEO Functions
 * Helper functions for SEO management and meta tags
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Get SEO settings for a page
 */
function getSeoSettings($pdo, $page_slug) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM seo_settings WHERE page_slug = ?");
        $stmt->execute([$page_slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting SEO settings: " . $e->getMessage());
        return null;
    }
}

/**
 * Update or create SEO settings
 */
function updateSeoSettings($pdo, $page_slug, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO seo_settings 
            (page_slug, meta_title, meta_description, meta_keywords, og_title, 
             og_description, og_image, canonical_url, no_index, no_follow)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                meta_title = VALUES(meta_title),
                meta_description = VALUES(meta_description),
                meta_keywords = VALUES(meta_keywords),
                og_title = VALUES(og_title),
                og_description = VALUES(og_description),
                og_image = VALUES(og_image),
                canonical_url = VALUES(canonical_url),
                no_index = VALUES(no_index),
                no_follow = VALUES(no_follow)
        ");
        
        return $stmt->execute([
            $page_slug,
            $data['meta_title'] ?? null,
            $data['meta_description'] ?? null,
            $data['meta_keywords'] ?? null,
            $data['og_title'] ?? null,
            $data['og_description'] ?? null,
            $data['og_image'] ?? null,
            $data['canonical_url'] ?? null,
            $data['no_index'] ?? 0,
            $data['no_follow'] ?? 0
        ]);
    } catch (PDOException $e) {
        error_log("Error updating SEO settings: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate meta tags HTML
 */
function generateMetaTags($seo_settings, $defaults = []) {
    $html = '';
    
    // Meta title
    $title = $seo_settings['meta_title'] ?? $defaults['title'] ?? APP_NAME;
    $html .= "<title>" . htmlspecialchars($title) . "</title>\n";
    
    // Meta description
    $description = $seo_settings['meta_description'] ?? $defaults['description'] ?? '';
    if ($description) {
        $html .= '<meta name="description" content="' . htmlspecialchars($description) . '">' . "\n";
    }
    
    // Meta keywords
    $keywords = $seo_settings['meta_keywords'] ?? $defaults['keywords'] ?? '';
    if ($keywords) {
        $html .= '<meta name="keywords" content="' . htmlspecialchars($keywords) . '">' . "\n";
    }
    
    // Canonical URL
    $canonical = $seo_settings['canonical_url'] ?? $defaults['canonical'] ?? '';
    if ($canonical) {
        $html .= '<link rel="canonical" href="' . htmlspecialchars($canonical) . '">' . "\n";
    }
    
    // Robots meta
    $robots = [];
    if (!empty($seo_settings['no_index'])) {
        $robots[] = 'noindex';
    }
    if (!empty($seo_settings['no_follow'])) {
        $robots[] = 'nofollow';
    }
    if (!empty($robots)) {
        $html .= '<meta name="robots" content="' . implode(', ', $robots) . '">' . "\n";
    }
    
    return $html;
}

/**
 * Generate Open Graph tags
 */
function generateOpenGraphTags($seo_settings, $defaults = []) {
    $html = '';
    
    // OG Title
    $og_title = $seo_settings['og_title'] ?? $seo_settings['meta_title'] ?? $defaults['title'] ?? APP_NAME;
    $html .= '<meta property="og:title" content="' . htmlspecialchars($og_title) . '">' . "\n";
    
    // OG Description
    $og_description = $seo_settings['og_description'] ?? $seo_settings['meta_description'] ?? $defaults['description'] ?? '';
    if ($og_description) {
        $html .= '<meta property="og:description" content="' . htmlspecialchars($og_description) . '">' . "\n";
    }
    
    // OG Image
    $og_image = $seo_settings['og_image'] ?? $defaults['image'] ?? APP_URL . '/assets/images/og-default.jpg';
    $html .= '<meta property="og:image" content="' . htmlspecialchars($og_image) . '">' . "\n";
    
    // OG URL
    $og_url = $seo_settings['canonical_url'] ?? $defaults['url'] ?? '';
    if ($og_url) {
        $html .= '<meta property="og:url" content="' . htmlspecialchars($og_url) . '">' . "\n";
    }
    
    // OG Type
    $html .= '<meta property="og:type" content="website">' . "\n";
    
    // OG Site Name
    $html .= '<meta property="og:site_name" content="' . htmlspecialchars(APP_NAME) . '">' . "\n";
    
    return $html;
}

/**
 * Generate Twitter Card tags
 */
function generateTwitterCardTags($seo_settings, $defaults = []) {
    $html = '';
    
    // Twitter Card Type
    $html .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
    
    // Twitter Title
    $twitter_title = $seo_settings['og_title'] ?? $seo_settings['meta_title'] ?? $defaults['title'] ?? APP_NAME;
    $html .= '<meta name="twitter:title" content="' . htmlspecialchars($twitter_title) . '">' . "\n";
    
    // Twitter Description
    $twitter_description = $seo_settings['og_description'] ?? $seo_settings['meta_description'] ?? $defaults['description'] ?? '';
    if ($twitter_description) {
        $html .= '<meta name="twitter:description" content="' . htmlspecialchars($twitter_description) . '">' . "\n";
    }
    
    // Twitter Image
    $twitter_image = $seo_settings['og_image'] ?? $defaults['image'] ?? APP_URL . '/assets/images/og-default.jpg';
    $html .= '<meta name="twitter:image" content="' . htmlspecialchars($twitter_image) . '">' . "\n";
    
    return $html;
}

/**
 * Generate Schema.org markup
 */
function generateSchemaMarkup($type = 'WebSite', $data = []) {
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => $type
    ];
    
    switch ($type) {
        case 'WebSite':
            $schema['name'] = APP_NAME;
            $schema['url'] = APP_URL;
            if (isset($data['description'])) {
                $schema['description'] = $data['description'];
            }
            break;
            
        case 'Organization':
            $schema['name'] = APP_NAME;
            $schema['url'] = APP_URL;
            if (isset($data['logo'])) {
                $schema['logo'] = $data['logo'];
            }
            break;
            
        case 'BreadcrumbList':
            if (isset($data['items'])) {
                $schema['itemListElement'] = $data['items'];
            }
            break;
    }
    
    $schema = array_merge($schema, $data);
    
    return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
}

/**
 * Get all pages for SEO management
 */
function getAllSeoPages($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM seo_settings ORDER BY page_slug ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting SEO pages: " . $e->getMessage());
        return [];
    }
}

/**
 * Generate sitemap entries
 */
function generateSitemapEntries($pdo) {
    try {
        $entries = [];
        
        // Get all SEO pages
        $pages = getAllSeoPages($pdo);
        
        foreach ($pages as $page) {
            if (!$page['no_index']) {
                $url = $page['canonical_url'] ?? APP_URL . '/' . $page['page_slug'];
                
                $entries[] = [
                    'loc' => $url,
                    'lastmod' => $page['updated_at'],
                    'changefreq' => 'weekly',
                    'priority' => '0.8'
                ];
            }
        }
        
        return $entries;
    } catch (PDOException $e) {
        error_log("Error generating sitemap entries: " . $e->getMessage());
        return [];
    }
}

/**
 * Clean URL for SEO
 */
function cleanUrlForSeo($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

/**
 * Generate meta description from content
 */
function generateMetaDescription($content, $max_length = 160) {
    $content = strip_tags($content);
    $content = preg_replace('/\s+/', ' ', $content);
    $content = trim($content);
    
    if (strlen($content) > $max_length) {
        $content = substr($content, 0, $max_length - 3) . '...';
    }
    
    return $content;
}

/**
 * Validate URL
 */
function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}
