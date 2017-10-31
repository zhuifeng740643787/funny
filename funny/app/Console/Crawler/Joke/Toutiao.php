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
 * 抓取头条段子
 * Class Joke
 * @package App\Console\Crawler\Toutiao
 */
class Toutiao extends Command
{
    protected $name = 'crawler:joke-toutiao';
    protected $description = '抓取【头条】的段子';

    private $_max_behot_time = 0; //抓取时间
    private $_url = 'http://www.toutiao.com/api/article/feed/?';
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
        if ($this->_max_behot_time == 0) {
            $this->_recrawl_times++;
            return;
        }

        if($this->_duplicate_times < 100) {
            return;
        }
        $this->_duplicate_times = 0;
        $this->_has_more = true;
        $this->_max_behot_time = JokeModel::getLastOnlineTime();
    }

    // 处理抓取的数据
    private function _handleResult($result)
    {
        if (!$result || $result['message'] != 'success') {
            $this->_error_times++;
            return;
        }

        // 存入
        $this->_has_more = $result['has_more'];
        $this->_max_behot_time = $result['next']['max_behot_time'];
        foreach ($result['data'] as $key => $data) {
            $item = $data['group'];
            $user = $item['user'];

            $saved = JokeModel::add([
                'source_type' => JokeModel::SOURCE_TYPE_TOUTIAO,
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
                'source_type' => User::SOURCE_TYPE_TOUTIAO,
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
            'category' => 'essay_joke',
            'utm_source' => 'toutiao',
            'widen' => '1',
            'max_behot_time' => $this->_max_behot_time,
            'max_behot_time_tmp' => $this->_max_behot_time,
            'tadrequire' => 'true',
            'as' => 'A1A569DF525CF4E',
            'cp' => '59F2BC5FF42E5E1',
        ];

        // curl 'http://www.toutiao.com/api/article/feed/?category=essay_joke&utm_source=toutiao&widen=1&max_behot_time=0&max_behot_time_tmp=0&tadrequire=true&as=A1A569DF525CF4E&cp=59F2BC5FF42E5E1'
        // -H 'Pragma: no-cache'
        // -H 'Accept-Encoding: gzip, deflate, sdch'
        // -H 'Accept-Language: zh-CN,zh;q=0.8'
        // -H 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2926.0 Safari/537.36'
        // -H 'Content-Type: application/x-www-form-urlencoded'
        // -H 'Accept: text/javascript, text/html, application/xml, text/xml, */*'
        // -H 'Cache-Control: no-cache' -H 'X-Requested-With: XMLHttpRequest'
        // -H 'Cookie: UM_distinctid=15c38d316528d2-045ed2e0733282-63360c7f-232800-15c38d31653934; uuid="w:6d3f93b40e3e4062909a601ed93865fa"; _ga=GA1.2.316909076.1495601977; csrftoken=d743fee59a1375af4265e86c236f2cd6; tt_webid=6423561578725377537; WEATHER_CITY=%E5%8C%97%E4%BA%AC; CNZZDATA1259612802=22452934-1495601301-%7C1509083074; utm_source=toutiao; __tasessionId=1lyj4lki91509084975323; tt_webid=6423561578725377537'
        // -H 'Connection: keep-alive'
        // -H 'Referer: http://www.toutiao.com/ch/essay_joke/'
        // --compressed
        $options = [
            CURLOPT_HTTPHEADER => [
                'Pragma: no-cache',
                'Accept-Encoding: gzip, deflate, sdch',
                'Accept-Language: zh-CN,zh;q=0.8',
                'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2926.0 Safari/537.36',
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: text/javascript, text/html, application/xml, text/xml, */*',
                'Cache-Control: no-cache',
                'X-Requested-With: XMLHttpRequest',
                'Cookie: UM_distinctid=15c38d316528d2-045ed2e0733282-63360c7f-232800-15c38d31653934; uuid="w:6d3f93b40e3e4062909a601ed93865fa"; _ga=GA1.2.316909076.1495601977; csrftoken=d743fee59a1375af4265e86c236f2cd6; tt_webid=6423561578725377537; WEATHER_CITY=%E5%8C%97%E4%BA%AC; CNZZDATA1259612802=22452934-1495601301-%7C1509083074; utm_source=toutiao; __tasessionId=1lyj4lki91509084975323; tt_webid=6423561578725377537',
                'Connection: keep-alive',
                'Referer: http://www.toutiao.com/ch/essay_joke/',
            ],// 设置 HTTP 头字段的数组
            CURLOPT_ENCODING => '', // HTTP请求头中"Accept-Encoding: "的值。 这使得能够解码响应的内容。 支持的编码有"identity"，"deflate"和"gzip"。如果为空字符串""，会发送所有支持的编码类型。
        ];

        return CurlHelper::get($this->_url, $params, $options);
    }


}