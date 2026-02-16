<?php
/**
 * Performance Optimization Functions
 * Database query optimization, asset minification, compression
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/cache-functions.php';

/**
 * Enable output compression
 * 
 * @return void
 */
function enableCompression(): void {
    if (!headers_sent() && extension_loaded('zlib')) {
        ini_set('zlib.output_compression', 'On');
        ini_set('zlib.output_compression_level', '6');
    }
}

/**
 * Minify HTML output
 * 
 * @param string $html
 * @return string
 */
function minifyHTML(string $html): string {
    // Remove comments
    $html = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $html);
    
    // Remove whitespace
    $html = preg_replace('/\s+/', ' ', $html);
    
    // Remove spaces around tags
    $html = preg_replace('/>\s+</', '><', $html);
    
    return trim($html);
}

/**
 * Minify CSS
 * 
 * @param string $css
 * @return string
 */
function minifyCSS(string $css): string {
    // Remove comments
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    
    // Remove whitespace
    $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
    $css = preg_replace('/\s+/', ' ', $css);
    
    // Remove spaces around selectors and braces
    $css = preg_replace('/\s*([{}|:;,])\s*/', '$1', $css);
    
    // Remove trailing semicolons
    $css = str_replace(';}', '}', $css);
    
    return trim($css);
}

/**
 * Minify JavaScript
 * 
 * @param string $js
 * @return string
 */
function minifyJS(string $js): string {
    // Remove single-line comments
    $js = preg_replace('~//[^\n]*~', '', $js);
    
    // Remove multi-line comments
    $js = preg_replace('~/\*.*?\*/~s', '', $js);
    
    // Remove whitespace
    $js = preg_replace('/\s+/', ' ', $js);
    
    // Remove spaces around operators
    $js = preg_replace('/\s*([=+\-*\/{}();,:])\s*/', '$1', $js);
    
    return trim($js);
}

/**
 * Optimize database query
 * 
 * @param string $query
 * @return string
 */
function optimizeQuery(string $query): string {
    // Add LIMIT if not present for SELECT queries
    if (stripos($query, 'SELECT') === 0 && stripos($query, 'LIMIT') === false) {
        $query .= ' LIMIT 1000';
    }
    
    return $query;
}

/**
 * Execute optimized query with caching
 * 
 * @param string $query
 * @param array $params
 * @param int $cacheTTL
 * @return array
 */
function executeOptimizedQuery(string $query, array $params = [], int $cacheTTL = 300): array {
    global $conn;
    
    // Create cache key from query and params
    $cacheKey = 'query_' . md5($query . serialize($params));
    
    // Try to get from cache
    $result = cacheGet($cacheKey);
    
    if ($result !== null) {
        return $result;
    }
    
    // Execute query
    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        
        if ($stmt) {
            // Bind parameters dynamically
            $types = '';
            $values = [];
            
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
                $values[] = $param;
            }
            
            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            $queryResult = $stmt->get_result();
        }
    } else {
        $queryResult = $conn->query($query);
    }
    
    $rows = [];
    if ($queryResult) {
        while ($row = $queryResult->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    
    // Cache the result
    cacheSet($cacheKey, $rows, $cacheTTL);
    
    return $rows;
}

/**
 * Lazy load images
 * 
 * @param string $html
 * @return string
 */
function lazyLoadImages(string $html): string {
    // Replace img src with data-src for lazy loading
    $html = preg_replace(
        '/<img([^>]*?)src=["\']([^"\']*)["\']([^>]*?)>/i',
        '<img$1src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="$2"$3 loading="lazy">',
        $html
    );
    
    return $html;
}

/**
 * Get database connection pool
 * 
 * @return mysqli
 */
function getDBConnection(): mysqli {
    static $connections = [];
    
    $connectionId = md5(DB_HOST . DB_USER . DB_NAME);
    
    if (!isset($connections[$connectionId]) || !$connections[$connectionId]->ping()) {
        $connections[$connectionId] = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($connections[$connectionId]->connect_error) {
            die('Connection failed: ' . $connections[$connectionId]->connect_error);
        }
        
        $connections[$connectionId]->set_charset(DB_CHARSET);
    }
    
    return $connections[$connectionId];
}

/**
 * Optimize database tables
 * 
 * @return array Results of optimization
 */
function optimizeTables(): array {
    global $conn;
    
    $results = [];
    
    // Get all tables
    $tablesResult = $conn->query("SHOW TABLES");
    
    while ($row = $tablesResult->fetch_array()) {
        $table = $row[0];
        
        // Optimize table
        $optimizeResult = $conn->query("OPTIMIZE TABLE `{$table}`");
        
        if ($optimizeResult) {
            $results[$table] = 'optimized';
        } else {
            $results[$table] = 'failed';
        }
    }
    
    return $results;
}

/**
 * Analyze slow queries
 * 
 * @return array
 */
function analyzeSlowQueries(): array {
    global $conn;
    
    // Enable slow query log temporarily
    $conn->query("SET GLOBAL slow_query_log = 'ON'");
    $conn->query("SET GLOBAL long_query_time = 2");
    
    // Get slow query log path
    $result = $conn->query("SHOW VARIABLES LIKE 'slow_query_log_file'");
    $row = $result->fetch_assoc();
    $logFile = $row['Value'] ?? '';
    
    if (empty($logFile) || !file_exists($logFile)) {
        return [];
    }
    
    // Parse slow query log (simplified)
    $queries = [];
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        if (strpos($line, '# Query_time:') === 0) {
            $queries[] = $line;
        }
    }
    
    return array_slice($queries, -10); // Return last 10 slow queries
}

