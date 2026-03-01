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
$user_type = null;
$user_id = null;

if (isset($_SESSION['student_id'])) {
    $is_authenticated = true;
    $user_type = 'student';
    $user_id = $_SESSION['student_id'];
} elseif (isset($_SESSION['employer_id'])) {
    $is_authenticated = true;
    $user_type = 'supervisor';
    $user_id = $_SESSION['employer_id'];
} elseif (isset($_SESSION['admin_id'])) {
    $is_authenticated = true;
    $user_type = 'admin';
    $user_id = $_SESSION['admin_id'];
}

if (!$is_authenticated) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized'
    ]);
    exit;
}


$since = $_GET['since'] ?? null;
$type = $_GET['type'] ?? 'all';
$filter_student_id = $_GET['student_id'] ?? null;


if ($user_type === 'student') {
    $filter_student_id = $user_id;
}

$response = [
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'since' => $since
];

try {
    
    if ($type === 'all' || $type === 'certificate') {
        $certQuery = "
            SELECT 
                c.certificate_id,
                c.student_id,
                c.certificate_no,
                c.file_path,
                c.hours_completed,
                c.generated_at,
                s.first_name,
                s.last_name
            FROM certificates c
            LEFT JOIN students s ON c.student_id = s.student_id
        ";
        
        $certConditions = [];
        $certParams = [];
        
        
        if ($filter_student_id) {
            $certConditions[] = "c.student_id = ?";
            $certParams[] = $filter_student_id;
        }
        
        
        if ($since) {
            $certConditions[] = "c.generated_at > ?";
            $certParams[] = $since;
        }
        
        if (!empty($certConditions)) {
            $certQuery .= " WHERE " . implode(" AND ", $certConditions);
        }
        
        $certQuery .= " ORDER BY c.generated_at DESC";
        
        
        if ($since) {
            $certQuery .= " LIMIT 10";
        } else {
            $certQuery .= " LIMIT 50";
        }
        
        $certStmt = $pdo->prepare($certQuery);
        $certStmt->execute($certParams);
        $certificates = $certStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $certLatest = null;
        if (!empty($certificates)) {
            $certLatest = $certificates[0]['generated_at'];
        }
        
        $response['certificates'] = [
            'latest_timestamp' => $certLatest,
            'has_updates' => !empty($certificates),
            'count' => count($certificates),
            'data' => $certificates
        ];
    }
    
    
    if ($type === 'all' || $type === 'project') {
        $projQuery = "
            SELECT 
                ps.submission_id,
                ps.project_id,
                ps.student_id,
                ps.file_path,
                ps.submission_status,
                ps.status,
                ps.remarks,
                ps.submission_date,
                ps.graded_at,
                p.project_name,
                s.first_name,
                s.last_name
            FROM project_submissions ps
            LEFT JOIN projects p ON ps.project_id = p.project_id
            LEFT JOIN students s ON ps.student_id = s.student_id
        ";
        
        $projConditions = [];
        $projParams = [];
        
        
        if ($filter_student_id) {
            $projConditions[] = "ps.student_id = ?";
            $projParams[] = $filter_student_id;
        }
        
        
        if ($since) {
            $projConditions[] = "(ps.submission_date > ? OR ps.graded_at > ?)";
            $projParams[] = $since;
            $projParams[] = $since;
        }
        
        if (!empty($projConditions)) {
            $projQuery .= " WHERE " . implode(" AND ", $projConditions);
        }
        
        
        $projQuery .= " ORDER BY GREATEST(
            COALESCE(ps.submission_date, '0000-00-00 00:00:00'),
            COALESCE(ps.graded_at, '0000-00-00 00:00:00')
        ) DESC";
        
        
        if ($since) {
            $projQuery .= " LIMIT 10";
        } else {
            $projQuery .= " LIMIT 50";
        }
        
        $projStmt = $pdo->prepare($projQuery);
        $projStmt->execute($projParams);
        $submissions = $projStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $projLatest = null;
        if (!empty($submissions)) {
            
            foreach ($submissions as $sub) {
                $subDate = $sub['submission_date'];
                $gradedDate = $sub['graded_at'];
                
                if ($projLatest === null) {
                    $projLatest = $subDate;
                } else {
                    if ($subDate && strtotime($subDate) > strtotime($projLatest)) {
                        $projLatest = $subDate;
                    }
                    if ($gradedDate && strtotime($gradedDate) > strtotime($projLatest)) {
                        $projLatest = $gradedDate;
                    }
                }
            }
        }
        
        $response['projects'] = [
            'latest_timestamp' => $projLatest,
            'has_updates' => !empty($submissions),
            'count' => count($submissions),
            'data' => $submissions
        ];
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Check updates error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
