<?php

function get_students_list($pdo, $employer_id = null) {
    if ($employer_id === null) {
        $stmt = $pdo->query("SELECT student_id, username FROM students ORDER BY username");
    } else {
        $companyStmt = $pdo->prepare("SELECT company_id FROM employers WHERE employer_id = ?");
        $companyStmt->execute([$employer_id]);
        $companyData = $companyStmt->fetch();
        
        if (!$companyData || !$companyData['company_id']) {
            $stmt = $pdo->prepare("
                SELECT student_id, username 
                FROM students 
                WHERE created_by = ? 
                ORDER BY username
            ");
            $stmt->execute([$employer_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT s.student_id, s.username
                FROM students s
                WHERE s.company_id = ?
                ORDER BY s.username
            ");
            $stmt->execute([$companyData['company_id']]);
        }
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_attendance_records($pdo, $employer_id = null) {
    if ($employer_id === null) {
        $sql = "
            SELECT
                s.student_id,
                s.first_name,
                s.middle_name,
                s.last_name,
                s.school,
                s.required_hours,
                a.log_date,
                a.time_in,
                a.lunch_out,
                a.lunch_in,
                a.time_out,
                a.status,
                a.reason,
                a.verified,
                a.daily_task,
                CASE
                    WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL THEN
                        CONCAT(
                            FLOOR((
                                TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out)
                                - COALESCE(TIMESTAMPDIFF(MINUTE, a.lunch_out, a.lunch_in), 0)
                            ) / 60), 'h ',
                            MOD((
                                TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out)
                                - COALESCE(TIMESTAMPDIFF(MINUTE, a.lunch_out, a.lunch_in), 0)
                            ), 60), 'm'
                        )
                    ELSE '---'
                END AS daily_hours
            FROM students s
            LEFT JOIN attendance a ON s.student_id = a.student_id
            ORDER BY s.last_name, s.first_name, s.middle_name, a.log_date
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } else {
        $companyStmt = $pdo->prepare("SELECT company_id FROM employers WHERE employer_id = ?");
        $companyStmt->execute([$employer_id]);
        $companyData = $companyStmt->fetch();
        
        if (!$companyData || !$companyData['company_id']) {
            $sql = "
                SELECT
                    s.student_id,
                    s.first_name,
                    s.middle_name,
                    s.last_name,
                    s.school,
                    s.required_hours,
                    a.log_date,
                    a.time_in,
                    a.lunch_out,
                    a.lunch_in,
                    a.time_out,
                    a.status,
                    a.reason,
                    a.verified,
                    a.daily_task,
                    CASE
                        WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL THEN
                            CONCAT(
                                FLOOR((
                                    TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out)
                                    - COALESCE(TIMESTAMPDIFF(MINUTE, a.lunch_out, a.lunch_in), 0)
                                ) / 60), 'h ',
                                MOD((
                                    TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out)
                                    - COALESCE(TIMESTAMPDIFF(MINUTE, a.lunch_out, a.lunch_in), 0)
                                ), 60), 'm'
                            )
                        ELSE '---'
                    END AS daily_hours
                FROM students s
                LEFT JOIN attendance a ON s.student_id = a.student_id
                WHERE s.created_by = ?
                ORDER BY s.last_name, s.first_name, s.middle_name, a.log_date
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$employer_id]);
        } else {
            $sql = "
                SELECT
                    s.student_id,
                    s.first_name,
                    s.middle_name,
                    s.last_name,
                    s.school,
                    s.required_hours,
                    a.log_date,
                    a.time_in,
                    a.lunch_out,
                    a.lunch_in,
                    a.time_out,
                    a.status,
                    a.reason,
                    a.verified,
                    a.daily_task,
                    CASE
                        WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL THEN
                            CONCAT(
                                FLOOR((
                                    TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out)
                                    - COALESCE(TIMESTAMPDIFF(MINUTE, a.lunch_out, a.lunch_in), 0)
                                ) / 60), 'h ',
                                MOD((
                                    TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out)
                                    - COALESCE(TIMESTAMPDIFF(MINUTE, a.lunch_out, a.lunch_in), 0)
                                ), 60), 'm'
                            )
                        ELSE '---'
                    END AS daily_hours
                FROM students s
                LEFT JOIN attendance a ON s.student_id = a.student_id
                WHERE s.company_id = ?
                ORDER BY s.last_name, s.first_name, s.middle_name, a.log_date
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$companyData['company_id']]);
        }
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_total_minutes($pdo, $employer_id = null) {
    if ($employer_id === null) {
        $sql = "
            SELECT a.student_id,
                SUM(
                    CASE
                        WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL AND a.verified = 1 THEN
                            TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out)
                            - COALESCE(TIMESTAMPDIFF(MINUTE, a.lunch_out, a.lunch_in), 0)
                        ELSE 0
                    END
                ) AS total_minutes
            FROM attendance a
            GROUP BY a.student_id
        ";
        $acc_stmt = $pdo->prepare($sql);
        $acc_stmt->execute();
    } else {
        $companyStmt = $pdo->prepare("SELECT company_id FROM employers WHERE employer_id = ?");
        $companyStmt->execute([$employer_id]);
        $companyData = $companyStmt->fetch();
        
        if (!$companyData || !$companyData['company_id']) {
            $sql = "
                SELECT a.student_id,
                    SUM(
                        CASE
                            WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL AND a.verified = 1 THEN
                                TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out)
                                - COALESCE(TIMESTAMPDIFF(MINUTE, a.lunch_out, a.lunch_in), 0)
                            ELSE 0
                        END
                    ) AS total_minutes
                FROM attendance a
                INNER JOIN students s ON a.student_id = s.student_id
                WHERE s.created_by = ?
                GROUP BY a.student_id
            ";
            $acc_stmt = $pdo->prepare($sql);
            $acc_stmt->execute([$employer_id]);
        } else {
            $sql = "
                SELECT a.student_id,
                    SUM(
                        CASE
                            WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL AND a.verified = 1 THEN
                                TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out)
                                - COALESCE(TIMESTAMPDIFF(MINUTE, a.lunch_out, a.lunch_in), 0)
                            ELSE 0
                        END
                    ) AS total_minutes
                FROM attendance a
                INNER JOIN students s ON a.student_id = s.student_id
                WHERE s.company_id = ?
                GROUP BY a.student_id
            ";
            $acc_stmt = $pdo->prepare($sql);
            $acc_stmt->execute([$companyData['company_id']]);
        }
    }
    
    $acc_rows = $acc_stmt->fetchAll(PDO::FETCH_ASSOC);
    $acc_map = [];
    foreach ($acc_rows as $ar) {
        $acc_map[$ar['student_id']] = (int) ($ar['total_minutes'] ?? 0);
    }
    return $acc_map;
}

