<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminRule extends BaseModel
{
	protected $tableName = 'admin_rule';
	
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
