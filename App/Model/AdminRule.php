<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminRule extends BaseModel
{
	protected $tableName = 'admin_rule';
	//	public function findAll($page, $limit)
	//	{
	//		$list = $this->order('created_at', 'ASC')->limit(($page - 1) * $limit, $limit)->all();
	//		return empty($list) ? [] : $list;
	//	}
	
	/**
	 * @param array $ids
	 * @return array
	 * @throws
	 */
	public function getIdsInNode(array $ids): array
	{
		return $this->where('id', $ids, 'in')->where('status', 1)->column('node');
	}
}
