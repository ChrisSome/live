<?php


namespace App\Task;

use App\lib\pool\PhoneCodeService as PhoneCodeService;
use App\Model\AdminSysSettings;
use App\Model\AdminUserPhonecode;
use App\Utility\Log\Log;
use EasySwoole\Component\Singleton;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class TestTask implements TaskInterface
{
    use Singleton;
    protected $taskData;

    public function __construct($taskData)
    {
        Log::getInstance()->info('code 2');

        $this->taskData = $taskData;
    }

    /**
     * @param int $taskId
     * @param int $workerIndex
     * @throws \Exception
     */
    public function run(int $taskId,int $workerIndex)
    {
        // TODO: Implement run() method.
        $isDebug = AdminSysSettings::getInstance()->getSysKey('is_debug');
        Log::getInstance()->info('code 4');

        if (!$isDebug) {

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

        } else {
            Log::getInstance()->info('短信功能未开启');

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