<?php


namespace App\HttpController\User;


use App\Base\FrontUserController;
use App\Common\AppFunc;
use App\lib\PasswordTool;
use App\lib\Tool;
use App\Model\AdminSensitive;
use App\Model\AdminSysSettings;
use App\Model\AdminUser;
use App\Model\AdminUser as UserModel;
use App\Model\AdminUserInterestCompetition;
use App\Model\AdminUserPhonecode;
use App\Model\AdminUserSetting;
use App\Storage\OnlineUser;
use App\Task\TestTask;
use easySwoole\Cache\Cache;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Http\Message\Status;
use EasySwoole\Redis\Redis as Redis;
use EasySwoole\RedisPool\Redis as RedisPool;
use EasySwoole\Validate\Validate;
use App\Utility\Message\Status as Statuses;


class Login extends FrontUserController
{
    protected $isCheckSign = false;
    public $needCheckToken = false;


    const DEFAULT_PHOTO = 'http://live-broadcast-system.oss-cn-hongkong.aliyuncs.com/859c3661cbcc2902.jpg';
    public function index()
    {
        return $this->render('front.user.login');
    }
    public function userLogin()
    {
        $valitor = new Validate();
        $valitor->addColumn('mobile', '手机号码')->required('手机号不为空')
            ->regex('/^1\d{10}/', '手机号格式不正确');
        $valitor->addColumn('type')->required();
        if (!$valitor->validate($this->params)) {
            return $this->writeJson(Status::CODE_BAD_REQUEST, $valitor->getError()->__toString());
        }

        if (!$type = $this->params['type']) {
            return $this->writeJson(Statuses::CODE_W_PARAM, Statuses::$msg[Statuses::CODE_W_PARAM]);
        }
        $mobile = $this->params['mobile'];
        if ($type == 1) {//手机号登录
//            if (!$this->params['code'] || !$mobile_code = AdminUserPhonecode::getInstance()->getLastCodeByMobile($mobile)) {
//                return $this->writeJson(Statuses::CODE_W_PARAM, Statuses::$msg[Statuses::CODE_W_PARAM]);
//
//            }
//            if ($mobile_code['code'] != $this->params['code']) {
//                return $this->writeJson(Statuses::CODE_W_PARAM, Statuses::$msg[Statuses::CODE_W_PARAM]);
//            }

            if (!$user = AdminUser::getInstance()->where('mobile', $mobile)->get()) {
                return $this->writeJson(Statuses::CODE_W_PHONE, Statuses::$msg[Statuses::CODE_W_PHONE]);
            } else if ($user->status == AdminUser::STATUS_BAN) {
                return $this->writeJson(Statuses::CODE_USER_STATUS_BAN, Statuses::$msg[Statuses::CODE_USER_STATUS_BAN]);

            } else if ($user->status == AdminUser::STATUS_CANCEL) {
                return $this->writeJson(Statuses::CODE_USER_STATUS_CANCLE, Statuses::$msg[Statuses::CODE_USER_STATUS_CANCLE]);

            }



        } else if ($type == 2) { //账号密码登录
            $password = $this->params['password'];

            if (!$user = AdminUser::getInstance()->where('mobile', $mobile)->where('status', [AdminUser::STATUS_NORMAL, AdminUser::STATUS_REPORTED, AdminUser::STATUS_FORBIDDEN], 'in')->get()) {
                return $this->writeJson(Statuses::CODE_W_PHONE, Statuses::$msg[Statuses::CODE_W_PHONE]);
            } else if (!PasswordTool::getInstance()->checkPassword($password, $user->password_hash)) {
                return $this->writeJson(Statuses::CODE_W_PHONE, Statuses::$msg[Statuses::CODE_W_PHONE]);
            }

        } else {
            return $this->writeJson(Statuses::CODE_W_PARAM, Statuses::$msg[Statuses::CODE_W_PARAM]);

        }
        if ($cid = $this->params['cid']) {
            $user->cid = $this->params['cid'];
            $user->update();
        }
        $time = time();
        $token = md5($user['id'] . Config::getInstance()->getConf('app.token') . $time);
        $uid = $user->id;
        RedisPool::invoke('redis', function(Redis $redis) use ($uid, $token) {
           $redis->set(sprintf(UserModel::USER_TOKEN_KEY, $token), $uid);
        });
        //长链接绑定
        $fd = $this->params['fd'];
        $data = [
            'fd' => $fd,
            'nickname' => $user->nickname,
            'user_id' => $user->id,
            'last_heartbeat' => time(),
            'match_id' => 0,
            'level' => $user->level
        ];
        if (OnlineUser::getInstance()->get($fd)) {
            OnlineUser::getInstance()->update($fd, $data);
        } else {
            OnlineUser::getInstance()->set($fd, $data);
        }
        $user_info = [
            'id' => $user->id,
            'nickname' => $user->nickname,
            'photo' => $user->photo,
            'point' => $user->point,
            'level' => $user->level,
            'is_offical' => $user->is_offical,
            'mobile' => $user->mobile,
            'notice_setting' => json_decode($user->userSetting()->notice, true),
            'wx_name' => $user->wx_name,
            'status' => $user->status

        ];
        $this->response()->setCookie('front_id', $user['id']);
        $this->response()->setCookie('front_token', $token);
        $this->response()->setCookie('front_time', $time);
        return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK], $user_info);


    }

    /**
     * 退出登陆
     */
    public function doLogout()
    {

        $sUserKey = sprintf(UserModel::USER_TOKEN_KEY, $this->auth['front_token']);
        $key = sprintf(UserModel::USER_TOKEN_KEY, Cache::get($sUserKey));
        RedisPool::invoke('redis', function(Redis $redis) use ($key) {
            $redis->del($key);
        });
        $this->response()->setCookie('front_token', '');
        $this->response()->setCookie('front_id', '');
        $this->response()->setCookie('front_time', '');

//        $this->response()->redirect("/api/user/login");
        return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK]);

    }



    /**
     * 用户短信验证码
     * 不需要type区分
     */
    public function userSendSmg()
    {


        $valitor = new Validate();
        $valitor->addColumn('mobile', '手机号码')->required('手机号不为空')
            ->regex('/^1[3456789]\d{9}$/', '手机号格式不正确');

        if ($valitor->validate($this->params)) {

            $mobile = $this->params['mobile'];

        } else {
            return $this->writeJson(Statuses::CODE_W_PARAM, $valitor->getError()->__toString());

        }

        $code = Tool::getInstance()->generateCode();
        //异步task

        $res = TaskManager::getInstance()->async(new TestTask([
            'code' => $code,
            'mobile' => $mobile,
            'name' => '短信验证码'
        ]));
        return $this->writeJson(Statuses::CODE_OK, '验证码以发送至尾号' . substr($mobile, -4) .'手机', $res);

    }



    /**
     * 微信绑定接口
     * @return bool
     */
    public function bindWx()
    {
        $params = $this->params;
        $valitor = new Validate();
        //验证参数
        $valitor->addColumn('access_token')->required('access_token不能为空');
        $valitor->addColumn('open_id')->required('open_id不能为空');
        $uid = $this->request()->getCookieParams('front_id');
        $user = AdminUser::create()->get(['id'=>$uid]);
        if (!$user) {
            return $this->writeJson(Statuses::CODE_LOGIN_ERR, Statuses::$msg[Statuses::CODE_LOGIN_ERR]);

        }
        if (!$valitor->validate($this->params)) {
            return $this->writeJson(Statuses::CODE_ERR, $valitor->getError()->__toString());
        }

        //获取三方微信账户信息
        $mThirdWxInfo = AdminUser::getInstance()->getWxUser($params['access_token'], $params['open_id']);
        $aWxInfo = json_decode($mThirdWxInfo, true);
        if (json_last_error()) {
            return $this->writeJson(Statuses::CODE_ERR, 'json parse error');
        }
        if (!empty($aWxInfo['errcode'])) {
            return $this->writeJson(Statuses::CODE_ERR, $aWxInfo['errmsg']);
        } else {
            if ($user = AdminUser::getInstance()->where('third_wx_unionid', base64_encode($aWxInfo['unionid']))->get()) {
                return $this->writeJson(Statuses::CODE_BIND_WX, Statuses::$msg[Statuses::CODE_BIND_WX]);

            }
            $wxInfo = [
                'wx_photo' => $aWxInfo['headimgurl'],
                'wx_name'  => $aWxInfo['nickname'],
                'third_wx_unionid' => base64_encode($aWxInfo['unionid']),
                'photo' => $aWxInfo['headimgurl']
            ];
            $bool = AdminUser::create()->update($wxInfo, ['id'=>$user['id']]);
            if (!$bool) {
                return $this->writeJson(Statuses::CODE_BINDING_ERR, Statuses::$msg[Statuses::CODE_BINDING_ERR]);
            } else {
                return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK], $wxInfo);

            }


        }

    }


    public function wxLogin()
    {
        $params = $this->params;
        //获取三方微信账户信息
        $mThirdWxInfo = AdminUser::getInstance()->getWxUser($params['access_token'], $params['open_id']);
        $aWxInfo = json_decode($mThirdWxInfo, true);
        if (json_last_error()) {
            return $this->writeJson(Statuses::CODE_ERR, 'json parse error');
        }

        if (!empty($aWxInfo['errcode'])) {
            return $this->writeJson(Statuses::CODE_ERR, $aWxInfo['errmsg']);
        } else {
            $wxInfo = [
                'wx_photo' => $aWxInfo['headimgurl'],
                'wx_name'  => $aWxInfo['nickname'],
                'third_wx_unionid' => base64_encode($aWxInfo['unionid']),
            ];
            if (!$user = AdminUser::getInstance()->where('third_wx_unionid', base64_encode($aWxInfo['unionid']))->get()) {
                return $this->writeJson(Statuses::CODE_UNBIND_WX, Statuses::$msg[Statuses::CODE_UNBIND_WX], $wxInfo);
            } else {
                if ($cid = $this->params['cid']) {
                    $user->cid = $this->params['cid'];
                    $user->device_type = $this->params['device_type'];
                    $user->update();
                }
                $time = time();
                $token = md5($user['id'] . Config::getInstance()->getConf('app.token') . $time);
                $uid = $user->id;
                RedisPool::invoke('redis', function(Redis $redis) use ($uid, $token) {
                    $redis->set(sprintf(UserModel::USER_TOKEN_KEY, $token), $uid);
                });
                //长链接绑定
                $fd = $this->params['fd'];
                $data = [
                    'fd' => $fd,
                    'nickname' => $user->nickname,
                    'user_id' => $user->id,
                    'last_heartbeat' => time(),
                    'match_id' => 0,
                    'level' => $user->level
                ];

                if (OnlineUser::getInstance()->get($fd)) {
                    OnlineUser::getInstance()->update($fd, $data);
                } else {
                    OnlineUser::getInstance()->set($fd, $data);
                }

                $user_info = [
                    'id' => $user->id,
                    'nickname' => $user->nickname,
                    'photo' => $user->photo,
                    'point' => $user->point,
                    'level' => $user->level,
                    'is_offical' => $user->is_offical,
                    'mobile' => $user->mobile,
                    'notice_setting' => json_decode($user->userSetting()->notice, true),
                    'wx_name' => $user->wx_name,
                    'status' => $user->status

                ];
                $this->response()->setCookie('front_id', $user['id']);
                $this->response()->setCookie('front_token', $token);
                $this->response()->setCookie('front_time', $time);
                return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK], $user_info);

            }


        }
    }

    /**
     * 注册
     * @return bool
     * @throws \Exception
     */
    public function logon()
    {


        $validator = new Validate();
        $validator->addColumn('nickname')->required();
        $validator->addColumn('mobile')->required();
        $validator->addColumn('password')->required();
        if (!$validator->validate($this->params)) {
            return $this->writeJson(Statuses::CODE_W_PARAM, Statuses::$msg[Statuses::CODE_W_PARAM]);
        }


        if ($sensitive = AdminSensitive::getInstance()->where('word', '%' . trim($this->params['nickname']) . '%', 'like')->get()) {
            //敏感词
            return $this->writeJson(Statuses::CODE_ADD_POST_SENSITIVE, sprintf(Statuses::$msg[Statuses::CODE_ADD_POST_SENSITIVE], $sensitive->word));
        } else if (AppFunc::have_special_char($this->params['nickname'])) {
            //是否utf8编码
            return $this->writeJson(Statuses::CODE_UNVALID_CODE, Statuses::$msg[Statuses::CODE_UNVALID_CODE], $sensitive->word);

        }
//        else if (!preg_match('/^[a-zA-Z0-9_\u4e00-\u9fa5]{2,16}$/', $this->params['nickname'])) {
//            //昵称
//            return $this->writeJson(Statuses::CODE_W_FORMAT_NICKNAME, Statuses::$msg[Statuses::CODE_W_FORMAT_NICKNAME]);
//
//        }
        else if (AdminUser::getInstance()->where('nickname', $this->params['nickname'])->get()) {
            //是否重复
            return $this->writeJson(Statuses::CODE_USER_DATA_EXIST, Statuses::$msg[Statuses::CODE_USER_DATA_EXIST]);

        }
        if (AdminUser::getInstance()->where('mobile', $this->params['mobile'])->get()) {
            return $this->writeJson(Statuses::CODE_PHONE_EXIST, Statuses::$msg[Statuses::CODE_PHONE_EXIST]);

        }
        $password = $this->params['password'];
        if (!preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,16}$/', $password)) {
            return $this->writeJson(Statuses::CODE_W_FORMAT_PASS, Statuses::$msg[Statuses::CODE_W_FORMAT_PASS]);
        }

        $password_hash = PasswordTool::getInstance()->generatePassword($password);
        try{
            $ip = $this->request()->getHeaders()['x-real-ip'][0];
            $result = \Ritaswc\ZxIPAddress\IPv4Tool::query($ip);
            if ($result['addr'][0]) {
                $arr = explode('省', $result['addr'][0]);
                $province = $arr[0];
                $city = isset($arr[1]) ? $arr[1] : '';
                list($provinceCode, $cityCode) = AppFunc::getProvinceAndCityCode($province, $city);
            }
            $userData = [
                'nickname' => $this->params['nickname'],
                'password_hash' => $password_hash,
                'mobile' => $this->params['mobile'],
//                'photo' => !empty($this->params['wx_photo']) ? $this->params['wx_photo'] : Gravatar::makeGravatar($this->params['nickname']),
                'photo' => !empty($this->params['wx_photo']) ? $this->params['wx_photo'] : self::DEFAULT_PHOTO,
                'sign_at' => date('Y-m-d H:i:s'),
                'cid' => isset($this->params['cid']) ? $this->params['cid'] : '',
                'wx_photo' => !empty($this->params['wx_photo']) ? $this->params['wx_photo'] : '',
                'wx_name' => !empty($this->params['wx_name']) ? $this->params['wx_name'] : '',
                'third_wx_unionid' => !empty($this->params['third_wx_unionid']) ? $this->params['third_wx_unionid'] : '',
                'city_code' => !empty($cityCode) ? $cityCode : 0,
                'province_code' => !empty($provinceCode) ? $provinceCode : 0,
                'device_type' => $this->params['device_type']
            ];
            $rs = AdminUser::getInstance()->insert($userData);

            $time = time();
            $token = md5($rs . Config::getInstance()->getConf('app.token') . $time);
            $sUserKey = sprintf(UserModel::USER_TOKEN_KEY, $token);
            $mobile = $this->params['mobile'];
            RedisPool::invoke('redis', function(Redis $redis) use ($sUserKey, $mobile) {
                $redis->set($sUserKey, $mobile);
            });
            $logon = true;
            //写用户设置
            $notice = [
                'only_notice_my_interest' => 0,  //仅提示我关注的
                'start' => 1, //比赛开始
                'goal' => 1, //进球
                'over' => 1, //结束
                'red_card' => 1, //红牌
                'yellow' => 1, //黄牌
                'show_time_axis' => 1 //显示时间轴
            ];
            $push = ['start' => 1, 'goal' => 1, 'over' => 1,  'open_push' => 1, 'information' => 1];
            $private = ['see_my_post' => 1, 'see_my_post_comment' => 1, 'see_my_information_comment' => 1];
            TaskManager::getInstance()->async(function () use($rs, $notice, $push, $private){

                $settingData = [
                    'user_id'    => $rs,
                    'notice' => json_encode($notice),
                    'push' => json_encode($push),
                    'private' => json_encode($private)
                ];
                AdminUserSetting::getInstance()->insert($settingData);
                //写用户关注赛事
                $competitionIds = [];
                if ($competitions = AdminSysSettings::getInstance()->where('sys_key', 'recommond_com')->get()) {
                    foreach (json_decode($competitions->sys_value, true) as $item) {
                        foreach ($item as $value) {
                            $competitionIds[] = $value['competition_id'];
                        }
                    }
                    $userInterestComData = [
                        'competition_ids' => json_encode($competitionIds),
                        'user_id' => $rs
                    ];
                    AdminUserInterestCompetition::getInstance()->insert($userInterestComData);
                }

            });
        } catch (\Exception $e) {
            return $this->writeJson(Statuses::CODE_ERR, '用户不存在或密码错误');

        }
        $user = AdminUser::getInstance()->where('id', $rs)->get();

        if ($fd = $this->params['fd']) {
            $data = [
                'fd' => $fd,
                'nickname' => $user->nickname,
                'user_id' => $user->id,
                'last_heartbeat' => time(),
                'match_id' => 0,
                'level' => $user->level
            ];

            if (OnlineUser::getInstance()->get($fd)) {
                OnlineUser::getInstance()->update($fd, $data);
            } else {
                OnlineUser::getInstance()->set($fd, $data);
            }
        }
        $user_info = [
            'id' => $user->id,
            'nickname' => $user->nickname,
            'photo' => $user->photo,
            'point' => $user->point,
            'level' => $user->level,
            'is_offical' => $user->is_offical,
            'mobile' => $user->mobile,
            'notice_setting' => $notice,
            'wx_name' => $user->wx_name
        ];
        if ($logon) {
            $this->response()->setCookie('front_id', $rs);
            $this->response()->setCookie('front_time', $time);
            $this->response()->setCookie('front_token', $token);
            return $this->writeJson(Statuses::CODE_OK, 'OK', $user_info);
        } else {
            return $this->writeJson(Statuses::CODE_ERR, '用户不存在或密码错误');
        }


    }

    public function checkPhoneCode()
    {

        if (!$this->params['code'] || !$this->params['mobile']) {
            return $this->writeJson(Statuses::CODE_W_PARAM, Statuses::$msg[Statuses::CODE_W_PARAM]);

        } else {
            $phoneCode = AdminUserPhonecode::getInstance()->getLastCodeByMobile($this->params['mobile']);
            if (!$phoneCode || $phoneCode->status != 0 || $phoneCode->code != $this->params['code']) {
                return $this->writeJson(Statuses::CODE_W_PHONE_CODE, Statuses::$msg[Statuses::CODE_W_PHONE_CODE]);
            }
        }
        if (empty($this->params['mobile'])) {
            return $this->writeJson(Statuses::CODE_W_PARAM, Statuses::$msg[Statuses::CODE_W_PARAM]);

        } else if (AdminUser::getInstance()->where('mobile', $this->params['mobile'])->get()) {
            return $this->writeJson(Statuses::CODE_PHONE_EXIST, Statuses::$msg[Statuses::CODE_PHONE_EXIST]);
        }
        return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK]);


    }


    /**
     * 忘记密码
     * @return bool
     * @throws \Exception
     */
    public function forgetPass()
    {
        if (!$user = AdminUser::getInstance()->where('mobile', $this->params['mobile'])->get()) {
            return $this->writeJson(Statuses::CODE_USER_NOT_EXIST, Statuses::$msg[Statuses::CODE_USER_NOT_EXIST]);
        }
        $phoneCode = AdminUserPhonecode::getInstance()->getLastCodeByMobile($this->params['mobile']);
        if (!$phoneCode || $phoneCode->status != 0 || $phoneCode->code != $this->params['phone_code']) {

            return $this->writeJson(Statuses::CODE_W_PHONE_CODE, Statuses::$msg[Statuses::CODE_W_PHONE_CODE]);

        }

        $password = $this->params['password'];
        if (!preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,16}$/', $password)) {
            return $this->writeJson(Statuses::CODE_W_FORMAT_PASS, Statuses::$msg[Statuses::CODE_W_FORMAT_PASS]);
        }

        $password_hash = PasswordTool::getInstance()->generatePassword($password);
        $user->password_hash = $password_hash;
        $user->update();
        return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK]);

    }







}