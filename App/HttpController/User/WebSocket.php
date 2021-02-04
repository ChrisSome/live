<?php


namespace App\HttpController\User;


use App\Base\FrontUserController;
use App\Common\AppFunc;
use App\lib\Tool;
use App\Utility\Log\Log;
use App\Utility\Message\Status;
use App\WebSocket\WebSocketStatus;
use EasySwoole\EasySwoole\ServerManager;
use \Swoole\Coroutine\Http\Client;

class WebSocket extends FrontUserController
{

    public $needCheckToken = false;
    protected $isCheckSign = false;

    protected $uriM = 'https://open.sportnanoapi.com/api/v4/football/match/diary?user=%s&secret=%s&date=%s';
    protected $user = 'mark9527';
    protected $secret = 'dbfe8d40baa7374d54596ea513d8da96';

    public function index()
    {
        $this->render('front.websocket.index', [
//            'server' => 'ws://192.168.254.103:9504'
            'server' => 'ws://8.210.195.192:9504'
        ]);
    }


    function callback($instance, $channelName, $message) {
        $info = json_encode([$channelName, $message]);
        Log::getInstance()->info($info);
    }

    public function contentPush($diff, $match_id)
    {
        $fd_arr = AppFunc::getUsersInRoom($match_id);
        if (!$fd_arr) {
            return;
        }
        $tool = Tool::getInstance();
        $server = ServerManager::getInstance()->getSwooleServer();
        $returnData = [
            'event' => 'match_tlive',
            'match_id' => $match_id,
            'content' => $diff
        ];
        foreach ($fd_arr as $fd) {
            $connection = $server->connection_info($fd);
            if (is_array($connection) && $connection['websocket_status'] == 3) {  // 用户正常在线时可以进行消息推送
                Log::getInstance()->info('tlive content-' . json_encode($diff) . '-fd-' . $fd . '-match_id-' . $match_id);
                $server->push($fd, $tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $returnData));
            }
        }
    }

    /**
     * 异常比赛的提示
     * @param $match_id
     * @param $status
     * @param $type 1足球 2篮球
     */
    public function noticeException($match_id, $status, $type)
    {
        $fd_arr = AppFunc::getUsersInRoom($match_id, 2);
        if (!$fd_arr) {
            return;
        }
        $tool = Tool::getInstance();
        $server = ServerManager::getInstance()->getSwooleServer();
        $returnData = [
            'event' => 'exception-match-notice',
            'match_id' => $match_id,
            'status' => $status,
            'type' => $type
        ];
        foreach ($fd_arr as $fd) {
            $connection = $server->connection_info($fd);
            if (is_array($connection) && $connection['websocket_status'] == 3) {  // 用户正常在线时可以进行消息推送
                Log::getInstance()->info('exception-match-' . json_encode($returnData));
                $server->push($fd, $tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $returnData));
            }
        }
    }

    public function basketballContentPush($diff, $match_id, $status_id)
    {
        $fd_arr = AppFunc::getUsersInRoom($match_id, 2);
        if (!$fd_arr) {
            return;
        }
        $tool = Tool::getInstance();
        $server = ServerManager::getInstance()->getSwooleServer();
        $returnData = [
            'event' => 'basketball_match_tlive',
            'match_id' => $match_id,
            'content' => $diff,
            'status_id' => $status_id
        ];
        foreach ($fd_arr as $fd) {
            $connection = $server->connection_info($fd);
            if (is_array($connection) && $connection['websocket_status'] == 3) {  // 用户正常在线时可以进行消息推送
                Log::getInstance()->info('basketball tlive content-' . json_encode($diff) . '-fd-' . $fd . '-match_id-' . $match_id);
                $server->push($fd, $tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $returnData));
            }
        }
    }

}