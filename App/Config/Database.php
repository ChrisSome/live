<?php

return [
  'MYSQL' => [
        //数据库配置
       'host'                 => '127.0.0.1',//数据库连接ip  docker用mysql 
       'username'                 => 'root',//数据库用户名
       'password'             => 'prybEL5CaeK5rsMT',//数据库密码
       'db'             => 'es_admin',//数据库
       'port'                 => '3317',//端口
       'timeout'              => '30',//超时时间
       'connect_timeout'      => '5',//连接超时时间
       'charset'              => 'utf8',//字符编码
       'strict_type'          => false, //开启严格模式，返回的字段将自动转为数字类型
       'fetch_mode'           => false,//开启fetch模式, 可与pdo一样使用fetch/fetchAll逐行或获取全部结果集(4.0版本以上)
       'alias'                => '',//子查询别名
       'isSubQuery'           => false,//是否为子查询
       'max_reconnect_times ' => '3',//最大重连次数
    ],
    'REDIS' => [
        'host' => '127.0.0.1',
        'port' => '6379',
        'auth' => '',
        'timeout' => 3,
        'max_reconnect_times ' => '3',//最大重连次数
    ]
];

?>
