<?php
/**
 * Created by PhpStorm.
 * User: heirelance
 * Date: 2018/8/21
 * Time: 9:21
 */

namespace Irelance\Torrent;

class Util
{
    public static function decodeInt($x)
    {
        $x = unpack('N', $x);
        return $x[1];
    }

    public static function encodeInt($x)
    {
        return pack('N', $x);
    }

    public static function decodeShort($x)
    {
        $x = unpack('n', $x);
        return $x[1];
    }

    public static function encodeShort($x)
    {
        return pack('n', $x);
    }

    public static function readString(&$str, $size = 65535)
    {
        $result = substr($str, 0, $size);
        $str = substr($str, $size);
        return $result;
    }

    public static function safeInt($val)
    {
        switch (gettype($val)) {
            case "integer":
                return true;
            case "double":
                return $val === (float)(int)$val;
            case "string":
                $losslessCast = (string)(int)$val;

                if ($val !== $losslessCast && $val !== "+$losslessCast") {
                    return false;
                }

                return $val <= PHP_INT_MAX && $val >= PHP_INT_MIN;
            default:
                return false;
        }
    }

    public static function intToIp($n)
    {
        $iphex = dechex($n);//将10进制数字转换成16进制
        $len = strlen($iphex);//得到16进制字符串的长度
        if (strlen($iphex) < 8) {
            $iphex = '0' . $iphex;//如果长度小于8，在最前面加0
            $len = strlen($iphex); //重新得到16进制字符串的长度
        }
        //这是因为ipton函数得到的16进制字符串，如果第一位为0，在转换成数字后，是不会显示的
        //所以，如果长度小于8，肯定要把第一位的0加上去
        //为什么一定是第一位的0呢，因为在ipton函数中，后面各段加的'0'都在中间，转换成数字后，不会消失
        for ($i = 0, $j = 0; $j < $len; $i = $i + 1, $j = $j + 2) {//循环截取16进制字符串，每次截取2个长度
            $ippart = substr($iphex, $j, 2);//得到每段IP所对应的16进制数
            $fipart = substr($ippart, 0, 1);//截取16进制数的第一位
            if ($fipart == '0') {//如果第一位为0，说明原数只有1位
                $ippart = substr($ippart, 1, 1);//将0截取掉
            }
            $ip[] = hexdec($ippart);//将每段16进制数转换成对应的10进制数，即IP各段的值
        }
        $ip = array_reverse($ip);

        return implode('.', $ip);//连接各段，返回原IP值
    }

    public static function chardet($str)
    {
        $list = array('GBK', 'UTF-8', 'UTF-16LE', 'UTF-16BE', 'ISO-8859-1');
        foreach ($list as $item) {
            $tmp = mb_convert_encoding($str, $item, $item);
            if (md5($tmp) == md5($str)) {
                return $item;
            }
        }
        return null;
    }
}