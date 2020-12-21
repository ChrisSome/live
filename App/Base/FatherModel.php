<?php
namespace App\Base;

use App\Utility\Pool\MysqlObject;
use App\Utility\Pool\MysqlPool;
use EasySwoole\Component\CoroutineSingleTon;
use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\ORM\AbstractModel;
use Swoole\Coroutine;

abstract class FatherModel extends AbstractModel
{
//    use CoroutineSingleTon;
    private static $instance = [];

    public $db;

    static function getInstance(...$args)
    {
        $cid = Coroutine::getCid();
        if(!isset(self::$instance[$cid])){
            self::$instance[$cid] = new static(...$args);
            /*
             * 兼容非携程环境
             */
            if($cid > 0){
                Coroutine::defer(function ()use($cid){
                    unset(self::$instance[$cid]);
                });
            }
        }
        return self::$instance[$cid];
    }



}
