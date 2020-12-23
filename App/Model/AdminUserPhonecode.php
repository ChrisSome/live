<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminUserPhonecode extends BaseModel
{
	const STATUS_USED = 1;
	const STATUS_UNUSED = 0;
	protected $tableName = 'admin_user_phonecode';
	
	/**
	 * 获取用户验证码
	 * @param $mobile
	 * @return mixed
	 * @throws
	 */
	public function getLastCodeByMobile($mobile)
	{
		$tmp = $this->where('mobile', $mobile)->where('status', self::STATUS_UNUSED)
			->where('created_at', time() - 15 * 60, '>')->order('created_at', 'desc')->get();
		return empty($tmp) ? null : $tmp;
	}
}
