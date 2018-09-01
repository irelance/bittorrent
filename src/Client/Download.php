<?php
/**
 * Created by PhpStorm.
 * User: heirelance
 * Date: 2018/8/22
 * Time: 15:21
 */

namespace Irelance\Torrent\Client;


use Irelance\Torrent\Model;
use Irelance\Torrent\Torrent;

class Download extends Model
{
    const EVENT_STARTED = 'started';
    const EVENT_STOPPED = 'stopped';
    const EVENT_COMPLETED = 'completed';

    protected static $table = 'download';
    protected static $fields = ['is_new', 'uploaded', 'downloaded', 'left', 'event', 'save_path', 'bitfield'];

    protected static function defaultValues($id)
    {
        self::cacheSet($id, 'is_new', true);
        self::cacheSet($id, 'uploaded', 0);
        self::cacheSet($id, 'downloaded', 0);
        self::cacheSet($id, 'left', 0);
        self::cacheSet($id, 'event', self::EVENT_STARTED);
        self::cacheSet($id, 'save_path', '');
        self::cacheSet($id, 'bitfield', '');
        if ($torrent = Torrent::infoHash($id)) {
            self::cacheSet($id, 'left', $torrent->getTotal());
            $byteCount = (int)ceil(count($torrent->pieces) / 8);
            self::cacheSet($id, 'bitfield', str_repeat(chr(0), $byteCount));
        }
    }

    public function addMission($savePath)
    {
        if (!$torrent = Torrent::infoHash($this->id)) {
            $this->flush();
            return false;
        }
        $this->save_path = $savePath;
        $this->is_new = false;
        //todo search cache to get the real left and bitfield
        foreach ($torrent->trackers as $announcePath) {
            new Tracker($announcePath);
            new TorrentTracker($this->id . ':' . $announcePath);
        }
        return true;
    }

    public function isFinish()
    {
        return !$this->left;
    }
}