<?php

use App\Mqtt\Client;


$options = [
    'clean_session' => false,
    'client_id' => 'demo-publish-123456',
    'username' => '',
    'password' => '',
];

$mqtt = new Client('127.0.0.1', 1883, $options);

$mqtt->onConnect = function ($mqtt) {
    $mqtt->publish('/World', 'hello swoole mqtt');
};

$mqtt->onError = function ($exception) {
    echo "error\n";
};

$mqtt->onClose = function () {
    echo "close\n";
};

$mqtt->connect();