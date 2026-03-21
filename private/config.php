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
$backup_cron_token = getenv('BACKUP_CRON_TOKEN') ?: "";
if (empty($backup_cron_token)) {
    $tokenFile = __DIR__ . '/backup_token.php';
    if (is_file($tokenFile)) {
        $loaded = include $tokenFile;
        if (is_string($loaded) && $loaded !== '') {
            $backup_cron_token = $loaded;
        }
    }
}


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


$csp = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'";
$csp .= " https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;"
      . " style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com;"
      . " img-src 'self' data: https: blob:;"
      . " font-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.gstatic.com;"
      . " connect-src 'self' https:;"
      . " frame-src 'self' blob: data:;"
      . " frame-ancestors 'self';";
header("Content-Security-Policy: {$csp}");


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
    'from_name' => getenv('FROM_NAME') ?: 'Internship System',
    'reply_to_email' => getenv('REPLY_TO_EMAIL') ?: 'noreply@yourdomain.com',
    'reply_to_name' => getenv('REPLY_TO_NAME') ?: 'Internship System Support',
    'debug_mode' => getenv('EMAIL_DEBUG') ?: false,
];


// ---------------------------------------------------------------------------
// DB-based login attempt tracking
// Thresholds:  5 attempts → 30-second lockout (keeps counting)
//             10 attempts → account locked, must reset password via email
// ---------------------------------------------------------------------------

if (!function_exists('get_login_attempt_row')) {
    function get_login_attempt_row($pdo, $username, $ip) {
        $stmt = $pdo->prepare(
            "SELECT * FROM login_attempts WHERE username = ? AND ip_address = ? LIMIT 1"
        );
        $stmt->execute([$username, $ip]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('check_login_attempts')) {
    /**
     * Returns one of:
     *   'ok'       – allowed to attempt
     *   'cooldown' – 30-second cooldown active (5–9 attempts)
     *   'locked'   – hard-locked at 10+ attempts, must reset via email
     */
    function check_login_attempts($pdo, $username) {
        $ip           = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $cooldown_sec = 30;
        $soft_limit   = 3;
        $hard_limit   = 4;

        $row = get_login_attempt_row($pdo, $username, $ip);
        if (!$row) return 'ok';

        $count = (int)$row['attempt_count'];

        // Hard lock — must reset password
        if ($count >= $hard_limit) return 'locked';

        // Soft cooldown
        if ($count >= $soft_limit && $row['locked_at'] !== null) {
            $elapsed = time() - strtotime($row['locked_at']);
            if ($elapsed < $cooldown_sec) return 'cooldown';
        }

        return 'ok';
    }
}

if (!function_exists('get_lockout_remaining')) {
    /** Seconds left in the 30-second cooldown, or 0 if not in cooldown. */
    function get_lockout_remaining($pdo, $username) {
        $ip           = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $cooldown_sec = 30;
        $soft_limit   = 3;

        $row = get_login_attempt_row($pdo, $username, $ip);
        if (!$row || (int)$row['attempt_count'] < $soft_limit || $row['locked_at'] === null) {
            return 0;
        }
        $remaining = $cooldown_sec - (time() - strtotime($row['locked_at']));
        return max(0, (int)$remaining);
    }
}

if (!function_exists('record_login_attempt')) {
    function record_login_attempt($pdo, $username) {
        $ip         = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $soft_limit = 3;

        $row = get_login_attempt_row($pdo, $username, $ip);

        if (!$row) {
            $stmt = $pdo->prepare(
                "INSERT INTO login_attempts (username, ip_address, attempt_count, locked_at)
                 VALUES (?, ?, 1, NULL)"
            );
            $stmt->execute([$username, $ip]);
            return;
        }

        $new_count = (int)$row['attempt_count'] + 1;
        // Set / refresh locked_at every time we hit or exceed the soft limit
        $locked_at = ($new_count >= $soft_limit) ? date('Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare(
            "UPDATE login_attempts
             SET attempt_count = ?, locked_at = ?
             WHERE username = ? AND ip_address = ?"
        );
        $stmt->execute([$new_count, $locked_at, $username, $ip]);
    }
}

if (!function_exists('clear_login_attempts')) {
    function clear_login_attempts($pdo, $username) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $pdo->prepare(
            "DELETE FROM login_attempts WHERE username = ? AND ip_address = ?"
        );
        $stmt->execute([$username, $ip]);
    }
}

if (!function_exists('generate_reset_token')) {
    /**
     * Creates a secure reset token for the given username.
     * Returns the plain token (to be emailed) or false on failure.
     */
    function generate_reset_token($pdo, $username) {
        $ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $token  = bin2hex(random_bytes(32));           // 64-char hex string
        $hashed = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1-hour window

        $stmt = $pdo->prepare(
            "UPDATE login_attempts
             SET reset_token = ?, reset_expires = ?, reset_used = 0
             WHERE username = ? AND ip_address = ?"
        );
        $stmt->execute([$hashed, $expires, $username, $ip]);

        return ($stmt->rowCount() > 0) ? $token : false;
    }
}

if (!function_exists('validate_reset_token')) {
    /**
     * Returns the username the token belongs to, or false if invalid/expired.
     */
    function validate_reset_token($pdo, $token) {
        $hashed = hash('sha256', $token);
        $stmt   = $pdo->prepare(
            "SELECT username FROM login_attempts
             WHERE reset_token = ?
               AND reset_expires > NOW()
               AND reset_used = 0
             LIMIT 1"
        );
        $stmt->execute([$hashed]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['username'] : false;
    }
}

if (!function_exists('consume_reset_token')) {
    function consume_reset_token($pdo, $token) {
        $hashed = hash('sha256', $token);
        $stmt   = $pdo->prepare(
            "UPDATE login_attempts
             SET reset_used = 1, reset_token = NULL, attempt_count = 0, locked_at = NULL
             WHERE reset_token = ?"
        );
        $stmt->execute([$hashed]);
    }
}
?>