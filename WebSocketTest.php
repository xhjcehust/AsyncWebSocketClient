<?php
/**
 * Created by PhpStorm.
 * User: marsnowxiao
 * Date: 2017/6/15
 * Time: 下午7:45
 */

require "WebSocketClient.php";

$client = new WebSocketClient();

$client->on("open",function ($client) {
    $fd = $client->getTcpClient()->sock;
    echo "fd: $fd is open\n";
    $msg = [
        "path" => "/index/index/index",
        "data" => "hhh"
    ];
    $client->send(json_encode($msg));
});

$client->on("message", function ($client, $frame) {
    $fd = $client->getTcpClient()->sock;
    echo "fd: $fd received: {$frame->data}\n";
});

$client->on("close", function ($client) {
    $fd = $client->getTcpClient()->sock;
    echo "fd: $fd is closed\n";
});

$client->connect("127.0.0.1", 9502);