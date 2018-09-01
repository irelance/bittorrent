<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/26
 * Time: 16:01
 */

namespace Irelance\Torrent\Protocol;


use Irelance\Torrent\Bencode;
use Irelance\Torrent\Server\Node;
use Irelance\Torrent\Torrent;
use Irelance\Torrent\Util;
use Irelance\Torrent\Server\Peer as ServerPeer;

class Peer
{
    const PROTOCOL_STRING = 'BitTorrent protocol';
    const PROTOCOL_STRING_LENGTH = 19;

    const HAND_SHAKE = 'hand_shark';
    const HEART_BEAT = 'heart_beat';

    const MSG_CHOKE = 0;
    const MSG_UNCHOKE = 1;
    const MSG_INTERESTED = 2;
    const MSG_NOT_INTERESTED = 3;
    const MSG_HAVE = 4;
    const MSG_BITFIELD = 5;
    const MSG_REQUEST = 6;
    const MSG_PIECE = 7;
    const MSG_CANCEL = 8;

    const MSG_PORT = 9;

    const MSG_SUGGEST = 13;
    const MSG_HAVE_ALL = 14;
    const MSG_HAVE_NONE = 15;
    const MSG_REJECT_REQUEST = 16;
    const MSG_ALLOWED_FAST = 17;

    const MSG_EXTENDED = 20;

    const MSG_HASH_REQUEST = 21;
    const MSG_HASHES = 22;
    const MSG_HASH_REJECT = 23;

    const RESERVED_0 = 0x00;
    const RESERVED_1 = 0x00;
    const RESERVED_2 = 0x00;
    const RESERVED_3 = 0x00;
    const RESERVED_4 = 0x00;
    const RESERVED_5 = 0x00;
    const RESERVED_6 = 0x00;
    const RESERVED_7 = 0x04;

    public static $chunkSize = 1024 * 16;

    public static function handShake(string $infoHash): string
    {
        if (strlen($infoHash) == 40) {
            $infoHash = hex2bin($infoHash);
        }
        return
            //pstrlen
            chr(self::PROTOCOL_STRING_LENGTH) .
            //pstr
            self::PROTOCOL_STRING .
            //reserved
            self::RESERVED_0 . self::RESERVED_1 . self::RESERVED_2 . self::RESERVED_3 .
            self::RESERVED_4 . self::RESERVED_5 . self::RESERVED_6 . self::RESERVED_7 .
            //info_hash
            $infoHash .
            //peer id
            ServerPeer::$peerId;
    }

    protected static function pack(string $payload): string
    {
        return Util::encodeInt(strlen($payload)) . $payload;
    }

    public static function heartbeat(): string
    {
        return self::pack('');
    }

    public static function choke(): string
    {
        return self::pack(chr(self::MSG_CHOKE));
    }

    public static function unchoke(): string
    {
        return self::pack(chr(self::MSG_UNCHOKE));
    }

    public static function interested(): string
    {
        return self::pack(chr(self::MSG_INTERESTED));
    }

    public static function notInterested(): string
    {
        return self::pack(chr(self::MSG_NOT_INTERESTED));
    }

    public static function have(int $pieceIndex): string
    {
        $packet = chr(self::MSG_HAVE) . Util::encodeInt($pieceIndex);
        return self::pack($packet);
    }

    public static function bitfield(string $bitfield): string
    {
        $packet = chr(self::MSG_BITFIELD) . $bitfield;
        return self::pack($packet);
    }

    public static function request(Torrent $torrent, int $pieceIndex, int $chunkIndex): string
    {
        $chunkCount = (int)ceil($torrent->pieceLength / self::$chunkSize);
        $chunkIndex = $chunkIndex % $chunkCount;
        $begin = $chunkIndex * self::$chunkSize;
        $length = min(self::$chunkSize, $torrent->pieceLength - $begin);
        if ($pieceIndex + 1 == count($torrent->pieces) && $chunkIndex + 1 == $chunkCount) {
            $length = min($length, $torrent->getTotal() % self::$chunkSize);
        }
        $packet = chr(self::MSG_REQUEST) .
            Util::encodeInt($pieceIndex) .
            Util::encodeInt($begin) .
            Util::encodeInt($length);
        return self::pack($packet);
    }

    public static function piece(int $index, int $begin, string $data): string
    {//todo auto build with $index, $begin, $length
        $packet = chr(self::MSG_PIECE) .
            Util::encodeInt($index) .
            Util::encodeInt($begin) .
            $data;
        return self::pack($packet);
    }

