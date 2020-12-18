<?php

namespace App\Model;

use App\Base\BaseModel;
use App\lib\Tool;

class AdminUserPostsCategory extends BaseModel
{
    protected $tableName = "admin_user_posts_category";
    const STATUS_NORMAL     = 1;        //用户发布成功/审核处理中

    const IS_TOP = 1; //置顶


    public function saveIdData($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }

    /**
     * 转换where or条件
     * @param string $col
     * @param array $where
     * @return string
     */
    public function getWhereArray(string $col, array $where)
    {
        if (!$where) return '';
        foreach ($where as $v) {
            $col .= ('=' . $v . ' or ');
        }
        return '(' . rtrim($col, 'or ') . ')';
    }


    protected function getColorAttr($value, $data)
    {
        return json_decode($data['color'], true);

    }

    protected function getDisposeAttr($value, $data)
    {
        return json_decode($data['dispose'], true);
    }
}
