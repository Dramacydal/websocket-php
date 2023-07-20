<?php

/**
 * Test case for Server.
 * Note that this test is performed by mocking socket/stream calls.
 */

declare(strict_types=1);

namespace WebSocket\Test\Server;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Phrity\Net\Mock\StreamFactory;
use Phrity\Net\Mock\Stack\{
    ExpectSocketServerTrait,
    ExpectSocketStreamTrait,
    ExpectStreamFactoryTrait,
    StackItem
};
use Phrity\Net\{
    StreamException,
    Uri
};
use Phrity\Util\ErrorHandler;
use WebSocket\{
    Server,
    BadOpcodeException,
    ConnectionException,
    TimeoutException
};
use WebSocket\Http\Request;
use WebSocket\Test\{
    MockStreamTrait,
    MockUri
};
use WebSocket\Message\{
    Close,
    Text
};

class ServerTest extends TestCase
{
    use ExpectSocketServerTrait;
    use ExpectSocketStreamTrait;
    use ExpectStreamFactoryTrait;
    use MockStreamTrait;

    public function setUp(): void
    {
        error_reporting(-1);
        $this->setUpStack();
    }

    public function tearDown(): void
    {
        $this->tearDownStack();
    }

    public function testServerMasked(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());

        $this->assertFalse($server->isConnected());
        $this->assertEquals(4096, $server->getFragmentSize());

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();
        $this->expectWsServerPerformHandshake();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(9, strlen($params[0]));
        });
        $server->text('Connect');

        $this->assertEquals(8000, $server->getPort());

        $this->expectSocketStreamIsConnected();
        $this->assertTrue($server->isConnected());
        $this->assertEquals(4096, $server->getFragmentSize());

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(19, strlen($params[0]));
        });
        $server->text('Sending a message');

        $request = $server->getHandshakeRequest();
        $this->assertInstanceOf(Request::class, $request);

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('gZM=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(4, $params[0]);
        })->setReturn(function () {
            return base64_decode('IW+Vrg==');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(19, $params[0]);
        })->setReturn(function () {
            return base64_decode('cwr2y0gZ/MBGT/SOTArm3UAI8A==');
        });
        $message = $server->receive();

        $this->assertEquals('Receiving a message', $message->getContent());

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(8, strlen($params[0]));
        });
        $server->close();

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('iJo=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(4, $params[0]);
        })->setReturn(function () {
            return base64_decode('9Tc+CA==');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(26, $params[0]);
        })->setReturn(function () {
            return base64_decode('9t99ZJpEWyiUVFVmmkBSbZFQW2zPFw84xQc=');
        });
        $this->expectSocketStreamClose();
        $this->expectSocketStreamIsConnected();
        $message = $server->receive();
        $this->assertInstanceOf(Close::class, $message);

        $this->expectSocketStreamIsConnected();
        $this->assertFalse($server->isConnected());

        $this->expectSocketStreamIsConnected();
        $server->close(); // Already closed
    }

    public function testServerWithTimeout(): void
    {
        $this->expectStreamFactory();
        $server = new Server(['timeout' => 300]);
        $server->setStreamFactory(new StreamFactory());

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();
        $this->expectSocketStreamSetTimeout()->addAssert(function (string $method, array $params) {
            $this->assertEquals(300, $params[0]);
        });
        $this->expectWsServerPerformHandshake();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(9, strlen($params[0]));
        });
        $server->text('Connect');

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamClose();
        unset($server);
    }

    public function testSendMessages(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();
        $this->expectWsServerPerformHandshake();
        $server->connect();

        $this->expectSocketStreamIsConnected();
        $this->assertTrue($server->isConnected());

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(9, strlen($params[0]));
        });
        $server->send(new Text('Connect'));

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamClose();
        unset($server);
    }

    public function testPayload128(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());
        $server->setFragmentSize(65540);

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();
        $this->expectWsServerPerformHandshake();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(9, strlen($params[0]));
        });
        $server->text('Connect');

        $payload = file_get_contents(__DIR__ . '/../../mock/payload.128.txt');

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(132, strlen($params[0]));
        });
        $server->text($payload);

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('gX4=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('AIA=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(128, $params[0]);
        })->setReturn(function () use ($payload) {
            return $payload;
        });
        $message = $server->receive();

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamClose();
        unset($server);
    }

    public function testPayload65536(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());
        $server->setFragmentSize(65540);

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();
        $this->expectWsServerPerformHandshake();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(9, strlen($params[0]));
        });
        $server->text('Connect');

        $payload = file_get_contents(__DIR__ . '/../../mock/payload.65536.txt');

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(65546, strlen($params[0]));
        });
        $server->text($payload);

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('gX8=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(8, $params[0]);
        })->setReturn(function () {
            return base64_decode('AAAAAAABAAA=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(65536, $params[0]);
        })->setReturn(function () use ($payload) {
            return substr($payload, 0, 16374);
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(49162, $params[0]);
        })->setReturn(function () use ($payload) {
            return substr($payload, 16374, 8192);
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(40970, $params[0]);
        })->setReturn(function () use ($payload) {
            return substr($payload, 24566, 8192);
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(32778, $params[0]);
        })->setReturn(function () use ($payload) {
            return substr($payload, 32758, 8192);
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(24586, $params[0]);
        })->setReturn(function () use ($payload) {
            return substr($payload, 40950, 8192);
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(16394, $params[0]);
        })->setReturn(function () use ($payload) {
            return substr($payload, 49142, 8192);
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(8202, $params[0]);
        })->setReturn(function () use ($payload) {
            return substr($payload, 57334, 8192);
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(10, $params[0]);
        })->setReturn(function () use ($payload) {
            return substr($payload, 65526, 10);
        });
        $message = $server->receive();

        $this->assertEquals($payload, $message->getContent());

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamClose();
        unset($server);
    }

    public function testMultiFragment(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();
        $this->expectWsServerPerformHandshake();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(9, strlen($params[0]));
        });
        $server->text('Connect');

        $server->setFragmentSize(8);

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(10, strlen($params[0]));
        });
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(10, strlen($params[0]));
        });
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(5, strlen($params[0]));
        });
        $server->text('Multi fragment test');

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('AYg=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(4, $params[0]);
        })->setReturn(function () {
            return base64_decode('aR27Eg=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(8, $params[0]);
        })->setReturn(function () {
            return base64_decode('JGjXZgA93WA=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('AIg=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(4, $params[0]);
        })->setReturn(function () {
            return base64_decode('3fAuRQ==');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(8, $params[0]);
        })->setReturn(function () {
            return base64_decode('vJdDILOEDjE=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('gIM=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(4, $params[0]);
        })->setReturn(function () {
            return base64_decode('CTx1wQ==');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(3, $params[0]);
        })->setReturn(function () {
            return base64_decode('bE8B');
        });
        $message = $server->receive();
        $this->assertEquals('Multi fragment test', $message->getContent());

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamClose();
        unset($server);
    }

    public function testPingPong(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();
        $this->expectWsServerPerformHandshake();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(9, strlen($params[0]));
        });
        $server->text('Connect');

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(13, strlen($params[0]));
        });
        $server->ping('Server ping');

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(2, strlen($params[0]));
        });
        $server->ping();

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('ios=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(4, $params[0]);
        })->setReturn(function () {
            return base64_decode('AQEBAQ==');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(11, $params[0]);
        })->setReturn(function () {
            return base64_decode('UmRzd2RzIXFob2Y=');
        });
        $message = $server->receive();

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('ioA=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(4, $params[0]);
        })->setReturn(function () {
            return base64_decode('AQEBAQ==');
        });
        $message = $server->receive();

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('iYs=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(4, $params[0]);
        })->setReturn(function () {
            return base64_decode('tE3AyQ==');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(11, $params[0]);
        })->setReturn(function () {
            return base64_decode('9yGprNo54LndI6c=');
        });
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(13, strlen($params[0]));
        });
        $message = $server->receive();

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('gZM=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(4, $params[0]);
        })->setReturn(function () {
            return base64_decode('IW+Vrg==');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(19, $params[0]);
        })->setReturn(function () {
            return base64_decode('cwr2y0gZ/MBGT/SOTArm3UAI8A==');
        });
        $message = $server->receive();

        $this->assertEquals('Receiving a message', $message->getContent());

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamClose();
        unset($server);
    }

    public function testRemoteClose(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();
        $this->expectWsServerPerformHandshake();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(9, strlen($params[0]));
        });
        $server->text('Connect');

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('iIk=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(4, $params[0]);
        })->setReturn(function () {
            return base64_decode('Nk/p9A==');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(9, $params[0]);
        })->setReturn(function () {
            return base64_decode('dSOqmFk8gJpR');
        });
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(29, strlen($params[0]));
        });

        $this->expectSocketStreamClose();
        $this->expectSocketStreamIsConnected();
        $message = $server->receive();

        unset($server);
    }

    public function testSetTimeout(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();
        $this->expectWsServerPerformHandshake();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(9, strlen($params[0]));
        });
        $server->text('Connect');

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamSetTimeout(300);
        $server->setTimeout(300);

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamClose();
        unset($server);
    }

    public function testFailedSocketServer(): void
    {
        $this->expectStreamFactory();
        $server = new Server(['port' => 9999]);
        $server->setStreamFactory(new StreamFactory());

        $this->expectStreamFactoryCreateSockerServer()->addAssert(function ($method, $params) {
            $this->assertEquals(9999, $params[0]->getPort());
        });
        $this->expectSocketServer()->addAssert(function ($method, $params) {
            $this->assertEquals(9999, $params[0]->getPort());
        })->setReturn(function ($params) {
            throw new StreamException(StreamException::SERVER_SOCKET_ERR, ['uri' => $params[0]->__toString()]);
        });
        $this->expectStreamFactoryCreateSockerServer()->addAssert(function ($method, $params) {
            $this->assertEquals(10000, $params[0]->getPort());
        });
        $this->expectSocketServer()->addAssert(function ($method, $params) {
            $this->assertEquals(10000, $params[0]->getPort());
        })->setReturn(function ($params) {
            throw new StreamException(StreamException::SERVER_SOCKET_ERR, ['uri' => $params[0]->__toString()]);
        });
        $this->expectException(ConnectionException::class);
        $this->expectExceptionCode(ConnectionException::SERVER_SOCKET_ERR);
        $this->expectExceptionMessage('Could not open listening socket:');
        $server->accept();

        unset($server);
    }

    public function testFailedConnect(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept()->setReturn(function ($params) {
            throw new StreamException(StreamException::SERVER_ACCEPT_ERR);
        });
        $this->expectException(ConnectionException::class);
        $this->expectExceptionCode(ConnectionException::SERVER_ACCEPT_ERR);
        $this->expectExceptionMessage('Server failed to connect');
        $server->text('Connect');

        unset($server);
    }

    public function testFailedWsKey(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();

        $this->expectSocketStreamReadLine()->addAssert(function (string $method, array $params): void {
            $this->assertEquals(1024, $params[0]);
        })->setReturn(function (array $params) {
            return "GET /my/mock/path HTTP/1.1\r\nHost: localhost:8000\r\nUser-Agent: websocket-client-php\r\n"
            . "Connection: Upgrade\r\nUpgrade: websocket\r\n"
            . "Sec-WebSocket-Version: 13"
            . "\r\n\r\n";
        });
        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamClose();
        $this->expectException(ConnectionException::class);
        $this->expectExceptionCode(ConnectionException::SERVER_HANDSHAKE_ERR);
        $this->expectExceptionMessage('Client had no Key in upgrade request');
        $server->text('Connect');

        unset($server);
    }
