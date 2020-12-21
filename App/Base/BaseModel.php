<?php

namespace App\Base;

use App\Utility\Pool\MysqlPool;
use EasySwoole\ORM\AbstractModel;
use EasySwoole\Component\CoroutineSingleTon;
use EasySwoole\Component\Pool\PoolManager;

abstract class BaseModel extends AbstractModel
{
//    use CoroutineSingleTon;
	protected $db;
    private static $instance=[];

    static function getInstance(...$args)
    {
//        $obj_name = static::class;
//        if(!isset(self::$instance[$obj_name])){
//            self::$instance[$obj_name] = new static(...$args);
//        }
//        return self::$instance[$obj_name];
        return self::create();
    }

    /**
     * @param $filedName
     * @param $value
     * @param array $where
     * @return bool
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function setValue($filedName, $value, $where = [])
    {
        return self::create()->update([$filedName=> $value], $where);
    }

    /**
     * 新增数据
     * @param array $data
     * @return bool|int
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function insert($data = [])
    {
        return self::create($data)->save();
    }

    /**
     * @param $options
     * @return BaseModel|array|bool|AbstractModel|null
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function find($options)
    {
        if (!is_array($options)) {
            $options = ['id' => $options];
        }
        return $this->where($options)->get();
    }

    /**
     * 设置排序
     * @param mixed ...$args
     * @return AbstractModel
     */
    public function orderBy(...$args)
    {
        return parent::order($args); // TODO: Change the autogenerated stub
    }
}