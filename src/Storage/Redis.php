<?php
/**
 * Created by PhpStorm.
 * User: heirelance
 * Date: 2018/8/27
 * Time: 11:54
 */

namespace Irelance\Torrent\Storage;


use Illuminate\Support\Arr;
use Predis\Client;

class Redis
{
    protected static $instance;
    protected static $prefix;

    protected $conn;


    protected function __construct()
    {
        $config = require __DIR__ . '/../../config/redis.php';
        $this->conn = new Client($config['parameters'], $config['options']);
        static::setPrefix();
    }

    public static function setPrefix()
    {
        $config = require __DIR__ . '/../../config/redis.php';
        self::$prefix = Arr::get($config, 'options.prefix', '');
    }

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function set($key, $value, $expired = 0)
    {
        if ($expired) {
            return self::getInstance()->conn->setex($key, $expired, $value);
        }
        return self::getInstance()->conn->set($key, $value);
    }

    public static function get($key, $default = null)
    {
        $result = self::getInstance()->conn->get($key);
        return is_null($result) ? $default : $result;
    }

    public static function del($key)
    {
        return self::getInstance()->conn->del($key);
    }

    public static function has($key)
    {
        return self::getInstance()->conn->exists($key);
    }

    public static function hset($hash, $key, $value)
    {
        return self::getInstance()->conn->hset($hash, $key, $value);
    }

    public static function hadd($hash, $key, $add)
    {
        return self::getInstance()->conn->hincrby($hash, $key, $add);
    }

    public static function hget($hash, $key, $default = null)
    {
        $result = self::getInstance()->conn->hget($hash, $key);
        return is_null($result) ? $default : $result;
    }

    public static function hdel($hash, $key)
    {
        return self::getInstance()->conn->hdel($hash, $key);
    }

    public static function hhas($hash, $key)
    {
        return self::getInstance()->conn->hexists($hash, $key);
    }

    public static function keys($search = '*', $subPrefix = '')
    {
        static::setPrefix();
        $prefix = self::$prefix . $subPrefix;
        $prefixLength = strlen($prefix);
        return array_map(function ($val) use ($prefixLength) {
            return substr($val, $prefixLength);
        }, self::getInstance()->conn->keys($subPrefix . $search));
    }

    public static function clean($search = '*')
    {
        $conn = self::getInstance()->conn;
        foreach (self::keys($search) as $key) {
            $conn->del($key);
        }
    }
}