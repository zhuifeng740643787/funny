<?php
/**
 * Created by PhpStorm.
 * User: gongyidong
 * Date: 2017/10/27
 * Time: 下午3:32
 */

namespace App\Helper;


class LogHelper
{

    public static function printLog(...$message)
    {
        if (func_num_args() == 0) {
            return;
        }

        $file_name = config('console.log_dir') . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
        if (file_exists($file_name) && !is_writable($file_name)) {
            die("consele log cannot write in");
        }
        $f = fopen($file_name, 'a+');
        fwrite($f, date('Y-m-d H:i:s') . "\t");

        if (is_string($message)) {
            fwrite($f, $message);
        } else {
            fwrite($f, func_num_args() > 1 ? json_encode($message) : json_encode($message[0]));
        }
        fwrite($f, "\r\n");
        fclose($f);
    }


}