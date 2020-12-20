<?php

namespace App\HttpController\User;

use Throwable;
use App\lib\Tool;
use App\Task\TestTask;
use App\Common\AppFunc;
use App\Model\AdminUser;
use App\lib\PasswordTool;
use App\Storage\OnlineUser;
use EasySwoole\Redis\Redis;
use easySwoole\Cache\Cache;
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
use EasySwoole\Mysqli\Exception\Exception;
use App\Model\AdminUserInterestCompetition;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\RedisPool\Redis as RedisPool;
use App\Utility\Message\Status as StatusMapper;
use EasySwoole\ORM\Exception\Exception as OrmException;

class Login extends FrontUserController
{
	protected $isCheckSign = false;
	protected $needCheckToken = false;
	const DEFAULT_PHOTO = 'http://live-broadcast-system.oss-cn-hongkong.aliyuncs.com/859c3661cbcc2902.jpg';
	
	/**
	 * 用户登录
	 * @throws OrmException | Exception | Throwable
	 */
	public function userLogin(): bool
	{
		// 参数校验
		$validator = new Validate();
		$validator->addColumn('mobile', '手机号码')
			->required('手机号不能为空')->regex('/^1\d{10}/', '手机号格式不正确');
		$validator->addColumn('type')->required();
		if (!$validator->validate($this->params)) {
			$this->output(Status::CODE_BAD_REQUEST, $validator->getError()->__toString());
		}
		// 手机号
		$mobile = $this->params['mobile'];
		// 登录方式: 1手机号登录, 2账号密码登录
		$type = intval($this->params['type']);
		if ($type != 1 && $type != 2) $this->output(StatusMapper::CODE_W_PARAM, StatusMapper::$msg[StatusMapper::CODE_W_PARAM]);
		// 手机验证码校验
		/*
		if ($type == 1) {
			$isCodeEmpty = empty($this->params['code']);
			$codeInfo = $isCodeEmpty ? null : AdminUserPhonecode::getInstance()->getLastCodeByMobile($mobile);
			// 验证码错误
			if ($isCodeEmpty || empty($codeInfo['code'])) {
				return $this->writeJson(Statuses::CODE_W_PARAM, Statuses::$msg[Statuses::CODE_W_PARAM]);
			}
			// 验证码不匹配
			if ($codeInfo['code'] != $this->params['code']) {
				return $this->writeJson(Statuses::CODE_W_PARAM, Statuses::$msg[Statuses::CODE_W_PARAM]);
			}
		}
		*/
		// 获取用户信息
		$statusArr = [AdminUser::STATUS_NORMAL, AdminUser::STATUS_REPORTED, AdminUser::STATUS_FORBIDDEN];
		$user = AdminUser::getInstance()->where('mobile', $mobile)->where('status', $statusArr, 'in')->get();
		// 用户不存在
		if (empty($user)) {
			$this->output(StatusMapper::CODE_W_PHONE, StatusMapper::$msg[StatusMapper::CODE_W_PHONE]);
		}
		// 密码错误
		if ($type == 2 && !PasswordTool::getInstance()->checkPassword($this->params['password'], $user['password_hash'])) {
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
		$cid = empty($this->params['cid']) ? '' : trim($this->params['cid']);
		if (!empty($cid)) {
			$user->cid = $cid;
			$user->update();
		}
		// 缓存登录标识
		$timestamp = time();
		$userId = $user['id'];
		$token = md5($userId . Config::getInstance()->getConf('app.token') . $timestamp);
		RedisPool::invoke('redis', function (Redis $redis) use ($userId, $token) {
			$redis->set(sprintf(UserModel::USER_TOKEN_KEY, $token), $userId);
		});
		// 长链接绑定
		$fd = empty($this->params['fd']) ? false : $this->params['fd'];
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
	 * 退出登录
	 */
	public function doLogout(): bool
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
	
	/**
	 * 发送短信验证码
	 */
	public function userSendSmg(): bool
	{
		// 参数校验
		$validator = new Validate();
		$validator->addColumn('mobile', '手机号码')
			->required('手机号不能为空')->regex('/^1[3456789]\d{9}$/', '手机号格式不正确');
		if (!$validator->validate($this->params)) {
			$this->output(StatusMapper::CODE_W_PARAM, $validator->getError()->__toString());
		}
		$mobile = $this->params['mobile'];
		$code = Tool::getInstance()->generateCode();
		// 异步task
		$result = TaskManager::getInstance()->async(new TestTask([
			'code' => $code,
			'mobile' => $mobile,
			'name' => '短信验证码',
		]));
		$this->output(StatusMapper::CODE_OK, '验证码以发送至尾号' . substr($mobile, -4) . '手机', $result);
	}
	
	/**
	 * 微信绑定接口
	 * @throws OrmException | Exception | Throwable
	 */
	public function bindWx(): bool
	{
		// 参数校验
		$validator = new Validate();
		$validator->addColumn('access_token')->required('access_token不能为空');
		$validator->addColumn('open_id')->required('open_id不能为空');
		if (!$validator->validate($this->params)) {
			$this->output(StatusMapper::CODE_ERR, $validator->getError()->__toString());
		}
		// 获取用户信息
		$userId = $this->request()->getCookieParams('front_id');
		$user = AdminUser::create()->get(['id' => $userId]);
		if (empty($user)) {
			$this->output(StatusMapper::CODE_LOGIN_ERR, StatusMapper::$msg[StatusMapper::CODE_LOGIN_ERR]);
		}
		$params = $this->params;
		// 获取三方微信账户信息
		$wxInfo = AdminUser::getInstance()->getWxUser($params['access_token'], $params['open_id']);
		$wxInfo = empty($wxInfo) ? [] : json_decode($wxInfo, true);
		if (json_last_error()) {
			$this->output(StatusMapper::CODE_ERR, 'json parse error');
		}
		if (!empty($wxInfo['errcode'])) {
			$this->output(StatusMapper::CODE_ERR, $wxInfo['errmsg']);
		}
		// 判断是否微信已绑定
		$user = AdminUser::getInstance()->where('third_wx_unionid', base64_encode($wxInfo['unionid']))->get();
		if (!empty($user)) {
			$this->output(StatusMapper::CODE_BIND_WX, StatusMapper::$msg[StatusMapper::CODE_BIND_WX]);
		}
		// 更新用户数据
		$data = [
			'photo' => $wxInfo['headimgurl'],
			'wx_name' => $wxInfo['nickname'],
			'wx_photo' => $wxInfo['headimgurl'],
			'third_wx_unionid' => base64_encode($wxInfo['unionid']),
		];
		if (!AdminUser::create()->update($data, $userId)) {
			$this->output(StatusMapper::CODE_BINDING_ERR, StatusMapper::$msg[StatusMapper::CODE_BINDING_ERR]);
		}
		$this->output(StatusMapper::CODE_OK, StatusMapper::$msg[StatusMapper::CODE_OK], $wxInfo);
	}
	
	/**
	 * 微信登录
	 * @throws OrmException | Exception | Throwable
	 */
	public function wxLogin(): bool
	{
		// 获取三方微信账户信息
		$params = $this->params;
		$wxInfo = AdminUser::getInstance()->getWxUser($params['access_token'], $params['open_id']);
		$wxInfo = empty($wxInfo) ? [] : json_decode($wxInfo, true);
		if (json_last_error()) {
			$this->output(StatusMapper::CODE_ERR, 'json parse error');
		}
		if (!empty($wxInfo['errcode'])) {
			$this->output(StatusMapper::CODE_ERR, $wxInfo['errmsg']);
		}
		$unionId = empty($wxInfo['unionid']) ? '' : base64_encode($wxInfo['unionid']);
		$user = empty($unionId) ? false : AdminUser::getInstance()->where('third_wx_unionid', $unionId)->get();
		if (empty($user)) {
			$wxInfo = [
				'third_wx_unionid' => $unionId,
				'wx_name' => $wxInfo['nickname'],
				'wx_photo' => $wxInfo['headimgurl'],
			];
			$this->output(StatusMapper::CODE_UNBIND_WX, StatusMapper::$msg[StatusMapper::CODE_UNBIND_WX], $wxInfo);
		}
		// 更新用户数据
		$userId = $user['id'];
		$cid = $params['cid'];
		if (!empty($cid)) {
			$user->cid = $cid;
			$user->device_type = empty($params['device_type']) ? 0 : $params['device_type'];
			$user->update();
		}
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
	 * 用户注册
	 * @return bool
	 * @throws Throwable
	 */
	public function logon(): bool
	{
		// 参数校验
		$validator = new Validate();
		$validator->addColumn('nickname')->required();
		$validator->addColumn('mobile')->required();
		$validator->addColumn('password')->required();
		if (!$validator->validate($this->params)) {
			$this->output(StatusMapper::CODE_W_PARAM, StatusMapper::$msg[StatusMapper::CODE_W_PARAM]);
		}
		// 敏感词校验
		$params = $this->params;
		$nickname = trim($params['nickname']);
		$sensitive = AdminSensitive::getInstance()->where('word', '%' . $nickname . '%', 'like')->get();
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
		if (AdminUser::getInstance()->where('nickname', $nickname)->get()) {
			$this->output(StatusMapper::CODE_USER_DATA_EXIST, StatusMapper::$msg[StatusMapper::CODE_USER_DATA_EXIST]);
		}
		if (AdminUser::getInstance()->where('mobile', $params['mobile'])->get()) {
			$this->output(StatusMapper::CODE_PHONE_EXIST, StatusMapper::$msg[StatusMapper::CODE_PHONE_EXIST]);
		}
		// 密码格式校验
		if (!preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,16}$/', $params['password'])) {
			$this->output(StatusMapper::CODE_W_FORMAT_PASS, StatusMapper::$msg[StatusMapper::CODE_W_FORMAT_PASS]);
		}
		// 密码处理
		$password = PasswordTool::getInstance()->generatePassword($params['password']);
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
			// 注册失败
			if (empty($userId) || $userId < 1) {
				$this->output(StatusMapper::CODE_ERR, '用户注册失败');
			}
			// 用户设置
			$timestamp = time();
			$token = md5($userId . Config::getInstance()->getConf('app.token') . $timestamp);
			RedisPool::invoke('redis', function (Redis $redis) use ($userId, $token) {
				$redis->set(sprintf(UserModel::USER_TOKEN_KEY, $token), $userId);
			});
			$notice = [
				'goal' => 1, //进球
				'over' => 1, //结束
				'yellow' => 1, //黄牌
				'red_card' => 1, //红牌
				'start' => 1, //比赛开始
				'show_time_axis' => 1, //显示时间轴
				'only_notice_my_interest' => 0,  //仅提示我关注的
			];
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
				$competitions = AdminSysSettings::getInstance()->where('sys_key', 'recommond_com')->get();
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
		} catch (\Exception $e) {
			$this->output(StatusMapper::CODE_ERR, '用户不存在或密码错误');
		}
		// 获取新增的用户信息
		$user = AdminUser::getInstance()->get($userId);
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
	 * 校验手机验证码
	 * @throws OrmException | Exception | Throwable
	 */
	public function checkPhoneCode(): bool
	{
		// 参数校验
		$params = $this->params;
		if (empty($params['code']) || empty($params['mobile'])) {
			$this->output(StatusMapper::CODE_W_PARAM, StatusMapper::$msg[StatusMapper::CODE_W_PARAM]);
		}
		// 获取验证码信息
		$tmp = AdminUserPhonecode::getInstance()->getLastCodeByMobile($params['mobile']);
		if (empty($tmp['status']) || $tmp['code'] != $params['code']) {
			$this->output(StatusMapper::CODE_W_PHONE_CODE, StatusMapper::$msg[StatusMapper::CODE_W_PHONE_CODE]);
		}
		// 手机号用户已存在
		if (AdminUser::getInstance()->where('mobile', $params['mobile'])->get()) {
			$this->output(StatusMapper::CODE_PHONE_EXIST, StatusMapper::$msg[StatusMapper::CODE_PHONE_EXIST]);
		}
		$this->output(StatusMapper::CODE_OK, StatusMapper::$msg[StatusMapper::CODE_OK]);
	}
	
	/**
	 * 忘记密码
	 * @throws OrmException | Throwable
	 */
	public function forgetPass(): bool
	{
		// 参数校验
		$validator = new Validate();
		$validator->addColumn('password', '密码')
			->required('密码不能为空');
		$validator->addColumn('phone_code', '验证码')
			->required('验证码不能为空');
		$validator->addColumn('mobile', '手机号码')
			->required('手机号不能为空')->regex('/^1[3456789]\d{9}$/', '手机号格式不正确');
		if (!$validator->validate($this->params)) {
			$this->output(StatusMapper::CODE_W_PARAM, $validator->getError()->__toString());
		}
		$params = $this->params;
		// 获取用户信息
		$mobile = $params['mobile'];
		$user = AdminUser::getInstance()->where('mobile', $mobile)->get();
		if (empty($user)) {
			$this->output(StatusMapper::CODE_USER_NOT_EXIST, StatusMapper::$msg[StatusMapper::CODE_USER_NOT_EXIST]);
		}
		// 验证码校验
		$code = $params['phone_code'];
		$tmp = AdminUserPhonecode::getInstance()->getLastCodeByMobile($mobile);
		if (empty($tmp['status']) || $tmp['code'] != $code) {
			$this->output(StatusMapper::CODE_W_PHONE_CODE, StatusMapper::$msg[StatusMapper::CODE_W_PHONE_CODE]);
		}
		// 密码校验
		$password = $params['password'];
		if (!preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,16}$/', $password)) {
			$this->output(StatusMapper::CODE_W_FORMAT_PASS, StatusMapper::$msg[StatusMapper::CODE_W_FORMAT_PASS]);
		}
		// 密码更新
		$password = PasswordTool::getInstance()->generatePassword($password);
		$user->password_hash = $password;
		$user->update();
		$this->output(StatusMapper::CODE_OK, StatusMapper::$msg[StatusMapper::CODE_OK]);
	}
}