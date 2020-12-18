<?php

namespace App\Task;

use App\Common\AppFunc;
use App\GeTui\BatchSignalPush;
use App\lib\Tool;
use App\Model\AdminMatch;
use App\Model\AdminMatchTlive;
use App\Model\AdminNoticeMatch;
use App\Model\AdminUser;
use App\Storage\OnlineUser;
use App\Utility\Log\Log;
use App\WebSocket\WebSocketStatus;
use easySwoole\Cache\Cache;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class MatchUpdate  implements TaskInterface
{
    protected $taskData;

    public function __construct($taskData)
    {
        Log::getInstance()->info('111');
        $this->taskData = $taskData;
    }

    public function run(int $taskId, int $workerIndex)
    {
        $match_info_list = $this->taskData['match_info_list'];
        if (!$match_info_list) {
            Log::getInstance()->info('match_info_list_empty');
            return;
        } else {
            Log::getInstance()->info('match_info_list_not_empty');

        }

        $tool = Tool::getInstance();
        $server = ServerManager::getInstance()->getSwooleServer();
        $start_fd = 0;
        $returnData = [
            'event' => 'match_update',
            'match_info_list' => $match_info_list
        ];
        while (true) {
            $conn_list = $server->getClientList($start_fd, 10);
            if (!$conn_list || count($conn_list) === 0) {
                break;
            }
            $start_fd = end($conn_list);

            foreach ($conn_list as $fd) {
                $connection = $server->connection_info($fd);
                if (is_array($connection) && $connection['websocket_status'] == 3) {  // 用户正常在线时可以进行消息推送
                    Log::getInstance()->info('push succ' . $fd);
                    $server->push($fd, $tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $returnData));
                } else {
                    Log::getInstance()->info('lost-connection-' . $fd);
                }
            }
        }


    }



    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
        Log::getInstance()->info('error_info' . json_encode($throwable));
    }
}