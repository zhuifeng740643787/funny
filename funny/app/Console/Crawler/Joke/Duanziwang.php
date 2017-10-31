<?php
/**
 * Created by PhpStorm.
 * User: gongyidong
 * Date: 2017/10/27
 * Time: 下午2:01
 */

namespace App\Console\Crawler\Joke;

use App\Helper\CurlHelper;
use App\Helper\LogHelper;
use App\Models\User;
use Illuminate\Console\Command;
use App\Models\Joke as JokeModel;

/**
 * 抓取 段子网 段子
 * Class Duanziwang
 * @package App\Console\Crawler\Duanziwang
 */
class Duanziwang extends Command
{
    protected $name = 'crawler:joke-duanziwang';
    protected $description = '抓取【段子网】的段子';

    private $_url = 'http://duanziwang.com/category/duanzi/page/';
    private $_page = 1;
    private $_error_times = 0;// 请求发送错误的次数
    private $_duplicate_times = 0; // 请求的资源重复的个数
    private $_has_more = true; // 是否还有更多
    private $_sleep_seconds = 2;// 暂停秒数
    private $_new_count = 0;// 新增个数

    public function handle()
    {
        libxml_use_internal_errors(true); //禁用libxml错误，并允许用户根据需要提取错误信息
        LogHelper::printLog("----------- Job {$this->name} START ----------------");
        while (!$this->_needExit()) {
            $result = $this->_crawl();
            $this->_handleResult($result);
            LogHelper::printLog("error_times=$this->_error_times duplicate_time=$this->_duplicate_times has_more=$this->_has_more  sleep_seconds={$this->_sleep_seconds}s");
            sleep($this->_sleep_seconds);// 暂停一秒
            $this->_page++;
        }

        LogHelper::printLog("----------- new count={$this->_new_count}----------------");
        LogHelper::printLog("----------- Job {$this->name} END ----------------");

    }


    // 退出脚本的条件
    private function _needExit()
    {
        return $this->_error_times >= 3
            || $this->_duplicate_times >= 100
            || !$this->_has_more;
    }

    // 获取DOMNode
    private function _DOMinnerHTML(\DOMNode $element)
    {
        $innerHTML = "";
        $children = $element->childNodes;

        foreach ($children as $child) {
            $innerHTML .= $element->ownerDocument->saveHTML($child);
        }

        return $innerHTML;
    }

    // 处理单个
    private function _parseItem($html)
    {
        $params = [
            'joke' => [
                'source_type' => JokeModel::SOURCE_TYPE_DZW,
                'source_id' => 0,
                'user_id' => 0,
                'content' => '',
                'online_time' => '',
                'create_time' => '',
                'favorite_count' => 0,
                'digg_count' => 0,
                'bury_count' => 0,
                'share_count' => 0,
            ],
            'user' => [
                'source_type' => User::SOURCE_TYPE_DZW,
                'source_id' => 0,
                'name' => '',
                'avatar_url' => '',
            ]];

        $get_id_preg_pattern = "|[\w]+\/([\d]+)\.html|U";// 获取ID的正则
        $doc = new \DOMDocument();
        $doc->loadHTML('<meta http-equiv="Content-Type" content="charset=utf-8" />' . $html);
        $xpath = new \DOMXPath($doc);

        // 段子
        $joke_header_node = $xpath->query('//header/h2/a')[0];
        preg_match($get_id_preg_pattern, $joke_header_node->getAttribute('href'), $out_joke);
        $params['joke']['source_id'] = $out_joke[1];
        $joke_content_node = $xpath->query('//p[@class="note"]')[0];
        $params['joke']['content'] = $joke_content_node->nodeValue;
        $params['joke']['user_id'] = $params['user']['source_id'];
        $joke_time_node = $xpath->query('//p[@class="text-muted time"]')[0];
        preg_match("|.+\(([\d-]+)\)|U", $joke_time_node->nodeValue, $out_joke_time);
        $params['joke']['online_time'] = strpos($out_joke_time[1], '20') === 0 ? strtotime($out_joke_time[1]) : strtotime(date('Y') . '-' . $out_joke_time[1]);
        $params['joke']['create_time'] = $params['joke']['online_time'];
        // 统计
        $params['joke']['digg_count'] = intval(trim($xpath->query('//p[@class="text-muted views"]//a[@class="post-like"]')[0]->textContent, "赞 \(\)"));

        return $params;

    }

