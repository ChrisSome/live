<?php


namespace App\lib\pool;



use App\Utility\Pool\RedisPool;
use EasySwoole\Component\Pool\PoolManager;



abstract class BaseRedis
{
    protected $redis;
    protected $table;
    private static $instance=[];

    static function getInstance(...$args)
    {
        $obj_name = static::class;
        if(!isset(self::$instance[$obj_name])){
            self::$instance[$obj_name] = new static(...$args);
        }
        return self::$instance[$obj_name];
    }

    protected function __construct()
    {
        try {
            $db = RedisPool::defer();
            if($db instanceof RedisObject) {
                $this->redis = $db;
            } else {
                throw new \Exception('redis pool is empty');
            }
        } catch (\Exception $e) {
            var_dump($e->getTraceAsString(), $e->getFile(), $e->getLine());
        }

    }



    function __destruct()
    {
        if ($this->redis instanceof RedisObject) {
            PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($this->redis);
        }
    }


    function __call($name, $arguments)
    {
        // TODO: Implement __call() method.
        return $this->redis->$name(... $arguments);
    }
}