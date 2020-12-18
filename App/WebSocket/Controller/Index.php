<?php
/**
 * Created by PhpStorm.
 * User: evalor
 * Date: 2018-12-02
 * Time: 01:19
 */

namespace App\WebSocket\Controller;

use App\Storage\OnlineUser;
use App\WebSocket\Actions\User\UserInfo;
use App\WebSocket\Actions\User\UserOnline;
use Exception;

class Index extends Base
{
    /**
     * 当前用户信息
     * @throws Exception
     */
    function info()
    {
        $params = $this->caller()->getArgs();
        $info = $this->currentUser($params['fd']);
        if ($info) {
            $message = new UserInfo;
            $message->setUserFd($info['fd']);
            $message->setAvatar($info['avatar']);
            $message->setUsername($info['username']);
            $message->setUserId($info['user_id']);
            $this->response()->setMessage($message);
        }
    }

    /**
     * 在线用户列表
     * @throws Exception
     */
    function online()
    {
        //根据群组id
        //上线时记录id
        $table = OnlineUser::getInstance()->table();
        $users = array();

        foreach ($table as $user) {
            $users['user' . $user['fd']] = $user;
        }

        if (!empty($users)) {
            $message = new UserOnline;
            $message->setList($users);
            $this->response()->setMessage($message);
        }
    }

    function heartbeat()
    {
        $client = $this->caller()->getClient();
        $fd = $client->getFd();
        //更新
        OnlineUser::getInstance()->updateHeartbeat($fd);
        $this->response()->setMessage('PONG');
    }
}