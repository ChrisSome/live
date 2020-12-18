<?php


namespace App\Task;

use App\Common\AppFunc;
use App\Model\AdminUser;
use App\Model\AdminUserSerialPoint;
use App\Utility\Pool\RedisPool;
use easySwoole\Cache\Cache;
use EasySwoole\Component\Singleton;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use App\Utility\Log\Log;

class SerialPointTask implements TaskInterface
{
    use Singleton;
    protected $taskData;

    public function __construct($taskData)
    {
        Log::getInstance()->info('111');

        $this->taskData = $taskData;

    }

    /**
     * @param int $taskId
     * @param int $workerIndex
     */
    function run(int $taskId,int $workerIndex)
    {

        if ($this->taskData['task_id'] == 1) {
            if (AdminUserSerialPoint::getInstance()->where('user_id', $this->taskData['user_id'])->where('task_id', 1)->where('created_at', date('Y-m-d'))->get()) {
                return;
            }
        } else {
            //任务次数限制
            $done_times = AdminUserSerialPoint::getInstance()->where('user_id', $this->taskData['user_id'])
                ->where('task_id', $this->taskData['task_id'])->where('created_at', date('Y-m-d'))->count();
            if (isset(AdminUserSerialPoint::USER_TASK[$this->taskData['task_id']]['times_per_day'])) {
                $task_times = AdminUserSerialPoint::USER_TASK[$this->taskData['task_id']]['times_per_day'];
            } else {
                return;
            }
            if ($done_times >= $task_times) {
                return;
            }
        }
        Log::getInstance()->info('222444');

        $task_id = $this->taskData['task_id'];
        $user_task = AdminUserSerialPoint::USER_TASK[$task_id];
        Log::getInstance()->info('aaa');

        $data = [
            'created_at' => date('Y-m-d'),
            'task_id' => $task_id,
            'type' => 1,
            'user_id' => $this->taskData['user_id'],
            'task_name' => $user_task['name'],
            'point' => $user_task['points_per_time']

        ];
        $bool = AdminUserSerialPoint::getInstance()->insert($data);
        Log::getInstance()->info('bbb');

        $user = AdminUser::getInstance()->find($this->taskData['user_id']);
        $user->point += $user_task['points_per_time'];
        $level = AppFunc::getUserLvByPoint($user->point);
        $user->level = $level;
        $user->update();


    }




    function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }
}