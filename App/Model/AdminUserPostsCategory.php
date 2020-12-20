<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminUserPostsCategory extends BaseModel
{
	const IS_TOP = 1; //置顶
	const STATUS_NORMAL = 1; //用户发布成功/审核处理中
	protected $tableName = 'admin_user_posts_category';
	
	protected function getColorAttr($value, $data)
	{
		return json_decode($data['color'], true);
	}
	
	protected function getDisposeAttr($value, $data)
	{
		return json_decode($data['dispose'], true);
	}
}