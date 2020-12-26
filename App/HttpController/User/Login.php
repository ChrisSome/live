<?php

namespace App\HttpController\User;

use Exception;
use App\lib\Tool;
use App\Common\AppFunc;
use App\Task\PhoneTask;
use App\Model\AdminUser;
use App\lib\PasswordTool;
use App\Storage\OnlineUser;
use EasySwoole\Redis\Redis;
use easySwoole\Cache\Cache;
use App\Task\SerialPointTask;
use App\Model\AdminSensitive;
use App\Model\AdminSysSettings;
use App\Model\AdminUserSetting;
use Ritaswc\ZxIPAddress\IPv4Tool;
use App\Base\FrontUserController;
use App\Model\AdminUserPhonecode;
use EasySwoole\EasySwoole\Config;
use EasySwoole\Validate\Validate;
use EasySwoole\Http\Message\Status;
use App\Model\AdminUser as UserModel;
use App\Model\AdminUserInterestCompetition;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\RedisPool\Redis as RedisPool;
use App\Utility\Message\Status as StatusMapper;

class Login extends FrontUserController
{
	protected $isCheckSign = false;
	protected $needCheckToken = false;
	const DEFAULT_PHOTO = 'http://live-broadcast-system.oss-cn-hongkong.aliyuncs.com/859c3661cbcc2902.jpg';
	
	/**
	 * 用户登录
	 * @throws
	 */
	public function userLogin()
	{
		// 参数校验
		$validator = new Validate();
		$validator->addColumn('mobile', '手机号码')->required('手机号不能为空')->regex('/^1\d{10}/', '手机号格式不正确');
		$validator->addColumn('type')->required();
		if (!$validator->validate($this->param())) {
			$this->output(Status::CODE_BAD_REQUEST, $validator->getError()->__toString());
		}
		// 手机号
		$mobile = $this->param('mobile');
		// 登录方式: 1手机号登录, 2账号密码登录
		$type = $this->param('type', true);
		if ($type != 1 && $type != 2) $this->output(StatusMapper::CODE_W_PARAM, StatusMapper::$msg[StatusMapper::CODE_W_PARAM]);
		// 手机验证码校验
		if ($type == 1) {
			$code = $this->param('code');
			$codeInfo = empty($code) ? null : AdminUserPhonecode::getInstance()->getLastCodeByMobile($mobile);
			// 验证码错误
			if (empty($code) || empty($codeInfo['code'])) {
				$this->output(StatusMapper::CODE_W_PARAM, StatusMapper::$msg[StatusMapper::CODE_W_PARAM]);
			}
			// 验证码不匹配
			if ($codeInfo['code'] != $code) {
				$this->output(StatusMapper::CODE_W_PARAM, StatusMapper::$msg[StatusMapper::CODE_W_PARAM]);
			}
		}
		// 获取用户信息
		$statusArr = [AdminUser::STATUS_NORMAL, AdminUser::STATUS_REPORTED, AdminUser::STATUS_FORBIDDEN];
		$user = AdminUser::getInstance()->findOne(['mobile' => $mobile, 'status' => [$statusArr, 'in']]);
		if (empty($user)) {
			$this->output(StatusMapper::CODE_PHONE_NOT_EXISTS, StatusMapper::$msg[StatusMapper::CODE_PHONE_NOT_EXISTS]);
		}
		// 密码错误
		$password = $this->param('password');
		if ($type == 2 && !PasswordTool::getInstance()->checkPassword($password, $user['password_hash'])) {
			$this->output(StatusMapper::CODE_W_PHONE, StatusMapper::$msg[StatusMapper::CODE_W_PHONE]);
		}
		// 用户已被禁用
		if ($user['status'] == AdminUser::STATUS_BAN) {
			$this->output(StatusMapper::CODE_USER_STATUS_BAN, StatusMapper::$msg[StatusMapper::CODE_USER_STATUS_BAN]);
		}
		// 用户已被注销
		if ($user['status'] == AdminUser::STATUS_CANCEL) {
			$this->output(StatusMapper::CODE_USER_STATUS_CANCLE, StatusMapper::$msg[StatusMapper::CODE_USER_STATUS_CANCLE]);
		}
		// 更新用户手机cid
		$cid = $this->param('cid');
		if (!empty($cid)) AdminUser::getInstance()->setField('cid', $cid, $user['id']);
		// 缓存登录标识
		$timestamp = time();
		$userId = $user['id'];
		$token = md5($userId . Config::getInstance()->getConf('app.token') . $timestamp);
		RedisPool::invoke('redis', function (Redis $redis) use ($userId, $token) {
			$redis->set(sprintf(UserModel::USER_TOKEN_KEY, $token), $userId);
		});
		// 长链接绑定
		$fd = $this->param('fd');
		if (!empty($fd)) {
			$data = [
				'fd' => $fd,
				'match_id' => 0,
				'user_id' => $userId,
				'level' => $user['level'],
				'last_heartbeat' => $timestamp,
				'nickname' => $user['nickname'],
			];
			$tmp = OnlineUser::getInstance()->get($fd);
			if (empty($tmp)) OnlineUser::getInstance()->set($fd, $data);
			if (!empty($tmp)) OnlineUser::getInstance()->update($fd, $data);
		}
		// 封装用户信息
		$userInfo = [
			'id' => $userId,
			'photo' => $user['photo'],
			'point' => $user['point'],
			'level' => $user['level'],
			'status' => $user['status'],
			'mobile' => $user['mobile'],
			'wx_name' => $user['wx_name'],
			'nickname' => $user['nickname'],
			'is_offical' => $user['is_offical'],
			'notice_setting' => json_decode($user->userSetting()->notice, true),
		];
		$this->response()->setCookie('front_id', $userId);
		$this->response()->setCookie('front_token', $token);
		$this->response()->setCookie('front_time', $timestamp);
		$this->output(StatusMapper::CODE_OK, StatusMapper::$msg[StatusMapper::CODE_OK], $userInfo);
	}
	
