<?php
/**
 * Created by PhpStorm.
 * User: heirelance
 * Date: 2018/8/28
 * Time: 13:14
 */

namespace Irelance\Torrent;


use Illuminate\Support\Str;
use Irelance\Torrent\Storage\Redis;

abstract class Model
{
    protected static $table = '';
    protected static $primaryKey = 'id';

    //protected static $cache = 'redis';
    //protected static $persistence = 'mysql';

    protected static $fields = [];//$primaryKey not include

    protected $id;//$primaryKey value

    public static function buildCacheKey($id)
    {
        return static::$table . ':' . $id;
    }

    public static function find($id)
    {
        if (static::isNew($id)) {
            return null;
        }
        $instance = new static($id);
        return $instance;
    }

    public static function get($search = '*')
    {
        $result = [];
        foreach (Redis::keys($search, static::$table . ':') as $id) {
            if ($id == Redis::hget(static::buildCacheKey($id), static::$primaryKey)) {
                $instance = new static($id);
                $result[] = $instance;
            }
        }
        return $result;
    }

    /**
     * check if instance is new in cache
     * @param $id
     * @return bool
     */
    public static function isNew($id)
    {
        return !Redis::has(static::buildCacheKey($id));
    }

    /**
     * check if instance is new in persistence
     * @param $id
     * @return bool
     */
    public static function isStorage($id)
    {
        return false;
    }

    protected static function cacheSet($id, $name, $value)
    {
        Redis::hset(static::buildCacheKey($id), $name, $value);
    }

    public static function initialCache($id, $force = false)
    {
        if ($force || static::isNew($id)) {
            foreach (static::$fields as $field) {
                self::cacheSet($id, $field, null);
            }
            self::cacheSet($id, static::$primaryKey, $id);
            static::defaultValues($id);
        }
    }

    public function __construct($id)
    {
        $this->id = $id;
        static::initialCache($id);
    }

    abstract protected static function defaultValues($id);

    public function __set($name, $value)
    {
        if (in_array($name, static::$fields)) {
            self::cacheSet($this->id, $name, $value);
            $method = 'afterSet' . Str::studly($name);
            if (method_exists($this, $method)) {
                $this->$method();
            }
        }
    }

    public function __get($name)
    {
        if (in_array($name, static::$fields)) {
            return Redis::hget(static::buildCacheKey($this->id), $name);
        }
        return null;
    }

    public function flush()
    {
        Redis::del(static::buildCacheKey($this->id));
    }

    /**
     * get fields value from persistence
     */
    public function rollback()
    {
    }

    /**
     * save cache to persistence
     * @return bool
     */
    public function save()
    {
        return false;
    }
}