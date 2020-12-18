<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-01-01
 * Time: 20:06
 */

return [
    'SERVER_NAME' => "EasySwoole",
    'MAIN_SERVER' => [
        'LISTEN_ADDRESS' => '0.0.0.0',
        'PORT' => 9504,
        'SERVER_TYPE' => EASYSWOOLE_WEB_SERVER, //可选为 EASYSWOOLE_SERVER  EASYSWOOLE_WEB_SERVER EASYSWOOLE_WEB_SOCKET_SERVER,EASYSWOOLE_REDIS_SERVER
        'SOCK_TYPE' => SWOOLE_TCP,
        'RUN_MODEL' => SWOOLE_PROCESS,
        'SETTING' => [
            'worker_num' => 8,
            'task_worker_num' => 8,
            'reload_async' => true,
            'task_enable_coroutine' => true,
            'max_wait_time'=>3
        ],
    ],
    'TEMP_DIR' => null,
    'LOG_DIR' => RUNNING_ROOT . '/Log',
    'IMG_DIR' => RUNNING_ROOT . '/App/Static/image',
    'PHAR' => [
        'EXCLUDE' => ['.idea', 'Log', 'Temp', 'easyswoole', 'easyswoole.install']
    ],
    'DEBUG' => false,  // 是否开启 debug
    'REDIS' => [
        'host' => '127.0.0.1',
        'port' => '6379',
        'auth' => 'zhibo_test',
        'timeout' => 3,
        'max_reconnect_times ' => '3',//最大重连次数
    ],
    'GETUI' => [
        'APPID' => 'LE2EByDFzB8t1zhfgTZdk8',
        'APPKEY' => '1pzc0QJtjG6UyddwH404c9',
        'HOST' => 'http://sdk.open.api.igexin.com/apiex.htm',
        'MASTERSECRET' => '0QMqcC8YkF6xiQNA0sGdM2',
    ],
];