	/**
	 * 微信登录
	 * @throws
	 */
	public function wxLogin()
	{
		// 参数校验
		$validator = new Validate();
		$validator->addColumn('access_token')->required('access_token不能为空');
		$validator->addColumn('open_id')->required('open_id不能为空');
		if (!$validator->validate($this->param())) {
			$this->output(StatusMapper::CODE_ERR, $validator->getError()->__toString());
		}
		$params = $this->param();
		// 获取微信用户信息
		$wxInfo = AdminUser::getInstance()->getWxUser($params['access_token'], $params['open_id']);
		$wxInfo = empty($wxInfo) ? [] : json_decode($wxInfo, true);
		if (json_last_error()) $this->output(StatusMapper::CODE_ERR, 'json parse error');
		if (!empty($wxInfo['errcode'])) $this->output(StatusMapper::CODE_ERR, $wxInfo['errmsg']);
		$unionId = empty($wxInfo['unionid']) ? '' : base64_encode($wxInfo['unionid']);
		$user = empty($unionId) ? false : AdminUser::getInstance()->findOne(['third_wx_unionid' => $unionId]);
		if (empty($user)) {
			$result = [
				'third_wx_unionid' => $unionId,
				'wx_name' => $wxInfo['nickname'],
				'wx_photo' => $wxInfo['headimgurl'],
			];
			$this->output(StatusMapper::CODE_UNBIND_WX, StatusMapper::$msg[StatusMapper::CODE_UNBIND_WX], $result);
		}
		// 更新用户数据
		$userId = $user['id'];
		$cid = empty($params['cid']) ? '' : trim($params['cid']);
		if (!empty($cid)) $user->saveDataById($userId, [
			'cid' => $cid,
			'device_type' => empty($params['device_type']) || intval($params['device_type']) < 1 ? 0 : intval($params['device_type']),
		]);
		// 缓存登录标识
		$timestamp = time();
		$token = md5($userId . Config::getInstance()->getConf('app.token') . $timestamp);
		RedisPool::invoke('redis', function (Redis $redis) use ($userId, $token) {
			$redis->set(sprintf(UserModel::USER_TOKEN_KEY, $token), $userId);
		});
		// 长链接绑定
		$fd = empty($params['fd']) ? false : $params['fd'];
		if (!empty($fd)) {
			$data = [
				'fd' => $fd,
				'match_id' => 0,
				'user_id' => $userId,
				'level' => $user['level'],
				'last_heartbeat' => $timestamp,
				'nickname' => $user['nickname'],
			];
			$tmp = OnlineUser::getInstance()->get($fd);
			if (empty($tmp)) OnlineUser::getInstance()->set($fd, $data);
			if (!empty($tmp)) OnlineUser::getInstance()->update($fd, $data);
		}
		// 封装用户信息
		$setting = $user->userSetting();
		$result = [
			'id' => $userId,
			'photo' => $user['photo'],
			'point' => $user['point'],
			'level' => $user['level'],
			'status' => $user['status'],
			'mobile' => $user['mobile'],
			'wx_name' => $user['wx_name'],
			'nickname' => $user['nickname'],
			'is_offical' => $user['is_offical'],
			'notice_setting' => empty($setting['notice']) ? [] : json_decode($setting['notice'], true),
		];
		$this->response()->setCookie('front_id', $userId);
		$this->response()->setCookie('front_token', $token);
		$this->response()->setCookie('front_time', $timestamp);
		$this->output(StatusMapper::CODE_OK, StatusMapper::$msg[StatusMapper::CODE_OK], $result);
	}
	
