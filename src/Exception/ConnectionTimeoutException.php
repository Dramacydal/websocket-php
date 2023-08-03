<?php

/**
 * Copyright (C) 2014-2023 Textalk and contributors.
 *
 * This file is part of Websocket PHP and is free software under the ISC License.
 * License text: https://raw.githubusercontent.com/sirn-se/websocket-php/master/COPYING.md
 */

namespace WebSocket\Exception;

/**
 * WebSocket\Exception\ConnectionTimeoutException class.
 * Connection operation has timed out.
 */
class ConnectionTimeoutException extends Exception implements MessageLevelInterface
{
    public function __construct()
    {
        parent::__construct('Connection operation timeout');
    }
}
