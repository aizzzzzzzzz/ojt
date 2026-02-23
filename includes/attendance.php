<?php
/**
 * Attendance handling functions
 * Note: WebSocket notifications have been removed - using polling instead
 */

function handle_attendance_action($pdo, $student_id, $today, $action) {
    $allowed = ['time_in','lunch_out','lunch_in','time_out'];
    if (!in_array($action, $allowed)) {
        return "Invalid action.";
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND log_date = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$student_id, $today]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $now = date('Y-m-d H:i:s');

        if (!$row) {
            if ($action !== 'time_in') {
                $pdo->rollBack();
                return "You must Time In first.";
            } else {
                $insert = $pdo->prepare("INSERT INTO attendance (student_id, employer_id, log_date, time_in, status) VALUES (?, NULL, ?, ?, 'present')");
                $insert->execute([$student_id, $today, $now]);
                $pdo->commit();
                
                // Note: WebSocket notification removed - using polling instead
                
                return "Time In recorded at " . date('H:i:s', strtotime($now)) . ".";
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
                    break;
                case 'lunch_out':
                    if (!$row['time_in']) {
                        $pdo->rollBack();
                        return "You need to Time In first.";
                    }
                    if ($row['lunch_out']) {
                        $pdo->rollBack();
                        return "Lunch Out already recorded ({$row['lunch_out']}).";
                    }
                    $updates[] = "lunch_out = ?";
                    $params[] = $now;
                    break;
                case 'lunch_in':
                    if (!$row['lunch_out']) {
                        $pdo->rollBack();
                        return "You need to Lunch Out first.";
                    }
                    if ($row['lunch_in']) {
                        $pdo->rollBack();
                        return "Lunch In already recorded ({$row['lunch_in']}).";
                    }
                    $updates[] = "lunch_in = ?";
                    $params[] = $now;
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
                
                // Note: WebSocket notification removed - using polling instead
                
                return ucfirst(str_replace('_',' ', $action)) . " recorded at " . date('H:i:s', strtotime($now)) . ".";
            }
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return "Database error: " . $e->getMessage();
    }
    return "";
}
?>
