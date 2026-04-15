<?php

function ensure_supervisor_email_support(PDO $pdo): void {
    static $checked = false;

    if ($checked) {
        return;
    }

    try {
        $column = $pdo->query("SHOW COLUMNS FROM employers LIKE 'email'")->fetch(PDO::FETCH_ASSOC);
        if (!$column) {
            $pdo->exec("ALTER TABLE employers ADD COLUMN email VARCHAR(255) DEFAULT NULL AFTER username");
        }
    } catch (PDOException $e) {
        error_log("Failed to ensure employers.email column: " . $e->getMessage());
    }

    $checked = true;
}

function ensure_evaluation_verification_table(PDO $pdo): void {
    static $checked = false;

    if ($checked) {
        return;
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS evaluation_verification_codes (
                verification_id INT AUTO_INCREMENT PRIMARY KEY,
                verification_key VARCHAR(64) NOT NULL UNIQUE,
                employer_id INT NOT NULL,
                student_id INT NOT NULL,
                sent_to_email VARCHAR(255) NOT NULL,
                code_hash VARCHAR(255) NOT NULL,
                attempts TINYINT NOT NULL DEFAULT 0,
                max_attempts TINYINT NOT NULL DEFAULT 5,
                expires_at DATETIME NOT NULL,
                verified_at DATETIME DEFAULT NULL,
                used_at DATETIME DEFAULT NULL,
                evaluation_id INT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_eval_verify_lookup (verification_key),
                INDEX idx_eval_verify_owner (employer_id, student_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        // Repair legacy schema where verification_id exists but is not AUTO_INCREMENT.
        $idColumn = $pdo->query("SHOW COLUMNS FROM evaluation_verification_codes LIKE 'verification_id'")
            ->fetch(PDO::FETCH_ASSOC);
        $idExtra = strtolower((string) ($idColumn['Extra'] ?? ''));
        if ($idColumn && strpos($idExtra, 'auto_increment') === false) {
            $maxId = (int) ($pdo->query("SELECT COALESCE(MAX(verification_id), 0) FROM evaluation_verification_codes")
                ->fetchColumn());
            $pdo->exec("ALTER TABLE evaluation_verification_codes MODIFY verification_id INT NOT NULL AUTO_INCREMENT");
            if ($maxId > 0) {
                $pdo->exec("ALTER TABLE evaluation_verification_codes AUTO_INCREMENT = " . ($maxId + 1));
            }
        }
    } catch (PDOException $e) {
        error_log("Failed to ensure evaluation_verification_codes table: " . $e->getMessage());
    }

    $checked = true;
}

function ensure_evaluation_security_schema(PDO $pdo): void {
    ensure_supervisor_email_support($pdo);
    ensure_evaluation_verification_table($pdo);
}

function generate_evaluation_verification_code(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function generate_evaluation_verification_key(): string {
    return bin2hex(random_bytes(16));
}

function mask_email_address(string $email): string {
    $email = trim($email);
    if ($email === '' || strpos($email, '@') === false) {
        return $email;
    }

    [$local, $domain] = explode('@', $email, 2);
    $localLength = strlen($local);

    if ($localLength <= 2) {
        $maskedLocal = substr($local, 0, 1) . '*';
    } else {
        $maskedLocal = substr($local, 0, 2) . str_repeat('*', max(1, $localLength - 2));
    }

    return $maskedLocal . '@' . $domain;
}

function create_evaluation_verification_request(PDO $pdo, int $employerId, int $studentId, string $email): array {
    ensure_evaluation_security_schema($pdo);

    $verificationKey = generate_evaluation_verification_key();
    $plainCode = generate_evaluation_verification_code();
    $expiresAt = date('Y-m-d H:i:s', time() + (10 * 60));

    $deactivate = $pdo->prepare("
        UPDATE evaluation_verification_codes
        SET used_at = NOW()
        WHERE employer_id = ?
          AND student_id = ?
          AND verified_at IS NULL
          AND used_at IS NULL
    ");
    $deactivate->execute([$employerId, $studentId]);

    $insert = $pdo->prepare("
        INSERT INTO evaluation_verification_codes (
            verification_key, employer_id, student_id, sent_to_email,
            code_hash, expires_at
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    try {
        $insert->execute([
            $verificationKey,
            $employerId,
            $studentId,
            $email,
            password_hash($plainCode, PASSWORD_DEFAULT),
            $expiresAt
        ]);
    } catch (PDOException $e) {
        // Last-chance repair for environments where the table existed without AUTO_INCREMENT.
        if (strpos($e->getMessage(), "Duplicate entry '0' for key 'PRIMARY'") !== false) {
            $pdo->exec("ALTER TABLE evaluation_verification_codes MODIFY verification_id INT NOT NULL AUTO_INCREMENT");
            $insert->execute([
                $verificationKey,
                $employerId,
                $studentId,
                $email,
                password_hash($plainCode, PASSWORD_DEFAULT),
                $expiresAt
            ]);
        } else {
            throw $e;
        }
    }

    return [
        'verification_key' => $verificationKey,
        'plain_code' => $plainCode,
        'expires_at' => $expiresAt,
    ];
}

function get_evaluation_verification_request(PDO $pdo, string $verificationKey): ?array {
    ensure_evaluation_security_schema($pdo);

    $stmt = $pdo->prepare("
        SELECT *
        FROM evaluation_verification_codes
        WHERE verification_key = ?
        LIMIT 1
    ");
    $stmt->execute([$verificationKey]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function is_evaluation_verification_complete(PDO $pdo, string $verificationKey, int $employerId, int $studentId): bool {
    $row = get_evaluation_verification_request($pdo, $verificationKey);

    if (!$row) {
        return false;
    }

    if ((int) $row['employer_id'] !== $employerId || (int) $row['student_id'] !== $studentId) {
        return false;
    }

    if (!empty($row['used_at']) || empty($row['verified_at'])) {
        return false;
    }

    return strtotime((string) $row['expires_at']) >= time();
}

function verify_evaluation_verification_code(PDO $pdo, string $verificationKey, int $employerId, int $studentId, string $submittedCode): array {
    $row = get_evaluation_verification_request($pdo, $verificationKey);

    if (!$row) {
        return ['success' => false, 'message' => 'No verification request was found. Please request a new code.'];
    }

    if ((int) $row['employer_id'] !== $employerId || (int) $row['student_id'] !== $studentId) {
        return ['success' => false, 'message' => 'This verification code does not match the selected evaluation.'];
    }

    if (!empty($row['used_at'])) {
        return ['success' => false, 'message' => 'This verification code is no longer active. Please request a new code.'];
    }

    if (strtotime((string) $row['expires_at']) < time()) {
        return ['success' => false, 'message' => 'The verification code has expired. Please request a new one.'];
    }

    if (!empty($row['verified_at'])) {
        return ['success' => true, 'message' => 'Verification already completed.'];
    }

    $attempts = (int) ($row['attempts'] ?? 0);
    $maxAttempts = (int) ($row['max_attempts'] ?? 5);
    if ($attempts >= $maxAttempts) {
        return ['success' => false, 'message' => 'Maximum verification attempts reached. Please request a new code.'];
    }

    $submittedCode = trim($submittedCode);
    if ($submittedCode !== '' && password_verify($submittedCode, $row['code_hash'])) {
        $stmt = $pdo->prepare("
            UPDATE evaluation_verification_codes
            SET verified_at = NOW()
            WHERE verification_key = ?
        ");
        $stmt->execute([$verificationKey]);

        return ['success' => true, 'message' => 'Email verification successful. You may now continue with the evaluation.'];
    }

    $stmt = $pdo->prepare("
        UPDATE evaluation_verification_codes
        SET attempts = attempts + 1
        WHERE verification_key = ?
    ");
    $stmt->execute([$verificationKey]);

    $remaining = max(0, $maxAttempts - ($attempts + 1));
    $message = $remaining > 0
        ? "Incorrect verification code. {$remaining} attempt(s) remaining."
        : 'Incorrect verification code. Please request a new one.';

    return ['success' => false, 'message' => $message];
}

function mark_evaluation_verification_used(PDO $pdo, string $verificationKey, int $evaluationId): void {
    $stmt = $pdo->prepare("
        UPDATE evaluation_verification_codes
        SET used_at = NOW(), evaluation_id = ?
        WHERE verification_key = ?
          AND verified_at IS NOT NULL
          AND used_at IS NULL
    ");
    $stmt->execute([$evaluationId, $verificationKey]);
}
