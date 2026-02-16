<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// Check admin authentication
if (!isset($_SESSION['admin_name'])) {
    http_response_code(403);
    die('Access denied');
}

// Template file path
$template_file = __DIR__ . '/../templates/bulk-task-template.csv';

if (!file_exists($template_file)) {
    http_response_code(404);
    die('Template file not found');
}

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="bulk-task-template.csv"');
header('Content-Length: ' . filesize($template_file));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Output file contents
readfile($template_file);
exit;
