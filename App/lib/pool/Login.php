<?php


namespace App\lib\pool;


use App\lib\Tool;
use App\Utility\Log\Log;
use App\Utility\Pool\RedisPool;
use EasySwoole\Component\Singleton;

class Login extends RedisPool
{
    use Singleton;

    const LOGIN_IP_ERROR_KEY = 'login:error:ip:%s';
    const LOGIN_EMAIL_ERROR_KEY = 'login:error:email:%s';
    const ONLINE_USER_QUEUES = 'online:user';
    const USERS_IN_ROOM = 'users_in_room:%s'; //该房间下的用户  roomid

    /**
     * 坚决不重复
     * @return string
     */
    public function getMid()
    {
        $mid = Tool::getInstance()->makeRandomString(15);
        while (in_array($mid, $this->lrange(self::ONLINE_USER_QUEUES, 0, -1))) {
            $mid = Tool::getInstance()->makeRandomString(15);
        }
        return $mid;
    }


    public function getUserKey($id, $mid)
    {
        return sprintf('hash:user:%s:%s', $id, $mid);
    }


    public function getUser($uid, $mid)
    {
        return $this->hgetall(sprintf('hash:user:%s:%s', $uid, $mid));
    }

    /**
     * 更新token
     * @param $uid
     * @param $mid
     * @return mixed
     */
    public function updateToken($uid, $mid)
    {
        return $this->expires(sprintf('hash:user:%s:%s', $uid, $mid), 7200);
    }

    /**
     * 房间记录用户
     * @param $roomId
     * @param $uInfo
     * @return mixed
     */
    public function userInRoom($roomId, $fd)
    {

        return $this->sadd(sprintf(self::USERS_IN_ROOM, $roomId), $fd);
    }

    /**
     * 获取房间用户
     * @param $roomId
     * @return mixed
     */
    public function getUsersInRoom($roomId) {
        return $this->sMembers(sprintf(self::USERS_IN_ROOM, $roomId));
    }


}