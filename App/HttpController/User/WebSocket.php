<?php

namespace App\HttpController\User;

use App\lib\Tool;
use App\Common\AppFunc;
use App\Utility\Log\Log;
use App\Base\FrontUserController;
use App\WebSocket\WebSocketStatus;
use EasySwoole\EasySwoole\ServerManager;

class WebSocket extends FrontUserController
{
	protected $isCheckSign = false;
	protected $needCheckToken = false;
	private $user = 'mark9527';
	private $secret = 'dbfe8d40baa7374d54596ea513d8da96';
	private $uriM = 'https://open.sportnanoapi.com/api/v4/football/match/diary?user=%s&secret=%s&date=%s';
	
	function callback($instance, $channelName, $message)
	{
		$info = json_encode([$channelName, $message]);
		Log::getInstance()->info($info);
	}
	
	public function contentPush($diff, $matchId)
	{
		$fd = AppFunc::getUsersInRoom($matchId);
		if (empty($fd)) return;
		
		$tool = Tool::getInstance();
		$server = ServerManager::getInstance()->getSwooleServer();
		$data = ['event' => 'match_tlive', 'match_id' => $matchId, 'content' => $diff];
		foreach ($fd as $v) {
			$connection = $server->connection_info($v);
			// 用户正常在线时可以进行消息推送
			if (!empty($connection['websocket_status']) && $connection['websocket_status'] == 3) {
				$jsonStr = $tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $data);
				$server->push($v, $jsonStr);
			}
		}
	}
}