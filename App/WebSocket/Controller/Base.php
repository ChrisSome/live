<?php
/**
 * Created by PhpStorm.
 * User: evalor
 * Date: 2018-12-02
 * Time: 01:54
 */

namespace App\WebSocket\Controller;

use App\lib\Tool;
use App\Model\AdminNormalProblems;
use App\Model\AdminUser;
use App\Model\ChatHistory;
use App\Storage\OnlineUser;
use App\Utility\Log\Log;
use App\WebSocket\WebSocketStatus;
use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\ORM\DbManager;
use EasySwoole\Socket\AbstractInterface\Controller;
use EasySwoole\Socket\Client\WebSocket as WebSocketClient;
use Exception;

/**
 * 基础控制器
 * Class Base
 * @package App\WebSocket\Controller
 */
class Base extends Controller
{

    public $is_login = false;




    public function checkUser2($fd, $jurisdiction)
    {
        Log::getInstance()->info('fd-' . $fd);
        if (!$onLineUser = OnlineUser::getInstance()->get($fd)) {
            return false;
        }

        if (isset($jurisdiction['login']) && !$onLineUser['user_id']) {
            return false;
        }
        if (isset($jurisdiction['status']) && !$onLineUser['user_id']) {

            if (!$user = AdminUser::getInstance()->find(['id' => $onLineUser['user_id']])) {
                return false;
            } else if (!in_array($user['status'], [AdminUser::STATUS_NORMAL, AdminUser::STATUS_REPORTED])) {
                return false;
            }
        }

        return $onLineUser;

    }

    public function checkUser($fd)
    {
        if (!$onLineUser = OnlineUser::getInstance()->get($fd)) {
            return [];
        }
        if (!$jurisdiction) {
            return $onLineUser;
        }

        if (isset($jurisdiction['login']) && $onLineUser['user_id']) {
            return $onLineUser;
        }

        if (isset($jurisdiction['status']) && $onLineUser['user_id']) {
            $user_id = $onLineUser['user_id'];
            $user = DbManager::getInstance()->invoke(function ($client) use ($user_id) {
                $userModel = AdminUser::invoke($client)->find($user_id);
                return $userModel;
            });
            if ($user && !in_array($user->status, [AdminUser::STATUS_NORMAL, AdminUser::STATUS_REPORTED])) {
                return $onLineUser;

            }
        }
        return [];
    }

    /**
     * 获取当前的用户
     * @return array|string
     * @throws Exception
     */
    public function currentUser($fd)
    {
        /** @var WebSocketClient $client */
        return OnlineUser::getInstance()->get($fd);
    }

    /**
     * 未找到的方法
     */
    public function actionNotFund()
    {
        $this->response()->setMessage(Tool::getInstance()->writeJson(404, '方法未定义'));

        return ;
    }


    /**
     * json格式错误
     */
    public function actionParseError()
    {
        $this->response()->setMessage(Tool::getInstance()->writeJson(410, '请检查json格式'));

        return ;
    }



}