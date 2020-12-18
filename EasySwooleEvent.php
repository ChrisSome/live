<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/28
 * Time: 下午6:33
 */

namespace EasySwoole\EasySwoole;

use App\Process\Consumer;
use App\Process\NamiPushTask;
use App\Storage\OnlineUser;
use App\Utility\Log\Log;
use App\WebSocket\event\OnWorkStart;
use App\WebSocket\WebSocketEvents;
use App\WebSocket\WebSocketParser;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\Socket\Dispatcher;
use EasySwoole\Utility\File;
use App\Process\HotReload;
use App\Utility\Template\Blade;
use EasySwoole\Template\Render;
use easySwoole\Cache\Cache;
use EasySwoole\ORM\Db\Config as DbConfig;
use EasySwoole\ORM\DbManager;
use EasySwoole\ORM\Db\Connection;
use EasySwoole\EasySwoole\Config;
class EasySwooleEvent implements Event
{

    public static function initialize()
    {
        // TODO: Implement initialize() method.
        date_default_timezone_set('Asia/Shanghai');
        //ini_set('memory_limit', '2048M');

        // 加载配置项
        self::loadConf();

//        //设置redis
        //$redisConf = Config::getInstance()->getConf('database')['REDIS'];
        $redisConf = Config::getInstance()->getConf('REDIS');
        $redisPoolConfig = new  \EasySwoole\Redis\Config\RedisConfig();
        $redisPoolConfig->setHost($redisConf['host']);
        $redisPoolConfig->setPort($redisConf['port']);
        $redisPoolConfig = \EasySwoole\RedisPool\Redis::getInstance()->register('redis',$redisPoolConfig);
        //配置连接池连接数
        $redisPoolConfig->setMinObjectNum(15);
        $redisPoolConfig->setMaxObjectNum(100);

	//$conf_server = Config::getInstance()->getConf('MAIN_SERVER');
	//file_put_contents('/var/wwwroot/log.txt', '3-' . json_encode($conf_server));
        //数据库
        //$dbConf = Config::getInstance()->getConf('database')['MYSQL'];
        $dbConf = Config::getInstance()->getConf('MYSQL');
	$config = new DbConfig();
        $config->setDatabase($dbConf['db']);
        $config->setUser($dbConf['username']);
        $config->setPassword($dbConf['password']);
        $config->setHost($dbConf['host']);
        $config->setPort($dbConf['port']);
        $config->setCharset($dbConf['charset']);
        //连接池配置
        $config->setGetObjectTimeout(3.0); //设置获取连接池对象超时时间
        $config->setIntervalCheckTime(30*1000); //设置检测连接存活执行回收和创建的周期
        $config->setMaxIdleTime(15); //连接池对象最大闲置时间(秒)
        $config->setMinObjectNum(15); //设置最小连接池存在连接对象数量
        $config->setMaxObjectNum(100); //设置最大连接池存在连接对象数量
        $config->setAutoPing(5); //设置自动ping客户端链接的间隔
        DbManager::getInstance()->addConnection(new Connection($config));
    }

    public static function loadConf()
    {
        $files = File::scanDirectory(EASYSWOOLE_ROOT . '/App/Config');
        if (is_array($files)) {
            foreach ($files['files'] as $file) {
                $fileNameArr = explode('.', $file);
                $fileSuffix = end($fileNameArr);
                if ($fileSuffix == 'php') {
                    Config::getInstance()->loadFile($file);
                } elseif ($fileSuffix == 'env') {
                    Config::getInstance()->loadEnv($file);
                }
            }
        }
    }

    public static function mainServerCreate(EventRegister $register)
    {
        /**
         * ****************  服务热启动  ****************
         */
        $hot_reload = (new HotReload('HotReload', ['disableInotify' => false]))->getProcess();
        ServerManager::getInstance()->getSwooleServer()->addProcess($hot_reload);
        /**
         * timer定时
         */
//        $nami_task = (new NamiPushTask('NamiPush', ['disableInotify' => false]))->getProcess();
//        ServerManager::getInstance()->getSwooleServer()->addProcess($nami_task);
        // template
        $viewDir = EASYSWOOLE_ROOT . '/App/Views';
        $cacheDir = EASYSWOOLE_ROOT . '/Temp/Template';
        Render::getInstance()->getConfig()->setRender(new Blade($viewDir,$cacheDir));
        Render::getInstance()->attachServer(ServerManager::getInstance()->getSwooleServer());

        /**
         * ****************  缓存  ****************
         */
        $conf = Config::getInstance()->getConf('app.cache');
        Cache::init($conf);

        /**
         * ****************  注册websocket相关  ****************
         */
        $web = new WebSocketEvents();
        OnlineUser::getInstance();
        $onWorkerStart = new OnWorkStart();
        $register->set(EventRegister::onWorkerStart, function (\swoole_websocket_server $server,  $workerId) use ($onWorkerStart) {
            $onWorkerStart->onWorkerStart($server, $workerId);
        });
        //注册连接事件
        $register->add(EventRegister::onOpen, function (\swoole_server $server, \swoole_http_request $request) use ($web) {
            $web::onOpen($server, $request);
        });
        $register->add(EventRegister::onClose, function (\swoole_server $server, int $fd, int $reactorId) use ($web) {
            $web::onClose($server, $fd, $reactorId);
        });
        $conf = new \EasySwoole\Socket\Config();
        $conf->setType($conf::WEB_SOCKET);
        $conf->setParser(new WebSocketParser);
        $dispatch = new Dispatcher($conf);
        $register->set(EventRegister::onMessage, function (\swoole_server $server, \swoole_websocket_frame $frame) use ($dispatch) {
            $dispatch->dispatch($server, $frame->data, $frame);
        });
        $register->add(EventRegister::onTask, function () {

        });



        /**
         * ****************  mysql 热启动  ****************
         */
        $register->add($register::onWorkerStart,function (){
            //链接预热
            DbManager::getInstance()->getConnection()->getClientPool()->keepMin();
        });



    }

    public static function onRequest(Request $request, Response $response): bool
    {
        // TODO: Implement onRequest() method.
        return true;
    }

    public static function afterRequest(Request $request, Response $response): void
    {
        // TODO: Implement afterAction() method.
    }
}
