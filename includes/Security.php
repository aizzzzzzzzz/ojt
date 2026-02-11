<?php
class Security {
    private static $instance = null;

    private function __construct() {}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function generateCSRFToken() {
        return generate_csrf_token();
    }

    public function validateCSRFToken($token) {
        return validate_csrf_token($token);
    }

    public function sanitizeInput($data) {
        return sanitize_input($data);
    }

    public function sanitizeArray($data) {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } else {
                $sanitized[$key] = $this->sanitizeInput($value);
            }
        }
        return $sanitized;
    }

    public function validatePassword($password) {
        return validate_password($password);
    }

    public function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    public function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }

    public function checkRateLimit($identifier) {
        return check_login_attempts($identifier);
    }

    public function recordLoginAttempt($identifier) {
        record_login_attempt($identifier);
    }

    public function cleanForDisplay($data) {
        if (is_array($data)) {
            return array_map([$this, 'cleanForDisplay'], $data);
        }
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function validateFileUpload($file, $allowedTypes = [], $maxSize = 5242880) { // 5MB default
        $errors = [];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error: ' . $file['error'];
            return $errors;
        }

        if ($file['size'] > $maxSize) {
            $errors[] = 'File size exceeds maximum allowed size.';
        }

        if (!empty($allowedTypes)) {
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($fileInfo, $file['tmp_name']);
            finfo_close($fileInfo);

            if (!in_array($mimeType, $allowedTypes)) {
                $errors[] = 'File type not allowed.';
            }
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'Invalid file upload.';
        }

        return $errors;
    }

    public function moveUploadedFile($file, $destination) {
        $destinationDir = dirname($destination);

        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        return move_uploaded_file($file['tmp_name'], $destination);
    }

    public function generateSecureFilename($originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        return $this->generateRandomString(16) . '.' . $extension;
    }

    public function logSecurityEvent($event, $details = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => json_encode($details)
        ];

        $logFile = __DIR__ . '/../private/security.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logEntry = implode(' | ', $logData) . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

        try {
            $db = Database::getInstance();
            $db->execute(
                "INSERT INTO security_logs (event, ip_address, user_agent, details, created_at) VALUES (?, ?, ?, ?, NOW())",
                [$event, $logData['ip'], $logData['user_agent'], $logData['details']]
            );
        } catch (Exception $e) {
            
        }
    }

    public function detectSuspiciousActivity($data) {
        $suspicious = [];

        $sqlPatterns = ['/union/i', '/select/i', '/insert/i', '/update/i', '/delete/i', '/drop/i', '/--/', '/#/'];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                foreach ($sqlPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $suspicious[] = "Potential SQL injection in $key: $value";
                        break;
                    }
                }
            }
        }

        $xssPatterns = ['/<script/i', '/javascript:/i', '/on\w+\s*=/i'];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                foreach ($xssPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $suspicious[] = "Potential XSS in $key: $value";
                        break;
                    }
                }
            }
        }

        if (!empty($suspicious)) {
            $this->logSecurityEvent('suspicious_activity_detected', [
                'issues' => $suspicious,
                'data' => $data
            ]);
        }

        return $suspicious;
    }

    public function processFormData($data) {
        $this->detectSuspiciousActivity($data);

        return $this->sanitizeArray($data);
    }
}
?>
