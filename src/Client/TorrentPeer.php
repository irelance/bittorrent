<?php
/**
 * Created by PhpStorm.
 * User: heirelance
 * Date: 2018/8/22
 * Time: 14:09
 */

namespace Irelance\Torrent\Client;


use Irelance\Torrent\AttributesVariablitiy;
use Irelance\Torrent\Chunk;
use Irelance\Torrent\Model;
use Irelance\Torrent\Piece;
use Irelance\Torrent\Torrent;
use Swoole\Client;
use Irelance\Torrent\Protocol\Peer as ProtocolPeer;

/**
 * Class TorrentPeer
 * @package Irelance\Torrent\Client
 * A Client TorrentPeer
 * @property Client $client
 */
class TorrentPeer extends Model
{
    public static $timeout = -1;//-1 if no timeout
    protected $client;

    protected static $table = 'torrent_peer';
    protected static $fields = ['info_hash', 'ip', 'port', 'bitfield'];

    protected static function defaultValues($id)
    {
        list($infoHash, $ip, $port) = explode(':', $id);
        self::cacheSet($id, 'info_hash', $infoHash);
        self::cacheSet($id, 'ip', $ip);
        self::cacheSet($id, 'port', $port);
        self::cacheSet($id, 'bitfield', '');
        self::cacheSet($id, 'status', true);
        if ($torrent = Torrent::infoHash($id)) {
            $byteCount = (int)ceil(count($torrent->pieces) / 8);
            self::cacheSet($id, 'bitfield', str_repeat(chr(0), $byteCount));
        }
    }

    public function connect()
    {
        if (!$this->status) {
            return false;
        }
        $this->client = new Client(SWOOLE_SOCK_TCP);
        if (!$this->client->connect($this->ip, $this->port, self::$timeout)) {
            return false;
        }
        return true;
    }

    public function handShake()
    {
        if (!$this->status) {
            return false;
        }
        $request = ProtocolPeer::handShake($this->info_hash);
        $this->client->send($request);
        sleep(3);
        $data = $this->client->recv();
        while ($data) {
            $response = ProtocolPeer::unpack($data);
            var_dump($response);
            switch ($response['type']) {
                case ProtocolPeer::MSG_BITFIELD:
                    $this->bitfield = $response['bitfield'];
                    break;
                case ProtocolPeer::MSG_HAVE:
                    $index = (int)floor($response['index'] / 8);
                    $mask = 0b00000001 << (7 - ($response['index'] % 8));
                    $this->bitfield[$index] |= $mask;
                    break;
                case ProtocolPeer::MSG_HAVE_ALL:
                    if ($torrent = Torrent::infoHash($this->id)) {
                        $bitfield = str_repeat(chr(0b11111111), (int)floor(count($torrent->pieces) / 8));
                        if ($mod = count($torrent->pieces) % 8) {
                            $bitfield .= chr(0b11111111 << (8 - $mod));
                        }
                        $this->bitfield = $bitfield;
                    }
                    break;
                case ProtocolPeer::MSG_HAVE_NONE:
                    $this->status = false;
                    break;
            }
        }
    }

    public function interested()
    {
        if (!$this->status) {
            return false;
        }
        $request = ProtocolPeer::interested();
        $this->client->send($request);
        sleep(3);
        $response = ProtocolPeer::unpack($this->client->recv());
        var_dump($response);
    }

    public function request($pieceIndex = 0, $chunkIndex = 0)
    {
        if (!$this->status) {
            return false;
        }
        $index = (int)floor($pieceIndex / 8);
        $mask = 0b00000001 << (7 - ($pieceIndex % 8));
        if ($this->bitfield[$index] & $mask != $mask) {
            return false;
        }
        new Piece($this->info_hash . ':' . $pieceIndex);
        $chunk = new Chunk($this->info_hash . ':' . $pieceIndex . ':' . $chunkIndex);
        $request = ProtocolPeer::request(Torrent::infoHash($this->info_hash), $pieceIndex, $chunkIndex);
        $this->client->send($request);
        sleep(3);
        $response = ProtocolPeer::unpack($this->client->recv());
        if ($response['type'] == ProtocolPeer::MSG_PIECE) {
            $chunk->data = $response['data'];
        }
    }
}