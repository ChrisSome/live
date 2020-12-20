<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminCategory extends BaseModel
{
	const STATUS_NORMAL = 1;
	const CATEGORY_ANNOUNCEMENT = 2;
	protected $tableName = 'admin_system_message_category';
	
	/**
	 * @param array $ids
	 * @return mixed
	 * @throws
	 */
	public function getIdsInNode(array $ids = [])
	{
		return $this->where('id', $ids, 'IN')->indexBy('pname');
	}
}