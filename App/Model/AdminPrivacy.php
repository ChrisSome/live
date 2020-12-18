<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminPrivacy extends BaseModel
{
    protected $tableName = "admin_privacy";

    public function findAll($page, $limit)
    {
        return $this->order('created_at', 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->all();
    }

    public function saveIdData($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }
}