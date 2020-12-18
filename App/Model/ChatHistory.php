<?php

namespace App\Model;

use App\Base\BaseModel;

class ChatHistory extends BaseModel
{
    protected $tableName = "admin_messages";


    public function getSenderNickname()
    {
        return $this->hasOne(AdminUser::class, null, 'sender_user_id', 'id');
    }

    public function getAtNickname()
    {
        return $this->hasOne(AdminUser::class, null, 'at_user_id', 'id');

    }


    protected function getContentAttr($value, $data)
    {
        return base64_decode($value);
    }
}