    public static function cancel(Torrent $torrent, int $pieceIndex, int $chunkIndex): string
    {
        $chunkCount = (int)ceil($torrent->pieceLength / self::$chunkSize);
        $chunkIndex = $chunkIndex % $chunkCount;
        $begin = $chunkIndex * self::$chunkSize;
        $length = min(self::$chunkSize, $torrent->pieceLength - $begin);
        $packet = chr(self::MSG_CANCEL) .
            Util::encodeInt($pieceIndex) .
            Util::encodeInt($begin) .
            Util::encodeInt($length);
        return self::pack($packet);
    }

    public static function port(): string
    {
        $packet = chr(self::MSG_PORT) . Util::encodeShort(Node::$port);//DHT node port
        return self::pack($packet);
    }

    public static function suggest(int $pieceIndex): string
    {
        $packet = chr(self::MSG_SUGGEST) . Util::encodeInt($pieceIndex);
        return self::pack($packet);
    }

    public static function haveAll(): string
    {
        return self::pack(chr(self::MSG_HAVE_ALL));
    }

    public static function haveNone(): string
    {
        return self::pack(chr(self::MSG_HAVE_NONE));
    }

    public static function rejectRequest(int $index, int $begin, int $length): string
    {
        $packet = chr(self::MSG_REJECT_REQUEST) .
            Util::encodeInt($index) .
            Util::encodeInt($begin) .
            Util::encodeInt($length);
        return self::pack($packet);
    }

    public static function allowedFast(int $pieceIndex): string
    {
        $packet = chr(self::MSG_ALLOWED_FAST) . Util::encodeInt($pieceIndex);
        return self::pack($packet);
    }

    public static function extended(int $msgId, array $data): string
    {
        $packet = chr(self::MSG_EXTENDED) . chr($msgId) . Bencode::bencode($data);
        return self::pack($packet);
    }

    public static function unpack(string $data): array
    {
        $result = [];
        if (19 == ($pstrlen = ord(Util::readString($data, 1)))) {//理论上peer_msg没有这么长的data[318767104,335544319]
            if (self::PROTOCOL_STRING !== ($pstr = Util::readString($data, $pstrlen))) {
                return $result;
            }
            $reserved = [];
            for ($i = 0; $i < 8; $i++) {
                $reserved[] = ord(Util::readString($data, 1));
            }
            $infoHash = Util::readString($data, 20);
            $peerId = Util::readString($data, 20);
            $type = self::HAND_SHAKE;
            return compact('type', 'reserved', 'infoHash', 'peerId');
        } else {
            if (!$len = Util::decodeInt(chr($pstrlen) . Util::readString($data, 3))) {
                return ['type' => self::HEART_BEAT];
            }
            $type = ord(Util::readString($data, 1));
            $data = Util::readString($data, $len - 1);
            switch ($type) {
                case self::MSG_CHOKE:
                case self::MSG_UNCHOKE:
                case self::MSG_INTERESTED:
                case self::MSG_NOT_INTERESTED:
                case self::MSG_HAVE_ALL:
                case self::MSG_HAVE_NONE:
                    return compact('type');
                case self::MSG_BITFIELD:
                    $bitfield = $data;
                    return compact('type', 'bitfield');
                case self::MSG_REQUEST:
                case self::MSG_CANCEL:
                case self::MSG_REJECT_REQUEST:
                    $index = Util::decodeInt(Util::readString($data, 4));
                    $begin = Util::decodeInt(Util::readString($data, 4));
                    $length = Util::decodeInt(Util::readString($data, 4));
                    return compact('type', 'index', 'begin', 'length');
                case self::MSG_PIECE:
                    $index = Util::decodeInt(Util::readString($data, 4));
                    $begin = Util::decodeInt(Util::readString($data, 4));
                    return compact('type', 'index', 'begin', 'data');
                case self::MSG_PORT:
                    $port = Util::decodeShort($data);
                    return compact('type', 'port');
                case self::MSG_HAVE:
                case self::MSG_SUGGEST:
                case self::MSG_ALLOWED_FAST:
                    $index = Util::decodeInt($data);
                    return compact('type', 'index');
                case self::MSG_EXTENDED:
                    return compact('type');
                default:
            }
        }
        return $result;
    }
}