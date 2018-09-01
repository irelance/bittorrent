<?php
/**
 * Created by PhpStorm.
 * User: heirelance
 * Date: 2018/8/28
 * Time: 14:48
 */

namespace Irelance\Torrent\Client;


use Irelance\Torrent\Model;

class TorrentTracker extends Model
{
    protected static $table = 'torrent_tracker';
    protected static $fields = ['status', 'tracker', 'info_hash', 'is_new', 'retry_in', 'next_update', 'interval'];

    protected static function defaultValues($id)
    {
        self::cacheSet($id, 'info_hash', substr($id,0,40));//todo torrent v2 not support
        self::cacheSet($id, 'tracker', substr($id,41));
        self::cacheSet($id, 'status', true);
        self::cacheSet($id, 'is_new', true);
        self::cacheSet($id, 'retry_in', 5);
        self::cacheSet($id, 'next_update', time());
        self::cacheSet($id, 'interval', 300);
    }

    protected function afterSetRetryIn()
    {
        if ($this->retry_in == 'never' || $this->retry_in <= 0) {
            $this->status = false;
        }
    }

    protected function afterSetInterval()
    {
        $this->next_update = time() + $this->interval;
        $this->is_new = false;
    }
}