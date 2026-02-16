<?php
/**
 * Simple .env file loader
 * Loads environment variables from .env file into $_ENV and getenv()
 */

function loadEnv(string $path = null): void {
    if ($path === null) {
        $path = __DIR__ . '/../.env';
    }
    
    if (!file_exists($path)) {
        // In production, .env might not exist if using server environment variables
        return;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    if ($lines === false) {
        return;
    }
    
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            // Set in $_ENV and putenv()
            if (!empty($key)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

/**
 * Get environment variable with fallback
 */
function env(string $key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    
    if ($value === false) {
        return $default;
    }
    
    // Convert string booleans
    if (strtolower($value) === 'true') {
        return true;
    }
    if (strtolower($value) === 'false') {
        return false;
    }
    if (strtolower($value) === 'null') {
        return null;
    }
    
    return $value;
}

// Auto-load .env when this file is included
loadEnv();
