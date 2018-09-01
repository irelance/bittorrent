<?php
/**
 * Created by PhpStorm.
 * User: heirelance
 * Date: 2018/8/21
 * Time: 14:01
 */

namespace Irelance\Torrent\Client;

use Irelance\Torrent\Model;

class Tracker extends Model
{
    public static $compact = 1;
    public static $noPeerId = 1;
    public static $numwant = 10;

    protected static $table = 'tracker';
    protected static $fields = ['scheme', 'success', 'failure', 'status',];

    protected static function defaultValues($id)
    {
        $urlInfo = parse_url($id);
        static::cacheSet($id, 'scheme', strtolower($urlInfo['scheme']));
        static::cacheSet($id, 'status', true);
        static::cacheSet($id, 'success', 0);
        static::cacheSet($id, 'failure', 0);
    }
}