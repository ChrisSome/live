<?php


namespace App\Task;

use App\Model\AdminUser;
use App\Utility\Pool\RedisPool;
use easySwoole\Cache\Cache;
use EasySwoole\Component\Singleton;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use App\lib\pool\Login;

class LoginTask implements TaskInterface
{
    use Singleton;
    protected $taskData;
    const LOGIN_IP_ERROR_KEY = 'login:error:ip:%s';
    const LOGIN_MOBILE_ERROR_KEY = 'login:error:mobile:%s';

    public function __construct($taskData)
    {
        $this->taskData = $taskData;

    }
    function run(int $taskId,int $workerIndex) {

    }

    function execData()
    {
        // TODO: Implement run() method.
        if ($this->taskData['type'] != 'success') {
            $this->addErrorStatistics();
        } else {
            $this->execLoginSuccess();
        }
    }


    public function execLoginSuccess()
    {
        //处理登陆成功的逻辑， 需要更新用户登陆时间， 还有比如每天登陆统计， 一登陆次数， 二登陆人数等
        //写入在线登陆日志， 登陆成功，跳转至页面， 获取播放源列表
        //需要删除用户之前的tokenKey

        return Login::getInstance()->setEx( sprintf(AdminUser::USER_TOKEN_KEY,
            $this->taskData['token']), 7200, json_encode($this->taskData['data']));

    }

    private function addErrorStatistics() {
        if ($this->taskData['type'] == 'ip') {
            $key = sprintf(self::LOGIN_IP_ERROR_KEY, $this->taskData[$this->taskData['type']]);
        } else {
            $key = sprintf(self::LOGIN_MOBILE_ERROR_KEY, $this->taskData[$this->taskData['type']]);
        }

        return Login::getInstance()->incryBy($key);
    }

    function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }
}