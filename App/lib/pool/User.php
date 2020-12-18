<?php


namespace App\lib\pool;


use App\Utility\Log\Log;
use App\Pool\RedisPool;
use EasySwoole\Component\Singleton;
use EasySwoole\Redis\Redis as Redis;

class User extends RedisPool
{

    /**
     * 用户信息redis lib,用户储存一些非必要数据
     */
    use Singleton;

    const USER_FOLLOWS = "user_follows:uid:%s";  //用户关注列表
    const USER_FANS = 'user_fans:uid:%s'; //用户粉丝列表


    const USER_MESS = 'user_messageCount:uid:%s'; //哈希表 用来存放用户消息的数量
    const USER_MESS_TYPE_COUNT = 'user_message:type_%s';  //type=4的消息的未读数

    const USER_MESS_TYPE_TABLE = 'user_message_number:uid:%s';  //用与存各类消息数量的哈希表

    const USER_INTEREST_MATCH = 'user_insterest_match:match_id:%s';  //关注此场比赛的用户
    const USER_INTEREST_MATCHES = 'user_interest_matches';
    const USER_BLACK_LIST = 'user_black_list:%s';

    /**
     * 关注用户
     * @param $key
     * @param $val
     * @return mixed
     */
    public function addUserFollows($key, $val)
    {
        return $this->sadd($key, $val);
    }

    public function addFans($key, $val) {
        return $this->sadd($key, $val);
    }

    public function delUserFollows($key, $val)
    {
        return $this->srem($key, $val);
    }

    public function delFans($key, $val) {
        return $this->srem($key, $val);
    }
    /**
     * 关注列表
     * @param $key
     * @return string
     */
    public function getUserFollowings($key)
    {
        return $this->smembers($key);
    }


    public function getCount($key)
    {
        return $this->scard($key);
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
     * 是否关注
     * @param $uid
     * @param $followId
     * @return mixed
     */
    public function isFollow($uid, $followId) {
        if (!$uid) {
            return false;
        }
        $mFollowings = $this->getUserFollowings(sprintf(self::USER_FOLLOWS, $uid));
        if (in_array($followId, $mFollowings)) {
            return true;
        } else {
            return false;
        }

    }

    /**
     * 我的粉丝数
     * @param $uid
     * @return mixed
     */
    public function myFansCount($uid)
    {
        return $this->getCount(sprintf(self::USER_FANS, $uid));
    }

    /**
     * 我的关注数
     * @param $uid
     * @return mixed
     */
    public function myFollowings($uid)
    {
        return $this->getCount(sprintf(self::USER_FOLLOWS, $uid));
    }


    /**
     * 增加一条未读消息
     * @param $type
     * @param $uid
     * @return bool
     */
    public  function userMessageAddUnread($type, $uid) {

        return $this->hIncrBy(sprintf(self::USER_MESS_TYPE_TABLE, $uid), sprintf(self::USER_MESS_TYPE_COUNT, $type), 1);

    }


    /**
     * 进入消息中心 置空未读消息
     * @param $type
     * @param $uid
     * @return mixed
     */
    public function deleteTypeUnreadCount($type, $uid) {
        return $this->hSet(sprintf(self::USER_MESS_TYPE_TABLE, $uid), sprintf(self::USER_MESS_TYPE_COUNT, $type), 0);
    }


    /**
     * 用户消息的信息
     * @param $uid
     * @return mixed
     */
    public function userMessageCountInfo($uid) {
        return $this->hVals(sprintf(self::USER_MESS_TYPE_TABLE, $uid));
    }

    public function userUnReadTypes($uid) {
        return $this->hGetAll(sprintf(self::USER_MESS_TYPE_TABLE, $uid));
    }
}