<?php
/**
 * Created by PhpStorm.
 * User: gongyidong
 * Date: 2017/10/27
 * Time: 下午2:14
 */

namespace App\Helper;

class CurlHelper
{

    // GET 请求
    public static function get($url, $params, $options = [], $is_json = true, $timeout = 10, $connect_timeout = 10)
    {
        if ($params) {
            $url = rtrim($url, " \t\n\r\0\x0B\?") . '?' . (is_array($params) ? http_build_query($params) : $params);
        }
        $options += [
            CURLOPT_TIMEOUT => $timeout,// 允许CURL函数执行的最长秒数
            CURLOPT_CONNECTTIMEOUT => $connect_timeout, // 尝试连接等待的秒数，0为无限等待
        ];

        return self::request($url, $options, $is_json);
    }

    // POST 请求
    public static function post($url, $params = [], $options = [], $is_json = true, $timeout = 10, $connect_timeout = 10)
    {
        $options += [
            CURLOPT_TIMEOUT => $timeout,// 允许CURL函数执行的最长秒数
            CURLOPT_CONNECTTIMEOUT => $connect_timeout, // 尝试连接等待的秒数，0为无限等待
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
        ];

        return self::request($url, $options, $is_json);
    }


    private static function request($url, $options, $is_json = true)
    {
        LogHelper::printLog('url=' . $url);
        $ch = curl_init();

        $options += [
            CURLOPT_URL => $url,
            CURLOPT_AUTOREFERER => true, // 自动重定向
            CURLOPT_FOLLOWLOCATION => true, // 根据服务器返回的HTTP头中的Location：重定向，是递归的，Location:发送几次就重定向几次
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
        ];
        curl_setopt_array($ch, $options);

        $result = curl_exec($ch);
        $info = curl_getinfo($ch);

        $error_no = curl_errno($ch);
        $error_msg = curl_error($ch);
        curl_close($ch);
        if ($error_no) {
            LogHelper::printLog($error_msg);
            return false;
        }

        if ($info['http_code'] == 200) {
            return $is_json ? json_decode($result, true) : $result;
        }

        return false;
    }


}