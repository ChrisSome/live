<?php


namespace App\Utility\Pool;


use EasySwoole\Component\Singleton;

class RedisPool
{
    use Singleton;

    public $redis;

    /**
     * 获取redis pool
     * Base constructor.
     */
    public function __construct()
    {
        if (!$this->redis) {
            $this->redis = \EasySwoole\RedisPool\Redis::defer('redis');
        }
    }


    /**
     * 统计次数
     * @param $key
     * @param int $num
     * @return bool|string
     */
    public function incryBy($key, $num = 1)
    {
        return $this->redis->incrBy($key, $num);
    }

    /**
     * __call重写里面的方法
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        // TODO: Implement __call() method.
        return $this->redis->$name(... $arguments);
    }

    /**
     * 析构函数
     */
    public function __destruct()
    {
        if ($this->redis) {
            \EasySwoole\Component\Timer::getInstance()->clear('redis');
            $this->redis = null;
        }
    }
}