    private function _strToInt($str)
    {
        $str = trim($str, " \t\n\r\0\x0B  ");
        return intval($str);
    }

    // xpath解析html
    private function _xpathParse($result)
    {
        $doc = new \DOMDocument();
        $doc->loadHTML($result);
        $xpath = new \DOMXPath($doc);
        $list = $xpath->query('/html/body//div[@class="content-wrap"]//article[@class="excerpt"]');

        if (count($list) == 0) {
            $this->_has_more = false;
            return false;
        }
        foreach ($list as $k => $item) {
            $html = $this->_DOMinnerHTML($item);
            $params = $this->_parseItem($html);
            $this->_save($k, $params);
        }
    }

    private function _save($key, $params = [])
    {
        $saved = JokeModel::add($params['joke']);

        LogHelper::printLog($key . ': user_id=' . $params['joke']['user_id'] . ' joke_id=' . $params['joke']['source_id']);

        if (!$saved) {
            LogHelper::printLog('duplicate id=' . $params['joke']['source_id']);
            $this->_duplicate_times++;
            return;
        }

        $this->_new_count++;
        if(!$params['user']['source_id']) {
            return;
        }
        User::add($params['user']);
    }

    // 处理抓取的数据
    private function _handleResult($result)
    {
        if (!$result) {
            $this->_error_times++;
            return;
        }

        $this->_xpathParse($result);
    }

    // 获取URL
    private function _getURL()
    {
        return $this->_url . $this->_page;
    }

    // 请求
    private function _crawl()
    {
        $params = [];

//        curl 'http://duanziwang.com/category/duanzi/page/3'
//        -H 'Cookie: Hm_lvt_805519f49ee7b4e242d02aeb20885385=1509332532; Hm_lpvt_805519f49ee7b4e242d02aeb20885385=1509332831'
//        -H 'Accept-Encoding: gzip, deflate, sdch'
//        -H 'Accept-Language: zh-CN,zh;q=0.8'
//        -H 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2926.0 Safari/537.36'
//        -H 'Proxy-Authorization: Basic emh1aWZlbmc3NDA2NDM3ODc6Z29uZ3lpZG9uZw=='
//        -H 'Accept: text/html, */*; q=0.01'
//        -H 'Referer: http://duanziwang.com/category/duanzi'
//        -H 'X-Requested-With: XMLHttpRequest'
//        -H 'Proxy-Connection: keep-alive'
//        --compressed
        $options = [
            CURLOPT_HTTPHEADER => [
                'Cookie: Hm_lvt_805519f49ee7b4e242d02aeb20885385=1509332532; Hm_lpvt_805519f49ee7b4e242d02aeb20885385=1509332831',
                'Accept-Encoding: gzip, deflate, sdch',
                'Accept-Language: zh-CN,zh;q=0.8',
                'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2926.0 Safari/537.36',
                'Proxy-Authorization: Basic emh1aWZlbmc3NDA2NDM3ODc6Z29uZ3lpZG9uZw==',
                'Accept: text/html, */*; q=0.01',
                'Referer: http://duanziwang.com/category/duanzi',
                'X-Requested-With: XMLHttpRequest',
                'Proxy-Connection: keep-alive',
            ],// 设置 HTTP 头字段的数组
            CURLOPT_ENCODING => '', // HTTP请求头中"Accept-Encoding: "的值。 这使得能够解码响应的内容。 支持的编码有"identity"，"deflate"和"gzip"。如果为空字符串""，会发送所有支持的编码类型。
        ];

        return CurlHelper::get($this->_getURL(), $params, $options, false);
    }


}