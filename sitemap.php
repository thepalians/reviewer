<?php
/**
 * Dynamic Sitemap Generator
 * Generates XML sitemap for SEO
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/seo-functions.php';

header('Content-Type: application/xml; charset=utf-8');

$db = new Database();
$pdo = $db->connect();

// Start XML
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

// Get sitemap entries from SEO settings
$entries = generateSitemapEntries($pdo);

foreach ($entries as $entry) {
    echo '<url>';
    echo '<loc>' . htmlspecialchars($entry['loc']) . '</loc>';
    echo '<lastmod>' . date('Y-m-d', strtotime($entry['lastmod'])) . '</lastmod>';
    echo '<changefreq>' . $entry['changefreq'] . '</changefreq>';
    echo '<priority>' . $entry['priority'] . '</priority>';
    echo '</url>';
}

// Add additional static pages
$static_pages = [
    ['loc' => APP_URL . '/', 'priority' => '1.0', 'changefreq' => 'daily'],
    ['loc' => APP_URL . '/index.php', 'priority' => '1.0', 'changefreq' => 'daily'],
    ['loc' => APP_URL . '/help.php', 'priority' => '0.7', 'changefreq' => 'weekly'],
];

foreach ($static_pages as $page) {
    echo '<url>';
    echo '<loc>' . htmlspecialchars($page['loc']) . '</loc>';
    echo '<lastmod>' . date('Y-m-d') . '</lastmod>';
    echo '<changefreq>' . $page['changefreq'] . '</changefreq>';
    echo '<priority>' . $page['priority'] . '</priority>';
    echo '</url>';
}

echo '</urlset>';
