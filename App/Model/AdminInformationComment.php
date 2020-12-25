<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminInformationComment extends BaseModel
{
    const STATUS_REPORTED = 2;
    const STATUS_NORMAL = 0;
    const STATUS_DELETE = 1;
    const STATUS_ADMIN_DELETE = 3;

    const SHOW_IN_FRONT = [self::STATUS_REPORTED, self::STATUS_NORMAL, self::STATUS_ADMIN_DELETE];
    protected $tableName = "admin_information_comments";


    /**
     * $value mixed 是原值
     * $data  array 是当前model所有的值
     */
//    protected function getContentAttr($value, $data)
//    {
//        return base64_decode($data['content']);
//
//    }
    public function getLimit($page, $limit)
    {
        return $this->order('created_at', 'DESC')->order('id', 'ASC')
            ->limit(($page - 1) * $limit, $limit)
            ->withTotalCount();
    }

    public function getUserInfo()
    {
        return $this->hasOne(AdminUser::class, null, 'user_id', 'id')->field(['id', 'photo', 'nickname', 'level', 'is_offical']);
    }

    public function getTUserInfo()
    {

        if ($user = $this->hasOne(AdminUser::class, null, 't_u_id', 'id')) {
            return $user->field(['id', 'photo', 'nickname', 'level', 'is_offical']);
        } else {
            return [];
        }


    }

    public function getParent()
    {
        return $this->hasOne(AdminUser::class, null, 'id', 'parent_id')->field(['user_id', 'id']);

    }

    public function getParentComment()
    {
        return $this->hasOne(AdminUser::class, null, 'parent_id', 'id');

    }

    public function getInformation()
    {
        return $this->hasOne(AdminInformation::class, null, 'information_id', 'id');

    }

}
