<?php

namespace App\Base;

use App\Common\AppFunc;
use App\Model\AdminUser;
use App\Utility\Message\Status;
use EasySwoole\EasySwoole\Config;
use App\Model\AdminLog as LogModel;

class FrontUserController extends BaseController
{
	protected $params = []; // 请求参数清单
	protected $auth = null; // 登录用户信息
	protected $role = null; // 登录用户角色
	protected $isCheckSign = false;
	private $needCheckToken = false;
	private $signKey = 'sign';
	private $noNeedSignKeys = ['content', 'id'];
	private $ignoreCheckRoutes = [
		'/User/Login',
		'/User/Post/detail',
		'/User/System/detail',
		'/User/User/userSendSmg',
	];
	
	/**
	 * 加密或者验签
	 * @param $params
	 * @return bool
	 */
	private function checkSign($params): bool
	{
		if (!isset($params[$this->signKey])) {
			$this->writeJson(403, '验签不通过');
			return false;
		}
		ksort($params); // Ascii升序
		$string = ''; // 加密字符串
		foreach ($params as $k => $v) {
			if ($k != $this->signKey && !in_array($k, $this->noNeedSignKeys)) {
				$string .= $k . '=' . $v . '&';
			}
		}
		$string = md5(rtrim($string, '&'));
		if ($string != $params[$this->signKey]) {
			$this->writeJson(403, '验签不通过');
			return false;
		}
		return true;
	}
	
	/**
	 * 检查token 是否合法
	 * @throws
	 */
	private function checkToken(): bool
	{
		// 参数校验
		$request = $this->request();
		$authId = $request->getCookieParams('front_id');
		if (empty($authId) || intval($authId) < 1) return false;
		
		$this->auth = null;
		$authId = intval($authId);
		$timestamp = $request->getCookieParams('front_time');
		$token = md5($authId . Config::getInstance()->getConf('app.token') . $timestamp);
		if ($request->getCookieParams('front_token') == $token) {
			$this->auth = AdminUser::getInstance()->find($authId);
			return true;
		}
		$token = $request->getHeaderLine('authorization');
		if (empty($token)) return false;
		$key = sprintf(AdminUser::USER_TOKEN_KEY, $token);
		$tmp = AppFunc::redisGetKey($key);
		if (empty($tmp)) return false;
		AppFunc::redisSetStr($key, $tmp);
		$this->auth = json_decode($tmp, true);
		return true;
	}
	
	/**
	 * 操作记录
	 * @throws
	 */
	protected function Record(): bool
	{
		$request = $this->request();
		$data = [
			'uid' => $this->auth['id'],
			'url' => $request->getUri()->getPath(),
			'data' => json_encode($request->getParsedBody()),
		];
		LogModel::getInstance()->insert($data);
		return true;
	}
	
	/**
	 * 请求前执行
	 * @param string|null $action
	 * @return bool|null
	 */
	public function onRequest(?string $action): ?bool
	{
		$request = $this->request();
		$params = $request->getRequestParam();
		$this->params = empty($params) ? [] : $params;
		$isOk = $this->checkToken();
		if ($this->needCheckToken && !$this->checkToken() && !$isOk) {
			$this->writeJson(Status::CODE_VERIFY_ERR, '登录令牌缺失或者已过期');
			return false;
		}
		$route = $request->getUri()->getPath();
		if ($this->isCheckSign && !in_array($route, $this->ignoreCheckRoutes) && !empty($params)) {
			return $this->checkSign($params);
		}
		return parent::onRequest($action);
	}
}