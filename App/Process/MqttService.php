<?php


namespace App\Process;

use App\lib\Mqtt\Client;
use App\Utility\Log\Log;
use EasySwoole\Component\Process\AbstractProcess;


class MqttService extends AbstractProcess
{

    protected $footballTopic = 'sports/football/match.v1';

    public function run($arg)
    {
//                Log::getInstance()->info('start connect:3333');

        $mqtt = new Client('s.sportnanoapi.com', 443);

        $mqtt->onConnect = function ($mqtt) {
            Log::getInstance()->info('start connect:');

            $mqtt->subscribe($this->footballTopic);
        };

        $mqtt->onMessage = function ($topic, $content) {

            Log::getInstance()->info('topic:' . $topic);
            Log::getInstance()->info('content:' . json_encode($content));
        };

        $mqtt->onError = function ($exception) use ($mqtt) {

            Log::getInstance()->info('mqtt exception' . $exception->errMsg);

        };

        $mqtt->onClose = function () {
            Log::getInstance()->notice('mqtt断开');

        };

        $mqtt->connect();


    }


    public function onShutDown()
    {
        // TODO: Implement onShutDown() method.
    }

    public function onReceive(string $str)
    {
        // TODO: Implement onReceive() method.
    }
}