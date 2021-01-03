<?php

namespace App\WebSocket;

use App\lib\pool\Login as Base;
use App\lib\Tool;
use App\Model\AdminMatchTlive;
use App\Model\AdminUser;
use App\Model\ChatHistory;
use App\Storage\OnlineUser;
use App\Utility\Log\Log;
use App\WebSocket\Actions\Broadcast\BroadcastAdmin;
use App\WebSocket\Actions\User\UserOutRoom;
use easySwoole\Cache\Cache;
use EasySwoole\ORM\DbManager;
use EasySwoole\Utility\Random;
use \swoole_server;
use \swoole_websocket_server;
use \swoole_http_request;
use EasySwoole\EasySwoole\ServerManager;
use \Exception;

////////////////////////////////////////////////////////////////////
//                          _ooOoo_                               //
//                         o8888888o                              //
//                         88" . "88                              //
//                         (| ^_^ |)                              //
//                         O\  =  /O                              //
//                      ____/`---'\____                           //
//                    .'  \\|     |//  `.                         //
//                   /  \\|||  :  |||//  \                        //
//                  /  _||||| -:- |||||-  \                       //
//                  |   | \\\  -  /// |   |                       //
//                  | \_|  ''\---/''  |   |                       //
//                  \  .-\__  `-`  ___/-. /                       //
//                ___`. .'  /--.--\  `. . ___                     //
//            \  \ `-.   \_ __\ /__ _/   .-` /  /                 //
//      ========`-.____`-.___\_____/___.-`____.-'========         //
//                           `=---='                              //
//      ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^        //
//         佛祖保佑       永无BUG       永不修改                     //
////////////////////////////////////////////////////////////////////
/**
 * WebSocket Events
 * Class WebSocketEvents
 * @package App\WebSocket
 */
class WebSocketEvents
{
    static function onWorkerStart()
    {

    }

    /**
     * @param swoole_websocket_server $server
     * @param swoole_http_request $request
     * @return bool
     */
    static function onOpen(\swoole_websocket_server $server, \swoole_http_request $request)
    {
        $fd = $request->fd;
        $user_online = OnlineUser::getInstance()->get($fd);
        //这里也可以做一个唯一标志 考虑以后有用
        $mid = uniqid($fd . '-');
        $user_id = $request->get['user_id'];
        $match_id = isset($request->get['match_id']) ? $request->get['match_id'] : 0;
        if ($user_id) {
            $user = DbManager::getInstance()->invoke(function ($client) use ($user_id) {
                $userModel = AdminUser::invoke($client)->find($user_id);
                return $userModel;
            });


        }
        //如果已经有设备登陆,则强制退出, 根据后台配置是否允许多终端登陆

        $info = [
            'fd' => $fd,
            'nickname' => isset($user) ? $user->nickname : '',
            'match_id' => $match_id,
            'user_id' => isset($user) ? $user->id : 0,
            'level' => isset($user) ? $user->level : 0,
        ];
        if (!$user_online) {
            OnlineUser::getInstance()->set($fd, $info);
        } else {
            OnlineUser::getInstance()->update($fd, $info);
        }

        $resp_info = [
            'event' => 'connection-succ',
            'info' => ['fd' => $fd, 'mid' => $mid],

        ];

        $server->push($fd, Tool::getInstance()->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $resp_info));

    }

    /**
     * 链接被关闭时
     * @param swoole_server $server
     * @param int $fd
     * @param int $reactorId
     * @throws Exception
     */
    static function onClose(\swoole_server $server, int $fd, int $reactorId)
    {
//        Log::getInstance()->info('fd was closed-' . $fd);
        OnlineUser::getInstance()->delete($fd);
        ServerManager::getInstance()->getSwooleServer()->close($fd);

    }
}
