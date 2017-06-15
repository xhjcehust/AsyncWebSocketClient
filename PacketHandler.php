<?php
/**
 * Created by PhpStorm.
 * User: marsnowxiao
 * Date: 2017/6/15
 * Time: 下午5:12
 */
class WebSocketFrame {
    public $finish;
    public $opcode;
    public $data;
}

class PacketHandler {
    const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
    const TOKEN_LENGHT = 16;
    const maxPacketSize = 2000000;
    private $key = "";

    private static function generateToken($length)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"§$%&/()=[]{}';

        $useChars = array();
        // select some random chars:
        for ($i = 0; $i < $length; $i++) {
            $useChars[] = $characters[mt_rand(0, strlen($characters) - 1)];
        }
        // Add numbers
        array_push($useChars, rand(0, 9), rand(0, 9), rand(0, 9));
        shuffle($useChars);
        $randomString = trim(implode('', $useChars));
        $randomString = substr($randomString, 0, self::TOKEN_LENGHT);

        return base64_encode($randomString);
    }

    public function buildHandShakeRequest($host, $port)
    {
        $this->key = static::generateToken(self::TOKEN_LENGHT);

        return "GET / HTTP/1.1" . "\r\n" .
            "Origin: null" . "\r\n" .
            "Host: {$host}:{$port}" . "\r\n" .
            "Sec-WebSocket-Key: {$this->key}" . "\r\n" .
            "User-Agent: SwooleWebsocketClient"."/0.1.4" . "\r\n" .
            "Upgrade: Websocket" . "\r\n" .
            "Connection: Upgrade" . "\r\n" .
            "Sec-WebSocket-Protocol: wamp" . "\r\n" .
            "Sec-WebSocket-Version: 13" . "\r\n" . "\r\n";
    }

    public function verifyUpgrade($packet)
    {
        $headers = explode("\r\n", $packet);
        unset($headers[0]);
        $headerInfo = [];
        foreach ($headers as $header) {
            $arr = explode(":", $header);
            if (count($arr) == 2) {
                list($field, $value) = $arr;
                $headerInfo[trim($field)] = trim($value);
            }
        }

        return (isset($headerInfo['Sec-WebSocket-Accept']) && $headerInfo['Sec-WebSocket-Accept'] == base64_encode(pack('H*', sha1($this->key.self::GUID))));
    }

    public function processDataFrame(&$packet)
    {
        if (strlen($packet) < 2)
            return null;
        $header = substr($packet, 0, 2);
        $index = 0;

        //fin:1 rsv1:1 rsv2:1 rsv3:1 opcode:4
        $handle = ord($packet[$index]);
        $finish = ($handle >> 7) & 0x1;
        $rsv1 = ($handle >> 6) & 0x1;
        $rsv2 = ($handle >> 5) & 0x1;
        $rsv3 = ($handle >> 4) & 0x1;
        $opcode = $handle & 0xf;
        $index++;

        //mask:1 length:7
        $handle = ord($packet[$index]);
        $mask = ($handle >> 7) & 0x1;

        //0-125
        $length = $handle & 0x7f;
        $index++;
        //126 short
        if ($length == 0x7e)
        {
            if (strlen($packet) < $index + 2)
                return null;
            //2 byte
            $handle = unpack('nl', substr($packet, $index, 2));
            $index += 2;
            $length = $handle['l'];
        }
        //127 int64
        elseif ($length > 0x7e)
        {
            if (strlen($packet) < $index + 8)
                return null;
            //8 byte
            $handle = unpack('Nh/Nl', substr($packet, $index, 8));
            $index += 8;
            $length = $handle['l'];
            if ($length > static::maxPacketSize)
            {
                throw new \Exception("frame length is too big.\n");
            }
        }

        //mask-key: int32
        if ($mask)
        {
            if (strlen($packet) < $index + 4)
                return null;
            $mask = array_map('ord', str_split(substr($packet, $index, 4)));
            $index += 4;
        }

        if (strlen($packet) < $index + $length)
            return null;
        $data = substr($packet, $index, $length);
        $index += $length;

        $packet = substr($packet, $index);

        $frame = new WebSocketFrame;
        $frame->finish = $finish;
        $frame->opcode = $opcode;
        $frame->data = $data;
        return $frame;
    }
}