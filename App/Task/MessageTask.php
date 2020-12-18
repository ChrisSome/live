<?php


namespace App\Task;

use App\Model\AdminUserMessageRecord;
use App\Utility\Pool\RedisPool;
use EasySwoole\Component\Singleton;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use App\lib\pool\Login;

class MessageTask implements TaskInterface
{
    use Singleton;
    protected $taskData;
    public function __construct($taskData)
    {
        $this->taskData = $taskData;

    }

    function run(int $taskId,int $workerIndex)
    {
        $this->execData();

    }

    function execData()
    {
        // TODO: Implement run() method.
        //1. 获取用户是否已读
        $isRead = AdminUserMessageRecord::getInstance()->where('message_id', $this->taskData['message_id'])
            ->where('user_id', $this->taskData['user_id'])->count();
        if (!$isRead) {
            AdminUserMessageRecord::getInstance()->insert($this->taskData);
        }
    }



    function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }
}