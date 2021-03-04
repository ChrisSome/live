<?php
/**
 * Created by PhpStorm.
 * User: evalor
 * Date: 2018-11-28
 * Time: 20:23
 */

namespace App\Task;

use App\Common\AppFunc;
use App\lib\Tool;
use App\Model\AdminUser;
use App\Model\ChatHistory;
use App\Storage\OnlineUser;
use App\WebSocket\WebSocketStatus;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use App\Utility\Log\Log;

/**
 * 发送广播消息
 * Class BroadcastTask
 * @package App\Task
 */
class BroadcastTask implements TaskInterface
{
    protected $taskData;

    public function __construct($taskData)
    {
        $this->taskData = $taskData;
    }


    /**
     * @param int $taskId
     * @param int $workerIndex
     * @return bool|void
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \EasySwoole\Pool\Exception\PoolEmpty
     * @throws \Throwable
     */
    function run(int $taskId, int $workerIndex) {

        $taskData = $this->taskData;
        $server = ServerManager::getInstance()->getSwooleServer();
        //获取该房间内所有用户
        $aMessage = $taskData['payload'];
        $sportType = (int)$aMessage['sportType'];
        $insertId = DbManager::getInstance()->invoke(function ($client) use ($aMessage) {
            $chatModel = ChatHistory::invoke($client);
            $chatModel->sender_user_id = (int)$aMessage['fromUserId'];
            $chatModel->type = (int)$aMessage['type'];
            $chatModel->match_id = (int)$aMessage['matchId'];
            $chatModel->content = $aMessage['content'];
            $chatModel->at_user_id = (int)$aMessage['atUserId'];
            $chatModel->sport_type = (int)$aMessage['sportType'];

            $data = $chatModel->save();
            return $data;
        });

        if (!$insertId) {
            Log::getInstance()->error('发布聊天失败');
            return ;
        }
        $tool = Tool::getInstance();
        $atUser = [];
        if ($atUserId = $aMessage['atUserId']) {
            $atUser = DbManager::getInstance()->invoke(function ($client) use ($atUserId) {
                $userModel = AdminUser::invoke($client)->field(['nickname', 'level', 'id'])->find($atUserId);
                return $userModel;
            });
        }

        $returnData = [
            'event' => 'broadcast-roomBroadcast',
            'data' => [
                'sender_user_info' => OnlineUser::getInstance()->get($aMessage['fromUserFd']),
                'at_user_info' => $atUser,
                'message_info' => [
                    'id' => $insertId,
                    'content' => base64_decode($aMessage['content'])
                ],
            ],

        ];

        if ($users = AppFunc::getUsersInRoom($aMessage['matchId'], $sportType)) {
            foreach ($users as $user) {
                $connection = $server->connection_info($user);
                if (is_array($connection) && $connection['websocket_status'] == 3) {  // 用户正常在线时可以进行消息推送
                    $server->push($user, $tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $returnData));
                }
            }
        }
        return true;



    }

    function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        throw $throwable;
    }

}