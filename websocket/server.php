<?php


require __DIR__ . '/../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class AttendanceNotifier implements MessageComponentInterface {
    protected $clients;
    protected $subscriptions;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->subscriptions = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        
        $this->clients->attach($conn);
        
        
        $conn->resourceId = $conn->getRemoteId();
        
        echo "New connection! ({$conn->resourceId})\n";
        
        
        $conn->send(json_encode([
            'type' => 'connected',
            'message' => 'Connected to OJT Attendance WebSocket Server',
            'resourceId' => $conn->resourceId
        ]));
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        
        $data = json_decode($msg, true);
        
        if (isset($data['type']) && $data['type'] === 'subscribe') {
            
            $this->subscriptions[$from->resourceId] = $data['channels'] ?? [];
            echo "Client {$from->resourceId} subscribed to: " . implode(', ', $this->subscriptions[$from->resourceId]) . "\n";
        }
        
        
        $from->send(json_encode([
            'type' => 'ack',
            'message' => 'Message received'
        ]));
    }

    public function onClose(ConnectionInterface $conn) {
        
        $this->clients->detach($conn);
        unset($this->subscriptions[$conn->resourceId]);
        
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        
        
        $conn->close();
    }

    
    public function broadcast($data) {
        $message = json_encode($data);
        
        foreach ($this->clients as $client) {
            try {
                $client->send($message);
            } catch (\Exception $e) {
                echo "Error sending to client {$client->resourceId}: {$e->getMessage()}\n";
            }
        }
        
        echo "Broadcast sent to " . $this->clients->count() . " clients\n";
    }
}


$notifier = new AttendanceNotifier();


$webSocketServer = new IoServer(
    new HttpServer(
        new WsServer($notifier)
    ),
    8080,
    '0.0.0.0'
);

echo "OJT Attendance WebSocket Server started on port 8080\n";
echo "Waiting for connections...\n";


$webSocketServer->run();
