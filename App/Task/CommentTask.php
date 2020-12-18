<?php


namespace App\Task;


use App\Model\AdminPostComment;
use App\Model\AdminUserPost;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use App\Utility\Log\Log;

class CommentTask implements TaskInterface
{
    protected $taskData;
    public function __construct($taskData)
    {
        $this->taskData = $taskData;
    }

    function run(int $taskId, int $workerIndex)
    {
        // TODO: Implement run() method.
       $this->dosome();
    }


    public function dosome()
    {

        //插入一条评论
        $model = AdminPostComment::getInstance()->create($this->taskData);
        $insertId = $model->save();
        if ($this->taskData['parent_id']) {
            AdminPostComment::create()->update([
               'respon_number' => QueryBuilder::inc(1)
            ],[
                'id' => $this->taskData['parent_id']
            ]);


        }

        AdminUserPost::create()->update([
            'respon_number' => QueryBuilder::inc(1)
        ],[
            'id' => $this->taskData['post_id']
        ]);
        Log::getInstance()->info('insertid' . $insertId);
        return $insertId;
    }


    function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.

        Log::getInstance()->info('message' . $throwable->getMessage());
    }
}