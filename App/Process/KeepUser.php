<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/3/6
 * Time: 下午12:14
 */

namespace App\Process;


use App\Storage\OnlineUser;
use App\Utility\Log\Log;
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Component\Timer;
use EasySwoole\EasySwoole\ServerManager;
use Swoole\Process;
/**
 * Class KeepUser
 * @package App\Process
 * 定时任务，统计哪些不在线的用户删除其缓存
 */
class KeepUser  extends AbstractProcess
{

    public function run($args)
    {
        Timer::getInstance()->loop(300 * 1000, function () {
            $online = OnlineUser::getInstance();
            $server = ServerManager::getInstance()->getSwooleServer();
            foreach ($online->table() as $mid => $info) {
                $connection = $server->connection_info($info['fd']);
                if (!is_array($connection) || $connection['websocket_status'] != 3) {
                    //删除
                    $online->heartbeatCheck($info['fd']);
                }
            }
        });

    }

}