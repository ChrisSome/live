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