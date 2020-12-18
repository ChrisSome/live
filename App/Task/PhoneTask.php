<?php


namespace App\Task;

use App\lib\pool\PhoneCodeService as PhoneCodeService;
use App\Model\AdminSysSettings;
use App\Model\AdminUserPhonecode;
use App\Utility\Log\Log;
use EasySwoole\Component\Singleton;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class PhoneTask implements TaskInterface
{
    use Singleton;
    protected $taskData;

    public function __construct($taskData)
    {
        $this->taskData = $taskData;
    }


    function run(int $taskId,int $workerIndex)
    {

        return "返回值:".$this->taskData['name'];
    }

    function insert()
    {

        // TODO: Implement run() method.
        $isDebug = AdminSysSettings::getInstance()->getSysKey('is_debug');
        $isDebug = false;
        if (!$isDebug) {
            //需要引入短信表发送短信
            $phoneCodeS = new PhoneCodeService();
            $content = sprintf(PhoneCodeService::$copying, $this->taskData['code']);
            $xsend = $phoneCodeS->sendMess($this->taskData['mobile'], $content);
            if ($xsend['status'] !== PhoneCodeService::STATUS_SUCCESS) {
                Log::getInstance()->info('用户' . $this->taskData['mobile'] . '短信发送失败 ：' . $this->taskData['code']);
            } else {
                $data = [
                    'mobile' => $this->taskData['mobile'],
                    'code' => $this->taskData['code']
                ];
                AdminUserPhonecode::getInstance()->insert($data);
                Log::getInstance()->info('用户' . $this->taskData['mobile'] . '短信发送成功 ：' . $this->taskData['code']);

            }

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