/*
    public function testSendBadOpcode(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();
        $this->expectWsServerPerformHandshake();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(9, strlen($params[0]));
        });
        $server->text('Connect');

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamClose();
        $this->expectException(BadOpcodeException::class);
        $this->expectExceptionCode(ConnectionException::BAD_OPCODE);
        $this->expectExceptionMessage('Bad opcode \'bad\'.  Try \'text\' or \'binary\'.');
        $server->send('Bad Opcode', 'bad');
    }
*/
    public function testRecieveBadOpcode(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();
        $this->expectWsServerPerformHandshake();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(9, strlen($params[0]));
        });
        $server->text('Connect');

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('jww=');
        });
        $this->expectSocketStreamRead()->setReturn(function () {
            return 'Test message';
        });
        $this->expectSocketStreamClose();
        $this->expectSocketStreamIsConnected();
        $this->expectException(BadOpcodeException::class);
        $this->expectExceptionCode(BadOpcodeException::BAD_OPCODE);
        $this->expectExceptionMessage("Invalid opcode '15' provided");
        $message = $server->receive();

        unset($server);
    }

    public function testBrokenWrite(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();
        $this->expectWsServerPerformHandshake();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(9, strlen($params[0]));
        });
        $server->text('Connect');

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamWrite()->setReturn(function () {
            return 14;
        });
        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamGetMetadata()->setReturn(function () {
            return ['eof' => true, 'mode' => 'rw', 'seekable' => false];
        });
        $this->expectSocketStreamClose();
        $this->expectSocketStreamIsConnected();
        $this->expectException(ConnectionException::class);
        $this->expectExceptionCode(1025);
        $this->expectExceptionMessage('Could only write 14 out of 18 bytes.');
        $server->text('Failing to write');

        unset($server);
    }

    public function testFailedWrite(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();
        $this->expectWsServerPerformHandshake();
        $this->expectSocketStreamWrite()->setReturn(function () {
            throw new StreamException(StreamException::NOT_WRITABLE);
        });
        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamGetMetadata()->setReturn(function () {
            return ['timed_out' => true, 'mode' => 'rw', 'seekable' => false];
        });
        $this->expectSocketStreamClose();
        $this->expectSocketStreamIsConnected();
        $this->expectException(TimeoutException::class);
        $this->expectExceptionCode(ConnectionException::TIMED_OUT);
        $this->expectExceptionMessage('Connection timeout');
        $server->text('Failing to write');

        unset($server);
    }

    public function testBrokenRead(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();
        $this->expectWsServerPerformHandshake();
        $this->expectSocketStreamRead()->setReturn(function () {
            throw new StreamException(StreamException::NOT_READABLE);
        });
        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamGetMetadata()->setReturn(function () {
            return ['eof' => true, 'mode' => 'rw', 'seekable' => false];
        });
        $this->expectSocketStreamClose();
        $this->expectSocketStreamIsConnected();
        $this->expectException(ConnectionException::class);
        $this->expectExceptionCode(ConnectionException::EOF);
        $this->expectExceptionMessage('Connection closed');
        $server->receive();

        unset($server);
    }

    public function testEmptyRead(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();
        $this->expectWsServerPerformHandshake();
        $this->expectSocketStreamRead()->setReturn(function () {
            return '';
        });
        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamGetMetadata()->setReturn(function () {
            return ['timed_out' => true, 'mode' => 'rw', 'seekable' => false];
        });
        $this->expectSocketStreamClose();
        $this->expectSocketStreamIsConnected();
        $this->expectException(TimeoutException::class);
        $this->expectExceptionCode(1024);
        $this->expectExceptionMessage('Empty read; connection dead?');
        $server->receive();

        unset($server);
    }

    public function testFrameFragmentation(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();
        $this->expectWsServerPerformHandshake();

        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('AYg=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(4, $params[0]);
        })->setReturn(function () {
            return base64_decode('aR27Eg==');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(8, $params[0]);
        })->setReturn(function () {
            return base64_decode('JGjXZgA93WA=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('ios=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(4, $params[0]);
        })->setReturn(function () {
            return base64_decode('AQEBAQ==');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(11, $params[0]);
        })->setReturn(function () {
            return base64_decode('UmRzd2RzIXFob2Y=');
        });
        $message = $server->receive();
        $this->assertEquals('Server ping', $message->getContent());

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('AIg=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(4, $params[0]);
        })->setReturn(function () {
            return base64_decode('3fAuRQ==');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(8, $params[0]);
        })->setReturn(function () {
            return base64_decode('vJdDILOEDjE=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('gIM=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(4, $params[0]);
        })->setReturn(function () {
            return base64_decode('CTx1wQ==');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(3, $params[0]);
        })->setReturn(function () {
            return base64_decode('bE8B');
        });
        $message = $server->receive();
        $this->assertEquals('Multi fragment test', $message->getContent());

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('iIk=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(4, $params[0]);
        })->setReturn(function () {
            return base64_decode('Nk/p9A==');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(9, $params[0]);
        })->setReturn(function () {
            return base64_decode('dSOqmFk8gJpR');
        });
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(29, strlen($params[0]));
        });
        $this->expectSocketStreamClose();
        $this->expectSocketStreamIsConnected();
        $message = $server->receive();
        $this->assertEquals('Closing', $message->getContent());
        $this->expectSocketStreamIsConnected();
        $this->assertFalse($server->isConnected());

        unset($server);
    }

    public function testMessageFragmentation(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();
        $this->expectWsServerPerformHandshake();
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('AYg=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(4, $params[0]);
        })->setReturn(function () {
            return base64_decode('aR27Eg==');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(8, $params[0]);
        })->setReturn(function () {
            return base64_decode('JGjXZgA93WA=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('ios=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(4, $params[0]);
        })->setReturn(function () {
            return base64_decode('AQEBAQ==');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(11, $params[0]);
        })->setReturn(function () {
            return base64_decode('UmRzd2RzIXFob2Y=');
        });
        $message = $server->receive();
        $this->assertInstanceOf('WebSocket\Message\Message', $message);
        $this->assertInstanceOf('WebSocket\Message\Pong', $message);
        $this->assertEquals('Server ping', $message->getContent());
        $this->assertEquals('pong', $message->getOpcode());

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(2, $params[0]);
        })->setReturn(function () {
            return base64_decode('iIk=');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(4, $params[0]);
        })->setReturn(function () {
            return base64_decode('Nk/p9A==');
        });
        $this->expectSocketStreamRead()->addAssert(function (string $method, array $params) {
            $this->assertEquals(9, $params[0]);
        })->setReturn(function () {
            return base64_decode('dSOqmFk8gJpR');
        });
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(29, strlen($params[0]));
        });
        $this->expectSocketStreamClose();
        $this->expectSocketStreamIsConnected();
        $message = $server->receive();
        $this->assertInstanceOf('WebSocket\Message\Message', $message);
        $this->assertInstanceOf('WebSocket\Message\Close', $message);
        $this->assertEquals('Closing', $message->getContent());
        $this->assertEquals('close', $message->getOpcode());

        unset($server);
    }

    public function testConvenicanceMethods(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());

        $this->assertNull($server->getName());
        $this->assertNull($server->getRemoteName());
        $this->assertEquals('WebSocket\Server(closed)', "{$server}");

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();
        $this->expectWsServerPerformHandshake();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(9, strlen($params[0]));
        });
        $server->text('Connect');

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(22, strlen($params[0]));
        });
        $server->binary(base64_encode('Binary content'));

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(base64_decode('iQA='), $params[0]);
        });
        $server->ping();

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(base64_decode('igA='), $params[0]);
        });
        $server->pong();

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamGetLocalName()->setReturn(function () {
            return '127.0.0.1:12345';
        });
        $this->assertEquals('127.0.0.1:12345', $server->getName());

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamGetRemoteName()->setReturn(function () {
            return '127.0.0.1:8000';
        });
        $this->assertEquals('127.0.0.1:8000', $server->getRemoteName());

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamGetLocalName()->setReturn(function () {
            return '127.0.0.1:12345';
        });
        $this->assertEquals('WebSocket\Server(127.0.0.1:12345)', "{$server}");

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamClose();

        unset($server);
    }

    public function testUnconnectedServer(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());

        $this->assertFalse($server->isConnected());
        $server->setTimeout(30);
        $server->close();
        $this->assertFalse($server->isConnected());
        $this->assertNull($server->getName());
        $this->assertNull($server->getRemoteName());
    }

    public function testFailedHandshake(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());

        $this->assertNull($server->getName());
        $this->assertNull($server->getRemoteName());
        $this->assertEquals('WebSocket\Server(closed)', "{$server}");

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();
        $this->expectSocketStreamReadLine()->addAssert(function (string $method, array $params): void {
            $this->assertEquals(1024, $params[0]);
        })->setReturn(function () {
            throw new StreamException(StreamException::NOT_READABLE);
        });
        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamClose();
        $this->expectException(ConnectionException::class);
        $this->expectExceptionCode(ConnectionException::SERVER_HANDSHAKE_ERR);
        $this->expectExceptionMessage('Client handshake error');
        $server->text('Connect');

        unset($server);
    }

    public function testServerDisconnect(): void
    {
        $this->expectStreamFactory();
        $server = new Server();
        $server->setStreamFactory(new StreamFactory());

        $this->assertNull($server->getName());
        $this->assertNull($server->getRemoteName());
        $this->assertEquals('WebSocket\Server(closed)', "{$server}");

        $this->expectStreamFactoryCreateSockerServer();
        $this->expectSocketServer();
        $this->expectSocketServerGetTransports();
        $this->expectSocketServerGetMetadata();
        $server->accept();

        $this->expectSocketServerAccept();
        $this->expectSocketStream();
        $this->expectSocketStreamGetMetadata();
        $this->expectWsServerPerformHandshake();
        $this->expectSocketStreamWrite()->addAssert(function ($method, $params) {
            $this->assertEquals(9, strlen($params[0]));
        });
        $server->text('Connect');

        $this->expectSocketStreamIsConnected();
        $this->assertTrue($server->isConnected());

        $this->expectSocketStreamIsConnected();
        $this->expectSocketStreamClose();
        $this->expectSocketStreamIsConnected();
        $server->disconnect();
        $this->assertFalse($server->isConnected());

        $this->expectSocketStreamIsConnected();

        unset($server);
    }
}