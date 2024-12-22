<?php

/**
 * Copyright (C) 2014-2024 Textalk and contributors.
 * This file is part of Websocket PHP and is free software under the ISC License.
 */

namespace WebSocket\Middleware;

use WebSocket\Connection;
use WebSocket\Http\HttpHandler;
use WebSocket\Http\Message;
use WebSocket\TraitNs\StringableTrait;

/**
 * WebSocket\Middleware\ProcessHttpStack class.
 * Worker stack for HTTP middleware implementations.
 */
class ProcessHttpStack
{
    use StringableTrait;

    private Connection $connection;
    private HttpHandler $httpHandler;
    private array $processors;

    /**
     * Create ProcessStack.
     * @param Connection $connection
     * @param HttpHandler $httpHandler
     * @param array $processors
     */
    public function __construct(Connection $connection, HttpHandler $httpHandler, array $processors)
    {
        $this->connection = $connection;
        $this->httpHandler = $httpHandler;
        $this->processors = $processors;
    }

    /**
     * Process middleware for incoming http message.
     * @return Message
     */
    public function handleHttpIncoming(): Message
    {
        $processor = array_shift($this->processors);
        if ($processor) {
            return $processor->processHttpIncoming($this, $this->connection);
        }
        return $this->httpHandler->pull();
    }

    /**
     * Process middleware for outgoing http message.
     * @param Message $message
     * @return Message
     */
    public function handleHttpOutgoing(Message $message): Message
    {
        $processor = array_shift($this->processors);
        if ($processor) {
            return $processor->processHttpOutgoing($this, $this->connection, $message);
        }
        return $this->httpHandler->push($message);
    }
}
