<?php

namespace Thruway\Transport;

use function GuzzleHttp\Psr7\parse_response;
use function GuzzleHttp\Psr7\str;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Ratchet\RFC6455\Handshake\ClientNegotiator;
use Ratchet\RFC6455\Handshake\ResponseVerifier;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\FrameInterface;
use Ratchet\RFC6455\Messaging\Message;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use React\Socket\ConnectorInterface;
use Thruway\Logging\Logger;
use Thruway\Peer\Client;
use Thruway\Serializer\JsonSerializer;

class WebSocketClientTransportProvider extends AbstractClientTransportProvider
{
    /** @var string */
    private $remoteAddress;

    /** @var string */
    private $connectUri;

    /** @var UriInterface */
    private $uri;

    /** @var ConnectorInterface */
    private $connector;

    /**
     * WebSocketClientTransportProvider constructor.
     * @param $remoteAddress
     * @param ConnectorInterface|null $connector
     */
    public function __construct($remoteAddress, ConnectorInterface $connector = null)
    {
        $this->uri = new Uri($remoteAddress);

        $port = $this->uri->getPort() ?? 80;

        if (!in_array($this->uri->getScheme(), ['ws', 'wss'], true)) {
            throw new \InvalidArgumentException('WebSocket address must use ws: or wss: scheme');
        }

        $connectUri = '';
        if ($this->uri->getScheme() === 'wss') {
            $connectUri = 'tls://';

            $port = $this->uri->getPort() ?? 443;
        }

        $this->connectUri = $connectUri . $this->uri->getHost() . ':' . $port;
        $this->connector  = $connector;
    }

    public function startTransportProvider(Client $peer, LoopInterface $loop)
    {
        Logger::info($this, 'Starting Transport');

        $this->client = $peer;
        $this->loop   = $loop;

        $this->connector = $this->connector ?? new Connector($loop);

        /** @var PromiseInterface $promise */
        $promise = $this->connector->connect($this->connectUri);

        $promise->then(function (ConnectionInterface $conn) {
            Logger::debug($this, 'TCP Connect');
            $cn = new ClientNegotiator();
            /** @var RequestInterface $request */
            $request = $cn->generateRequest($this->uri);
            $request = $request
                ->withHeader('Sec-WebSocket-Protocol', 'wamp.2.json')
                ->withHeader('User-Agent', 'thruway_websocket-client-transport/0.6.x-dev');

            $conn->write(str($request));

            $conn->on('data', function ($data) use ($request, $conn) {
                static $header = '';

                if (strlen($header) > 4096) {
                    Logger::error($this, 'Maximum response header size exceeded.');
                    $conn->close();
                }

                $header .= $data;
                if (false === $headerEnd = strpos($header, "\r\n\r\n")) {
                    return;
                }

                $bodyParts = substr($header, $headerEnd + 4);
                $header    = substr($header, 0, $headerEnd + 1);

                $response = parse_response($header);
                $rv       = new ResponseVerifier();
                if (!$rv->verifyAll($request, $response)) {
                    Logger::error($this, 'Invalid response to websocket handshake');
                    $conn->close();
                }

                $serializer = new JsonSerializer();

                // setup websocket stuff
                $mb = new MessageBuffer(
                    new CloseFrameChecker(),
                    function (Message $message) use ($serializer) {
                        $this->client->onMessage($this->transport, $serializer->deserialize($message->getPayload()));
                    },
                    function (FrameInterface $frame, MessageBuffer $messageBuffer) use ($conn) {
                        switch ($frame->getOpcode()) {
                            case Frame::OP_PING:
                                $conn->write((new Frame($frame->getPayload(), true, Frame::OP_PONG))->maskPayload()->getContents());
                                return;
                            case Frame::OP_CLOSE:
                                $conn->end((new Frame($frame->getPayload(), true, Frame::OP_CLOSE))->maskPayload()->getContents());
                                return;
                        }
                    },
                    false,
                    null,
                    function ($data) use ($conn) {
                        $conn->write($data);
                    }
                );
                $conn->removeAllListeners('data');
                $conn->on('data', function ($data) use ($mb) {
                    $mb->onData($data);
                });

                $this->transport = new WebSocketClientTransport($mb, $serializer);
                $this->client->onOpen($this->transport);

                $mb->onData($bodyParts);
            });
            $conn->on('error', function ($error) {
                Logger::error($this, 'Error during connect phase');
                $this->client->onClose('Transport error');
            });
            $conn->on('end', function () {
                Logger::debug($this, 'Connection closed');
                $this->client->onClose('Transport close');
            });
        }, function ($error) {
            Logger::error($this, 'Connect error');
        });
    }
}
