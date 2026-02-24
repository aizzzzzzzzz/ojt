<?php

if (defined('CONFIG_LOADED')) {
    return;
}
define('CONFIG_LOADED', true);


$is_local = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:8080', '127.0.0.1:8080']);



$host = getenv('DB_HOST') ?: "sql106.infinityfree.com";
$db   = getenv('DB_NAME') ?: "if0_41121145_student_db";
$user = getenv('DB_USER') ?: "if0_41121145";
$pass = getenv('DB_PASS') ?: "6fvGRVlsh9";


if ($is_local) {
    $host = "localhost";
    $db   = "student_db";
    $user = "root";
    $pass = "";
}


$https_available = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
                   (!in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) && 
                    isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");


if ($https_available) {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}

header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");


header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https: blob:; font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; connect-src 'self' https:; frame-ancestors 'self';");


if (session_status() === PHP_SESSION_NONE) {
    
    $cookie_secure = $https_available ? 1 : 0;
    
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', $cookie_secure);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', 3600);
    ini_set('session.cookie_lifetime', 0);
    session_start();
}


if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}


try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    $pdo->exec("SET time_zone = '+08:00'");
    date_default_timezone_set('Asia/Manila');
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please check your configuration.");
}


if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_expires'] = time() + 3600;
        } elseif (time() > $_SESSION['csrf_expires']) {
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


if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        if (is_array($data)) {
            return array_map('sanitize_input', $data);
        }
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $data;
    }
}


if (!function_exists('log_security_event')) {
    function log_security_event($event, $details = '', $level = 'INFO') {
        $logFile = __DIR__ . '/../logs/security.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user = $_SESSION['student_id'] ?? $_SESSION['employer_id'] ?? $_SESSION['admin_id'] ?? 'unknown';

        $logEntry = sprintf(
            "[%s] [%s] [%s] [%s] %s: %s\n",
            $timestamp,
            $level,
            $ip,
            $user,
            $event,
            $details
        );

        @error_log($logEntry, 3, $logFile);
    }
}


if (!function_exists('validate_password')) {
    function validate_password($password) {
        $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
        return preg_match($pattern, $password);
    }
}


$email_config = [
    'smtp_host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
    'smtp_port' => getenv('SMTP_PORT') ?: 587,
    'smtp_encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
    'smtp_username' => getenv('SMTP_USERNAME') ?: 'aizjedlian@gmail.com',
    'smtp_password' => getenv('SMTP_PASSWORD') ?: 'kriiazezwyeqqpnw',
    'from_email' => getenv('FROM_EMAIL') ?: 'noreply@yourdomain.com',
    'from_name' => getenv('FROM_NAME') ?: 'OJT System',
    'reply_to_email' => getenv('REPLY_TO_EMAIL') ?: 'noreply@yourdomain.com',
    'reply_to_name' => getenv('REPLY_TO_NAME') ?: 'OJT System Support',
    'debug_mode' => getenv('EMAIL_DEBUG') ?: false,
];


if (!function_exists('check_login_attempts')) {
    function check_login_attempts($identifier) {
        $max_attempts = 5;
        $lockout_time = 900;

        if (!isset($_SESSION['login_attempts']) || !isset($_SESSION['login_attempts'][$identifier])) {
            return true;
        }

        $attempt = $_SESSION['login_attempts'][$identifier];
        $now = time();
        
        
        if (($now - $attempt['time']) < $lockout_time) {
            if ($attempt['count'] >= $max_attempts) {
                return false;
            }
        }
        
        return true;
    }
}

if (!function_exists('record_login_attempt')) {
    function record_login_attempt($identifier) {
        $now = time();

        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = [];
        }

        if (isset($_SESSION['login_attempts'][$identifier])) {
            $_SESSION['login_attempts'][$identifier]['count']++;
            $_SESSION['login_attempts'][$identifier]['time'] = $now;
        } else {
            $_SESSION['login_attempts'][$identifier] = [
                'count' => 1,
                'time' => $now
            ];
        }
    }
}
?>
