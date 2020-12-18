<?php

namespace App\lib\Mqtt;


use App\Utility\Log\Log;
//use EasySwoole\Component\Process\AbstractProcess;

//class Subscribe  extends AbstractProcess {
class Subscribe  {

    protected $footballTopic = 'sports/football/match.v1';

    protected  function run($arg) {

//        Log::getInstance()->info('start connect:11122');

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

            Log::getInstance()->info('mqtt exception');

        };

        $mqtt->onClose = function () {
            Log::getInstance()->notice('mqtt close');

        };

        $mqtt->connect();

    }


}
