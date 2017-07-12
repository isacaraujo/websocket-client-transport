<?php

use Thruway\ClientSession;
use Thruway\Peer\Client;
use Thruway\Transport\WebSocketClientTransportProvider;

require __DIR__ . '/../vendor/autoload.php';

$client = new Client('realm1');
$client->addTransportProvider(new WebSocketClientTransportProvider('wss://ws.example.com/ws'));

$client->on('open', function (ClientSession $session) {
    $reg = $session->register('some.proc', function () {
        return 'hello';
    });

    $reg->then(function () use ($session) {
        $session->call('some.proc')->then(function ($r) {
            print_r($r);
        });
    });
});

$client->start();
