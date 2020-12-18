<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/14 0014
 * Time: 下午 17:27
 */

namespace App\WebSocket\event;


use App\Process\KeepUser;
use App\Storage\OnlineUser;
use App\Utility\Log\Log;
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

                foreach ($online->table() as $mid => $info) {
                    if (!isset($info['fd'])) continue;
                    $connection = $server->connection_info($info['fd']);
                    if (!is_array($connection) || $connection['websocket_status'] != 3) {
                        //删除
                        $online->heartbeatCheck($info['fd']);
                    }
                }
            });

        }

    }

}