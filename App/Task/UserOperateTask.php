<?php
/**
 * Created by PhpStorm.
 * User: evalor
 * Date: 2018-11-28
 * Time: 20:23
 */

namespace App\Task;

use App\Common\AppFunc;
use App\lib\Tool;
use App\Model\AdminInformation;
use App\Model\AdminInformationComment;
use App\Model\AdminMessage;
use App\Model\AdminPostComment;
use App\Model\AdminUser;
use App\Model\AdminUserPost;
use App\Model\ChatHistory;
use App\Storage\OnlineUser;
use App\WebSocket\WebSocketStatus;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use App\Utility\Log\Log;

/**
 * 发送广播消息
 * Class BroadcastTask
 * @package App\Task
 */
class UserOperateTask implements TaskInterface
{
    protected $taskData;

    public function __construct($taskData)
    {
        $this->taskData = $taskData['payload'];
    }


    /**
     * @param int $taskId
     * @param int $workerIndex
     * @return bool|void
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \EasySwoole\Pool\Exception\PoolEmpty
     * @throws \Throwable
     */
    function run(int $taskId, int $workerIndex) {

        $item_type = $this->taskData['item_type'];
        $type = $this->taskData['type'];
        $item_id = $this->taskData['item_id'];
        $uid = $this->taskData['uid'];
        $is_cancel = $this->taskData['is_cancel'];
        $author_id = $this->taskData['author_id'];
        if ($item_type == 1) {
            $model = AdminUserPost::getInstance();
            $status_report = AdminUserPost::NEW_STATUS_REPORTED;
        } else if ($item_type == 2) {
            $model = AdminPostComment::getInstance();
            $status_report = AdminPostComment::STATUS_REPORTED;

        } else if ($item_type == 3) {
            $model = AdminInformation::getInstance();
            $status_report = AdminInformation::STATUS_REPORTED;

        } else if ($item_type == 4) {
            $model = AdminInformationComment::getInstance();
            $status_report = AdminInformationComment::STATUS_REPORTED;

        } else if ($item_type == 5) {
            $model = AdminUser::getInstance();
            $status_report = AdminUser::STATUS_REPORTED;
        } else {
            return false;
        }

        switch ($type) {
            case 1:
                if (!$is_cancel) {
                    $model->update(['fabolus_number' => QueryBuilder::inc(1)], ['id' => $item_id]);
                    if ($author_id != $uid) {
                        if ($message = AdminMessage::getInstance()->where('user_id',  $author_id)->where('type', 2)->where('item_type', $item_type)->where('item_id', $item_id)->where('did_user_id', $uid)->get()) {
                            $message->status = AdminMessage::STATUS_UNREAD;
                            $message->created_at = date('Y-m-d H:i:s');
                            $message->update();
                        } else {
                            //发送消息
                            $data = [
                                'status' => AdminMessage::STATUS_UNREAD,
                                'user_id' => $author_id,
                                'type' => 2,
                                'item_type' => $item_type,
                                'item_id' => $item_id,
                                'title' => '点赞通知',
                                'did_user_id' => $uid
                            ];
                            AdminMessage::getInstance()->insert($data);
                        }

                    }
                } else {
                    $model->update(['fabolus_number' => QueryBuilder::dec(1)], ['id' => $item_id]);
                    if ($author_id != $uid && $message = AdminMessage::getInstance()->where('user_id',  $author_id)->where('type', 2)->where('item_type', $item_type)->where('item_id', $item_id)->where('did_user_id', $uid)->get()) {
                        $message->status = AdminMessage::STATUS_DEL;
                        $message->update();
                    }
                }



                break;
            case 2:
                if (!$is_cancel) {
                    $model->update(['collect_number' => QueryBuilder::inc(1)], ['id' => $item_id]);

                } else {
                    $model->update(['collect_number' => QueryBuilder::dec(1)], ['id' => $item_id]);

                }

                break;
            case 3:
                $model->update(['status', $status_report], ['id' => $item_id]);
                break;

        }


    }

    function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        throw $throwable;
    }

}