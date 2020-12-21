<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminUserPhonecode extends BaseModel
{
	const STATUS_USED = 1;
	const STATUS_UNUSED = 0;
	protected $tableName = 'admin_user_phonecode';
	/**
	 * @param int $page
	 * @param int $limit
	 * @return array
	 * @throws
	 */
	//	public function findAll(int $page, int $limit): array
	//	{
	//		$list = $this->order('created_at', 'DESC')->limit(($page - 1) * $limit, $limit)->all();
	//		return empty($list) ? [] : $list;
	//	}
	
	/**
	 * 获取用户验证码
	 * @param $mobile
	 * @return mixed
	 * @throws
	 */
	public function getLastCodeByMobile($mobile)
	{
		$tmp = $this->where('mobile', $mobile)->where('status', self::STATUS_UNUSED)
			->where('created_at', time() - 10 * 60, '>')->order('created_at', 'desc')->get();
		return empty($tmp) ? null : $tmp;
	}
}