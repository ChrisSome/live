<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminCategory extends BaseModel
{
    const CATEGORY_ANNOCEMENT = 2;

    const STATUS_NORMAL = 1;
    protected $tableName = "admin_system_message_category";

    public function findAll($page, $limit)
    {
        return $this->order('created_at', 'ASC')
            ->limit(($page - 1) * $limit, $limit)
            ->all();
    }

    // 查找pid 为 0 的数据
    public function pid0Data()
    {
        return $this->orderBy('created_at', 'ASC')
            ->where('pid', 0, '=')
            ->get(null, "id, name");
    }

    public function saveIdData($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }

    public function getIdsInNode($ids = [])
    {
        return $this->whereIn('id', $ids)->getColumn('pname');
    }
}
