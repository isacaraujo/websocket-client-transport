<?php

namespace Thruway\Test;

use Evenement\EventEmitter;
use React\Socket\ConnectionInterface;
use React\Stream\DuplexStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;

class ConnectionStub extends EventEmitter implements ConnectionInterface
{
    private $duplexStream;

    public function __construct(DuplexStreamInterface $duplexStream)
    {
        $this->duplexStream = $duplexStream;
    }

    public function isReadable()
    {
        return $this->duplexStream->isReadable();
    }

    public function isWritable()
    {
        return $this->duplexStream->isWritable();
    }

    public function pause()
    {
        $this->duplexStream->pause();
    }

    public function resume()
    {
        $this->duplexStream->resume();
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        return $this->duplexStream->pipe($dest, $options);
    }

    public function write($data)
    {
        return $this->duplexStream->write($data);
    }

    public function end($data = null)
    {
        $this->duplexStream->end($data);
    }

    public function close()
    {
        $this->duplexStream->close();
    }

    public function getRemoteAddress()
    {
        return '127.0.0.1';
    }

    public function getLocalAddress()
    {
        return '127.0.0.1';
    }
}
