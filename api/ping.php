<?php
/**
 * Simple ping endpoint for connection check
 */
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo json_encode([
    'status' => 'ok',
    'time' => time()
]);
