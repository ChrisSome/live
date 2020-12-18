<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminUserFeedBack extends BaseModel
{

    const STATUS_NORMAL = 1;
    const STATUS_DEL = 2;
    protected $tableName = "admin_user_feedback";

    public function findAll($page, $limit)
    {
        return $this->where('status', self::STATUS_NORMAL)->order('created_at', 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->all();
    }

    public function saveIdData($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }

    public static function findLike($col, $like)
    {
        return $col .  "like '%" . $like . "%'";
    }
}