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
                $online->heartbeatCheck();
            });

        }

    }

}