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
 * 抓取内涵段子
 * Class Joke
 * @package App\Console\Crawler\Toutiao
 */
class Neihanduanzi extends Command
{
    protected $name = 'crawler:joke-neihanduanzi';
    protected $description = '抓取【内涵段子】中的段子';

    private $_max_time = 0; //抓取时间
    private $_url = 'http://neihanshequ.com/joke/?';
    private $_error_times = 0;// 请求发送错误的次数
    private $_duplicate_times = 0; // 请求的资源重复的个数
    private $_has_more = true; // 是否还有更多
    private $_recrawl_times = 0;//重新抓取的次数
    private $_sleep_seconds = 1;// 暂停秒数
    private $_new_count = 0;// 新增个数

    public function handle()
    {
        LogHelper::printLog("----------- Job {$this->name} START ----------------");
        while (!$this->_needExit()) {
            $result = $this->_crawl();
            $this->_handleResult($result);
            LogHelper::printLog("error_times=$this->_error_times duplicate_time=$this->_duplicate_times has_more=$this->_has_more recrawl_times=$this->_recrawl_times sleep_seconds={$this->_sleep_seconds}s");
            sleep($this->_sleep_seconds);// 暂停一秒
            $this->_reCrawel();
        }
        LogHelper::printLog("----------- new count={$this->_new_count}----------------");
        LogHelper::printLog("----------- Job {$this->name} END ----------------");
    }

    // 退出脚本的条件
    private function _needExit()
    {
        return $this->_error_times >= 3
            || $this->_duplicate_times >= 200
            || !$this->_has_more
            || $this->_recrawl_times >= 5;
    }

    // 重新抓取时（max_time=0），当重复个数>100 时，跳到最小的max_time处继续抓取
    private function _reCrawel()
    {
        if ($this->_max_time == 0) {
            $this->_recrawl_times++;
            return;
        }

        if ($this->_duplicate_times < 100) {
            return;
        }
        $this->_duplicate_times = 0;
        $this->_has_more = true;
        $this->_max_time = JokeModel::getLastOnlineTime();
        $this->_recrawl_times++;
    }


    // 处理抓取的数据
    private function _handleResult($result)
    {
        if (!$result || $result['message'] != 'success') {
            $this->_error_times++;
            return;
        }
        $result = $result['data'];
        // 存入
        $this->_has_more = $result['has_more'];
        $this->_max_time = $result['max_time'];
        foreach ($result['data'] as $key => $data) {
            $item = $data['group'];
            $user = $item['user'];

            $saved = JokeModel::add([
                'source_type' => JokeModel::SOURCE_TYPE_NHDZ,
                'source_id' => $item['id'],
                'user_id' => $user['user_id'] == -1 ? 0 : $user['user_id'],
                'content' => $item['content'],
                'online_time' => $data['online_time'],
                'create_time' => $item['create_time'],
                'favorite_count' => $item['favorite_count'],
                'digg_count' => $item['digg_count'],
                'bury_count' => $item['bury_count'],
                'share_count' => $item['share_count'],
            ]);

            LogHelper::printLog($key . ': user_id=' . $user['user_id'] . ' joke_id=' . $item['id']);

            if (!$saved) {
                LogHelper::printLog('duplicate id=' . $item['id']);
                $this->_duplicate_times++;
                continue;
            }

            $this->_new_count++;

            if ($user['user_id'] == '-1') {
                continue;
            }

            User::add([
                'source_type' => User::SOURCE_TYPE_NHDZ,
                'source_id' => $user['user_id'],
                'name' => $user['name'],
                'avatar_url' => $user['avatar_url'],
            ]);
        }
    }


    // 请求
    private function _crawl()
    {
        $params = [
            'is_json' => 1,
            'app_name' => 'neihanshequ_web',
            'max_time' => $this->_max_time,
        ];

        // curl 'http://neihanshequ.com/joke/?is_json=1&app_name=neihanshequ_web&max_time=1509340063.81'
        // -H 'Cookie: uuid="w:1ee8297bb0624e7d94274d615ed06465"; tt_webid=6482558426714965518; _gat=1; Hm_lvt_3280fbe8d51d664ffc36594659b91638=1509340044; Hm_lpvt_3280fbe8d51d664ffc36594659b91638=1509340051; csrftoken=dbc9040e034f23e21ba229013e6b37c6; _ga=GA1.2.983265775.1509338253; _gid=GA1.2.2012666702.1509338253' -H 'Accept-Encoding: gzip, deflate, sdch'
        // -H 'Accept-Language: zh-CN,zh;q=0.8' -H 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2926.0 Safari/537.36'
        // -H 'Proxy-Authorization: Basic emh1aWZlbmc3NDA2NDM3ODc6Z29uZ3lpZG9uZw=='
        // -H 'Accept: application/json, text/javascript, */*; q=0.01'
        // -H 'Referer: http://neihanshequ.com/'
        // -H 'X-Requested-With: XMLHttpRequest'
        // -H 'Proxy-Connection: keep-alive'
        // -H 'X-CSRFToken: dbc9040e034f23e21ba229013e6b37c6'
        // --compressed

        $options = [
            CURLOPT_HTTPHEADER => [
                'Cookie: uuid="w:1ee8297bb0624e7d94274d615ed06465"; tt_webid=6482558426714965518; _gat=1; Hm_lvt_3280fbe8d51d664ffc36594659b91638=1509340044; Hm_lpvt_3280fbe8d51d664ffc36594659b91638=1509340051; csrftoken=dbc9040e034f23e21ba229013e6b37c6; _ga=GA1.2.983265775.1509338253; _gid=GA1.2.2012666702.1509338253',
                'Accept-Encoding: gzip, deflate, sdch',
                'Accept-Language: zh-CN,zh;q=0.8',
                'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2926.0 Safari/537.36',
                'Proxy-Authorization: Basic emh1aWZlbmc3NDA2NDM3ODc6Z29uZ3lpZG9uZw==',
                'Accept: application/json, text/javascript, */*; q=0.01',
                'Referer: http://neihanshequ.com/',
                'X-Requested-With: XMLHttpRequest',
                'Proxy-Connection: keep-alive',
                'X-CSRFToken: dbc9040e034f23e21ba229013e6b37c6',
            ],// 设置 HTTP 头字段的数组
            CURLOPT_ENCODING => '', // HTTP请求头中"Accept-Encoding: "的值。 这使得能够解码响应的内容。 支持的编码有"identity"，"deflate"和"gzip"。如果为空字符串""，会发送所有支持的编码类型。
        ];

        return CurlHelper::get($this->_url, $params, $options);
    }


}