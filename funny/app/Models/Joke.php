<?php
/**
 * Created by PhpStorm.
 * User: gongyidong
 * Date: 2017/10/27
 * Time: 下午2:11
 */

namespace App\Models;

class Joke extends BaseModel
{

    protected $table = 'joke';

    public static function beforeAdd($params)
    {
        // 头条与内涵段子很多内容相同
        if (in_array($params['source_type'], [self::SOURCE_TYPE_TOUTIAO, self::SOURCE_TYPE_NHDZ])) {
            return !self::whereIn('source_type', [self::SOURCE_TYPE_TOUTIAO, self::SOURCE_TYPE_NHDZ])->where('source_id', $params['source_id'])->first(['id']);
        }

        return !self::where('source_type', $params['source_type'])->where('source_id', $params['source_id'])->first(['id']);
    }

    // 获取最后的online_time
    public static function getLastOnlineTime()
    {
        $data = self::whereIn('source_type', [self::SOURCE_TYPE_TOUTIAO, self::SOURCE_TYPE_NHDZ])->orderBy('online_time', 'asc')->first(['online_time']);
        return $data ? $data->online_time : 0;
    }


    // 添加或更新
    public static function updateOrAdd($params)
    {
        $model = self::where('source_type', $params['source_type'])->where('content', $params['content'])->first();
        if (!$model) {
            return self::add($params);
        }

        foreach ($params as $k => $v) {
            $model->$k = $v;
        }

        if ($model->save()) {
            return $model;
        }
        return false;
    }

}