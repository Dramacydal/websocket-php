<?php

/**
 * Copyright (C) 2014-2024 Textalk and contributors.
 * This file is part of Websocket PHP and is free software under the ISC License.
 */

namespace WebSocket\Http;

use Phrity\Net\{
    SocketStream,
    Uri
};
use Psr\Http\Message\MessageInterface;
use Psr\Log\{
    LoggerInterface,
    LoggerAwareInterface,
};
use RuntimeException;
use WebSocket\TraitNs\StringableTrait;

/**
 * WebSocket\Http\HttpHandler class.
 * Reads and writes HTTP message to/from stream.
 * @deprecated Remove LoggerAwareInterface in v4
 */
class HttpHandler implements LoggerAwareInterface
{
    use StringableTrait;

    private SocketStream $stream;
    private bool $ssl;

    public function __construct(SocketStream $stream, bool $ssl = false)
    {
        $this->stream = $stream;
        $this->ssl = $ssl;
    }

    /**
     * @deprecated Remove in v4
     */
    public function setLogger(LoggerInterface $logger): void
    {
    }

    public function pull(): MessageInterface
    {
        $data = '';
        do {
            $buffer = $this->stream->readLine(1024);
            $data .= $buffer;
        } while (substr_count($data, "\r\n\r\n") == 0);

        list ($head, $body) = explode("\r\n\r\n", $data);
        $headers = array_filter(explode("\r\n", $head));
        $status = array_shift($headers);

        // Pulling server request
        preg_match('!^(?P<method>[A-Z]+) (?P<path>[^ ]*) HTTP/(?P<version>[0-9/.]+)!', $status, $matches);
        if (!empty($matches)) {
            $message = new ServerRequest($matches['method']);
            $path = $matches['path'];
            $version = $matches['version'];
        }

        // Pulling response
        preg_match('!^HTTP/(?P<version>[0-9/.]+) (?P<code>[0-9]*) (?P<reason>.*)!', $status, $matches);
        if (!empty($matches)) {
            $message = new Response($matches['code'], $matches['reason']);
            $version = $matches['version'];
        }

        if (empty($message)) {
            throw new RuntimeException('Invalid Http request.');
        }

        $message = $message->withProtocolVersion($version);
        foreach ($headers as $header) {
            $parts = explode(':', $header, 2);
            if (count($parts) == 2) {
                if ($message->getheaderLine($parts[0]) === '') {
                    $message = $message->withHeader($parts[0], trim($parts[1]));
                } else {
                    $message = $message->withAddedHeader($parts[0], trim($parts[1]));
                }
            }
        }
        if ($message instanceof Request) {
            $scheme = $this->ssl ? 'wss' : 'ws';
            $uri = new Uri("{$scheme}://{$message->getHeaderLine('Host')}{$path}");
            $message = $message->withUri($uri, true);
        }

        return $message;
    }

    public function push(MessageInterface $message): MessageInterface
    {
        $data = implode("\r\n", $message->getAsArray()) . "\r\n\r\n";
        $this->stream->write($data);
        return $message;
    }
}
