<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminUserMessageRecord extends BaseModel
{
	protected $tableName = 'admin_user_read_records';
	/**
	 * @param int $page
	 * @param int $limit
	 * @return array
	 * @throws
	 */
	//	public function findAll(int $page, int $limit): array
	//	{
	//		$list = $this->order('created_at', 'ASC')->limit(($page - 1) * $limit, $limit)->all();
	//		return empty($list) ? [] : $list;
	//	}
	
}