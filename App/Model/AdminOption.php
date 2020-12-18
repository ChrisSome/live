<?php

namespace App\Model;

use App\Base\BaseModel;
use App\lib\Tool;

class AdminOption extends BaseModel
{
    protected $tableName = "admin_user_options";


    public function findAll($page, $limit)
    {
        return $this->order('created_at', 'DESC')
            ->order('status', 'ASC')
            ->limit(($page - 1) * $limit, $limit)
            ->all();
    }


    public function saveIdData($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }


    /**
     * 通过微信token以及openid获取用户信息
     * @param $access_token
     * @param $openId
     * @return bool|string
     */
    public function getWxUser($access_token , $openId)
    {
        $url = sprintf("https://api.weixin.qq.com/cgi-bin/user/info?access_token=%s&openid=%s&lang=zh_CN", $access_token, $openId);

        return Tool::getInstance()->postApi($url);
    }
}
