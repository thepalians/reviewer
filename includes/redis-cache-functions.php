<?php
declare(strict_types=1);

/**
 * Redis Cache Functions
 * Caching layer with file fallback
 */

// Check if Redis is available
$GLOBALS['redis_available'] = false;
if (extension_loaded('redis')) {
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->ping();
        $GLOBALS['redis_available'] = true;
        $GLOBALS['redis_instance'] = $redis;
    } catch (Exception $e) {
        error_log("Redis connection failed: " . $e->getMessage());
        $GLOBALS['redis_available'] = false;
    }
}

/**
 * Get cached value
 */
function cacheGet(string $key): mixed {
    global $redis_available, $redis_instance, $pdo;
    
    try {
        if ($redis_available) {
            $value = $redis_instance->get($key);
            if ($value !== false) {
                return json_decode($value, true);
            }
        }
        
        // Fallback to database cache
        $stmt = $pdo->prepare("
            SELECT cache_value FROM cache_entries 
            WHERE cache_key = ? AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        
        return $result ? json_decode($result, true) : null;
    } catch (Exception $e) {
        error_log("Cache get error: " . $e->getMessage());
        return null;
    }
}

/**
 * Set cache value
 */
function cacheSet(string $key, mixed $value, int $ttl = 3600): bool {
    global $redis_available, $redis_instance, $pdo;
    
    try {
        $jsonValue = json_encode($value);
        
        if ($redis_available) {
            $redis_instance->setex($key, $ttl, $jsonValue);
        }
        
        // Also store in database cache
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
        $stmt = $pdo->prepare("
            INSERT INTO cache_entries (cache_key, cache_value, expires_at)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE cache_value = ?, expires_at = ?
        ");
        return $stmt->execute([$key, $jsonValue, $expiresAt, $jsonValue, $expiresAt]);
    } catch (Exception $e) {
        error_log("Cache set error: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete cache key
 */
function cacheDelete(string $key): bool {
    global $redis_available, $redis_instance, $pdo;
    
    try {
        if ($redis_available) {
            $redis_instance->del($key);
        }
        
        $stmt = $pdo->prepare("DELETE FROM cache_entries WHERE cache_key = ?");
        return $stmt->execute([$key]);
    } catch (Exception $e) {
        error_log("Cache delete error: " . $e->getMessage());
        return false;
    }
}

/**
 * Clear cache by pattern
 */
function cacheClear(string $pattern = '*'): bool {
    global $redis_available, $redis_instance, $pdo;
    
    try {
        if ($redis_available) {
            $keys = $redis_instance->keys($pattern);
            if (!empty($keys)) {
                $redis_instance->del($keys);
            }
        }
        
        // Clear database cache
        if ($pattern === '*') {
            $stmt = $pdo->query("DELETE FROM cache_entries");
        } else {
            $likePattern = str_replace('*', '%', $pattern);
            $stmt = $pdo->prepare("DELETE FROM cache_entries WHERE cache_key LIKE ?");
            $stmt->execute([$likePattern]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Cache clear error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get cached or execute function
 */
function cacheRemember(string $key, int $ttl, callable $callback): mixed {
    $cached = cacheGet($key);
    
    if ($cached !== null) {
        return $cached;
    }
    
    $value = $callback();
    cacheSet($key, $value, $ttl);
    
    return $value;
}

/**
 * Clean expired cache entries
 */
function cacheCleanExpired(): int {
    global $pdo;
    
    try {
        $stmt = $pdo->query("DELETE FROM cache_entries WHERE expires_at < NOW()");
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Cache cleanup error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get cache statistics
 */
function getCacheStats(): array {
    global $redis_available, $redis_instance, $pdo;
    
    $stats = [
        'redis_available' => $redis_available,
        'db_entries' => 0,
        'redis_keys' => 0,
        'redis_memory' => 0
    ];
    
    try {
        // Database cache stats
        $stmt = $pdo->query("SELECT COUNT(*) FROM cache_entries");
        $stats['db_entries'] = $stmt->fetchColumn();
        
        if ($redis_available) {
            $stats['redis_keys'] = $redis_instance->dbSize();
            $info = $redis_instance->info('memory');
            $stats['redis_memory'] = $info['used_memory_human'] ?? 'N/A';
        }
    } catch (Exception $e) {
        error_log("Cache stats error: " . $e->getMessage());
    }
    
    return $stats;
}
