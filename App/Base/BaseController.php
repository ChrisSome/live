<?php

namespace App\Base;

use EasySwoole\Http\AbstractInterface\Controller;

abstract class BaseController extends Controller
{
	public function index()
	{
		$this->actionNotFound('index');
	}
	
	public function show404()
	{
		$this->render('路由未能匹配');
	}
	
	public function render(string $message)
	{
		$this->response()->write($message);
	}
	
	public function writeJson($statusCode = 200, $msg = null, $result = null, bool $justData = false): bool
	{
		if ($this->response()->isEndResponse()) return true;
		$result = $justData ? $result : ['code' => $statusCode, 'msg' => $msg, 'data' => $result];
		$this->response()->write(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$this->response()->withHeader('Content-type', 'application/json;charset=utf-8');
		return true;
	}
}
