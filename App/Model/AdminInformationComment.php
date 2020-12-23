<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminInformationComment extends BaseModel
{
	const STATUS_NORMAL = 0;
	const STATUS_DELETE = 1;
	const STATUS_REPORTED = 2;
	const STATUS_ADMIN_DELETE = 3;
	const SHOW_IN_FRONT = [self::STATUS_REPORTED, self::STATUS_NORMAL, self::STATUS_ADMIN_DELETE];
	protected $tableName = 'admin_information_comments';
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function getUserInfo()
	{
		return $this->hasOne(AdminUser::class, null, 'user_id', 'id')
			->field(['id', 'photo', 'nickname', 'level', 'is_offical']);
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function getTUserInfo()
	{
		$user = $this->hasOne(AdminUser::class, null, 't_u_id', 'id');
		if (!empty($user)) return $user->field(['id', 'photo', 'nickname', 'level', 'is_offical']);
		return null;
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function getParent()
	{
		return $this->hasOne(AdminUser::class, null, 'id', 'parent_id')->field(['user_id', 'id']);
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function getParentComment()
	{
		return $this->hasOne(AdminUser::class, null, 'parent_id', 'id');
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function getInformation()
	{
		return $this->hasOne(AdminInformation::class, null, 'information_id', 'id');
	}
}