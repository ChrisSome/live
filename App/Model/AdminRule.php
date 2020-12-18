<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminRule extends BaseModel
{
    protected $tableName = "admin_rule";

    public function findAll($page, $limit)
    {
        return $this->orderBy('created_at', 'ASC')
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

        return $this->where('id', $ids, 'in')->where('status', 1)->field('node');
    }
}
