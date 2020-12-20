<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminUserOperate extends BaseModel
{
	const TYPE_BOOK_MARK = 2; //收藏
	const TYPE_RES_USER_BAN = 2; //禁言
	const TYPE_RES_ITEM_DELETE = 1; //删除
	const TYPE_RES_USER_FORBIDDEN = 1; //禁言
	protected $tableName = 'admin_user_operates';
	
	public function getLimit($page, $limit): AdminUserOperate
	{
		return $this->order('created_at', 'DESC')->limit(($page - 1) * $limit, $limit)->withTotalCount();
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function postInfo()
	{
		return $this->hasOne(AdminUserPost::class, null, 'item_id', 'id')->field(['id', 'title']);
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function userInfo()
	{
		return $this->hasOne(AdminUser::class, null, 'user_id', 'id')
			->field(['id', 'nickname', 'photo', 'level', 'is_offical']);
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function authorInfo()
	{
		return $this->hasOne(AdminUser::class, null, 'author_id', 'id')
			->field(['id', 'nickname', 'photo', 'level', 'is_offical']);
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function commentInfo()
	{
		return $this->hasOne(AdminPostComment::class, null, 'item_id', 'id');
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function informationComment()
	{
		return $this->hasOne(AdminInformationComment::class, null, 'item_id', 'id')
			->field(['id', 'content', 'title']);
	}
}