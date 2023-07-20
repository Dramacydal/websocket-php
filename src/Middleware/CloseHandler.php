<?php

/**
 * Copyright (C) 2014-2023 Textalk and contributors.
 *
 * This file is part of Websocket PHP and is free software under the ISC License.
 * License text: https://raw.githubusercontent.com/sirn-se/websocket-php/master/COPYING.md
 */

namespace WebSocket\Middleware;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use WebSocket\Connection;
use WebSocket\Message\{
    Close,
    Message
};

/**
 * WebSocket\Middleware\CloseHandler class.
 * Handles close procedure.
 */
class CloseHandler implements LoggerAwareInterface, ProcessIncomingInterface, ProcessOutgoingInterface
{
    use LoggerAwareTrait;

    public function processIncoming(ProcessStack $stack, Connection $connection): Message
    {
        $message = $stack->handleIncoming(); // Proceed before logic
        if (!$message instanceof Close) {
            return $message;
        }
        if (!$connection->isWritable()) {
            // Remote sent Close/Ack: disconnect
            $this->logger->debug("[close-handler] Received 'close' ackowledge, disconnecting");
            $connection->disconnect();
        } else {
            // Remote sent Close; acknowledge and close for further reading
            $this->logger->debug("[close-handler] Received 'close', status: {$message->getCloseStatus()}");
            $ack =  "Close acknowledged: {$message->getCloseStatus()}";
            $connection->closeRead();
            $connection->pushMessage(new Close($message->getCloseStatus(), $ack));
        }
        return $message;
    }

    public function processOutgoing(ProcessStack $stack, Connection $connection, Message $message): Message
    {
        $message = $stack->handleOutgoing($message); // Proceed before logic
        if (!$message instanceof Close) {
            return $message;
        }
        if (!$connection->isReadable()) {
            // Local sent Close/Ack: disconnect
            $this->logger->debug("[close-handler] Sent 'close' ackowledge, disconnecting");
            $connection->disconnect();
        } else {
            // Local sent Close: close for further writing, expect remote ackowledge
            $this->logger->debug("[close-handler] Sent 'close', status: {$message->getCloseStatus()}");
            $connection->closeWrite();
        }
        return $message;
    }

    public function __toString(): string
    {
        return get_class($this);
    }
}