<?php

namespace App\Base;

use EasySwoole\Http\AbstractInterface\Controller;

abstract class BaseController extends Controller
{
	function onException(\Throwable $throwable): void
	{
		$msg = $throwable->getMessage();
		if ($msg != 'request_end') echo $msg . PHP_EOL;
	}
	
	/**
	 * 返回请求结果
	 * @param int  $statusCode
	 * @param null $msg
	 * @param null $result
	 * @param bool $justData
	 * @return bool
	 * @throws
	 */
	public function output($statusCode = 200, $msg = null, $result = null, bool $justData = false): bool
	{
		if ($this->response()->isEndResponse() < 1) {
			$result = $justData ? $result : ['code' => $statusCode, 'msg' => $msg, 'data' => $result];
			$this->response()->write(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			$this->response()->withHeader('Content-type', 'application/json;charset=utf-8');
			$this->response()->withStatus(200);
			$this->response()->end();
		}
		throw new \Exception('request_end');
	}
}
