<?php
/**
 * WebSocket Server for Live Support Chat
 * 
 * This file is used to run the WebSocket server using Ratchet PHP.
 * It should be executed from the command line: php server.php
 */

// Composer autoload
require_once dirname(__FILE__, 5) . '/chat/wp-content/plugins/wordpress-live-chat/vendor/autoload.php';
require_once dirname(__FILE__, 5) . '/chat/wp-load.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

/**
 * Live Support Chat WebSocket Server
 */
class LiveSupportServer implements MessageComponentInterface {
    protected $clients;
    protected $users = [];
    protected $agents = [];

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        echo "Live Support Server started!\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!isset($data['action'])) {
            return;
        }
        
        switch ($data['action']) {
            case 'register':
                $this->handleRegister($from, $data);
                break;
                
            case 'message':
                $this->handleMessage($from, $data);
                break;
                
            case 'typing':
                $this->handleTyping($from, $data);
                break;
                
            case 'close':
                $this->handleClose($from, $data);
                break;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        
        // Remove from users or agents
        foreach ($this->users as $chatId => $client) {
            if ($client === $conn) {
                unset($this->users[$chatId]);
                
                // Notify agents that user disconnected
                foreach ($this->agents as $agentConn) {
                    $agentConn->send(json_encode([
                        'action' => 'user_disconnected',
                        'chat_id' => $chatId
                    ]));
                }
                
                break;
            }
        }
        
        // Remove from agents
        $agentId = array_search($conn, $this->agents);
        if ($agentId !== false) {
            unset($this->agents[$agentId]);
        }
        
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
    
    protected function handleRegister($conn, $data) {
        if (isset($data['type']) && $data['type'] === 'agent' && isset($data['agent_id'])) {
            // Register agent
            $this->agents[$data['agent_id']] = $conn;
            echo "Agent {$data['agent_id']} registered\n";
            
            // Send active chats to agent
            $activeChats = $this->getActiveChats();
            $conn->send(json_encode([
                'action' => 'active_chats',
                'chats' => $activeChats
            ]));
        } elseif (isset($data['type']) && $data['type'] === 'user' && isset($data['chat_id'])) {
            // Register user
            $this->users[$data['chat_id']] = $conn;
            echo "User for chat {$data['chat_id']} registered\n";
            
            // Notify agents of new user
            foreach ($this->agents as $agentConn) {
                $agentConn->send(json_encode([
                    'action' => 'new_chat',
                    'chat' => $this->getChatDetails($data['chat_id'])
                ]));
            }
        }
    }
    
    protected function handleMessage($from, $data) {
        if (!isset($data['chat_id']) || !isset($data['message'])) {
            return;
        }
        
        $chatId = $data['chat_id'];
        $message = $data['message'];
        $isAgent = isset($data['is_agent']) && $data['is_agent'];
        $userId = isset($data['user_id']) ? $data['user_id'] : null;
        
        // Save message to database
        $messageId = $this->saveMessage($chatId, $userId, $isAgent, $message);
        
        $messageData = [
            'action' => 'message',
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'message' => $message,
            'is_agent' => $isAgent,
            'user_id' => $userId,
            'timestamp' => current_time('mysql')
        ];
        
        // Send to appropriate recipient
        if ($isAgent) {
            // Agent sent message, send to user
            if (isset($this->users[$chatId])) {
                $this->users[$chatId]->send(json_encode($messageData));
            }
            
            // Also send to other agents
            foreach ($this->agents as $agentId => $conn) {
                if ($conn !== $from) {
                    $conn->send(json_encode($messageData));
                }
            }
        } else {
            // User sent message, send to all agents
            foreach ($this->agents as $agentConn) {
                $agentConn->send(json_encode($messageData));
            }
        }
    }
    
    protected function handleTyping($from, $data) {
        if (!isset($data['chat_id']) || !isset($data['is_typing'])) {
            return;
        }
        
        $chatId = $data['chat_id'];
        $isAgent = isset($data['is_agent']) && $data['is_agent'];
        
        $typingData = [
            'action' => 'typing',
            'chat_id' => $chatId,
            'is_typing' => $data['is_typing'],
            'is_agent' => $isAgent
        ];
        
        if ($isAgent) {
            // Agent is typing, notify user
            if (isset($this->users[$chatId])) {
                $this->users[$chatId]->send(json_encode($typingData));
            }
        } else {
            // User is typing, notify agents
            foreach ($this->agents as $agentConn) {
                $agentConn->send(json_encode($typingData));
            }
        }
    }
    
    protected function handleClose($from, $data) {
        if (!isset($data['chat_id'])) {
            return;
        }
        
        $chatId = $data['chat_id'];
        
        // Close chat in database
        $this->closeChat($chatId);
        
        $closeData = [
            'action' => 'chat_closed',
            'chat_id' => $chatId
        ];
        
        // Notify user
        if (isset($this->users[$chatId])) {
            $this->users[$chatId]->send(json_encode($closeData));
        }
        
        // Notify all agents
        foreach ($this->agents as $agentConn) {
            $agentConn->send(json_encode($closeData));
        }
    }
    
    protected function getActiveChats() {
        global $wpdb;
        $table = $wpdb->prefix . 'live_support_chats';
        
        $chats = $wpdb->get_results(
            "SELECT * FROM $table WHERE status = 'active' ORDER BY updated_at DESC"
        );
        
        return $chats;
    }
    
    protected function getChatDetails($chatId) {
        global $wpdb;
        $table = $wpdb->prefix . 'live_support_chats';
        
        $chat = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                $chatId
            )
        );
        
        return $chat;
    }
    
    protected function saveMessage($chatId, $userId, $isAgent, $message) {
        global $wpdb;
        $table = $wpdb->prefix . 'live_support_messages';
        $now = current_time('mysql');
        
        $wpdb->insert(
            $table,
            array(
                'chat_id' => $chatId,
                'user_id' => $userId,
                'is_agent' => $isAgent ? 1 : 0,
                'message' => $message,
                'created_at' => $now
            ),
            array('%d', '%d', '%d', '%s', '%s')
        );
        
        // Update chat timestamp
        $table_chats = $wpdb->prefix . 'live_support_chats';
        $wpdb->update(
            $table_chats,
            array('updated_at' => $now),
            array('id' => $chatId),
            array('%s'),
            array('%d')
        );
        
        return $wpdb->insert_id;
    }
    
    protected function closeChat($chatId) {
        global $wpdb;
        $table = $wpdb->prefix . 'live_support_chats';
        
        $wpdb->update(
            $table,
            array('status' => 'closed'),
            array('id' => $chatId),
            array('%s'),
            array('%d')
        );
    }
}

// Get options from WordPress
$options = get_option('live_support_options', [
    'websocket_port' => 8080,
    'allowed_origins' => home_url()
]);

$port = isset($options['websocket_port']) ? (int)$options['websocket_port'] : 8080;

// Create and run the server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new LiveSupportServer()
        )
    ),
    $port
);

echo "Server running on port $port\n";
$server->run();