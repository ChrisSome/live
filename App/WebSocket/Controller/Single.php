<?php


namespace App\WebSocket\Controller;


use App\lib\Tool;
use App\Task\BroadcastTask;
use App\WebSocket\Actions\Broadcast\BroadcastMessage;
use EasySwoole\EasySwoole\Task\TaskManager;

class Single extends Chat
{
    public $chat_type = 'single';

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


    }


}