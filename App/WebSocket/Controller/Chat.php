<?php


namespace App\WebSocket\Controller;


use App\lib\pool\Login;
use App\Model\AdminUser;
use App\Storage\OnlineUser;
use EasySwoole\Validate\Validate;

abstract class Chat extends Base
{
    public $chat_type = 'all';

    /**
     * 验证参数
     * @param $args
     * @param string $messages
     * @return bool
     */
    public function checkParams($args, &$messages = '')
    {
        $validate = new Validate();
        $validate->addColumn('content')->required(); //内容
        $validate->addColumn('match_id')->required(); //聊天室id
        $validate->addColumn('mid')->required(); //客户端标记id
        if ($this->chat_type == 'single') {
            $validate->addColumn('message_id')->required(); //@对象， 即上一条信息
        }

        if (!$validate->validate($args)) {
            $messages = $validate->getError()->__toString();

            return false;
        }
        $login = Login::getInstance();
        $list = $login->lrange(sprintf(OnlineUser::LIST_ONLINE, $args['match_id']), 0, -1);
        if (!in_array($args['mid'], $list)) {
            $messages = '请先进入直播间，再开始聊天';

            return false;
        }

        return true;
    }
}