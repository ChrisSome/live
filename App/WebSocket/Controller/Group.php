<?php


namespace App\WebSocket\Controller;


use App\lib\Tool;
use App\Model\AdminUser;
use App\Task\BroadcastTask;
use App\WebSocket\Actions\Broadcast\BroadcastMessage;
use EasySwoole\EasySwoole\Task\TaskManager;

class Group extends Chat
{
    public function chat()
    {
        $client = $this->caller()->getClient();
        $fd = $client->getFd();
        $args = $this->caller()->getArgs();

        //reciever_id
        if (!$this->checkParams($args, $messsage)) {
            $this->response()->setMessage(Tool::getInstance()->writeJson(405, $messsage));

            return ;
        }
        if (!$this->checkUserRight($fd, $args, $message)) {
            $this->response()->setMessage(Tool::getInstance()->writeJson(406, $message));

            return  ;
        }

        //获取
        $userInfo = $message;
        //获取
        $message = new BroadcastMessage;
        $message->setFromUserId($userInfo['user_id']);
        $message->setFromUserFd($client->getFd());
        $message->setContent($args['content']);
        $message->setType(isset($args['type']) ? $args['type'] : 'text');
        $message->setSendTime(date('Y-m-d H:i:s'));
        $message->setMessageId(isset($args['message_id']) ? $args['message_id'] : 0);
        $message->setMatchId($args['match_id']);
        $message->setMid($args['mid']);

        //异步任务处理聊天信息
        TaskManager::getInstance()->async(new BroadcastTask(['payload' => $message->__toString(), 'fromFd' => $fd]));

        $this->response()->setMessage(Tool::getInstance()->writeJson(200, '发布聊天成功'));

        return  ;
    }


}