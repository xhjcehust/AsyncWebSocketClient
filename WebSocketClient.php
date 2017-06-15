<?php
require "PacketHandler.php";

class WebSocketClient {
    private $client;
    private $state;
    private $host;
    private $port;
    private $handler;
    private $buffer;
    private $openCb;
    private $messageCb;
    private $closeCb;

    const HANDSHAKING = 1;
    const HANDSHAKED = 2;


    const WEBSOCKET_OPCODE_CONTINUATION_FRAME = 0x0;
    const WEBSOCKET_OPCODE_TEXT_FRAME = 0x1;
    const WEBSOCKET_OPCODE_BINARY_FRAME = 0x2;
    const WEBSOCKET_OPCODE_CONNECTION_CLOSE = 0x8;
    const WEBSOCKET_OPCODE_PING = 0x9;
    const WEBSOCKET_OPCODE_PONG = 0xa;

    const TOKEN_LENGHT = 16;

    public function __construct()
    {
        $this->client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        $this->client->on("connect", [$this, "onConnect"]);
        $this->client->on("receive", [$this, "onReceive"]);
        $this->client->on("close", [$this, "onClose"]);
        $this->client->on("error", [$this, "onError"]);
        $this->handler = new PacketHandler();
        $this->buffer = "";
    }

    public function connect($host, $port)
    {
        $this->host = $host;
        $this->port = $port;
        $this->client->connect($host, $port);
    }

    public function sendHandShake()
    {
        $this->state = static::HANDSHAKING;
        $request = $this->handler->buildHandShakeRequest($this->host, $this->port);
        $this->client->send($request);
    }

    public function onConnect($cli)
    {
        $this->sendHandShake();
    }

    public function onReceive($cli, $data)
    {
        if ($this->state == static::HANDSHAKING) {
            $this->buffer .= $data;
            $pos = strpos($this->buffer, "\r\n\r\n", true);

            if ($pos != false) {
                $header = substr($this->buffer, 0, $pos + 4);
                $this->buffer = substr($this->buffer, $pos + 4);

                if (true == $this->handler->verifyUpgrade($header)) {
                    $this->state = static::HANDSHAKED;
                    if (isset($this->openCb))
                        call_user_func($this->openCb, $this);
                } else {
                    echo "handshake failed\n";
                }
            }
        } else if ($this->state == static::HANDSHAKED) {
            $this->buffer .= $data;
        }
        if ($this->state == static::HANDSHAKED) {
            try {
                $frame = $this->handler->processDataFrame($this->buffer);
            } catch (\Exception $e) {
                $cli->close();
                return;
            }
            if ($frame != null) {
                if (isset($this->messageCb))
                    call_user_func($this->messageCb, $this, $frame);
            }
        }

    }

    public function onClose($cli)
    {
        if (isset($this->closeCb))
            call_user_func($this->closeCb, $this);
    }

    public function onError($cli)
    {
        echo "error occurred\n";
    }

    public function on($event, $callback)
    {
        if (strcasecmp($event, "open") === 0) {
            $this->openCb = $callback;
        } else if (strcasecmp($event, "message") === 0) {
            $this->messageCb = $callback;
        } else if (strcasecmp($event, "close") === 0) {
            $this->closeCb = $callback;
        } else {
            echo "$event is not supported\n";
        }
    }

    public function send($data, $type = 'text')
    {
        switch($type)
        {
            case 'text':
                $_type = self::WEBSOCKET_OPCODE_TEXT_FRAME;
                break;
            case 'binary':
            case 'bin':
                $_type = self::WEBSOCKET_OPCODE_BINARY_FRAME;
                break;
            case 'ping':
                $_type = self::WEBSOCKET_OPCODE_PING;
                break;
            case 'close':
                $_type = self::WEBSOCKET_OPCODE_CONNECTION_CLOSE;
                break;

            case 'ping':
                $_type = self::WEBSOCKET_OPCODE_PING;
                break;

            case 'pong':
                $_type = self::WEBSOCKET_OPCODE_PONG;
                break;

            default:
                echo "$type is not supported\n";
                return;
        }
        $data = \swoole_websocket_server::pack($data, $_type);
        $this->client->send($data);
    }

    public function getTcpClient()
    {
        return $this->client;
    }
}