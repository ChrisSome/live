<?php

namespace EasySwoole\EasySwoole;

use App\Process\HotReload;
use App\Storage\OnlineUser;
use easySwoole\Cache\Cache;
use EasySwoole\Http\Request;
use EasySwoole\Utility\File;
use EasySwoole\Http\Response;
use EasySwoole\ORM\DbManager;
use EasySwoole\ORM\Db\Connection;
use EasySwoole\Socket\Dispatcher;
use App\WebSocket\WebSocketEvents;
use App\WebSocket\WebSocketParser;
use App\WebSocket\event\OnWorkStart;
use EasySwoole\ORM\Db\Config as DbConfig;
use EasySwoole\RedisPool\RedisPoolException;
use EasySwoole\Socket\Config as SocketConfig;
use EasySwoole\RedisPool\Exception\Exception;
use easySwoole\Cache\Exception\CacheException;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\EasySwoole\AbstractInterface\Event;

class EasySwooleEvent implements Event
{
	/**
	 * @throws \EasySwoole\Pool\Exception\Exception
	 * @throws Exception
	 * @throws RedisPoolException
	 */
	public static function initialize()
	{
		// ini_set('memory_limit', '2048M');
		self::loadConf(); // 加载配置项
		
		// 配置Redis
		$config = Config::getInstance()->getConf('REDIS');
		$redisConfig = new \EasySwoole\Redis\Config\RedisConfig();
		$redisConfig->setHost($config['host']);
		$redisConfig->setPort($config['port']);
		$redisPoolConfig = \EasySwoole\RedisPool\Redis::getInstance()->register('redis', $redisConfig);
		$redisPoolConfig->setMinObjectNum(15);
		$redisPoolConfig->setMaxObjectNum(100);
		
		// 配置数据库
		$config = Config::getInstance()->getConf('MYSQL');
		$mysqlConfig = new DbConfig();
		$mysqlConfig->setHost($config['host']);
		$mysqlConfig->setPort($config['port']);
		$mysqlConfig->setDatabase($config['db']);
		$mysqlConfig->setUser($config['username']);
		$mysqlConfig->setCharset($config['charset']);
		$mysqlConfig->setPassword($config['password']);
		// 设置检测连接存活执行回收和创建的周期
		$mysqlConfig->setIntervalCheckTime(30000);
		// 连接池对象最大闲置时间(秒)
		$mysqlConfig->setMaxIdleTime(15);
		// 设置最小连接池存在连接对象数量
		$mysqlConfig->setMinObjectNum(15);
		// 设置最大连接池存在连接对象数量
		$mysqlConfig->setMaxObjectNum(100);
		// 设置获取连接池对象超时时间
		$mysqlConfig->setGetObjectTimeout(3.0);
		// 设置自动ping客户端链接的间隔
		$mysqlConfig->setAutoPing(5);
		DbManager::getInstance()->addConnection(new Connection($mysqlConfig));
	}
	
	public static function loadConf()
	{
		$files = File::scanDirectory(EASYSWOOLE_ROOT . '/App/Config');
		if (!is_array($files)) return;
		
		foreach ($files['files'] as $file) {
			$suffix = strtolower(substr($file, strrpos($file, '.') + 1));
			if ($suffix == 'php') {
				Config::getInstance()->loadFile($file);
			} elseif ($suffix == 'env') {
				Config::getInstance()->loadEnv($file);
			}
		}
	}
	
	/**
	 * @param EventRegister $register
	 * @throws CacheException
	 */
	public static function mainServerCreate(EventRegister $register)
	{
		// 配置服务热启动
		$hot_reload = (new HotReload('HotReload', ['disableInotify' => false]))->getProcess();
		ServerManager::getInstance()->getSwooleServer()->addProcess($hot_reload);
		
		// 配置timer定时
		// $namiTask = (new NamiPushTask('NamiPush', ['disableInotify' => false]))->getProcess();
		// ServerManager::getInstance()->getSwooleServer()->addProcess($namiTask);
		
		// 配置缓存
		$conf = Config::getInstance()->getConf('app.cache');
		Cache::init($conf);
		
		// 注册Websocket相关
		OnlineUser::getInstance();
		$web = new WebSocketEvents();
		$onWorkerStart = new OnWorkStart();
		$register->set(EventRegister::onWorkerStart, function ($server, $workerId) use ($onWorkerStart) {
			$onWorkerStart->onWorkerStart($server, $workerId);
		});
		// 注册连接事件
		$register->add(EventRegister::onOpen, function ($server, $request) use ($web) {
			$web::onOpen($server, $request);
		});
		$register->add(EventRegister::onClose, function ($server, $fd, $reactorId) use ($web) {
			$web::onClose($server, $fd, $reactorId);
		});
		$conf = new SocketConfig();
		$conf->setType($conf::WEB_SOCKET);
		$conf->setParser(new WebSocketParser);
		$dispatch = new Dispatcher($conf);
		$register->set(EventRegister::onMessage, function ($server, $frame) use ($dispatch) {
			$dispatch->dispatch($server, $frame->data, $frame);
		});
		$register->add(EventRegister::onTask, function () {
			// todo ...
		});
		// Mysql 热启动 链接预热
		$register->add($register::onWorkerStart, function () {
			DbManager::getInstance()->getConnection()->getClientPool()->keepMin();
		});
	}
	
	public static function onRequest(Request $request, Response $response): bool
	{
		return true;
	}
	
	public static function afterRequest(Request $request, Response $response): void
	{
		// todo ...
	}
}
