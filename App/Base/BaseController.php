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
	
	protected function param(string $key = '', bool $isInt = false, $default = null)
	{
		if (empty($key)) return $this->request()->getRequestParam();
		$param = $this->request()->getRequestParam($key);
		if (empty($param)) return is_null($default) ? ($isInt ? 0 : '') : $default;
		if ($isInt) {
			$value = intval($param);
			if ($value < 1 && is_int($default) && $default > 0) $value = $default;
			return $value < 1 ? 0 : $value;
		}
		return is_string($param) ? trim($param) : $param;
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
