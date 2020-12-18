<?php
namespace App\HttpController\User;

use App\Base\FrontUserController;
use App\Model\AdminUserSerialPoint;
use App\Task\SerialPointTask;
use App\Utility\Message\Status;
use EasySwoole\EasySwoole\Task\TaskManager;

/**
 * 用户积分管理中心
 * Class Point
 * @package App\HttpController\User
 */
class Point  extends FrontUserController{


    public $needCheckToken = false;
    public $isCheckSign = false;


    /**
     * 获取任务列表 及每日任务详情
     * @return bool
     */
    public function getAvailableTask()
    {
        $user_tasks = AdminUserSerialPoint::USER_TASK;
        foreach ($user_tasks as $k => $task) {
            if ($task['status'] != AdminUserSerialPoint::TASK_STATUS_NORMAL) {
                continue;
            }
            $done_times = AdminUserSerialPoint::getInstance()->where('task_id', $task['id'])->where('created_at', date('Y-m-d'))->count();
            $user_tasks[$k]['done_times'] = $done_times;
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $user_tasks);

    }

    /**
     * 做任务加积分，这里只能是每日签到
     * @return bool
     */
    public function userSign()
    {

        if (AdminUserSerialPoint::getInstance()->where('user_id', $this->auth['id'])->where('task_id', 1)->where('created_at', date('Y-m-d'))->get()) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

        $data['task_id'] = 1;
        $data['user_id'] = $this->auth['id'];
        TaskManager::getInstance()->async(new SerialPointTask($data));
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

    }

    public function getPointList()
    {
        $list = AdminUserSerialPoint::getInstance()->where('user_id', $this->auth['id'])->where('created_at', date('Y-m-d'))->order('id', 'DESC')->all();
        return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM], $list);

    }
}
