<?php
/**
 * Multi-Language Support (i18n) Functions
 * Handles translations and language switching
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Global language cache
$GLOBALS['translations_cache'] = [];
$GLOBALS['current_language'] = 'en';

/**
 * Get all active languages
 * 
 * @return array
 */
function getActiveLanguages(): array {
    global $conn;
    
    $result = $conn->query("
        SELECT * FROM languages 
        WHERE is_active = 1 
        ORDER BY name
    ");
    
    $languages = [];
    while ($row = $result->fetch_assoc()) {
        $languages[] = $row;
    }
    
    return $languages;
}

/**
 * Get language by code
 * 
 * @param string $code
 * @return array|null
 */
function getLanguageByCode(string $code): ?array {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT * FROM languages WHERE code = ?
    ");
    
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Set current language
 * 
 * @param string $languageCode
 * @return bool
 */
function setLanguage(string $languageCode): bool {
    $language = getLanguageByCode($languageCode);
    
    if ($language && $language['is_active']) {
        $GLOBALS['current_language'] = $languageCode;
        $_SESSION['language'] = $languageCode;
        
        // Load translations for this language
        loadTranslations($languageCode);
        
        return true;
    }
    
    return false;
}

/**
 * Get current language code
 * 
 * @return string
 */
function getCurrentLanguage(): string {
    return $GLOBALS['current_language'] ?? $_SESSION['language'] ?? 'en';
}

/**
 * Load translations for a language
 * 
 * @param string $languageCode
 * @return bool
 */
function loadTranslations(string $languageCode): bool {
    global $conn;
    
    // Check if already loaded
    if (isset($GLOBALS['translations_cache'][$languageCode])) {
        return true;
    }
    
    $stmt = $conn->prepare("
        SELECT translation_key, translation_value, module
        FROM translations 
        WHERE language_code = ?
    ");
    
    $stmt->bind_param('s', $languageCode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $translations = [];
    while ($row = $result->fetch_assoc()) {
        $translations[$row['translation_key']] = $row['translation_value'];
    }
    
    $GLOBALS['translations_cache'][$languageCode] = $translations;
    
    return true;
}

/**
 * Translate a string
 * 
 * @param string $key Translation key
 * @param array $params Parameters for placeholder replacement
 * @param string|null $languageCode Language code (null = current language)
 * @return string Translated string or key if not found
 */
function translate(string $key, array $params = [], ?string $languageCode = null): string {
    $languageCode = $languageCode ?? getCurrentLanguage();
    
    // Load translations if not loaded
    if (!isset($GLOBALS['translations_cache'][$languageCode])) {
        loadTranslations($languageCode);
    }
    
    $translations = $GLOBALS['translations_cache'][$languageCode] ?? [];
    $translated = $translations[$key] ?? $key;
    
    // Replace parameters
    foreach ($params as $paramKey => $paramValue) {
        $translated = str_replace('{' . $paramKey . '}', $paramValue, $translated);
    }
    
    return $translated;
}

/**
 * Shorthand for translate function
 * 
 * @param string $key
 * @param array $params
 * @return string
 */
function __($key, $params = []) {
    return translate($key, $params);
}

/**
 * Translate and echo
 * 
 * @param string $key
 * @param array $params
 * @return void
 */
function _e($key, $params = []) {
    echo translate($key, $params);
}

/**
 * Add or update translation
 * 
 * @param string $languageCode
 * @param string $key
 * @param string $value
 * @param string $module
 * @return bool
 */
function saveTranslation(string $languageCode, string $key, string $value, string $module = 'general'): bool {
    global $conn;
    
    $stmt = $conn->prepare("
        INSERT INTO translations 
        (language_code, translation_key, translation_value, module)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        translation_value = VALUES(translation_value),
        module = VALUES(module),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->bind_param('ssss', $languageCode, $key, $value, $module);
    
    $success = $stmt->execute();
    
    // Clear cache
    if ($success && isset($GLOBALS['translations_cache'][$languageCode])) {
        unset($GLOBALS['translations_cache'][$languageCode]);
    }
    
    return $success;
}

/**
 * Get all translations for a language
 * 
 * @param string $languageCode
 * @param string|null $module Filter by module
 * @return array
 */
function getTranslations(string $languageCode, ?string $module = null): array {
    global $conn;
    
    if ($module) {
        $stmt = $conn->prepare("
            SELECT * FROM translations 
            WHERE language_code = ? AND module = ?
            ORDER BY translation_key
        ");
        $stmt->bind_param('ss', $languageCode, $module);
    } else {
        $stmt = $conn->prepare("
            SELECT * FROM translations 
            WHERE language_code = ?
            ORDER BY module, translation_key
        ");
        $stmt->bind_param('s', $languageCode);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $translations = [];
    while ($row = $result->fetch_assoc()) {
        $translations[] = $row;
    }
    
    return $translations;
}

/**
 * Get translation modules
 * 
 * @return array
 */
function getTranslationModules(): array {
    global $conn;
    
    $result = $conn->query("
        SELECT DISTINCT module FROM translations ORDER BY module
    ");
    
    $modules = [];
    while ($row = $result->fetch_assoc()) {
        $modules[] = $row['module'];
    }
    
    return $modules;
}

/**
 * Delete translation
 * 
 * @param string $languageCode
 * @param string $key
 * @return bool
 */
function deleteTranslation(string $languageCode, string $key): bool {
    global $conn;
    
    $stmt = $conn->prepare("
        DELETE FROM translations 
        WHERE language_code = ? AND translation_key = ?
    ");
    
    $stmt->bind_param('ss', $languageCode, $key);
    
    $success = $stmt->execute();
    
    // Clear cache
    if ($success && isset($GLOBALS['translations_cache'][$languageCode])) {
        unset($GLOBALS['translations_cache'][$languageCode]);
    }
    
    return $success;
}

/**
 * Get browser language preference
 * 
 * @return string Language code
 */
function getBrowserLanguage(): string {
    $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    
    if (empty($acceptLanguage)) {
        return 'en';
    }
    
    // Parse Accept-Language header
    $languages = [];
    foreach (explode(',', $acceptLanguage) as $lang) {
        $parts = explode(';q=', $lang);
        $code = trim($parts[0]);
        $quality = isset($parts[1]) ? (float)$parts[1] : 1.0;
        
        // Extract primary language code
        $code = strtolower(substr($code, 0, 2));
        $languages[$code] = $quality;
    }
    
    // Sort by quality
    arsort($languages);
    
    // Find first supported language
    $activeLanguages = getActiveLanguages();
    $supportedCodes = array_column($activeLanguages, 'code');
    
    foreach (array_keys($languages) as $code) {
        if (in_array($code, $supportedCodes)) {
            return $code;
        }
    }
    
    return 'en';
}

/**
 * Set user language preference
 * 
 * @param int $userId
 * @param string $languageCode
 * @return bool
 */
function setUserLanguage(int $userId, string $languageCode): bool {
    global $conn;
    
    $stmt = $conn->prepare("
        UPDATE users 
        SET preferred_language = ?
        WHERE id = ?
    ");
    
    $stmt->bind_param('si', $languageCode, $userId);
    
    return $stmt->execute();
}

/**
 * Get user language preference
 * 
 * @param int $userId
 * @return string
 */
function getUserLanguage(int $userId): string {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT preferred_language FROM users WHERE id = ?
    ");
    
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['preferred_language'] ?? 'en';
    }
    
    return 'en';
}

/**
 * Initialize language for current user
 * 
 * @param int|null $userId
 * @return void
 */
function initLanguage(?int $userId = null): void {
    $languageCode = 'en';
    
    // Priority 1: Session language
    if (isset($_SESSION['language'])) {
        $languageCode = $_SESSION['language'];
    }
    // Priority 2: User preference
    elseif ($userId) {
        $languageCode = getUserLanguage($userId);
    }
    // Priority 3: Browser language
    else {
        $languageCode = getBrowserLanguage();
    }
    
    setLanguage($languageCode);
}

/**
 * Import translations from PHP file
 * 
 * @param string $languageCode
 * @param string $filePath
 * @return int Number of translations imported
 */
function importTranslationsFromFile(string $languageCode, string $filePath): int {
    if (!file_exists($filePath)) {
        return 0;
    }
    
    $translations = include $filePath;
    
    if (!is_array($translations)) {
        return 0;
    }
    
    $count = 0;
    foreach ($translations as $key => $value) {
        $module = 'general';
        
        // Extract module from key if in format "module.key"
        if (strpos($key, '.') !== false) {
            list($module, $key) = explode('.', $key, 2);
        }
        
        if (saveTranslation($languageCode, $key, $value, $module)) {
            $count++;
        }
    }
    
    return $count;
}

/**
 * Export translations to PHP file
 * 
 * @param string $languageCode
 * @param string $filePath
 * @return bool
 */
function exportTranslationsToFile(string $languageCode, string $filePath): bool {
    $translations = getTranslations($languageCode);
    
    $output = "<?php\n";
    $output .= "/**\n";
    $output .= " * Translations for {$languageCode}\n";
    $output .= " * Generated on " . date('Y-m-d H:i:s') . "\n";
    $output .= " */\n\n";
    $output .= "return [\n";
    
    foreach ($translations as $translation) {
        $key = $translation['module'] . '.' . $translation['translation_key'];
        $value = addslashes($translation['translation_value']);
        $output .= "    '{$key}' => '{$value}',\n";
    }
    
    $output .= "];\n";
    
    return file_put_contents($filePath, $output) !== false;
}
