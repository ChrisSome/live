<?php
namespace App\Model;

use App\Base\BaseModel;
class AdminUserFoulCenter extends BaseModel
{
    protected $tableName = "admin_user_foul_center";

    public function getOperate()
    {
        return $this->hasOne(AdminUserOperate::class, null, 'operate_id', 'id');
    }

    public function getPostTitle()
    {
        return $this->hasOne(AdminUserPost::class, null, 'item_id', 'id')->field(['title']);

    }


    public function getPostComment()
    {
        return $this->hasOne(AdminPostComment::class, null, 'item_id', 'id')->field(['content']);

    }

    public function getInformationCommentContent()
    {
        return $this->hasOne(AdminInformationComment::class, null, 'item_id', 'id')->field(['content']);

    }

    public function getChatMessageContent()
    {
        return $this->hasOne(ChatHistory::class, null, 'item_id', 'id')->field(['content']);

    }
}