<?php
/**
 * Created by PhpStorm.
 * User: evalor
 * Date: 2018-12-02
 * Time: 01:19
 */

namespace App\WebSocket\Controller;

use App\lib\Tool;
use App\Storage\OnlineUser;
use App\Task\BroadcastTask;
use App\Utility\Log\Log;
use App\WebSocket\WebSocketStatus;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Socket\Client\WebSocket as WebSocketClient;

class Broadcast extends Base
{

    public static $type = ['text' => 0, 'img' => 1];



    /**
     * 发送消息给房间内的所有人
     * @throws \Exception
     */
    function roomBroadcast()
    {
        /** @var WebSocketClient $client */
        $client = $this->caller()->getClient();
        $broadcastPayload = $this->caller()->getArgs();
        if (isset(self::$type[$broadcastPayload['content']])) {
            $type = self::$type[$broadcastPayload['content']];
        } else {
            $type = 1;
        }
        $fd = $client->getFd();
        $server = ServerManager::getInstance()->getSwooleServer();
        if (!$sender_user = OnlineUser::getInstance()->get($client->getFd())) {
            return $server->push($fd, $tool = Tool::getInstance()->writeJson(WebSocketStatus::STATUS_CONNECTION_FAIL, WebSocketStatus::$msg[WebSocketStatus::STATUS_CONNECTION_FAIL]));

        }
        if (!$sender_user['user_id']) {
            return $server->push($fd, $tool = Tool::getInstance()->writeJson(WebSocketStatus::STATUS_NOT_LOGIN, WebSocketStatus::$msg[WebSocketStatus::STATUS_NOT_LOGIN]));
        }
        if (!empty($broadcastPayload) && !empty($broadcastPayload['content']) && !empty($broadcastPayload['match_id'])) {
            $message = [
                'fromUserId' => $sender_user['user_id'],
                'fromUserFd' => $client->getFd(),
                'content' => base64_encode(addslashes($broadcastPayload['content'])),
                'type' => $type,
                'sendTime' => date('Y-m-d H:i:s'),
                'matchId' => $broadcastPayload['match_id'],
                'atUserId' => $broadcastPayload['at_user_id'],
            ];
            TaskManager::getInstance()->async(new BroadcastTask(['payload' => $message, 'fromFd' => $client->getFd()]));
        }
        $this->response()->setStatus($this->response()::STATUS_OK);
    }



}