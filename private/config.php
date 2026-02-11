<?php
// config.php - Enhanced Security Configuration

// ADD THIS AT THE VERY TOP to prevent multiple includes
if (defined('CONFIG_LOADED')) {
    return; // Already loaded, exit early
}
define('CONFIG_LOADED', true);

// Security Headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
// Enable HSTS only if not on localhost to avoid SSL errors during development
if (!in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'])) {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// Content Security Policy
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self'; connect-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;");

// Session Security Configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', 3600); // 1 hour
    ini_set('session.cookie_lifetime', 0); // Session cookie
    session_start();
}

// Regenerate session ID periodically for security
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Database Configuration - Using environment variables for portability
$host = getenv('DB_HOST') ?: "localhost";
$db   = getenv('DB_NAME') ?: "student_db";
$user = getenv('DB_USER') ?: "root";
$pass = getenv('DB_PASS') ?: "";

// Database connection with enhanced security
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    $pdo->exec("SET time_zone = '+08:00'");
    date_default_timezone_set('Asia/Manila');
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// CSRF token with enhanced security - ADD function_exists checks
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_expires'] = time() + 3600; // 1 hour expiry
        } elseif (time() > $_SESSION['csrf_expires']) {
            // Token expired, generate new one
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_expires'] = time() + 3600;
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validate_csrf_token')) {
    function validate_csrf_token($token) {
        return isset($_SESSION['csrf_token'], $_SESSION['csrf_expires']) &&
               time() <= $_SESSION['csrf_expires'] &&
               hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Enhanced Input sanitization
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $data;
    }
}

// Enhanced logging function
if (!function_exists('log_security_event')) {
    function log_security_event($event, $details = '', $level = 'INFO') {
        $logFile = __DIR__ . '/../logs/security.log';
        $logDir = dirname($logFile);

        // Create logs directory if it doesn't exist
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user = $_SESSION['student_id'] ?? 'unknown';

        $logEntry = sprintf(
            "[%s] [%s] [%s] [%s] %s: %s\n",
            $timestamp,
            $level,
            $ip,
            $user,
            $event,
            $details
        );

        error_log($logEntry, 3, $logFile);
    }
}

// Password validation function
if (!function_exists('validate_password')) {
    function validate_password($password) {
        // At least 8 characters, 1 uppercase, 1 lowercase, 1 number, 1 special char
        $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
        return preg_match($pattern, $password);
    }
}

// Email Configuration
$email_config = [
    // SMTP Settings
    'smtp_host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com', // Default to Gmail SMTP
    'smtp_port' => getenv('SMTP_PORT') ?: 587, // TLS port
    'smtp_encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls', // tls or ssl
    'smtp_username' => getenv('SMTP_USERNAME') ?: 'aizjedlian@gmail.com',
    'smtp_password' => getenv('SMTP_PASSWORD') ?: 'kriiazezwyeqqpnw',

    // Email Settings
    'from_email' => getenv('FROM_EMAIL') ?: 'noreply@yourdomain.com',
    'from_name' => getenv('FROM_NAME') ?: 'OJT System',
    'reply_to_email' => getenv('REPLY_TO_EMAIL') ?: 'noreply@yourdomain.com',
    'reply_to_name' => getenv('REPLY_TO_NAME') ?: 'OJT System Support',

    // Additional Settings
    'debug_mode' => getenv('EMAIL_DEBUG') ?: false, // Set to true for debugging
];

// Rate limiting for login attempts
if (!function_exists('check_login_attempts')) {
    function check_login_attempts($identifier) {
        $max_attempts = 5;
        $lockout_time = 900; // 15 minutes

        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = [];
        }

        $now = time();

        // Clean up old attempts
        $_SESSION['login_attempts'] = array_filter($_SESSION['login_attempts'], function($attempt) use ($now, $lockout_time) {
            return ($now - $attempt['time']) < $lockout_time;
        });

        // Check if identifier is locked out
        foreach ($_SESSION['login_attempts'] as $attempt) {
            if ($attempt['identifier'] === $identifier && $attempt['count'] >= $max_attempts) {
                if (($now - $attempt['time']) < $lockout_time) {
                    return false; // Locked out
                }
            }
        }

        return true; // Allow attempt
    }
}

if (!function_exists('record_login_attempt')) {
    function record_login_attempt($identifier) {
        $now = time();

        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = [];
        }

        $found = false;
        foreach ($_SESSION['login_attempts'] as &$attempt) {
            if ($attempt['identifier'] === $identifier) {
                $attempt['count']++;
                $attempt['time'] = $now;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $_SESSION['login_attempts'][] = [
                'identifier' => $identifier,
                'count' => 1,
                'time' => $now
            ];
        }
    }
}
?>