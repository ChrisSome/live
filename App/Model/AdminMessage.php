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
	/**
	 * @param       $page
	 * @param       $limit
	 * @param       $where
	 * @return array
	 * @throws
	 */
	//	public function findAll($page, $limit, $where = []): array
	//	{
	//		$list = $this->orderBy('created_at', 'ASC')->limit(($page - 1) * $limit, $limit)->all();
	//		return empty($list) ? [] : $list;
	//	}
	
	public function getLimit($page, $limit): AdminMessage
	{
		return $this->order('created_at', 'DESC')->limit(($page - 1) * $limit, $limit)->withTotalCount();
	}
}