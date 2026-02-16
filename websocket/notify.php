<?php


require __DIR__ . '/../vendor/autoload.php';


define('WS_HOST', '127.0.0.1');
define('WS_PORT', 8080);


function send_websocket_notification($type, $data = []) {
    $host = defined('WS_HOST') ? WS_HOST : '127.0.0.1';
    $port = defined('WS_PORT') ? WS_PORT : 8080;
    
    
    $socket = @fsockopen($host, $port, $errno, $errstr, 2);
    
    if (!$socket) {
        
        
        error_log("WebSocket server not available at {$host}:{$port}");
        return false;
    }
    
    
    $key = base64_encode(openssl_random_pseudo_bytes(16));
    
    
    $request = "GET / HTTP/1.1\r\n";
    $request .= "Host: {$host}:{$port}\r\n";
    $request .= "Upgrade: websocket\r\n";
    $request .= "Connection: Upgrade\r\n";
    $request .= "Sec-WebSocket-Key: {$key}\r\n";
    $request .= "Sec-WebSocket-Version: 13\r\n";
    $request .= "\r\n";
    
    fwrite($socket, $request);
    
    
    $response = fread($socket, 1024);
    
    
    $message = json_encode([
        'type' => $type,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    
    $frame = create_websocket_frame($message);
    fwrite($socket, $frame);
    
    
    fclose($socket);
    
    return true;
}


function create_websocket_frame($message) {
    $bytes = str_split($message);
    $data = '';
    
    foreach ($bytes as $byte) {
        $data .= pack('C', ord($byte));
    }
    
    $frame = '';
    
    
    $frame .= pack('C', 0x81);
    
    
    $length = strlen($message);
    
    if ($length <= 125) {
        $frame .= pack('C', $length);
    } elseif ($length <= 65535) {
        $frame .= pack('C', 126);
        $frame .= pack('n', $length);
    } else {
        $frame .= pack('C', 127);
        $frame .= pack('J', $length);
    }
    
    $frame .= $data;
    
    return $frame;
}


function notify_attendance_update($student_id, $action, $timestamp = null) {
    return send_websocket_notification('attendance_update', [
        'student_id' => $student_id,
        'action' => $action,
        'timestamp' => $timestamp ?? date('Y-m-d H:i:s')
    ]);
}


function notify_data_update($table, $action, $data = []) {
    return send_websocket_notification('data_update', [
        'table' => $table,
        'action' => $action,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}


if (php_sapi_name() === 'cli' && isset($argv[1])) {
    $options = getopt('', ['type:', 'student_id:', 'action:', 'table:', 'data:']);
    
    if (isset($options['type'])) {
        switch ($options['type']) {
            case 'attendance':
                $student_id = $options['student_id'] ?? 'unknown';
                $action = $options['action'] ?? 'update';
                $result = notify_attendance_update($student_id, $action);
                echo $result ? "Notification sent successfully\n" : "Failed to send notification\n";
                break;
                
            case 'data':
                $table = $options['table'] ?? 'unknown';
                $action = $options['action'] ?? 'update';
                $result = notify_data_update($table, $action);
                echo $result ? "Notification sent successfully\n" : "Failed to send notification\n";
                break;
                
            default:
                echo "Unknown notification type\n";
                exit(1);
        }
    }
}
