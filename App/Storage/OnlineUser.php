<?php

namespace App\Storage;

use App\Model\AdminUser;
use App\Utility\Log\Log;
use EasySwoole\Component\Singleton;
use EasySwoole\Component\TableManager;
use Swoole\Table;

/**
 * 在线用户
 * Class OnlineUser
 * @package App\Storage
 */
class OnlineUser
{
    use Singleton;
    protected $table;  // 储存用户信息的Table

    const INDEX_TYPE_ROOM_ID = 1;
    const INDEX_TYPE_ACTOR_ID = 2;
    const LIST_ONLINE = 'match:online:user:%s';
    const LIST_USERS_IN_ROOM = 'users_in_room_%s_user_id_%s'; //房间内的用户
    public function __construct()
    {
        TableManager::getInstance()->add('onlineUsers', [
            'fd' => ['type' => Table::TYPE_INT, 'size' => 32],
            'nickname' => ['type' => Table::TYPE_STRING, 'size' => 128], //昵称
            'last_heartbeat' => ['type' => Table::TYPE_STRING, 'size' => 16], //最后心跳
            'match_id' => ['type' => Table::TYPE_INT, 'size' => 8], //比赛id
            'user_id' => ['type' => Table::TYPE_INT, 'size' => 8], //用户id
            'level' => ['type' => Table::TYPE_INT, 'size' => 8], //用户级别
        ]);

        $this->table = TableManager::getInstance()->get('onlineUsers');
    }

    /**
     * 设置一条用户信息
     * @param $fd
     * @param $mid
     * @param $info
     * @return mixed
     */
    function set($fd, $info)
    {
        if ($info['user_id']) {
            $user = AdminUser::getInstance()->where('id', $info['user_id'])->get();
            $user_level = $user->level;
        } else {
            $user_level = 0;
        }
        return $this->table->set($fd, [
            'fd' => $fd,
//            'mid' => $info['mid'],
            'nickname' => $info['nickname'],
            'user_id' => (int)$info['user_id'],
            'level' => (int)$user_level,
            'last_heartbeat' => time(),
            'match_id' => !empty($info['match_id']) ? (int)$info['match_id'] : 0
        ]);
    }

    /**
     * 获取一条用户信息
     * @param $fd
     * @return array|mixed|null
     */
    function get($fd)
    {

        $info = $this->table->get($fd);
        return is_array($info) ? $info : null;
    }

    /**
     * 更新一条用户信息
     * @param $fd
     * @param $data
     */
    function update($fd, $data)
    {
        if ($info = $this->get($fd)) {
            $info = $data + $info;
            $this->table->set($fd, $info);
        }
    }




    /**
     * 删除一条用户信息
     * @param $fd
     */
    function delete($fd)
    {
        $info = $this->get($fd);
        if ($info) {
            return $this->table->del($fd);
        }

        return false;
    }

    /**
     * 心跳检查
     * @param int $ttl
     */
    function heartbeatCheck($ttl = 60)
    {
        foreach ($this->table as $item) {
            $time = $item['last_heartbeat'];
            if (($time + $ttl) < time()) {
                $this->table->del($item['fd']);
            }
        }
    }

    /**
     * 心跳更新
     * @param $fd
     */
    function updateHeartbeat($fd)
    {
        $this->update($fd, [
            'last_heartbeat' => time()
        ]);
    }

    /**
     * 直接获取当前的表所有数据
     * @return Table|null
     */
    function table()
    {
        return $this->table;
    }


}