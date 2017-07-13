<?php

namespace Thruway\Test;

use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\Promise\FulfilledPromise;
use React\Promise\Promise;
use React\Socket\ConnectorInterface;
use React\Stream\DuplexStreamInterface;
use React\Stream\ThroughStream;
use Thruway\Peer\Client;
use Thruway\Transport\WebSocketClientTransportProvider;

class WebSocketClientTransportProviderTest extends TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testConstructorNonWebSocketAddress() {
        $transportProvider = new WebSocketClientTransportProvider('http://127.0.0.1/ws');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testConstructorInvalidUri() {
        $transportProvider = new WebSocketClientTransportProvider('wss://:/');
    }

    public function testWssWillRequestTlsWithDefaultPort() {
        $connector = $this->getMockBuilder(ConnectorInterface::class)
            ->setMethods(['connect'])
            ->getMock();

        $connector->expects($this->once())
            ->method('connect')
            ->with('tls://127.0.0.1:443')
            ->willReturn(new Promise(function () {}));

        $transportProvider = new WebSocketClientTransportProvider('wss://127.0.0.1/', $connector);

        $client = $this->createMock(Client::class);
        $loop = $this->createMock(LoopInterface::class);

        $transportProvider->startTransportProvider($client, $loop);
    }

    public function testWsWillRequestWithDefaultPort() {
        $connector = $this->getMockBuilder(ConnectorInterface::class)
            ->setMethods(['connect'])
            ->getMock();

        $connector->expects($this->once())
            ->method('connect')
            ->with('127.0.0.1:80')
            ->willReturn(new Promise(function () {}));

        $transportProvider = new WebSocketClientTransportProvider('ws://127.0.0.1/', $connector);

        $client = $this->createMock(Client::class);
        $loop = $this->createMock(LoopInterface::class);

        $transportProvider->startTransportProvider($client, $loop);
    }

    public function testWssWillRequestWithSpecificPort() {
        $connector = $this->getMockBuilder(ConnectorInterface::class)
            ->setMethods(['connect'])
            ->getMock();

        $connector->expects($this->once())
            ->method('connect')
            ->with('tls://127.0.0.1:444')
            ->willReturn(new Promise(function () {}));

        $transportProvider = new WebSocketClientTransportProvider('wss://127.0.0.1:444/', $connector);

        $client = $this->createMock(Client::class);
        $loop = $this->createMock(LoopInterface::class);

        $transportProvider->startTransportProvider($client, $loop);
    }

    public function testWsWillRequestWithSpecificPort() {
        $connector = $this->getMockBuilder(ConnectorInterface::class)
            ->setMethods(['connect'])
            ->getMock();

        $connector->expects($this->once())
            ->method('connect')
            ->with('127.0.0.1:81')
            ->willReturn(new Promise(function () {}));

        $transportProvider = new WebSocketClientTransportProvider('ws://127.0.0.1:81/', $connector);

        $client = $this->createMock(Client::class);
        $loop = $this->createMock(LoopInterface::class);

        $transportProvider->startTransportProvider($client, $loop);
    }

    public function testCloseAfterSendingRequest() {
        $ds = new ThroughStream();
        $connector = new class($ds) implements ConnectorInterface {
            private $ds;

            public function __construct(DuplexStreamInterface $ds)
            {
                $this->ds = $ds;
            }

            public function connect($uri)
            {
                return new FulfilledPromise(new ConnectionStub($this->ds));
            }
        };

        $client = $this->createMock(Client::class);
        $loop = $this->createMock(LoopInterface::class);

        $transportProvider = new WebSocketClientTransportProvider('ws://127.0.0.1/', $connector);

        $transportProvider->startTransportProvider($client, $loop);


    }
}