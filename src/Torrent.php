<?php
/**
 * Created by PhpStorm.
 * User: heirelance
 * Date: 2018/8/20
 * Time: 17:56
 */

namespace Irelance\Torrent;


use Illuminate\Support\Arr;

class Torrent
{
    use AttributesVariablitiy;
    protected $array;

    protected function __construct($array)
    {
        $this->array = $array;
    }

    public static function string($string)
    {
        $raw = Bencode::bdecode($string);
        if (!self::support($raw)) {
            return null;
        }
        return new self($raw);
    }

    public static function file($path)
    {
        if (!file_exists($path)) {
            return null;
        }
        if ($instance = self::string(file_get_contents($path))) {
            $hash = bin2hex($instance->infoHash);
            @copy($path, self::getLocalTorrentPathByHash($hash));
        }
        return $instance;
    }

    public static function infoHash($hash)
    {
        if (file_exists($path = self::getLocalTorrentPathByHash($hash))) {
            return self::file($path);
        }
        //todo download from DHT
        return null;
    }

    public static function getLocalTorrentPathByHash($hash)
    {
        if (strlen($hash) == 20) {//just sha1, torrent v2 is not sha1 hash
            $hash = bin2hex($hash);
        }
        return dirname(__DIR__) . '/storage/torrent/' . $hash . '.torrent';
    }

    public static function support($array)
    {
        //todo 无跟踪的torrent字典没有“announce”键。相反，无跟踪的torrent有一个“nodes”键
        if (
            //!Arr::has($array, 'announce') ||
            !Arr::has($array, 'info.piece length') ||
            !Arr::has($array, 'info.pieces')
        ) {
            return false;
        }
        if (strlen($array['info']['pieces']) % 20 !== 0) {
            return false;
        }
        return true;
    }

    public function get($key, $default = null)
    {
        return Arr::get($this->array, $key, $default);
    }

    public function keys($key = null)
    {
        if (!$key) {
            return array_keys($this->array);
        }
        return array_keys(Arr::get($this->array, $key, []));
    }

    //attributes
    public function getTrackers()
    {
        return $this->attributes['trackers'] = array_values(array_unique(array_merge(
            [$this->get('announce')],
            array_column($this->get('announce-list', []), 0)
        )));
    }

    public function getCharset()
    {
        return $this->attributes['charset'] = Util::chardet($this->get('info.name'));
    }

    public function getName()
    {
        return $this->attributes['name'] = mb_convert_encoding($this->get('info.name'), $this->charset);
    }

    public function getLength()
    {
        return $this->attributes['length'] = $this->get('info.length');
    }

    public function isSingleFile()
    {
        return !!$this->length;
    }

    /**
     * 如果这个值被置为1，那么 BT客户端 必须通过向 种子文件 中指定的 Tracker 汇报自身的存在，从而获取其它 伙伴 的信息。反之如果置0，客户端 可以通过其它方法获取其它伙伴**，例如 PEX，DHT。因此，private 字段也可以理解为“禁止通过其它途径获取伙伴信息”。
     * @return bool
     */
    public function isPrivate()
    {
        return !!$this->get('info.private');
    }

    public function getFiles()
    {
        if ($this->isSingleFile()) {
            return [[
                'length' => $this->length,
                'path' => $this->name,
            ]];
        }
        $files = [];
        foreach ($this->get('info.files') as $file) {
            $files[] = [
                'length' => Arr::get($file, 'length', 0),
                'path' => $this->name . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, array_map(function ($value) {
                        return mb_convert_encoding($value, $this->charset);
                    }, Arr::get($file, 'path', []))),
            ];
        }
        return $files;
    }

    public function getPieceLength()
    {
        return $this->attributes['piece_length'] = $this->get('info.piece length');
    }

    public function getInfoHash()
    {
        return $this->attributes['info_hash'] = sha1(Bencode::bencode($this->get('info')), true);
    }

    public function getPieces()
    {
        return $this->attributes['pieces'] = str_split($this->get('info.pieces'), 20);
    }

    public function getTotal()
    {
        return array_sum(array_column($this->getFiles(), 'length'));
    }
}