<?php
/**
 * Created by PhpStorm.
 * User: marsnowxiao
 * Date: 2017/6/15
 * Time: 下午9:08
 */
$ws = new swoole_websocket_server("0.0.0.0", 9502);

$ws->set([
    'worker_num' => 1,
    'backlog' => 128,
    'max_request' => 50,
    'dispatch_mode'=>2,
]);

$ws->on('open', function ($ws, $request) {
    $ws->push($request->fd, "hello, welcome");
});

$ws->on('message', function ($ws, $frame) {
    $ws->push($frame->fd, "abcd");
});

$ws->on('close', function ($ws, $fd) {
    echo "client-{$fd} is closed\n";
});

$ws->start();