/**
 * Get performance metrics
 * 
 * @return array
 */
function getPerformanceMetrics(): array {
    global $conn;
    
    $metrics = [
        'cache_stats' => cacheStats(),
        'db_connections' => 0,
        'memory_usage' => memory_get_usage(true),
        'memory_peak' => memory_get_peak_usage(true),
        'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))
    ];
    
    // Get database stats
    $result = $conn->query("SHOW STATUS LIKE 'Threads_connected'");
    if ($row = $result->fetch_assoc()) {
        $metrics['db_connections'] = (int)$row['Value'];
    }
    
    return $metrics;
}

/**
 * Enable browser caching headers
 * 
 * @param int $maxAge Cache max age in seconds
 * @return void
 */
function setBrowserCacheHeaders(int $maxAge = 86400): void {
    if (!headers_sent()) {
        header('Cache-Control: public, max-age=' . $maxAge);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
        header('Pragma: cache');
    }
}

/**
 * Preload critical resources
 * 
 * @param array $resources
 * @return void
 */
function preloadResources(array $resources): void {
    if (headers_sent()) {
        return;
    }
    
    foreach ($resources as $resource) {
        $type = $resource['type'] ?? 'script';
        $url = $resource['url'] ?? '';
        $as = $resource['as'] ?? $type;
        
        if (!empty($url)) {
            header("Link: <{$url}>; rel=preload; as={$as}", false);
        }
    }
}

/**
 * Get page load time
 * 
 * @return float Time in seconds
 */
function getPageLoadTime(): float {
    return microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
}

/**
 * Start performance monitoring
 * 
 * @return void
 */
function startPerformanceMonitoring(): void {
    $GLOBALS['performance_start'] = microtime(true);
    $GLOBALS['memory_start'] = memory_get_usage(true);
}

/**
 * End performance monitoring and get results
 * 
 * @return array
 */
function endPerformanceMonitoring(): array {
    $endTime = microtime(true);
    $endMemory = memory_get_usage(true);
    
    $startTime = $GLOBALS['performance_start'] ?? $endTime;
    $startMemory = $GLOBALS['memory_start'] ?? $endMemory;
    
    return [
        'execution_time' => round($endTime - $startTime, 4),
        'memory_used' => $endMemory - $startMemory,
        'memory_peak' => memory_get_peak_usage(true),
        'queries_executed' => $GLOBALS['query_count'] ?? 0
    ];
}

/**
 * Log query for performance tracking
 * 
 * @param string $query
 * @param float $executionTime
 * @return void
 */
function logQueryPerformance(string $query, float $executionTime): void {
    if (!isset($GLOBALS['query_count'])) {
        $GLOBALS['query_count'] = 0;
    }
    
    $GLOBALS['query_count']++;
    
    // Log slow queries
    if ($executionTime > 1.0) {
        error_log("Slow query ({$executionTime}s): " . substr($query, 0, 100));
    }
}
