<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminMessage extends BaseModel
{
    protected $tableName = "admin_system_message_lists";


    const STATUS_UNREAD = 0;
    const STATUS_READ = 1;
    const STATUS_DEL = 2;


    const TYPE_NOTICE = 1;
    public function findAll($page, $limit, $where = [])
    {
        return $this->orderBy('created_at', 'ASC')
            ->limit(($page - 1) * $limit, $limit)
            ->all();
    }

    public function getLimit($page, $limit) {
        return $this->order('created_at', 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->withTotalCount();
    }
    public function saveIdData($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }




}
