<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');


header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
include_once __DIR__ . '/../../private/config.php';
include_once __DIR__ . '/../../includes/db.php';

$is_authenticated = false;
if (isset($_SESSION['employer_id']) || isset($_SESSION['admin_id']) || isset($_SESSION['student_id'])) {
    $is_authenticated = true;
}

if (!$is_authenticated) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized'
    ]);
    exit;
}


$since = $_GET['since'] ?? null;
$student_id = $_GET['student_id'] ?? null;

try {
    
    $query = "
        SELECT 
            a.id,
            a.student_id,
            a.log_date,
            a.time_in,
            a.time_out,
            a.lunch_out,
            a.lunch_in,
            a.status,
            a.updated_at,
            a.created_at,
            s.first_name,
            s.last_name
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.student_id
    ";
    
    $conditions = [];
    $params = [];
    
    
    if ($student_id) {
        $conditions[] = "a.student_id = ?";
        $params[] = $student_id;
    }
    
    
    if ($since) {
        $conditions[] = "GREATEST(
            COALESCE(a.time_in, '0000-00-00 00:00:00'),
            COALESCE(a.time_out, '0000-00-00 00:00:00'),
            COALESCE(a.lunch_out, '0000-00-00 00:00:00'),
            COALESCE(a.lunch_in, '0000-00-00 00:00:00'),
            COALESCE(a.updated_at, '0000-00-00 00:00:00'),
            COALESCE(a.created_at, '0000-00-00 00:00:00'),
            a.log_date
        ) > ?";
        $params[] = $since;
    }
    
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $query .= " ORDER BY GREATEST(
        COALESCE(a.time_in, '0000-00-00 00:00:00'),
        COALESCE(a.time_out, '0000-00-00 00:00:00'),
        COALESCE(a.lunch_out, '0000-00-00 00:00:00'),
        COALESCE(a.lunch_in, '0000-00-00 00:00:00'),
        COALESCE(a.updated_at, '0000-00-00 00:00:00'),
        COALESCE(a.created_at, '0000-00-00 00:00:00'),
        a.log_date
    ) DESC";
    
    
    if ($since) {
        $query .= " LIMIT 1";
    } else {
        $query .= " LIMIT 50";
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $latest = !empty($records) ? $records[0] : null;
    
    if ($latest) {
        
        $latest_action = null;
        $latest_timestamp = null;
        
        if (!empty($latest['time_in']) && $latest['time_in'] !== '0000-00-00 00:00:00') {
            $latest_action = 'time_in';
            $latest_timestamp = $latest['time_in'];
        }
        if (!empty($latest['lunch_out']) && $latest['lunch_out'] !== '0000-00-00 00:00:00') {
            $latest_action = 'lunch_out';
            $latest_timestamp = $latest['lunch_out'];
        }
        if (!empty($latest['lunch_in']) && $latest['lunch_in'] !== '0000-00-00 00:00:00') {
            $latest_action = 'lunch_in';
            $latest_timestamp = $latest['lunch_in'];
        }
        if (!empty($latest['time_out']) && $latest['time_out'] !== '0000-00-00 00:00:00') {
            $latest_action = 'time_out';
            $latest_timestamp = $latest['time_out'];
        }
        
        
        if (!empty($latest['updated_at']) && $latest['updated_at'] !== '0000-00-00 00:00:00') {
            if ($latest_timestamp === null || strtotime($latest['updated_at']) > strtotime($latest_timestamp)) {
                $latest_timestamp = $latest['updated_at'];
            }
        }
        
        echo json_encode([
            'success' => true,
            'latest_timestamp' => $latest_timestamp,
            'latest_student' => $latest['student_id'],
            'latest_student_name' => ($latest['first_name'] ?? '') . ' ' . ($latest['last_name'] ?? ''),
            'latest_action' => $latest_action,
            'log_date' => $latest['log_date']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'latest_timestamp' => null,
            'message' => 'No attendance records found'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Attendance polling error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
