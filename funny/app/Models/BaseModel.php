<?php
/**
 * Created by PhpStorm.
 * User: gongyidong
 * Date: 2017/10/27
 * Time: 下午2:12
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Mockery\Exception;

class BaseModel extends Model
{

    //来源 1=头条 2=内涵段子 3=百事不得姐 4=段子网 5=微博段子 6=中文幽默王
    const SOURCE_TYPE_TOUTIAO = 1;// 头条
    const SOURCE_TYPE_NHDZ = 2;// 头条
    const SOURCE_TYPE_BSBDJ = 3;// 百思不得姐
    const SOURCE_TYPE_DZW = 4;// 段子网
    const SOURCE_TYPE_WBDZ = 5;// 微博段子
    const SOURCE_TYPE_ZWYMW = 6;// 中文幽默王

    public static function beforeAdd($params)
    {
        return true;
    }

    final public static function add($params)
    {
        if (!static::beforeAdd($params)) {
            return false;
        }
        $model = new static();
        foreach ($params as $k => $v) {
            $model->$k = $v;
        }
        try {
            $saved = $model->save();
        } catch (Exception $e) {
            Log::error('save error: ' . $e->getMessage());
            return false;
        }

        return $model;
    }


}