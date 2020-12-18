<?php
namespace App\Base;

use App\Utility\Pool\MysqlObject;
use App\Utility\Pool\MysqlPool;
use EasySwoole\Component\CoroutineSingleTon;
use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\ORM\AbstractModel;

abstract class FatherModel extends AbstractModel
{
    use CoroutineSingleTon;
    public $db;



}
