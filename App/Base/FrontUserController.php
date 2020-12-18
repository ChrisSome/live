<?php

namespace App\Base;

use App\Model\AdminUser;
use EasySwoole\EasySwoole\Config;

use App\Model\AdminLog as LogModel;

use App\Common\AppFunc;
use App\Utility\Message\Status;
use EasySwoole\Template\Render;


/**
 * 前台用户基类
 * Class FrontUserController
 * @package App\Base
 */
class FrontUserController extends BaseController
{
	protected $auth;   // 保存了登录用户的信息
	protected $role_group;
	protected $isCheckSign = false;



    public function render(string $template, array $data = [])
    {
        $api = $this->request()->getUri()->getPath();
        $apis = explode('/', $api);
        if ($this->auth) {
            $data = array_merge(['realname' => $this->auth['realname'], 'auth' => $this->auth], $data);
        }
        $data = array_merge($data, [
            'module' => strtolower($apis[1]),
            'action' => isset($apis[2]) ? strtolower($apis[2]) : 0
        ]);

        $this->response()->write(Render::getInstance()->render($template, $data));
    }

    public $no_need_sign_keys = ['content', 'id'];
    private $sign_key = 'sign';
    /**
     * 加密或者验签
     * @param $params
     * @param bool $checked
     * @return bool|string
     */
    public function checkSign($params) {
        ksort($params); //ascii升序
        $sSafeStr = ''; //加密字段
        foreach ($params as $k => $v) {
            if ($k != $this->sign_key &&  !in_array($k, $this->no_need_sign_keys)) {
                $sSafeStr .= $k.'='.$v.'&';
            }
        }

        $sSafeStr = rtrim($sSafeStr, '&');
        if (!isset($params[$this->sign_key]) || md5($sSafeStr) != $params[$this->sign_key]) {
            $this->writeJson(403, '验签不通过');
            return false;
        }
        return true;
    }

	// 检查token 是否合法
	public function checkToken()
	{
		$r = $this->request();
		$id = $r->getCookieParams('front_id');
		$time = $r->getCookieParams('front_time');
		$token = md5($id . Config::getInstance()->getConf('app.token') . $time);
        if (!$id) {
            return false;
        }
        $this->auth['id'] = 0;
        if($r->getCookieParams('front_token') == $token) {
            $this->auth = AdminUser::getInstance()->find($id);
			return true;
		} else if ($token = $r->getHeaderLine('authorization')) {
		    //头部传递access_token
		    $tokenKey = sprintf(AdminUser::USER_TOKEN_KEY, $token);

		    if (!$json = AppFunc::redisGetKey($tokenKey)) {
                return false;
            } else {
		        AppFunc::redisSetStr($tokenKey, $json);
		        $this->auth = json_decode($json, true);
		        return true;
            }
        } else {
			return false;
		}
	}

	// 操作记录
	protected function Record()
	{
		$data = [
			'url'  => $this->request()->getUri()->getPath(),
			'data' => json_encode($this->request()->getParsedBody()),
			'uid'  => $this->auth['id']
		];
		LogModel::getInstance()->insert($data);
		return true;
	}

	// get 请求是否有权限访问
	public function  hasRuleForGet($rule)
	{
		if(!$this->role_group->hasRule($rule)) {
			$this->show404();
			return false;
		}

		return true;
	}

	// post 请求是否有权限访问
	public function  hasRuleForPost($rule)
	{
		if(!$this->role_group->hasRule($rule)) {
			$this->writeJson(Status::CODE_RULE_ERR,'权限不足');
			return false;
		}
		return true;
	}


	public $params;
    public $needCheckToken = false;
    public $needLogin = false;

	public $no_need_check_rule = [
        '/User/Login',
        '/User/System/detail',
        '/User/Post/detail',
        '/User/User/userSendSmg',
    ];

	public function onRequest(?string $action): ?bool
	{
	    $this->params = $this->request()->getRequestParam();
	    if ($this->needCheckToken) {
	        if(!$this->checkToken()) {
	            $this->writeJson(Status::CODE_VERIFY_ERR, '登陆令牌缺失或者已过期');
	            return false;

            }
        } else {
            $id = $this->request()->getCookieParams('front_id');
	        if ($id) {
                $this->checkToken();
            }
        }
        $api = $this->request()->getUri()->getPath();
	    if ($this->isCheckSign && !in_array($api, $this->no_need_check_rule) && !empty($this->params)) {
	        return $this->checkSign($this->params);
        }


	    return parent::onRequest($action);
	}

	public function dataJson($data)
	{
        if (!$this->response()->isEndResponse()) {
            $this->response()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->response()->withHeader('Content-type', 'application/json;charset=utf-8');
            return true;
        } else {
            return false;
        }
	}

	// 获取 page limit 信息
	public function getPage()
	{
		$request = $this->request();
		$data = $request->getRequestParam('page','limit');
		$data['page'] =  $data['page']?:1;
		$data['limit'] =  $data['limit']?:10;
		return $data;
	}
}