<?php
/**
 * Created by PhpStorm.
 * User: heirelance
 * Date: 2018/8/21
 * Time: 13:20
 */
namespace Irelance\Torrent;

use Illuminate\Support\Str;

trait AttributesVariablitiy
{
    protected $attributes = [];

    public function __get($name)
    {
        $key = Str::snake($name);
        if (isset($this->attributes[$key])) {
            return $this->attributes[$key];
        }
        $method = 'get' . Str::studly($name);
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        return null;
    }
}