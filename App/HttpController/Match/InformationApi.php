<?php

namespace App\HttpController\Match;

use App\lib\Utils;
use App\Common\Time;
use App\Common\AppFunc;
use App\Model\AdminUser;
use App\lib\FrontService;
use App\Model\AdminMatch;
use App\Model\AdminMessage;
use easySwoole\Cache\Cache;
use App\Model\AdminSensitive;
use App\Task\SerialPointTask;
use App\Model\AdminCompetition;
use App\Model\AdminInformation;
use App\Model\AdminSysSettings;
use App\Model\AdminUserOperate;
use App\Utility\Message\Status;
use App\Base\FrontUserController;
use EasySwoole\Validate\Validate;
use EasySwoole\Mysqli\QueryBuilder;
use App\Model\AdminInformationComment;
use EasySwoole\EasySwoole\Task\TaskManager;

class InformationApi extends FrontUserController
{
	//获取比赛趋势详情
	private $trendDetailURL = 'https://open.sportnanoapi.com/api/v4/football/match/trend/detail?user=%s&secret=%s&id=%s';
	private $url = 'https://open.sportnanoapi.com/api/sports/football/match/detail_live?user=%s&secret=%s';
	private $secret = 'dbfe8d40baa7374d54596ea513d8da96';
	private $user = 'mark9527';
	
