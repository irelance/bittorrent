<?php
/**
 * Created by PhpStorm.
 * User: heirelance
 * Date: 2018/8/30
 * Time: 16:10
 */

namespace Irelance\Torrent;


class Chunk extends Model
{
    protected static $table = 'chunk';
    protected static $fields = ['piece', 'index', 'data'];

    protected static function defaultValues($id)
    {
        list($infoHash, $pid, $index) = explode(':', $id);
        self::cacheSet($id, 'piece', $infoHash . ':' . $pid);
        self::cacheSet($id, 'index', $index);
        self::cacheSet($id, 'data', '');
    }

    public function isFinish()
    {
        if (!$this->data) {
            if ($piece = Piece::find($this->id)) {
                return $piece->isFinish();
            }
            return false;
        }
        return true;
    }
}