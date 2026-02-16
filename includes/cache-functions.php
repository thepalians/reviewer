<?php
/**
 * Caching Functions
 * Provides file-based caching with support for Redis (optional)
 */

declare(strict_types=1);

const CACHE_DIR = __DIR__ . '/../cache/';
const CACHE_DEFAULT_TTL = 3600; // 1 hour
const CACHE_ENABLED = true;

/**
 * Initialize cache directory
 * 
 * @return bool
 */
function initCache(): bool {
    if (!file_exists(CACHE_DIR)) {
        return mkdir(CACHE_DIR, 0755, true);
    }
    return true;
}

/**
 * Get cache key path
 * 
 * @param string $key
 * @return string
 */
function getCacheFilePath(string $key): string {
    $hash = md5($key);
    $dir = CACHE_DIR . substr($hash, 0, 2) . '/';
    
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    
    return $dir . $hash . '.cache';
}

/**
 * Get value from cache
 * 
 * @param string $key
 * @param mixed $default Default value if not found or expired
 * @return mixed
 */
function cacheGet(string $key, $default = null) {
    if (!CACHE_ENABLED) {
        return $default;
    }
    
    $filePath = getCacheFilePath($key);
    
    if (!file_exists($filePath)) {
        return $default;
    }
    
    $data = unserialize(file_get_contents($filePath));
    
    if (!is_array($data) || !isset($data['expires']) || !isset($data['value'])) {
        return $default;
    }
    
    // Check expiration
    if ($data['expires'] > 0 && time() > $data['expires']) {
        unlink($filePath);
        return $default;
    }
    
    return $data['value'];
}

/**
 * Set value in cache
 * 
 * @param string $key
 * @param mixed $value
 * @param int $ttl Time to live in seconds (0 = no expiration)
 * @return bool
 */
function cacheSet(string $key, $value, int $ttl = CACHE_DEFAULT_TTL): bool {
    if (!CACHE_ENABLED) {
        return false;
    }
    
    initCache();
    
    $filePath = getCacheFilePath($key);
    $expires = $ttl > 0 ? time() + $ttl : 0;
    
    $data = [
        'key' => $key,
        'value' => $value,
        'expires' => $expires,
        'created' => time()
    ];
    
    return file_put_contents($filePath, serialize($data)) !== false;
}

/**
 * Delete value from cache
 * 
 * @param string $key
 * @return bool
 */
function cacheDelete(string $key): bool {
    $filePath = getCacheFilePath($key);
    
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    
    return true;
}

/**
 * Check if key exists in cache and is not expired
 * 
 * @param string $key
 * @return bool
 */
function cacheHas(string $key): bool {
    if (!CACHE_ENABLED) {
        return false;
    }
    
    $filePath = getCacheFilePath($key);
    
    if (!file_exists($filePath)) {
        return false;
    }
    
    $data = unserialize(file_get_contents($filePath));
    
    if (!is_array($data) || !isset($data['expires'])) {
        return false;
    }
    
    // Check expiration
    if ($data['expires'] > 0 && time() > $data['expires']) {
        unlink($filePath);
        return false;
    }
    
    return true;
}

/**
 * Clear all cache
 * 
 * @return int Number of files deleted
 */
function cacheClear(): int {
    if (!file_exists(CACHE_DIR)) {
        return 0;
    }
    
    $count = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(CACHE_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'cache') {
            unlink($file->getPathname());
            $count++;
        }
    }
    
    return $count;
}

/**
 * Clear expired cache entries
 * 
 * @return int Number of files deleted
 */
function cacheClearExpired(): int {
    if (!file_exists(CACHE_DIR)) {
        return 0;
    }
    
    $count = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(CACHE_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'cache') {
            $data = unserialize(file_get_contents($file->getPathname()));
            
            if (is_array($data) && isset($data['expires']) && $data['expires'] > 0 && time() > $data['expires']) {
                unlink($file->getPathname());
                $count++;
            }
        }
    }
    
    return $count;
}

/**
 * Get cache statistics
 * 
 * @return array
 */
