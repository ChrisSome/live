<?php

namespace App\Model;

use App\Base\BaseModel;
use App\Common\AppFunc;
use EasySwoole\EasySwoole\Config;

class AdminAuth extends BaseModel
{
    protected $tableName  = "admin_auth";

    public function findAll($page, $limit)
    {
        $data = $this->where('deleted', 0, '=')
            ->join('admin_role', 'admin_auth.role_id = admin_role.id')
            ->where('admin_auth.deleted', 0, '=')
            ->orderBy('admin_auth.created_at', 'ASC')
            ->limit(($page - 1) * $limit, $limit)
            ->all();

        return $data;
    }

    public function saveIdData($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }

    public function findUname($uname)
    {
        var_dump(self::create()->where('uname', $uname)
            ->where('deleted', 0)
            ->get());
        return self::create()->where('uname', $uname)
            ->where('deleted', 0)
            ->get();
        return $this->where('uname', $uname)
            ->where('deleted', 0)
            ->getOne();
    }

    public function login($uname, $pwd)
    {
        try {
            $data  = $this->findUname($uname);
            return $this->pwdEncry($pwd, $data['encry']) == $data['pwd'] ? $data : false;
        } catch (\Exception $e) {
            var_dump($e->getMessage());
        }

    }

    public function pwdEncry($pwd, $rand)
    {
        $encry = Config::getInstance()->getConf('app.verify');
        return md5($rand . $pwd . $encry);
    }

    // $data = ['uname', 'pwd', 'status', 'display_name', 'role_id']; 必须
    public function add($data)
    {
        $data['encry'] = AppFunc::getRandomStr(6);
        $data['pwd']   = $this->pwdEncry($data['pwd'], $data['encry']);
        return $this->insert($data);
    }

    // $data = ['uname', 'pwd', 'status', 'display_name', 'role_id']; 必须
    public function saveDatas($id, $data)
    {
        $data['encry'] = AppFunc::getRandomStr(6);
        $data['pwd']   = $this->pwdEncry($data['pwd'], $data['encry']);

        return $this->saveIdData($id, $data);
    }

    public function setLoginedTime($id)
    {
        return $this->setValue('logined_at', date('Y-m-d H:i:s'), ['id' => $id]);
    }
}
