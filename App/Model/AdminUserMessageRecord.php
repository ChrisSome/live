<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminUserMessageRecord extends BaseModel
{
    protected $tableName = "admin_user_read_records";

    public function findAll($page, $limit, $where = [])
    {
        return $this->order('created_at', 'ASC')
            ->limit(($page - 1) * $limit, $limit)
            ->all();
    }


    public function saveIdData($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }
}
