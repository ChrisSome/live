<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminPlay extends BaseModel
{
    protected $tableName = "admin_play";

    public function findAll($page, $limit)
    {
        return $this->order('created_at', 'desc')
            ->limit(($page - 1) * $limit, $limit)
            ->all();
    }


    public function saveIdData($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }
}
