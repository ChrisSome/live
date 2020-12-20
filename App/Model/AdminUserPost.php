<?php

namespace App\Model;

use App\Base\BaseModel;
use EasySwoole\Mysqli\QueryBuilder;

class AdminUserPost extends BaseModel
{
	const IS_TOP = 1; //置顶
	const IS_NOT_TOP = 0; //非置顶
	const IS_REFINE = 1; //加精
	const NEW_STATUS_NORMAL = 1;//正常
	const NEW_STATUS_REPORTED = 2; //被举报
	const NEW_STATUS_DELETED = 3;//自己删除
	const NEW_STATUS_SAVE = 4; //保存
	const NEW_STATUS_ADMIN_DELETED = 5; //官方删除
	const NEW_STATUS_LOCK = 6; //锁定
	const STATUS_DEL = 2;  //删除
	const STATUS_EXAMINE_SUCCESS = 4; //审核成功（展示）
	const SHOW_IN_FRONT = [self::NEW_STATUS_NORMAL, self::NEW_STATUS_REPORTED, self::NEW_STATUS_LOCK]; //前端展示的帖子
	protected $tableName = 'admin_user_posts';
	
	public function getLimit(int $page, int $limit, string $order = 'created_at', string $desc = 'DESC'): AdminUserPost
	{
		return $this->order($order, $desc)->limit(($page - 1) * $limit, $limit)->withTotalCount();
	}
	
	/**
	 * 发帖人信息
	 * @return mixed
	 * @throws
	 */
	public function userInfo()
	{
		return $this->hasOne(AdminUser::class, null, 'user_id', 'id')
			->field(['id', 'photo', 'nickname', 'level', 'is_offical']);
	}
	
	/**
	 * 用户是否收藏
	 * @param $uid
	 * @param $pid
	 * @return mixed
	 * @throws
	 */
	public function isCollect($uid, $pid)
	{
		return $this->hasOne(AdminUserOperate::class, function (QueryBuilder $queryBuilder) use ($uid, $pid) {
			$queryBuilder->where('type', 2);
			$queryBuilder->where('user_id', $uid);
			$queryBuilder->where('item_type', 1);
			$queryBuilder->where('is_cancel', 0);
		}, 'id', 'item_id');
	}
	
	/**
	 * 是否赞过
	 * @param $uid
	 * @param $pid
	 * @return mixed
	 * @throws
	 */
	public function isFablous($uid, $pid)
	{
		return $this->hasOne(AdminUserOperate::class, function (QueryBuilder $queryBuilder) use ($uid, $pid) {
			$queryBuilder->where('type', 1);
			$queryBuilder->where('user_id', $uid);
			$queryBuilder->where('item_type', 1);
			$queryBuilder->where('is_cancel', 0);
		}, 'id', 'item_id');
	}
	
	/**
	 * 帖子所属板块信息
	 * @return mixed
	 * @throws
	 */
	public function postCat()
	{
		return $this->hasOne(AdminUserPostsCategory::class, null, 'cat_id', 'id');
	}
	
	/**
	 * 帖子回复数
	 * @return mixed
	 */
	public function commentCountForPost()
	{
		return $this->hasMany(AdminPostComment::class, function (QueryBuilder $queryBuilder) {
			$queryBuilder->where('action_type', 1);
		}, 'id', 'post_id');
	}
	
	/**
	 * 帖子最新回复
	 * @param $pid
	 * @return string
	 * @throws
	 */
	public function getLastResTime($pid): string
	{
		$data = $this->hasOne(AdminPostComment::class, function (QueryBuilder $queryBuilder) use ($pid) {
			$queryBuilder->where('post_id', $pid);
			$queryBuilder->orderBy('created_at', 'DESC');
			$queryBuilder->limit(1);
		}, 'id', 'post_id');
		if (!empty($data)) return $data->created_at;
		return '';
	}
}