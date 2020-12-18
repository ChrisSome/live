<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminSystemAnnoucement extends BaseModel
{
    protected $tableName = "admin_system_annoucement";


    const STATUS_NORMAL = 1;
    const STATUS_DEL = 2;
    public function findAll($page, $limit, $where = [])
    {
        return $this->orderBy('created_at', 'ASC')
            ->limit(($page - 1) * $limit, $limit)
            ->all();
    }

    public function getLimit($page, $limit) {
        return $this->orderBy('created_at', 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->withTotalCount();
    }
    public function saveIdData($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }

}
