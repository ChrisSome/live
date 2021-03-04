<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminUserOperate extends BaseModel
{

    const TYPE_BOOK_MARK = 2;//收藏

    const TYPE_RES_ITEM_DELETE = 1; //删除
    const TYPE_RES_USER_FOBIDDEN = 1;// 禁言
    const TYPE_RES_USER_BAN = 2;// 禁言

    protected $tableName = "admin_user_operates";

    public function getLimit($page, $limit, $sortColumn = 'created_at')
    {
        return $this->order($sortColumn, 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->withTotalCount();
    }

    public function post_info()
    {
        return $this->hasOne(AdminUserPost::class, null, 'item_id', 'id')->field(['id', 'title']);
    }

    public function user_info()
    {
        return $this->hasOne(AdminUser::class, null, 'user_id', 'id')->field(['id', 'nickname', 'photo', 'level', 'is_offical']);
    }


    public function author_info()
    {
        return $this->hasOne(AdminUser::class, null, 'author_id', 'id')->field(['id', 'nickname', 'photo', 'level', 'is_offical']);

    }

    public function comment_info()
    {
        return $this->hasOne(AdminPostComment::class, null, 'item_id', 'id');

    }

    public function information_comment()
    {
        return $this->hasOne(AdminInformationComment::class, null, 'item_id', 'id')->field(['id', 'content', 'title']);

    }

}
