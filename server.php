<?php
use Swoole\Table;
use Swoole\WebSocket\Server;
use Swoole\WebSocket\Frame;

// CONFIG START
$host_address = "0.0.0.0";
$host_port = 2644;
$protocol = 'wss'; // Change to 'ws' for non-SSL connection
$ssl_cert_file = '/var/www/certs/fullchain.pem';
$ssl_key_file = '/var/www/certs/privkey.pem';
$max_encrypted_length = 10000;
$debug = false;
// CONFIG END

// Create tables for storing clients, users, and public keys
$clientsTable = new Table(1024);
$clientsTable->column('fd', Table::TYPE_INT);
$clientsTable->create();

$usersTable = new Table(1024);
$usersTable->column('username', Table::TYPE_STRING, 64);
$usersTable->create();

$keysTable = new Table(1024);
$keysTable->column('publicKey', Table::TYPE_STRING, 4096);
$keysTable->create();

if ($protocol === 'wss') {
    $server = new Server($host_address, $host_port, SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL);
    $server->set([
        'ssl_cert_file' => $ssl_cert_file,
        'ssl_key_file' => $ssl_key_file
    ]);
} else {
    $server = new Server($host_address, $host_port, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
}

$server->on("start", function (Server $server) use ($host_address, $host_port, $protocol) {
    echo "Swoole WebSocket Server started at $protocol://$host_address:$host_port\n";
});

$server->on('open', function(Server $server, $request) use ($clientsTable) {
    echo "New connection! ({$request->fd})\n";
    $clientsTable->set($request->fd, ['fd' => $request->fd]);
});

$server->on('message', function(Server $server, Frame $frame) use ($clientsTable, $usersTable, $keysTable, $max_encrypted_length, $debug) {
    $data = json_decode($frame->data, true);
    if (!is_array($data) || !isset($data['type'])) {
        $server->push($frame->fd, json_encode(['type' => 'error', 'message' => 'Error: Invalid message format']));
        return;
    }

    if ($debug) {
        echo "Received message from {$frame->fd}: " . print_r($data, true) . "\n";
    }

    switch ($data['type']) {
        case 'set_username':
            if (!isset($data['username'], $data['publicKey']) || !is_string($data['username']) || !is_string($data['publicKey'])) {
                $server->push($frame->fd, json_encode(['type' => 'error', 'message' => 'Error: Invalid data for setting username']));
                break;
            }
            $username = filter_var($data['username'], FILTER_SANITIZE_STRING);
            if (strlen($username) > 64) {
                $server->push($frame->fd, json_encode(['type' => 'error', 'message' => 'Username must not exceed 64 characters']));
                break;
            }
            // Check if the username is already in use
            $usernameExists = false;
            foreach ($usersTable as $row) {
                if ($row['username'] === $username) {
                    $usernameExists = true;
                    break;
                }
            }
            if ($usernameExists) {
                $server->push($frame->fd, json_encode(['type' => 'error', 'message' => 'Username already in use']));
                break;
            }
            $usersTable->set($frame->fd, ['username' => $username]);
            $keysTable->set($username, ['publicKey' => $data['publicKey']]);
            $server->push($frame->fd, json_encode(['type' => 'username_set']));
            break;

        case 'get_public_key':
            if (!isset($data['username']) || !is_string($data['username'])) {
                $server->push($frame->fd, json_encode(['type' => 'error', 'message' => 'Error: Invalid data for getting public key']));
                break;
            }
            $username = filter_var($data['username'], FILTER_SANITIZE_STRING);
            $publicKey = $keysTable->get($username, 'publicKey');
            $server->push($frame->fd, json_encode([
                'type' => 'public_key',
                'username' => $username,
                'publicKey' => $publicKey
            ]));
            break;

        case 'message':
            if (!isset($data['to'], $data['message']) || !is_string($data['to']) || !is_string($data['message'])) {
                $server->push($frame->fd, json_encode(['type' => 'error', 'message' => 'Error: Invalid data for message']));
                break;
            }
            if (strlen($data['message']) > $max_encrypted_length) {  // Message length check
                echo "Message exceeds the maximum allowed length. Limit: $max_encrypted_length, Actual length: " . strlen($data['message']) . " characters\n";
                $server->push($frame->fd, json_encode([
                    'type' => 'error',
                    'message' => 'Message not sent! Encrypted message exceeds the maximum allowed length'
                ]));
                break;
            }
            $recipientUsername = filter_var($data['to'], FILTER_SANITIZE_STRING);
            $recipientFd = null;
            foreach ($usersTable as $fd => $user) {
                if ($user['username'] === $recipientUsername) {
                    $recipientFd = $fd;
                    break;
                }
            }
            if ($recipientFd !== null) {
                $server->push($recipientFd, json_encode([
                    'type' => 'message',
                    'from' => $usersTable->get($frame->fd, 'username'),
                    'message' => $data['message']
                ]));
            } else {
                $server->push($frame->fd, json_encode(['type' => 'error', 'message' => 'Recipient not found']));
            }
            break;

        case 'clear_chat':
            if (!isset($data['to']) || !is_string($data['to'])) {
                $server->push($frame->fd, json_encode(['type' => 'error', 'message' => 'Error: Invalid data for clear chat']));
                break;
            }
            $recipientUsername = filter_var($data['to'], FILTER_SANITIZE_STRING);
            $recipientFd = null;
            foreach ($usersTable as $fd => $user) {
                if ($user['username'] === $recipientUsername) {
                    $recipientFd = $fd;
                    break;
                }
            }
            if ($recipientFd !== null) {
                $server->push($recipientFd, json_encode(['type' => 'clear_chat']));
            } else {
                $server->push($frame->fd, json_encode(['type' => 'error', 'message' => 'Recipient not found']));
            }
            break;

        default:
            $server->push($frame->fd, json_encode(['type' => 'error', 'message' => 'Error: Unknown message type']));
            break;
    }

    // Send updated user list to all clients
    $userList = [];
    foreach ($usersTable as $row) {
        $userList[] = $row['username'];
    }
    foreach ($clientsTable as $client) {
        $server->push($client['fd'], json_encode(['type' => 'user_list', 'users' => $userList]));
    }
});

$server->on('close', function(Server $server, $fd) use ($clientsTable, $usersTable, $keysTable) {
    echo "Connection {$fd} closed\n";
    $username = $usersTable->get($fd, 'username');
    if ($username) {
        $keysTable->del($username);
    }
    $clientsTable->del($fd);
    $usersTable->del($fd);

    // Send updated user list to all clients
    $userList = [];
    foreach ($usersTable as $row) {
        $userList[] = $row['username'];
    }
    foreach ($clientsTable as $client) {
        $server->push($client['fd'], json_encode(['type' => 'user_list', 'users' => $userList]));
    }
});

$server->start();
?>
