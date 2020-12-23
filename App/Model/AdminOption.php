<?php

namespace App\Model;

use App\lib\Tool;
use App\Base\BaseModel;

class AdminOption extends BaseModel
{
	protected $tableName = 'admin_user_options';
	
	/**
	 * 通过微信token以及openid获取用户信息
	 * @param $access_token
	 * @param $openId
	 * @return bool|string
	 */
	public function getWxUser($access_token, $openId)
	{
		$url = sprintf("https://api.weixin.qq.com/cgi-bin/user/info?access_token=%s&openid=%s&lang=zh_CN", $access_token, $openId);
		return Tool::getInstance()->postApi($url);
	}
}