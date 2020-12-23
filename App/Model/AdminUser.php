<?php

namespace App\Model;

use App\lib\Tool;
use App\Base\BaseModel;
use EasySwoole\Mysqli\QueryBuilder;

class AdminUser extends BaseModel
{
	const USER_TOKEN_KEY = 'user:token:%s';   //token
	const STATUS_BAN = 0; //封禁
	const STATUS_NORMAL = 1;   //正常
	const STATUS_REPORTED = 2; //被举报
	const STATUS_CANCEL = 3;   //注销
	const STATUS_FORBIDDEN = 4;//禁言
	const STATUS_PRE_INIT = 1; //用户信息审核状态
	protected $tableName = 'admin_user';
	
	/**
	 * 通过微信token以及openid获取用户信息
	 * @param $accessToken
	 * @param $openId
	 * @return bool|string
	 */
	public function getWxUser($accessToken, $openId)
	{
		$url = sprintf('https://api.weixin.qq.com/sns/userinfo?access_token=%s&openid=%s&lang=zh_CN', $accessToken, $openId);
		return Tool::getInstance()->postApi($url);
	}
	
	/**
	 * 某人的评论数
	 * @return mixed
	 */
	public function commentCount()
	{
		return $this->hasMany(AdminPostComment::class, function (QueryBuilder $queryBuilder) {
			$queryBuilder->where('status', AdminPostComment::STATUS_NORMAL);
		}, 'id', 'user_id');
	}
	
	/**
	 * 收藏数
	 * @return mixed
	 */
	public function collectCount()
	{
		return $this->hasMany(AdminPostOperate::class, function (QueryBuilder $queryBuilder) {
			$queryBuilder->where('action_type', AdminPostOperate::ACTION_TYPE_COLLECT);
			$queryBuilder->where('comment_id', 0);
		}, 'id', 'user_id');
	}
	
	/**
	 * 我的发帖
	 * @return mixed
	 */
	public function postCount()
	{
		return $this->hasMany(AdminUserPost::class, function (QueryBuilder $queryBuilder) {
			$queryBuilder->where('status', AdminUserPost::STATUS_EXAMINE_SUCCESS);
		}, 'id', 'user_id');
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function userSetting()
	{
		return $this->hasOne(AdminUserSetting::class, null, 'id', 'user_id');
	}
	
	/**
	 * 需要给用户展示的赛事id以及用户关注的比赛id
	 * @param $uid
	 * @return mixed
	 * @throws
	 */
	public static function getUserShowCompetitionId($uid): array
	{
		//默认赛事
		$config = AdminSysSettings::create()->findOne(['sys_key' => AdminSysSettings::COMPETITION_ARR], 'sys_value');
		$config = empty($config['sys_value']) ? [] : json_decode($config['sys_value'], true);
		//用户关注赛事 与 比赛
		$res = AdminUserInterestCompetition::create()->alias('c')
			->join('admin_user_interest_matches as m', 'c.user_id=m.uid', 'left')
			->field(['c.*', 'm.match_ids'])->get(['user_id' => $uid]);
		$interestMatchArr = empty($res['match_ids']) ? [] : json_decode($res['match_ids'], true);
		$userInterestCompetition = empty($res['competition_ids']) ? [] : json_decode($res['competition_ids'], true);
		if (!empty($userInterestCompetition)) {
			$selectCompetitionIdArr = array_intersect($config, $userInterestCompetition);
		} else {
			$selectCompetitionIdArr = $config;
		}
		return [array_values($selectCompetitionIdArr), $interestMatchArr];
	}
}