	/**
	 * 忘记密码
	 * @throws
	 */
	public function forgetPass()
	{
		// 参数校验
		$validator = new Validate();
		$validator->addColumn('password', '密码')
			->required('密码不能为空');
		$validator->addColumn('phone_code', '验证码')
			->required('验证码不能为空');
		$validator->addColumn('mobile', '手机号码')
			->required('手机号不能为空')->regex('/^1[3456789]\d{9}$/', '手机号格式不正确');
		if (!$validator->validate($this->param())) {
			$this->output(StatusMapper::CODE_W_PARAM, $validator->getError()->__toString());
		}
		// 获取用户信息
		$mobile = $this->param('mobile');
		$user = AdminUser::getInstance()->findOne(['mobile' => $mobile]);
		if (empty($user)) {
			$this->output(StatusMapper::CODE_USER_NOT_EXIST, StatusMapper::$msg[StatusMapper::CODE_USER_NOT_EXIST]);
		}
		// 验证码校验
		$code = $this->param('phone_code');
		$tmp = AdminUserPhonecode::getInstance()->getLastCodeByMobile($mobile);
		if (empty($tmp) || $tmp['code'] != $code) {
			$this->output(StatusMapper::CODE_W_PHONE_CODE, StatusMapper::$msg[StatusMapper::CODE_W_PHONE_CODE]);
		}
		// 密码校验
		$password = $this->param('password');
		if (!preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,16}$/', $password)) {
			$this->output(StatusMapper::CODE_W_FORMAT_PASS, StatusMapper::$msg[StatusMapper::CODE_W_FORMAT_PASS]);
		}
		// 密码更新
		$password = PasswordTool::getInstance()->generatePassword($password);
		AdminUser::getInstance()->setField('password_hash', $password, $user['id']);
		$this->output(StatusMapper::CODE_OK, StatusMapper::$msg[StatusMapper::CODE_OK]);
	}
	
	/**
	 * 微信绑定接口
	 * @throws
	 */
	public function bindWx()
	{
		// 参数校验
		$validator = new Validate();
		$validator->addColumn('access_token')->required('access_token不能为空');
		$validator->addColumn('open_id')->required('open_id不能为空');
		if (!$validator->validate($this->param())) {
			$this->output(StatusMapper::CODE_ERR, $validator->getError()->__toString());
		}
		// 获取用户信息
		$userId = $this->request()->getCookieParams('front_id');
		$userId = empty($userId) || intval($userId) < 1 ? 0 : intval($userId);
		$user = $userId < 1 ? null : AdminUser::getInstance()->findOne($userId);
		if (empty($user) || !empty($user['third_wx_unionid'])) {
			$this->output(StatusMapper::CODE_LOGIN_ERR, StatusMapper::$msg[StatusMapper::CODE_LOGIN_ERR]);
		}
		$params = $this->param();
		// 获取三方微信账户信息
		$wxInfo = AdminUser::getInstance()->getWxUser($params['access_token'], $params['open_id']);
		$wxInfo = empty($wxInfo) ? [] : json_decode($wxInfo, true);
		if (json_last_error()) $this->output(StatusMapper::CODE_ERR, 'json parse error');
		if (!empty($wxInfo['errcode'])) $this->output(StatusMapper::CODE_ERR, $wxInfo['errmsg']);
		// 判断是否微信已绑定
		$user = AdminUser::getInstance()->findOne(['third_wx_unionid' => base64_encode($wxInfo['unionid'])]);
		if (!empty($user)) $this->output(StatusMapper::CODE_BIND_WX, StatusMapper::$msg[StatusMapper::CODE_BIND_WX]);
		// 更新用户数据
		$data = [
			'photo' => $wxInfo['headimgurl'],
			'wx_name' => $wxInfo['nickname'],
			'wx_photo' => $wxInfo['headimgurl'],
			'third_wx_unionid' => base64_encode($wxInfo['unionid']),
		];
		if (!AdminUser::create()->saveDataById($userId, $data)) {
			$this->output(StatusMapper::CODE_BINDING_ERR, StatusMapper::$msg[StatusMapper::CODE_BINDING_ERR]);
		}else{
			//绑定完时候加积分
			TaskManager::getInstance()->async(new SerialPointTask(['task_id' => 'special', 'user_id' => $userId]));
			 $this->output(StatusMapper::CODE_OK, StatusMapper::$msg[StatusMapper::CODE_OK], $wxInfo);
		}
		$this->output(StatusMapper::CODE_OK, StatusMapper::$msg[StatusMapper::CODE_OK], $wxInfo);
	}
	
	/**
	 * 发送短信验证码
	 * @throws
	 */
	public function userSendSmg()
	{
		// 参数校验
		$validator = new Validate();
		$validator->addColumn('mobile', '手机号码')
			->required('手机号不能为空')->regex('/^1[3456789]\d{9}$/', '手机号格式不正确');
		if (!$validator->validate($this->param())) {
			$this->output(StatusMapper::CODE_W_PARAM, $validator->getError()->__toString());
		}
		$mobile = $this->param('mobile');
		$code = Tool::getInstance()->generateCode();
		// 异步task
		$result = TaskManager::getInstance()->async(new PhoneTask([
			'code' => $code,
			'mobile' => $mobile,
			'name' => '短信验证码',
		]));
		$this->output(StatusMapper::CODE_OK, '验证码以发送至尾号' . substr($mobile, -4) . '手机', $result);
	}
	
	/**
	 * 校验手机验证码
	 * @throws
	 */
	public function checkPhoneCode()
	{
		// 参数校验
		$code = $this->param('code');
		$mobile = $this->param('mobile');
		if (empty($code) || empty($mobile)) {
			$this->output(StatusMapper::CODE_W_PARAM, StatusMapper::$msg[StatusMapper::CODE_W_PARAM]);
		}
		// 获取验证码信息
		$tmp = AdminUserPhonecode::getInstance()->getLastCodeByMobile($mobile);
		if (empty($tmp) || $tmp['code'] != $code) {
			$this->output(StatusMapper::CODE_W_PHONE_CODE, StatusMapper::$msg[StatusMapper::CODE_W_PHONE_CODE]);
		}
		// 手机号用户已存在
		if (AdminUser::getInstance()->findOne(['mobile' => $mobile])) {
			$this->output(StatusMapper::CODE_PHONE_EXIST, StatusMapper::$msg[StatusMapper::CODE_PHONE_EXIST]);
		}
		$this->output(StatusMapper::CODE_OK, StatusMapper::$msg[StatusMapper::CODE_OK]);
	}
	
	/**
	 * 用户注册
	 * @throws
	 */
	public function logon()
	{
		// 参数校验
		$validator = new Validate();
		$validator->addColumn('nickname')->required();
		$validator->addColumn('mobile')->required();
		$validator->addColumn('password')->required();
		if (!$validator->validate($this->param())) {
			$this->output(StatusMapper::CODE_W_PARAM, StatusMapper::$msg[StatusMapper::CODE_W_PARAM]);
		}
		// 敏感词校验
		$params = $this->param();
		$nickname = trim($params['nickname']);
		$sensitive = AdminSensitive::getInstance()->findOne(['word' => [$nickname, 'like']]);
		if (!empty($sensitive)) {
			$this->output(StatusMapper::CODE_ADD_POST_SENSITIVE, sprintf(StatusMapper::$msg[StatusMapper::CODE_ADD_POST_SENSITIVE], $sensitive->word));
		}
		// 是否utf8编码
		if (AppFunc::have_special_char($nickname)) {
			$this->output(StatusMapper::CODE_UNVALID_CODE, StatusMapper::$msg[StatusMapper::CODE_UNVALID_CODE], $sensitive->word);
		}
		// 账号格式校验
		/*
        if (!preg_match('/^[a-zA-Z0-9_\u4e00-\u9fa5]{2,16}$/', $nickname)) {
            return $this->writeJson(Statuses::CODE_W_FORMAT_NICKNAME, Statuses::$msg[Statuses::CODE_W_FORMAT_NICKNAME]);
        }
        */
		// 是否重复
		if (AdminUser::getInstance()->findOne(['nickname' => $nickname])) {
			$this->output(StatusMapper::CODE_USER_DATA_EXIST, StatusMapper::$msg[StatusMapper::CODE_USER_DATA_EXIST]);
		}
		if (AdminUser::getInstance()->findOne(['mobile' => $params['mobile']])) {
			$this->output(StatusMapper::CODE_PHONE_EXIST, StatusMapper::$msg[StatusMapper::CODE_PHONE_EXIST]);
		}
		// 密码格式校验
		if (!preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,16}$/', $params['password'])) {
			$this->output(StatusMapper::CODE_W_FORMAT_PASS, StatusMapper::$msg[StatusMapper::CODE_W_FORMAT_PASS]);
		}
		// 密码处理
		$password = PasswordTool::getInstance()->generatePassword($params['password']);
		//
		$timestamp = time();
		$token = '';
		$userId = 0;
		$notice = [
			'goal' => 1, //进球
			'over' => 1, //结束
			'yellow' => 1, //黄牌
			'red_card' => 1, //红牌
			'start' => 1, //比赛开始
			'show_time_axis' => 1, //显示时间轴
			'only_notice_my_interest' => 0,  //仅提示我关注的
		];
		try {
			// 用户IP&区域等获取
			$ip = empty($this->request()->getHeaders()['x-real-ip'][0]) ? '' : $this->request()->getHeaders()['x-real-ip'][0];
			$tmp = empty($ip) ? '' : IPv4Tool::query($ip);
			if (!empty($tmp['addr'][0])) {
				$arr = explode('省', $tmp['addr'][0]);
				$province = $arr[0];
				$city = empty($arr[1]) ? '' : $arr[1];
				[$provinceCode, $cityCode] = AppFunc::getProvinceAndCityCode($province, $city);
			}
			// 插入用户数据
			$data = [
				'nickname' => $nickname,
				'password_hash' => $password,
				'mobile' => $params['mobile'],
				'sign_at' => date('Y-m-d H:i:s'),
				'cid' => empty($params['cid']) ? '' : $params['cid'],
				'wx_name' => empty($params['wx_name']) ? '' : $params['wx_name'],
				'wx_photo' => empty($params['wx_photo']) ? '' : $params['wx_photo'],
				'photo' => empty($params['wx_photo']) ? self::DEFAULT_PHOTO : $params['wx_photo'],
				'third_wx_unionid' => empty($params['third_wx_unionid']) ? '' : $params['third_wx_unionid'],
				'city_code' => empty($cityCode) ? 0 : intval($cityCode),
				'province_code' => empty($provinceCode) ? 0 : intval($provinceCode),
				'device_type' => empty($params['device_type']) ? 0 : $params['device_type'],
			];
			$userId = AdminUser::getInstance()->insert($data);
			if ($userId < 1) $this->output(StatusMapper::CODE_ERR, '用户注册失败');
			// 用户设置
			$token = md5($userId . Config::getInstance()->getConf('app.token') . $timestamp);
			RedisPool::invoke('redis', function (Redis $redis) use ($userId, $token) {
				$redis->set(sprintf(UserModel::USER_TOKEN_KEY, $token), $userId);
			});
			$push = ['start' => 1, 'goal' => 1, 'over' => 1, 'open_push' => 1, 'information' => 1];
			$private = ['see_my_post' => 1, 'see_my_post_comment' => 1, 'see_my_information_comment' => 1];
			TaskManager::getInstance()->async(function () use ($userId, $notice, $push, $private) {
				$settingData = [
					'user_id' => $userId,
					'push' => json_encode($push),
					'notice' => json_encode($notice),
					'private' => json_encode($private),
				];
				AdminUserSetting::getInstance()->insert($settingData);
				// 写用户关注赛事
				$competitions = AdminSysSettings::getInstance()->findOne(['sys_key' => 'recommond_com']);
				if (!empty($competitions)) {
					$competitionIds = [];
					foreach (json_decode($competitions->sys_value, true) as $item) {
						foreach ($item as $value) {
							$competitionIds[] = $value['competition_id'];
						}
					}
					$userInterestComData = [
						'user_id' => $userId,
						'competition_ids' => json_encode($competitionIds),
					];
					AdminUserInterestCompetition::getInstance()->insert($userInterestComData);
				}
			});
		} catch (Exception $e) {
			$this->output(StatusMapper::CODE_ERR, '用户不存在或密码错误');
		}
		// 获取新增的用户信息
		$user = AdminUser::getInstance()->findOne($userId);
		// 长链接绑定
		$fd = empty($params['fd']) ? false : $params['fd'];
		if (!empty($fd)) {
			$data = [
				'fd' => $fd,
				'match_id' => 0,
				'user_id' => $userId,
				'level' => $user['level'],
				'last_heartbeat' => $timestamp,
				'nickname' => $user['nickname'],
			];
			$tmp = OnlineUser::getInstance()->get($fd);
			if (empty($tmp)) OnlineUser::getInstance()->set($fd, $data);
			if (!empty($tmp)) OnlineUser::getInstance()->update($fd, $data);
		}
		// 封装用户信息
		$userInfo = [
			'id' => $userId,
			'photo' => $user['photo'],
			'point' => $user['point'],
			'level' => $user['level'],
			'status' => $user['status'],
			'mobile' => $user['mobile'],
			'wx_name' => $user['wx_name'],
			'nickname' => $user['nickname'],
			'is_offical' => $user['is_offical'],
			'notice_setting' => $notice,
		];
		$this->response()->setCookie('front_id', $userId);
		$this->response()->setCookie('front_token', $token);
		$this->response()->setCookie('front_time', $timestamp);
		$this->output(StatusMapper::CODE_OK, StatusMapper::$msg[StatusMapper::CODE_OK], $userInfo);
	}
	
	/**
	 * 退出登录
	 * @throws
	 */
	public function doLogout()
	{
		$key = sprintf(UserModel::USER_TOKEN_KEY, $this->auth['front_token']);
		$key = sprintf(UserModel::USER_TOKEN_KEY, Cache::get($key));
		RedisPool::invoke('redis', function (Redis $redis) use ($key) {
			$redis->del($key);
		});
		$this->response()->setCookie('front_id', '');
		$this->response()->setCookie('front_time', '');
		$this->response()->setCookie('front_token', '');
		$this->output(StatusMapper::CODE_OK, StatusMapper::$msg[StatusMapper::CODE_OK]);
	}
}