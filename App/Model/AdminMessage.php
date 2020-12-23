<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminMessage extends BaseModel
{
	const STATUS_DEL = 2;
	const TYPE_NOTICE = 1;
	const STATUS_READ = 1;
	const STATUS_UNREAD = 0;
	protected $tableName = 'admin_system_message_lists';
}