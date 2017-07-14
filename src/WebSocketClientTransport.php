<?php

namespace Thruway\Transport;

use Ratchet\RFC6455\Messaging\MessageBuffer;
use Thruway\Message\Message;
use Thruway\Serializer\SerializerInterface;

class WebSocketClientTransport extends AbstractTransport
{
    private $mb;

    public function __construct(MessageBuffer $mb, SerializerInterface $serializer)
    {
        $this->mb         = $mb;
        $this->serializer = $serializer;
    }

    public function getTransportDetails()
    {
        return [];
    }

    public function sendMessage(Message $msg)
    {
        $this->mb->sendMessage($this->getSerializer()->serialize($msg));
    }
}
