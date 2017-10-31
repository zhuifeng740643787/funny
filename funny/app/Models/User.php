<?php
/**
 * Created by PhpStorm.
 * User: gongyidong
 * Date: 2017/10/27
 * Time: 下午2:11
 */

namespace App\Models;

class User extends BaseModel
{

    protected $table = 'user';

    public static function beforeAdd($params)
    {
        // 头条与内涵段子很多内容相同
        if(in_array($params['source_type'], [self::SOURCE_TYPE_TOUTIAO, self::SOURCE_TYPE_NHDZ])) {
            return !self::whereIn('source_type', [self::SOURCE_TYPE_TOUTIAO, self::SOURCE_TYPE_NHDZ])->where('source_id', $params['source_id'])->first(['id']);
        }

        return !self::where('source_type', $params['source_type'])->where('source_id', $params['source_id'])->first(['id']);
    }


}