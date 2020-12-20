<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminSensitive extends BaseModel
{
	const STATUS_DEL = 2;
	const STATUS_NORMAL = 1;
	protected $tableName = 'admin_sensitive';
	/**
	 * @param $page
	 * @param $limit
	 * @return array
	 * @throws
	 */
	//	public function findAll($page, $limit): array
	//	{
	//		$list = $this->where('status', self::STATUS_NORMAL)->order('created_at', 'DESC')
	//			->limit(($page - 1) * $limit, $limit)->all();
	//		return empty($list) ? [] : $list;
	//	}
	
}