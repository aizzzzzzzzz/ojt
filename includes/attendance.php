<?php
require_once __DIR__ . '/audit.php';

function calculate_shift_status($pdo, $student_id, $today, $time_in) {
    try {
        $stmt = $pdo->prepare("
            SELECT e.work_start, e.late_grace_minutes
            FROM students s
            LEFT JOIN employers e ON s.created_by = e.employer_id
            WHERE s.student_id = ?
            LIMIT 1
        ");
        $stmt->execute([$student_id]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$schedule || empty($schedule['work_start'])) {
            $stmt = $pdo->prepare("
                SELECT e.work_start, e.late_grace_minutes
                FROM students s
                LEFT JOIN employers e ON s.company_id = e.company_id
                WHERE s.student_id = ?
                LIMIT 1
            ");
            $stmt->execute([$student_id]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $work_start = $schedule['work_start'] ?? '08:00:00';
        $late_grace_minutes = (int)($schedule['late_grace_minutes'] ?? 10);

        $tz = new DateTimeZone('Asia/Manila');
        $time_in_dt = new DateTime($time_in, $tz);
        $work_start_dt = new DateTime($today . ' ' . $work_start, $tz);
        $grace_cutoff = clone $work_start_dt;
        $grace_cutoff->modify("+{$late_grace_minutes} minutes");

        $late_minutes = 0;
        if ($time_in_dt > $work_start_dt) {
            $diff = $work_start_dt->diff($time_in_dt);
            $late_minutes = ($diff->h * 60) + $diff->i;
        }

        $shift_status = 'on_time';
        $effective_start_time = null;

        if ($time_in_dt <= $work_start_dt) {
            $shift_status = 'on_time';
            $late_minutes = 0;
        } elseif ($time_in_dt <= $grace_cutoff) {
            $shift_status = 'late_grace';
        } else {
            $shift_status = 'adjusted_shift';
            $effective_start_time = $time_in;
        }

        return [
            'shift_status' => $shift_status,
            'late_minutes' => $late_minutes,
            'effective_start_time' => $effective_start_time
        ];

    } catch (Exception $e) {
        error_log("Shift status calculation error: " . $e->getMessage());
        return [
            'shift_status' => 'on_time',
            'late_minutes' => 0,
            'effective_start_time' => null
        ];
    }
}

function handle_attendance_action($pdo, $student_id, $today, $action) {
    $allowed = ['time_in','time_out'];
    if (!in_array($action, $allowed)) {
        return "Invalid action.";
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND log_date = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$student_id, $today]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $now = $pdo->query("SELECT DATE_FORMAT(UTC_TIMESTAMP() + INTERVAL 8 HOUR, '%Y-%m-%d %H:%i:%s')")->fetchColumn();

        if (!$row) {
            if ($action !== 'time_in') {
                $pdo->rollBack();
                return "You must Time In first.";
            } else {
                $shift_info = calculate_shift_status($pdo, $student_id, $today, $now);

                $insert = $pdo->prepare("
                    INSERT INTO attendance (
                        student_id, employer_id, log_date, time_in, status,
                        shift_status, late_minutes, effective_start_time
                    ) VALUES (?, NULL, ?, ?, 'present', ?, ?, ?)
                ");
                $insert->execute([
                    $student_id,
                    $today,
                    $now,
                    $shift_info['shift_status'],
                    $shift_info['late_minutes'],
                    $shift_info['effective_start_time']
                ]);
                $pdo->commit();

                $status_msg = $shift_info['shift_status'] === 'adjusted_shift'
                    ? " (Adjusted shift - started at " . date('H:i', strtotime($now)) . ")"
                    : "";
                log_activity('Time In', "Student recorded time in at " . date('H:i:s', strtotime($now)) . $status_msg);

                return "Time In recorded at " . date('H:i:s', strtotime($now)) . ". " .
                       ucfirst(str_replace('_', ' ', $shift_info['shift_status'])) .
                       ($shift_info['late_minutes'] > 0 ? " ({$shift_info['late_minutes']} min late)" : "") . ".";
            }
        } else {
            $updates = [];
            $params = [];
            switch ($action) {
                case 'time_in':
                    if ($row['time_in']) {
                        $pdo->rollBack();
                        return "Time In already recorded ({$row['time_in']}).";
                    }
                    $updates[] = "time_in = ?";
                    $params[] = $now;

                    $shift_info = calculate_shift_status($pdo, $student_id, $today, $now);
                    $updates[] = "shift_status = ?";
                    $params[] = $shift_info['shift_status'];
                    $updates[] = "late_minutes = ?";
                    $params[] = $shift_info['late_minutes'];
                    if ($shift_info['effective_start_time']) {
                        $updates[] = "effective_start_time = ?";
                        $params[] = $shift_info['effective_start_time'];
                    }
                    break;
                case 'time_out':
                    if (!$row['time_in']) {
                        $pdo->rollBack();
                        return "You need to Time In first.";
                    }
                    if ($row['time_out']) {
                        $pdo->rollBack();
                        return "Time Out already recorded ({$row['time_out']}).";
                    }
                    $updates[] = "time_out = ?";
                    $params[] = $now;
                    break;
            }

            if (!empty($updates) && $pdo->inTransaction()) {
                $sql = "UPDATE attendance SET " . implode(", ", $updates) . " WHERE student_id = ? AND log_date = ?";
                $params[] = $student_id;
                $params[] = $today;
                $upd = $pdo->prepare($sql);
                $upd->execute($params);
                $pdo->commit();

                log_activity(ucfirst(str_replace('_',' ', $action)), "Student recorded " . strtolower(str_replace('_',' ', $action)) . " at " . date('H:i:s', strtotime($now)));

                return ucfirst(str_replace('_',' ', $action)) . " recorded at " . date('H:i:s', strtotime($now)) . ".";
            }
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return "Database error: " . $e->getMessage();
    }
    return "";
}

function handle_dtr_upload($pdo, $student_id, $today, $file) {
    try {
        if (empty($file['tmp_name'])) {
            return ['success' => false, 'message' => 'No file selected.'];
        }

        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'File too large (max 5MB).'];
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and WebP images are allowed.'];
        }

        $uploadDir = __DIR__ . '/../storage/uploads/dtr/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = $student_id . '_' . date('Y-m-d') . '_' . uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $destPath = $uploadDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['success' => false, 'message' => 'Failed to upload file. Please try again.'];
        }

        $stmt = $pdo->prepare("SELECT dtr_picture FROM attendance WHERE student_id = ? AND log_date = ?");
        $stmt->execute([$student_id, $today]);
        $oldRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($oldRecord && !empty($oldRecord['dtr_picture'])) {
            $oldPath = __DIR__ . '/../storage/uploads/dtr/' . basename($oldRecord['dtr_picture']);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $relativeFilePath = 'storage/uploads/dtr/' . $fileName;
        $updateStmt = $pdo->prepare("UPDATE attendance SET dtr_picture = ? WHERE student_id = ? AND log_date = ?");
        $updateStmt->execute([$relativeFilePath, $student_id, $today]);

        log_activity('DTR Upload', "Student uploaded DTR picture for $today");

        return ['success' => true, 'message' => 'DTR picture uploaded successfully!', 'filePath' => $relativeFilePath];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}
?>
