<?php


namespace App\Task;

use App\Model\AdminMessage;
use App\Model\AdminPostComment;
use App\Model\AdminPostOperate;
use App\Model\AdminUser;
use App\Model\AdminUserPost;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use EasySwoole\ORM\DbManager;

class UserTask implements TaskInterface
{
    protected $taskData;

    public function __construct($taskData)
    {
        $this->taskData = $taskData;

    }
    function run(int $taskId,int $workerIndex) {
        $params = $this->taskData['params'];
        switch ($params['action_type']) {
            case 'chg_nickname':
                $this->updateNickname($params['value']);
                break;
            case 'chg_photo':
                $this->updatePhoto($params['value']);
                break;
        }
    }

    private function updateNickname($nickname)
    {
        try{
            //开启事务
            $userId = $this->taskData['user_id'];
            DbManager::getInstance()->startTransaction();
            AdminUser::create()->update(['nickname' => $nickname], ['id' => $userId]);
            AdminMessage::create()->update(['sender_nickname' => $nickname], ['sender_user_id' => $userId]); //聊天记录表
            AdminUserPost::create()->update(['nickname' => $nickname], ['user_id' => $userId]); //帖子表
            AdminPostComment::create()->update(['nickname' => $nickname], ['user_id' => $userId]); //帖子评论表
            AdminPostComment::create()->update(['parent_user_nickname' => $nickname], ['parent_user_id' => $userId]); //帖子评论表
            AdminPostOperate::create()->update(['nickname' => $nickname], ['user_id' => $userId]); //帖子操作表
        } catch(\Throwable  $e){
            //回滚事务
            var_dump($e->getTraceAsString(), $e->getMessage());
            DbManager::getInstance()->rollback();
        } finally {
            //提交事务
            DbManager::getInstance()->commit();
        }
    }


    private function updatePhoto($photo)
    {
        try{
            //开启事务
            $userId = $this->taskData['user_id'];
            DbManager::getInstance()->startTransaction();
            AdminUser::create()->update(['photo' => $photo], ['id' => $userId]);
            AdminMessage::create()->update(['sender_photo' => $photo], ['sender_user_id' => $userId]); //聊天记录表
            AdminUserPost::create()->update(['head_photo' => $photo], ['user_id' => $userId]); //帖子表
            AdminPostComment::create()->update(['photo' => $photo], ['user_id' => $userId]); //帖子评论表
        } catch(\Throwable  $e){
            //回滚事务
            var_dump($e->getTraceAsString(), $e->getMessage());
            DbManager::getInstance()->rollback();
        } finally {
            //提交事务
            DbManager::getInstance()->commit();
        }
    }







    function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }
}