<?php

return [
	'MYSQL' => [
		'port' => 3317,
		'timeout' => 30,
		'db' => 'es_admin',
		'charset' => 'utf8',
		'username' => 'root',
		'connect_timeout' => 5,
		'host' => '8.210.195.192',
		'max_reconnect_times' => 3,
		'password' => 'prybEL5CaeK5rsMT',
		'alias' => '',// 子查询别名
		'isSubQuery' => false,// 是否为子查询
		'strict_type' => false, // 开启严格模式，返回的字段将自动转为数字类型
		'fetch_mode' => false,// 开启fetch模式, 可与pdo一样使用fetch/fetchAll逐行或获取全部结果集(4.0版本以上)
	],
	'REDIS' => [
		'port' => 6379,
		'timeout' => 3,
		'auth' => 'zhibo_test',
		'host' => '172.31.227.128',
		'max_reconnect_times ' => 3,
	],
];