function get_evaluated_students($pdo, $employer_id = null) {
    if ($employer_id === null) {
        $eval_stmt = $pdo->prepare("
            SELECT DISTINCT e.student_id
            FROM evaluations e
        ");
        $eval_stmt->execute();
    } else {
        $companyStmt = $pdo->prepare("SELECT company_id FROM employers WHERE employer_id = ?");
        $companyStmt->execute([$employer_id]);
        $companyData = $companyStmt->fetch();
        
        if (!$companyData || !$companyData['company_id']) {
            $eval_stmt = $pdo->prepare("
                SELECT DISTINCT e.student_id
                FROM evaluations e
                INNER JOIN students s ON e.student_id = s.student_id
                WHERE s.created_by = ?
            ");
            $eval_stmt->execute([$employer_id]);
        } else {
            $eval_stmt = $pdo->prepare("
                SELECT DISTINCT e.student_id
                FROM evaluations e
                INNER JOIN students s ON e.student_id = s.student_id
                WHERE s.company_id = ?
            ");
            $eval_stmt->execute([$companyData['company_id']]);
        }
    }
    return array_flip($eval_stmt->fetchAll(PDO::FETCH_COLUMN));
}

function mark_student_absent($pdo, $student_id, $date, $reason, $employer_id = null) {
    if ($employer_id !== null) {
        $companyStmt = $pdo->prepare("SELECT company_id FROM employers WHERE employer_id = ?");
        $companyStmt->execute([$employer_id]);
        $companyData = $companyStmt->fetch();
        
        if ($companyData && $companyData['company_id']) {
            $studentStmt = $pdo->prepare("SELECT company_id FROM students WHERE student_id = ?");
            $studentStmt->execute([$student_id]);
            $studentData = $studentStmt->fetch();
            
            if (!$studentData || $studentData['company_id'] != $companyData['company_id']) {
                return "You don't have permission to mark this student absent";
            }
        } else {
            $studentStmt = $pdo->prepare("SELECT created_by FROM students WHERE student_id = ?");
            $studentStmt->execute([$student_id]);
            $studentData = $studentStmt->fetch();
            
            if (!$studentData || $studentData['created_by'] != $employer_id) {
                return "You don't have permission to mark this student absent";
            }
        }
    }
    
    $checkStmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND log_date = ?");
    $checkStmt->execute([$student_id, $date]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        $updateStmt = $pdo->prepare("
            UPDATE attendance
            SET status = 'Absent', reason = ?, verified = 0
            WHERE student_id = ? AND log_date = ?
        ");
        $updateStmt->execute([$reason, $student_id, $date]);
        return "Attendance updated to Absent";
    } else {
        $insertStmt = $pdo->prepare("
            INSERT INTO attendance (student_id, log_date, status, reason, verified)
            VALUES (?, ?, 'Absent', ?, 0)
        ");
        $insertStmt->execute([$student_id, $date, $reason]);
        return "Student marked as Absent";
    }
}
?>