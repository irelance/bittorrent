<?php
/**
 * Created by PhpStorm.
 * User: heirelance
 * Date: 2018/8/30
 * Time: 16:09
 */

namespace Irelance\Torrent;


use Irelance\Torrent\Client\Download;

class Piece extends Model
{
    protected static $table = 'piece';
    protected static $fields = ['info_hash', 'index', 'data'];

    protected static function defaultValues($id)
    {
        list($infoHash, $index) = explode(':', $id);
        self::cacheSet($id, 'info_hash', $infoHash);
        self::cacheSet($id, 'index', $index);
        self::cacheSet($id, 'data', '');
    }

    public function isFinish()
    {
        if (!$this->data) {
            if ($download = Download::find($this->id)) {
                return $download->isFinish();
            }
            return false;
        }
        return true;
    }
}