	/**
	 * 标题栏
	 * @throws
	 */
	public function titleBar()
	{
		// 配置数据
		$where = ['sys_key' => AdminSysSettings::SETTING_DATA_COMPETITION];
		$config = AdminSysSettings::getInstance()->findOne($where, 'sys_value');
		if (empty($config['sys_value'])) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);
		// 输出数据
		$result = [
			['type' => 1, 'competition_id' => 0, 'short_name_zh' => '头条'],
			['type' => 2, 'competition_id' => 0, 'short_name_zh' => '转会'],
		];
		$list = json_decode($config['sys_value'], true);
		foreach ($list as $v) {
			$id = intval($v);
			$where = ['competition_id' => $id];
			$competition = $id < 1 ? null : AdminCompetition::getInstance()->findOne($where, 'short_name_zh');
			if (empty($competition)) continue;
			$result[] = [
				'type' => 3,
				'competition_id' => $id,
				'short_name_zh' => empty($competition['short_name_zh']) ? '' : $competition['short_name_zh'],
			];
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 赛事比赛及相关资讯文章
	 * @throws
	 */
	public function competitionContent()
	{
		// 参数校验
		$competitionId = $this->param('competition_id', true);
		if ($competitionId < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 比赛数据
		$where = ['competition_id' => $competitionId, 'status_id' => FootballApi::STATUS_NO_START];
		$matches = AdminMatch::getInstance()->findAll($where, null, 'match_time,asc', false, 1, 2);
		$matches = FrontService::handMatch($matches, 0, true);
		// 输出数据
		$result = ['matches' => $matches, 'information' => ['list' => [], 'count' => 0]];
		// 分页参数
		$page = $this->param('page', true, 1);
		$size = $this->param('size', true, 10);
		$now = date('Y-m-d H:i:s');
		$field = 'id,title,fabolus_number,respon_number,img';
		$where = ['competition_id' => $competitionId, 'status' => AdminInformation::STATUS_NORMAL, 'created_at' => [$now, '>']];
		[$list, $result['information']['count']] = AdminInformation::getInstance()
			->findAll($where, $field, 'created_at,desc', true, $page, $size);
		foreach ($list as $v) {
			$competition = $v->getCompetition();
			$result['information']['list'][] = [
				'id' => $v['id'],
				'img' => $v['img'],
				'title' => $v['title'],
				'respon_number' => $v['respon_number'],
				'fabolus_number' => $v['fabolus_number'],
				'competition_id' => $v['competition_id'],
				'competition_short_name_zh' => empty($competition['short_name_zh']) ? '' : $competition['short_name_zh'],
			];
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 资讯详情
	 * @throws
	 */
	public function informationInfo()
	{
		// 资讯数据
		$informationId = $this->param('information_id', true);
		$information = $informationId > 0 ? AdminInformation::getInstance()->findOne($informationId) : false;
		if (empty($information)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 已被删除
		$isDeleted = intval($information['status']) == AdminInformation::STATUS_DELETE;
		if ($isDeleted) $this->output(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
		// 填充用户信息
		$userId = intval($information['user_id']);
		$fields = 'id,nickname,photo,is_offical,level';
		$information['user_info'] = $userId < 1 ? [] : AdminUser::getInstance()->findOne($userId, $fields);
		// 是否未被举报
		$where = ['item_type' => 3, 'type' => 1, 'is_cancel' => 0, 'item_id' => $informationId, 'user_id' => $this->authId];
		$tmp = AdminUserOperate::getInstance()->findOne($where);
		$information['is_fabolus'] = !empty($tmp);
		// 是否已被收藏
		$where = ['item_type' => 3, 'type' => 2, 'is_cancel' => 0, 'item_id' => $informationId, 'user_id' => $this->authId];
		$tmp = AdminUserOperate::getInstance()->findOne($where);
		$information['is_collect'] = !empty($tmp);
		// 分页参数
		$page = $this->param('page', true, 1);
		$size = $this->param('size', true, 10);
		// 类型 0:最热 1:最早 2:最新
		$orderType = $this->param('order_type', true);
		// 评论/回复数据
		$order = $orderType == 0 ? 'fabolus_number,desc' : ($orderType == 1 ? 'created_at,asc' : ($orderType == 2 ? 'created_at,desc' : null));
		$where = ['information_id' => $informationId, 'top_comment_id' => 0, 'parent_id' => 0];
		[$list, $count] = AdminInformationComment::getInstance()->findAll($where, null, $order, true, $page, $size);
		if (!empty($list)) {
			$commentIds = array_column($list, 'id');
			$userIds = array_unique(array_filter(array_column($list, 'user_id')));
			// 用户数据映射
			$userMapper = empty($userIds) ? [] : AdminUser::getInstance()
				->findAll(['id' => [$userIds, 'in']], 'id,nickname,photo,is_offical,level', null,
					false, 0, 0, 'id,*,true');
			// 回复统计映射
			$where = ['information_id' => $informationId, 'status' => AdminInformationComment::STATUS_NORMAL, 'top_comment_id' => [$commentIds, 'in']];
			$childCountMapper = empty($commentIds) ? [] : AdminInformationComment::getInstance()
				->findAll($where, 'top_comment_id,count(*) total', ['group' => 'top_comment_id'],
					false, 0, 0, 'top_comment_id,total,1');
			// 点赞数据映射
			$where = ['item_type' => 4, 'item_id' => [$commentIds, 'in'], 'type' => 1, 'user_id' => $this->authId, 'is_cancel' => 0];
			$operateMapper = empty($commentIds) ? [] : AdminUserOperate::getInstance()
				->findAll($where, 'item_id', null,
					false, 0, 0, 'item_id,item_id,false');
			// 回复数据映射
			$childGroupMapper = [];
			$subSql = 'select count(*)+1 from admin_information_comments x where x.top_comment_id=top_comment_id and x.information_id=' . $informationId .
				' and x.status=' . AdminInformationComment::STATUS_NORMAL . ' having (count(*)+1)<=3';
			$where = ['information_id' => $informationId, 'status' => AdminInformationComment::STATUS_NORMAL, 'top_comment_id' => [$commentIds, 'in'], 'exists' => $subSql];
			$tmp = empty($commentIds) ? [] : AdminInformationComment::getInstance()->findAll($where, null, 'created_at desc');
			foreach ($tmp as $v) {
				$id = intval($v['top_comment_id']);
				$childGroupMapper[$id][] = $v;
			}
			$comments = [];
			foreach ($list as $v) {
				$id = intval($v['id']);
				$userId = intval($v['user_id']);
				$userInfo = empty($userMapper[$userId]) ? [] : $userMapper[$userId];
				$children = empty($childGroupMapper[$id]) ? [] : $childGroupMapper[$id];
				$childrenCount = empty($childCountMapper[$id]) ? [] : $childCountMapper[$id];
				$comments[] = [
					'id' => $id,
					'user_info' => $userInfo,
					'created_at' => $v['created_at'],
					'respon_number' => $v['respon_number'],
					'child_comment_count' => $childrenCount,
					'fabolus_number' => $v['fabolus_number'],
					'content' => base64_decode($v['content']),
					'is_fabolus' => !empty($operateMapper[$id]),
					'is_follow' => AppFunc::isFollow($this->authId, $userId),
					'child_comment_list' => FrontService::handInformationComment($children, $this->authId),
				];
			}
			$list = $comments;
		}
		// 比赛信息
		$match = [];
		$matchId = intval($information['match_id']);
		$tmp = $matchId < 1 ? null : AdminMatch::getInstance()->findOne(['match_id' => $information['match_id']]);
		if (!empty($tmp)) {
			$tmp = FrontService::handMatch([$tmp], 0, true);
			if (isset($tmp[0])) {
				$match = [
					'match_id' => $tmp[0]['match_id'],
					'competition_id' => $tmp[0]['competition_id'],
					'competition_name' => $tmp[0]['competition_name'],
					'home_team_name' => $tmp[0]['home_team_name'],
					'away_team_name' => $tmp[0]['away_team_name'],
					'format_match_time' => $tmp[0]['format_match_time'],
				];
			}
		}
		// 输出数据
		$result = [
			'relate_match' => $match,
			'information_info' => $information,
			'comments' => $list, 'count' => $count,
		];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 发表评论
	 * @throws
	 */
	public function informationComment()
	{
		// 登录状态判断
		if ($this->authId < 1) $this->output(Status::CODE_LOGIN_ERR, Status::$msg[Status::CODE_LOGIN_ERR]);
		// 用户状态校验
		if ($this->auth['status'] == AdminUser::STATUS_FORBIDDEN) {
			$this->output(Status::CODE_STATUS_FORBIDDEN, Status::$msg[Status::CODE_STATUS_FORBIDDEN]);
		}
		// 防止频繁操作
		if (Cache::get('user_comment_information_' . $this->authId)) {
			$this->output(Status::CODE_WRONG_LIMIT, Status::$msg[Status::CODE_WRONG_LIMIT]);
		}
		// 参数校验
		$validator = new Validate();
		$validator->addColumn('information_id')->required()->min(1);
		$validator->addColumn('top_comment_id')->required()->min(0);
		$validator->addColumn('parent_id')->required()->min(0);
		$validator->addColumn('t_u_id')->required()->min(1);
		$validator->addColumn('content')->required()->notEmpty();
		if (!$validator->validate($this->param())) {
			$this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		}
		// 资讯数据
		$informationId = $this->param('information_id', true);
		$information = AdminInformation::getInstance()->findOne($informationId);
		if (empty($information)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 敏感词校验
		$words = $sensitiveWords = AdminSensitive::getInstance()->findAll(['status' => AdminSensitive::STATUS_NORMAL], 'word');
		foreach ($words as $v) {
			if (!empty($v['word']) && strstr($this->param('content'), $v['word'])) {
				$msg = sprintf(Status::$msg[Status::CODE_ADD_POST_SENSITIVE], $v['word']);
				$this->output(Status::CODE_ADD_POST_SENSITIVE, $msg);
			}
		}
		// 插入数据
		$commentId = AdminInformationComment::getInstance()->insert([
			'user_id' => $this->authId,
			't_u_id' => $this->param('t_u_id', true),
			'parent_id' => $this->param('parent_id', true),
			'information_id' => $this->param('information_id', true),
			'top_comment_id' => $this->param('top_comment_id', true),
			'content' => base64_encode(addslashes(htmlspecialchars($this->param('content')))),
		]);
		// 插入失败
		if ($commentId < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 关联数据处理
		$parentId = $this->param('parent_id', true);
		// 回复数累加
		TaskManager::getInstance()->async(function () use ($parentId, $informationId) {
			if ($parentId > 0) {
				// 父评论回复数累加
				AdminInformationComment::getInstance()->update(['respon_number' => QueryBuilder::inc(1)], $parentId);
			}
			// 资讯回复数累加
			AdminInformation::getInstance()->update(['respon_number' => QueryBuilder::inc(1)], $informationId);
		});
		TaskManager::getInstance()->async(new SerialPointTask(['task_id' => 4, 'user_id' => $this->authId]));
		// 防频繁操作
		Cache::set('user_comment_information_' . $this->authId, 1, 5);
		// 用户消息
		if ($parentId > 0) {
			$comment = AdminInformationComment::getInstance()->where($parentId);
			AdminMessage::getInstance()->insert([
				'type' => 3,
				'item_type' => 4,
				'title' => '资讯回复通知',
				'item_id' => $commentId,
				'did_user_id' => $this->authId,
				'status' => AdminMessage::STATUS_UNREAD,
				'user_id' => empty($comment['user_id']) ? 0 : intval($comment['user_id']),
			]);
		}
		// 输出封装
		$comment = AdminInformationComment::getInstance()->findOne($commentId);
		$comment = FrontService::handInformationComment([$comment], $this->authId);
		$comment = empty($comment[0]) ? [] : $comment[0];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $comment);
	}
	
	/**
	 * 二级评论列表
	 * @throws
	 */
	public function informationChildComment()
	{
		// 登录状态判断
		$authId = empty($this->auth['id']) || intval($this->auth['id']) < 1 ? 0 : intval($this->auth['id']); // 当前登录用户ID
		// 顶级评论数据
		$topCommentId = $this->param('top_comment_id', true);
		$topComment = $topCommentId < 1 ? null : AdminInformationComment::getInstance()->findOne($topCommentId);
		if (empty($topComment)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		$topComment = FrontService::handInformationComment([$topComment], $authId);
		$topComment = empty($topComment[0]) ? [] : $topComment[0];
		// 分页参数
		$page = $this->param('page', true, 1);
		$size = $this->param('page', true, 10);
		[$list, $count] = AdminInformationComment::getInstance()
			->findAll(['top_comment_id' => $topCommentId], null, 'created_at,desc', true, $page, $size);
		// 封装数据
		$list = FrontService::handInformationComment($list, $authId);
		// 输出数据
		$result = ['fatherComment' => $topComment, 'childComment' => $list, 'count' => $count];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 获取分类的内容
	 * @throws
	 */
	public function getCategoryInformation()
	{
		// 类型
		$type = $this->param('type', true, 1);
		// 赛事ID
		$competitionId = $this->param('competition_id', true);
		// 分页数据
		$page = $this->param('page', true, 1);
		$size = $this->param('size', true, 10);
		if ($type == 1) {
			// 配置数据
			$config = AdminSysSettings::getInstance()->findOne(['sys_key' => AdminSysSettings::SETTING_TITLE_BANNER], 'sys_value');
			$config = empty($config['sys_value']) ? null : json_decode($config['sys_value'], true);
			// 轮播清单
			$banners = empty($config['banner']) ? [] : $config['banner'];
			usort($banners, function ($av, $bv) {
				$as = intval($av['sort']);
				$bs = intval($bv['sort']);
				return $as > $bs ? -1 : ($as == $bs ? 0 : 1);
			});
			foreach ($banners as $k => $v) {
				if (!Time::isBetween($v['start_time'], $v['end_time'])) unset($banners[$k]);
			}
			$banners = array_values($banners);
			// 比赛数据
			$matches = empty($config['match']) ? [] : $config['match'];
			$matches = empty($matches) ? null : AdminMatch::getInstance()->findAll(['match_id' => [$matches, 'in']]);
			$matches = empty($matches) ? [] : FrontService::formatMatch($matches, 0);
			// 分页数据
			$where = ['type' => 1, 'status' => AdminInformation::STATUS_NORMAL];
			[$list, $count] = AdminInformation::getInstance()->findAll($where, null, 'created_at,desc', true, $page, $size);
			$list = empty($list) ? [] : FrontService::handInformation($list, $this->authId);
			// 输出数据
			$result = [
				'banner' => $banners,
				'matches' => $matches,
				'information' => ['list' => $list, 'count' => $count],
			];
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
		}
		if ($type == 2) {
			// 转会数据
			$where = ['type' => 2, 'status' => AdminInformation::STATUS_NORMAL];
			[$list, $count] = AdminInformation::getInstance()->findAll($where, 'type', 'created_at,desc', true, $page, $size);
			$list = empty($list) ? [] : FrontService::handInformation($list, $this->authId);
			// 输出数据
			$result = ['banner' => [], 'matches' => [], 'information' => ['list' => $list, 'count' => $count]];
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
		}
		// 参数校验
		if ($competitionId < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 普通赛事
		$where = ['competition_id' => $competitionId, 'status_id' => FootballApi::STATUS_NO_START];
		$matches = AdminMatch::getInstance()->findAll($where, null, 'match_time,asc', false, 1, 2);
		$matches = empty($matches) ? [] : FrontService::handMatch($matches, 0, true);
		// 资讯数据
		$where = ['competition_id' => $competitionId, 'status_id' => AdminInformation::STATUS_NORMAL];
		[$list, $count] = AdminInformation::getInstance()
			->findAll($where, null, 'created_at,desc', true, $page, $size);
		$list = empty($list) ? [] : FrontService::handInformation($list, $this->authId);
		// 输出数据
		$result = ['banner' => [], 'matches' => $matches, 'information' => ['list' => $list, 'count' => $count]];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
}