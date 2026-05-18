<?php
/**
 * Database Configuration
 * Presswick Sailing Club Issue Reporting System
 */

// Load environment variables if .env exists
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            // Remove surrounding quotes if present
            if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Function to get env var with fallback
if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        return $value;
    }
}

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'u798276650_psc'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));
define('APP_TIMEZONE', env('APP_TIMEZONE', 'Europe/London'));

// Align PHP and MySQL on the same timezone so created_at + relative time agree.
date_default_timezone_set(APP_TIMEZONE);


/**
 * Get database connection using PDO
 */
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            // Set MySQL session timezone to match PHP so NOW() and PHP DateTime agree.
            try {
                $offset = (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('P');
                $pdo->exec("SET time_zone = '" . $offset . "'");
            } catch (Exception $tzErr) {
                error_log('Timezone sync failed: ' . $tzErr->getMessage());
            }
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    
    return $pdo;
}

/**
 * Base URL Detection - More robust approach for live servers
 */
function getBaseUrl() {
    static $baseUrl = null;
    
    if ($baseUrl === null) {
        // Handle CLI context
        if (php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST'])) {
            $baseUrl = '';
            return $baseUrl;
        }
        
        // Try to detect the base URL more reliably
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Get the directory path where the application is installed
        $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Find the application root by looking at the script path
        $pathParts = explode('/', trim($scriptPath, '/'));
        
        // Remove script filename if present
        $scriptFile = end($pathParts);
        if (strpos($scriptFile, '.php') !== false) {
            array_pop($pathParts);
        }
        
        // Remove common subdirectories to find app root
        $lastPart = end($pathParts);
        if (in_array($lastPart, ['admin', 'problems', 'api', 'config', 'includes'])) {
            array_pop($pathParts);
        }
        
        // Build the base URL
        $appPath = !empty($pathParts) ? '/' . implode('/', $pathParts) : '';
        $baseUrl = $protocol . '://' . $host . $appPath;
        
        // Clean up trailing slashes
        $baseUrl = rtrim($baseUrl, '/');
        
        // Debug logging for troubleshooting
        error_log("PSC Debug - getBaseUrl(): protocol={$protocol}, host={$host}, scriptPath={$scriptPath}, appPath={$appPath}, final baseUrl={$baseUrl}");
    }
    
    return $baseUrl;
}

function getFullUrl($path = '') {
    $baseUrl = getBaseUrl();
    $path = ltrim($path, '/');
    return $baseUrl . ($path ? '/' . $path : '');
}

/**
 * Asset URL with a filemtime cache-buster, so browsers refetch on every change.
 * Falls back gracefully if the file can't be stat'd.
 */
function assetUrl($path) {
    $full = getFullUrl($path);
    $local = dirname(__DIR__) . '/' . ltrim($path, '/');
    $ver = @filemtime($local);
    if ($ver === false) {
        $ver = defined('APP_BUILD') ? APP_BUILD : time();
    }
    return $full . (strpos($full, '?') === false ? '?' : '&') . 'v=' . $ver;
}

// Alternative function for relative URLs (no domain)
function getRelativeUrl($path = '') {
    // Get just the path part without protocol/domain
    $fullUrl = getFullUrl($path);
    $parsed = parse_url($fullUrl);
    return $parsed['path'] ?? '/';
}

// Define base URL constant
define('BASE_URL', getBaseUrl());

/**
 * Application Settings
 */
define('APP_NAME', env('APP_NAME', 'PSC Issues'));
// Flat uploads directory (no per-problem subfolders)
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('UPLOAD_URL', getFullUrl('uploads/'));
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
// Empty array means allow any extension (validation handled in upload logic)
define('ALLOWED_EXTENSIONS', []); // previously: ['jpg', 'jpeg', 'png', 'gif']
define('MAX_IMAGES_PER_PROBLEM', 4);

// Ensure upload directory exists
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
?>