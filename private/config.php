<?php
// config.php - Enhanced Security Configuration

// Security Headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
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

// Database Configuration
$host = "localhost";
$db   = "student_db";
$user = "root";
$pass = "";

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

// CSRF token with enhanced security
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

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token'], $_SESSION['csrf_expires']) &&
           time() <= $_SESSION['csrf_expires'] &&
           hash_equals($_SESSION['csrf_token'], $token);
}

// Enhanced Input sanitization
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $data;
}

// Password validation function
function validate_password($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number, 1 special char
    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
    return preg_match($pattern, $password);
}

// Rate limiting for login attempts
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
?>


