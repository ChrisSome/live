<?php

namespace App\Model;

use App\Base\BaseModel;
use EasySwoole\Mysqli\QueryBuilder;

class AdminPostComment extends BaseModel
{
	const STATUS_DEL = 2;           //删除
	const STATUS_NORMAL = 0;        //正常
	const STATUS_REPORTED = 1;      //被举报
	const STATUS_ADMIN_DEL = 3;     //后台删除
	const SHOW_IN_FRONT = [self::STATUS_NORMAL, self::STATUS_REPORTED, self::STATUS_ADMIN_DEL];
	protected $relationT = 'admin_user';
	protected $tableName = 'admin_user_post_comments';
	
	public function getAll($page, $limit): AdminPostComment
	{
		return $this->order('created_at', 'DESC')->limit(($page - 1) * $limit, $limit)->withTotalCount();
	}
	
	/**
	 * 回复用户信息
	 * @return mixed
	 * @throws
	 */
	public function uInfo()
	{
		return $this->hasOne(AdminUser::class, null, 'user_id', 'id')
			->field(['id', 'mobile', 'photo', 'nickname', 'level', 'is_offical']);
	}
	
	/**
	 * 被回复用户信息
	 * @return mixed
	 * @throws
	 */
	public function tuInfo()
	{
		if ($this->hasOne(AdminUser::class, null, 't_u_id', 'id')) {
			return $this->hasOne(AdminUser::class, null, 't_u_id', 'id')
				->field(['id', 'mobile', 'photo', 'nickname', 'level', 'is_offical']);
		}
		return null;
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function postInfo()
	{
		return $this->hasOne(AdminUserPost::class, null, 'post_id', 'id')
			->field(['id', 'title', 'content', 'created_at']);
	}
	
	/**
	 * 是否点赞
	 * @param $uid
	 * @param $cid
	 * @return mixed
	 * @throws
	 */
	public function isFabolus($uid, $cid)
	{
		return $this->hasOne(AdminUserOperate::class, function (QueryBuilder $queryBuilder) use ($uid, $cid) {
			$queryBuilder->where('user_id', $uid);
			$queryBuilder->where('item_id', $cid);
			$queryBuilder->where('type', 1);
			$queryBuilder->where('is_cancel', 0);
			$queryBuilder->where('item_type', 2);
		}, 'id', 'item_id');
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function belong()
	{
		return $this->belongsToMany(AdminUser::class, 'admin_user', 'id', 'id', function (QueryBuilder $query) {
			$query->limit(10);
		});
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function getParentContent()
	{
		return $this->hasOne(self::class, null, 'parent_id', 'id');
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function getTopComment()
	{
		return $this->hasOne(self::class, null, 'top_comment_id', 'id');
	}
}