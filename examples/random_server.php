<?php

/**
 * Copyright (C) 2014-2024 Textalk and contributors.
 * This file is part of Websocket PHP and is free software under the ISC License.
 */

/**
 * Websocket server that read/write random data.
 * Run in console: php examples/random_server.php
 *
 * Console options:
 *  --port <int> : The port to listen to, default 80
 *  --ssl : Use SSL, default false
 *  --timeout <int> : Timeout in seconds, random default
 *  --framesize <int> : Frame size as bytes, random default
 *  --connections <int> : Max number of connections, default unlimited
 *  --debug : Output log data (if logger is available)
 */

namespace WebSocket;

require __DIR__ . '/../vendor/autoload.php';

error_reporting(-1);

$randStr = function (int $maxlength = 4096) {
    $string = '';
    $length = rand(1, $maxlength);
    for ($i = 0; $i < $length; $i++) {
        $string .= chr(rand(33, 126));
    }
    return $string;
};

echo "# Random server\n";

// Server options specified or default
$options = array_merge([
    'port'      => 80,
    'timeout'   => rand(1, 60),
    'framesize' => rand(1, 4096) * 8,
], getopt('', ['port:', 'ssl', 'timeout:', 'framesize:', 'connections:', 'debug']));

// Initiate server.
try {
    $server = new Server($options['port'], isset($options['ssl']));
    $server
        ->addMiddleware(new \WebSocket\Middleware\CloseHandler())
        ->addMiddleware(new \WebSocket\Middleware\PingResponder())
        ;
    $server->setMaxConnections(1);

    // If debug mode and logger is available
    if (isset($options['timeout'])) {
        $server->setTimeout($options['timeout']);
        echo "# Set timeout: {$options['timeout']}\n";
    }
    if (isset($options['framesize'])) {
        $server->setFrameSize($options['framesize']);
        echo "# Set frame size: {$options['framesize']}\n";
    }
    if (isset($options['connections'])) {
        $server->setMaxConnections($options['connections']);
        echo "# Set max connections: {$options['connections']}\n";
    }

    echo "# Listening on port {$server->getPort()}\n";
    $server->onHandshake(function ($server, $connection, $request, $response) {
        echo "> [{$connection->getRemoteName()}] Client connected {$request->getUri()}\n";
    })->onDisconnect(function ($server, $connection) {
        echo "> [{$connection->getRemoteName()}] Client disconnected\n";
    })->onText(function ($server, $connection, $message) {
        echo "> [{$connection->getRemoteName()}] Received [{$message->getOpcode()}]\n";
    })->onBinary(function ($server, $connection, $message) {
        echo "> [{$connection->getRemoteName()}] Received [{$message->getOpcode()}]\n";
    })->onPing(function ($server, $connection, $message) {
        echo "> [{$connection->getRemoteName()}] Received [{$message->getOpcode()}]\n";
    })->onPong(function ($server, $connection, $message) {
        echo "> [{$connection->getRemoteName()}] Received [{$message->getOpcode()}]\n";
    })->onClose(function ($server, $connection, $message) {
        echo "> [{$connection->getRemoteName()}] Received [{$message->getOpcode()}] {$message->getCloseStatus()}\n";
    })->onError(function ($server, $connection, $exception) {
        $name = $connection ? "[{$connection->getRemoteName()}]" : "[-]";
        echo "> {$name} Error: {$exception->getMessage()}\n";
    })->onTick(function ($server) use ($randStr) {
        // Random actions
        switch (rand(1, 5)) {
            case 1:
                echo "< [{$server->getConnectionCount()}] Sending text\n";
                $server->text("Text message {$randStr()}");
                break;
            case 2:
                echo "< [{$server->getConnectionCount()}] Sending binary\n";
                $server->binary("Binary message {$randStr()}");
                break;
            case 3:
                echo "< [{$server->getConnectionCount()}] Sending close\n";
                $server->close(rand(1000, 2000), "Close message {$randStr(8)}");
                break;
            case 4:
                echo "< [{$server->getConnectionCount()}] Sending ping\n";
                $server->ping("Ping message {$randStr(8)}");
                break;
            case 5:
                echo "< [{$server->getConnectionCount()}] Sending pong\n";
                $server->pong("Pong message {$randStr(8)}");
                break;
        }
    })->start();
} catch (\Throwable $e) {
    echo "> ERROR: {$e->getMessage()}\n";
}