function cacheStats(): array {
    $stats = [
        'total_files' => 0,
        'total_size' => 0,
        'expired_files' => 0,
        'valid_files' => 0
    ];
    
    if (!file_exists(CACHE_DIR)) {
        return $stats;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(CACHE_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'cache') {
            $stats['total_files']++;
            $stats['total_size'] += $file->getSize();
            
            $data = unserialize(file_get_contents($file->getPathname()));
            
            if (is_array($data) && isset($data['expires'])) {
                if ($data['expires'] > 0 && time() > $data['expires']) {
                    $stats['expired_files']++;
                } else {
                    $stats['valid_files']++;
                }
            }
        }
    }
    
    return $stats;
}

/**
 * Cache a database query result
 * 
 * @param string $query
 * @param callable $callback
 * @param int $ttl
 * @return mixed
 */
function cacheQuery(string $query, callable $callback, int $ttl = CACHE_DEFAULT_TTL) {
    $cacheKey = 'query_' . md5($query);
    
    $result = cacheGet($cacheKey);
    
    if ($result === null) {
        $result = $callback();
        cacheSet($cacheKey, $result, $ttl);
    }
    
    return $result;
}

/**
 * Remember value in cache (get or set)
 * 
 * @param string $key
 * @param callable $callback
 * @param int $ttl
 * @return mixed
 */
function cacheRemember(string $key, callable $callback, int $ttl = CACHE_DEFAULT_TTL) {
    $value = cacheGet($key);
    
    if ($value === null) {
        $value = $callback();
        cacheSet($key, $value, $ttl);
    }
    
    return $value;
}

/**
 * Increment cache value
 * 
 * @param string $key
 * @param int $amount
 * @return int New value
 */
function cacheIncrement(string $key, int $amount = 1): int {
    $value = (int)cacheGet($key, 0);
    $value += $amount;
    cacheSet($key, $value);
    
    return $value;
}

/**
 * Decrement cache value
 * 
 * @param string $key
 * @param int $amount
 * @return int New value
 */
function cacheDecrement(string $key, int $amount = 1): int {
    $value = (int)cacheGet($key, 0);
    $value -= $amount;
    cacheSet($key, $value);
    
    return $value;
}

/**
 * Get multiple values from cache
 * 
 * @param array $keys
 * @param mixed $default
 * @return array
 */
function cacheGetMultiple(array $keys, $default = null): array {
    $results = [];
    
    foreach ($keys as $key) {
        $results[$key] = cacheGet($key, $default);
    }
    
    return $results;
}

/**
 * Set multiple values in cache
 * 
 * @param array $values Key-value pairs
 * @param int $ttl
 * @return bool
 */
function cacheSetMultiple(array $values, int $ttl = CACHE_DEFAULT_TTL): bool {
    $success = true;
    
    foreach ($values as $key => $value) {
        if (!cacheSet($key, $value, $ttl)) {
            $success = false;
        }
    }
    
    return $success;
}

/**
 * Delete multiple values from cache
 * 
 * @param array $keys
 * @return bool
 */
function cacheDeleteMultiple(array $keys): bool {
    $success = true;
    
    foreach ($keys as $key) {
        if (!cacheDelete($key)) {
            $success = false;
        }
    }
    
    return $success;
}

/**
 * Cache tags support - tag a cache entry
 * 
 * @param string $key
 * @param string|array $tags
 * @return bool
 */
function cacheTag(string $key, $tags): bool {
    if (!is_array($tags)) {
        $tags = [$tags];
    }
    
    foreach ($tags as $tag) {
        $tagKey = 'tag_' . $tag;
        $taggedKeys = cacheGet($tagKey, []);
        
        if (!in_array($key, $taggedKeys)) {
            $taggedKeys[] = $key;
            cacheSet($tagKey, $taggedKeys, 0); // No expiration for tag lists
        }
    }
    
    return true;
}

/**
 * Flush cache by tag
 * 
 * @param string $tag
 * @return int Number of entries deleted
 */
function cacheFlushTag(string $tag): int {
    $tagKey = 'tag_' . $tag;
    $taggedKeys = cacheGet($tagKey, []);
    $count = 0;
    
    foreach ($taggedKeys as $key) {
        if (cacheDelete($key)) {
            $count++;
        }
    }
    
    cacheDelete($tagKey);
    
    return $count;
}
