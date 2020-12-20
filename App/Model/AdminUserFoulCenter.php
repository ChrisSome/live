<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminUserFoulCenter extends BaseModel
{
	protected $tableName = 'admin_user_foul_center';
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function getOperate()
	{
		return $this->hasOne(AdminUserOperate::class, null, 'operate_id', 'id');
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function getPostTitle()
	{
		return $this->hasOne(AdminUserPost::class, null, 'item_id', 'id')->field(['title']);
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function getPostComment()
	{
		return $this->hasOne(AdminPostComment::class, null, 'item_id', 'id')->field(['content']);
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function getInformationCommentContent()
	{
		return $this->hasOne(AdminInformationComment::class, null, 'item_id', 'id')->field(['content']);
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function getChatMessageContent()
	{
		return $this->hasOne(ChatHistory::class, null, 'item_id', 'id')->field(['content']);
	}
}