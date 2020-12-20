<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminUserSetting extends BaseModel
{
	const STATUS_DEL = 2;
	const STATUS_NORMAL = 1;
	protected $tableName = 'admin_user_setting';
}