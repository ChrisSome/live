<?php

return [
	'SERVER_NAME' => "EasySwoole",
	'MAIN_SERVER' => [
		'PORT' => 9501,
		'LISTEN_ADDRESS' => '0.0.0.0',
		'SOCK_TYPE' => SWOOLE_TCP,
		'RUN_MODEL' => SWOOLE_PROCESS,
		'SERVER_TYPE' => EASYSWOOLE_WEB_SOCKET_SERVER,
		'TASK' => [
			'timeout' => 15,
			'workerNum' => 8,
			'maxRunningNum' => 128,
		],
		'SETTING' => [
			'worker_num' => 8,
			'max_wait_time' => 3,
			'task_worker_num' => 8,
			'reload_async' => true,
			'task_enable_coroutine' => true,
		],
	],
	'TEMP_DIR' => '/Temp',
	'LOG_DIR' => EASYSWOOLE_ROOT . '/Temp/logs',
	'IMG_DIR' => EASYSWOOLE_ROOT . '/App/Static/image',
	'PHAR' => [
		'EXCLUDE' => ['.idea', 'Log', 'Temp', 'easyswoole', 'easyswoole.install'],
	],
	'DEBUG' => false, // 是否开启调试
	'GETUI' => [
		'APPID' => 'LE2EByDFzB8t1zhfgTZdk8',
		'APPKEY' => '1pzc0QJtjG6UyddwH404c9',
		'HOST' => 'http://sdk.open.api.igexin.com/apiex.htm',
		'MASTERSECRET' => '0QMqcC8YkF6xiQNA0sGdM2',
	],
];