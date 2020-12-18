<?php
namespace App\Mqtt;
use App\Utility\Log\Log;

class Subscribe{
    public static function index()
    {
        $mqtt = new Client("s.sportnanoapi.com", 443, "5a66ed1ef0e84545afc4c44ad0b0ec9f", true);

        if(!$mqtt->connect(true, NULL, 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96')){

            exit(1);
        }
        $mqtt->debug = true;
        $topic['sports/football/match.v1'] = ['qos' => 0, 'function' => 'procMsg'];

        $mqtt->subscribe($topic,0);

//        while($mqtt->proc()){
//
//        }

        $mqtt->close();

        function procmsg($topic,$msg){

            Log::getInstance()->info('my message' . json_encode($msg));
            Log::getInstance()->info('my topic' . $topic);
//            echo "Msg Recieved: ".date("r")."\nTopic:{$topic}\n$msg\n";
        }
    }
}