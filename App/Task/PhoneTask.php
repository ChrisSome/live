<?php


namespace App\Task;

use App\lib\pool\PhoneCodeService as PhoneCodeService;
use App\Model\AdminUserPhonecode;
use App\Utility\Log\Log;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class PhoneTask implements TaskInterface
{
    protected $taskData;

    public function __construct($taskData)
    {
        $this->taskData = $taskData;
    }



    function run(int $taskId, int $workerIndex)
    {
        //需要引入短信表发送短信
        $phoneCodeS = new PhoneCodeService();
        $content = sprintf(PhoneCodeService::$copying, $this->taskData['code']);

        $xsend = $phoneCodeS->sendMess($this->taskData['mobile'], $content);
        if ($xsend['status'] !== PhoneCodeService::STATUS_SUCCESS) {
        } else {
            $data = [
                'mobile' => $this->taskData['mobile'],
                'code' => $this->taskData['code']
            ];
            AdminUserPhonecode::getInstance()->insert($data);
            Log::getInstance()->info('用户' . $this->taskData['mobile'] . '短信发送成功 ：' . $this->taskData['code']);

        }

    }
    function finish()
    {
        return '123';
    }


    function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }
}