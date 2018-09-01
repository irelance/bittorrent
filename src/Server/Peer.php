<?php
/**
 * Created by PhpStorm.
 * User: heirelance
 * Date: 2018/8/22
 * Time: 15:03
 */

namespace Irelance\Torrent\Server;


use Illuminate\Support\Arr;
use Irelance\Torrent\Client\Download;
use Irelance\Torrent\Storage\Redis;
use Irelance\Torrent\Util;
use Irelance\Torrent\Protocol\Peer as ProtocolPeer;
use Swoole\Server;

class Peer
{
    public static $version = 'pTorrent-v1.0.0';
    public static $peerId = 'php-test-client-0001';
    public static $port = '9999';
    public static $key = 'B3A9EE32';
    public static $trackers = [];

    protected $connect;

    public function __construct()
    {
        $this->connect = new Server('0.0.0.0', self::$port, SWOOLE_BASE, SWOOLE_SOCK_TCP);
        $this->connect->set([
            'daemonize' => 1,
        ]);
        $this->connect->on('receive', function ($serv, $fd, $from_id, $data) {
            /* @var Server $serv */
            while (!empty($data)) {
                $result = ProtocolPeer::unpack($data);
                if (empty($result)) {
                    return $serv->close($fd);
                }
                switch ($result['type']) {
                    case ProtocolPeer::HAND_SHAKE:
                        $infoHash = bin2hex($result['infoHash']);
                        if (Download::isNew($infoHash)) {//todo
                            return $serv->close($fd);
                        }
                        if (Redis::hget('peer:' . $fd, 'is_handshake', false)) {
                            return $serv->close($fd);
                        }
                        $data = ProtocolPeer::handShake($result['infoHash']) .
                            ProtocolPeer::bitfield(Redis::hget('download:' . $infoHash, 'bitfield'));
                        foreach (Redis::hget('download:' . $infoHash, 'haves') as $index) {
                            $data .= ProtocolPeer::have($index);
                        }
                        if ($serv->send($fd, $data)) {
                            Redis::hset('peer:' . $fd, 'is_handshake', true);
                            Redis::hset('peer:' . $fd, 'info_hash', $infoHash);
                        }
                        break;
                    case ProtocolPeer::HEART_BEAT:
                        break;
                    case ProtocolPeer::MSG_INTERESTED:
                        if ($serv->send($fd, ProtocolPeer::unchoke())) {
                            Redis::hset('peer:' . $fd, 'is_chock', false);
                        }
                        break;
                    case ProtocolPeer::MSG_NOT_INTERESTED:
                        if ($serv->send($fd, ProtocolPeer::choke())) {
                            Redis::hset('peer:' . $fd, 'is_chock', true);
                        }
                        break;
                    case ProtocolPeer::MSG_REQUEST:
                        if (Redis::hget('peer:' . $fd, 'is_chock', true)) {
                            return $serv->close($fd);
                        }
                        if (!$infoHash = Redis::hget('peer:' . $fd, 'info_hash')) {
                            return $serv->close($fd);
                        }
                        if ($data = Redis::hget('download:' . $infoHash, 'haves_' . $result['index'])) {
                            return $serv->send($fd, ProtocolPeer::piece($result['index'], $result['begin'], substr($data, $result['begin'], $result['length'])));
                        }
                        return $serv->send($fd, ProtocolPeer::rejectRequest($result['index'], $result['begin'], $result['length']));
                    case ProtocolPeer::MSG_CANCEL:
                        break;

                }
            }
            return true;
        });

        $this->connect->on('connect', function ($serv, $fd) {
            Redis::hset('peer:' . $fd, 'is_handshake', false);
            Redis::hset('peer:' . $fd, 'is_chock', true);
        });
        $this->connect->on('close', function ($serv, $fd) {
            Redis::del('peer:' . $fd);
        });
        $this->connect->start();
    }
}