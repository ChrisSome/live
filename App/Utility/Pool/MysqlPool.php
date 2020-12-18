<?php
namespace App\Utility\Pool;

//use EasySwoole\Component\Pool\AbstractPool;
use EasySwoole\EasySwoole\Config;
use EasySwoole\ORM\AbstractModel;

class MysqlPool extends AbstractModel
{
    protected function createObject()
    {
        $conf   = Config::getInstance()->getConf('database.MYSQL');
        $dbConf = new \EasySwoole\Mysqli\Config($conf);
        return new MysqlObject($dbConf);
    }
}
