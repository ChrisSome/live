<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/14 0014
 * Time: 下午 17:27
 */

namespace App\WebSocket\event;


use App\Storage\OnlineUser;
use EasySwoole\Component\Timer;
use EasySwoole\EasySwoole\ServerManager;

class OnWorkStart
{
    public function onWorkerStart(\swoole_server $server ,$workerId)
    {
        $timer = Timer::getInstance();
        if($workerId == 1)
        {
            //1分钟轮询
            $timer->loop(60 * 1000, function (){
                $online = OnlineUser::getInstance();
                $server = ServerManager::getInstance()->getSwooleServer();

                foreach ($online->table() as $fd => $info) {
                    if (!isset($info['fd'])) continue;
                    $connection = $server->connection_info($info['fd']);
                    $time = $info['last_heartbeat'];
                    if (!is_array($connection) || $connection['websocket_status'] != 3 || $time + 60 < time()) {
                        //删除
                        $online->delete($fd);
                    }
                }
            });

        }

    }

}