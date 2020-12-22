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
		$params = $this->params;
		$competitionId = empty($params['competition_id']) || intval($params['competition_id']) < 1 ? 0 : intval($params['competition_id']);
		if ($competitionId < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 比赛数据
		$where = ['competition_id' => $competitionId, 'status_id' => FootballApi::STATUS_NO_START];
		$matches = AdminMatch::getInstance()->findAll($where, null, 'match_time,asc', false, 1, 2);
		$matches = FrontService::handMatch($matches, 0, true);
		// 输出数据
		$result = ['matches' => $matches, 'information' => ['list' => [], 'count' => 0]];
		// 分页参数
		$page = empty($params['page']) ? 1 : $params['page'];
		$size = empty($params['size']) ? 10 : $params['size'];
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
		$params = $this->params;
		// 当前登录用户ID
		$authId = empty($this->auth['id']) || intval($this->auth['id']) < 1 ? 0 : intval($this->auth['id']); // 当前登录用户ID
		// 资讯数据
		$informationId = empty($params['information_id']) || intval($params['information_id']) < 1 ? 0 : intval($params['information_id']);
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
		$where = ['item_type' => 3, 'type' => 1, 'is_cancel' => 0, 'item_id' => $informationId, 'user_id' => $authId];
		$tmp = AdminUserOperate::getInstance()->findOne($where);
		$information['is_fabolus'] = !empty($tmp);
		// 是否已被收藏
		$where = ['item_type' => 3, 'type' => 2, 'is_cancel' => 0, 'item_id' => $informationId, 'user_id' => $authId];
		$tmp = AdminUserOperate::getInstance()->findOne($where);
		$information['is_collect'] = !empty($tmp);
		// 分页参数
		$page = empty($params['page']) ? 1 : $params['page'];
		$size = empty($params['size']) ? 10 : $params['size'];
		// 类型 0:最热 1:最早 2:最新
		$orderType = empty($params['order_type']) || intval($params['order_type']) < 1 ? 0 : intval($params['order_type']);
		// 评论/回复数据
		$order = $orderType == 0 ? 'fabolus_number,desc' : ($orderType == 1 ? 'created_at,asc' : ($orderType == 2 ? 'created_at,desc' : null));
		$where = ['information_id' => $informationId, 'top_comment_id' => 0, 'parent_id' => 0];
		[$list, $count] = AdminInformationComment::getInstance()->findAll($where, null, $order, true, $page, $size);
		if (!empty($list)) {
			$commentIdsStr = join(',', array_column($list, 'id'));
			$userIdsStr = join(',', array_unique(array_filter(array_column($list, 'user_id'))));
			// 用户数据映射
			$userMapper = empty($userIdsStr) ? [] : Utils::queryHandler(AdminUser::getInstance(),
				'id in(' . $userIdsStr . ')', null,
				'id,nickname,photo,is_offical,level', false, null, 'id,*,1');
			// 回复统计映射
			$childCountMapper = Utils::queryHandler(AdminInformationComment::getInstance(),
				'information_id=? and status=? and top_comment_id in (' . $commentIdsStr . ')', [$informationId, AdminInformationComment::STATUS_NORMAL],
				'top_comment_id,count(*) total', false,
				['group' => 'top_comment_id'], 'top_comment_id,total,1');
			// 点赞数据映射
			$operateMapper = Utils::queryHandler(AdminUserOperate::getInstance(),
				'item_type=4 and item_id in(' . $commentIdsStr . ') and type=1 and user_id=? and is_cancel=0', $authId,
				'item_id', false, null, 'item_id,item_id,1');
			// 回复数据映射
			$childGroupMapper = [];
			$subSql = 'select count(*)+1 from admin_information_comments x where x.top_comment_id=a.top_comment_id and x.information_id=? and x.status=? having (count(*)+1)<=3';
			$tmp = Utils::queryHandler(AdminInformationComment::getInstance(),
				'information_id=? and status=? and top_comment_id in(' . $commentIdsStr . ') and exists(' . $subSql . ')',
				[$informationId, AdminInformationComment::STATUS_NORMAL, $informationId, AdminInformationComment::STATUS_NORMAL],
				'*', false, 'a.created_at desc');
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
					'is_follow' => AppFunc::isFollow($authId, $userId),
					'child_comment_list' => FrontService::handInformationComment($children, $authId),
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
		$authId = empty($this->auth['id']) || intval($this->auth['id']) < 1 ? 0 : intval($this->auth['id']); // 当前登录用户ID
		if ($authId < 1) $this->output(Status::CODE_LOGIN_ERR, Status::$msg[Status::CODE_LOGIN_ERR]);
		// 用户状态校验
		if ($this->auth['status'] == AdminUser::STATUS_FORBIDDEN) {
			$this->output(Status::CODE_STATUS_FORBIDDEN, Status::$msg[Status::CODE_STATUS_FORBIDDEN]);
		}
		// 防止频繁操作
		if (Cache::get('user_comment_information_' . $authId)) {
			$this->output(Status::CODE_WRONG_LIMIT, Status::$msg[Status::CODE_WRONG_LIMIT]);
		}
		// 参数校验
		$validator = new Validate();
		$validator->addColumn('information_id')->required()->min(1);
		$validator->addColumn('top_comment_id')->required()->min(0);
		$validator->addColumn('parent_id')->required()->min(0);
		$validator->addColumn('t_u_id')->required()->min(1);
		$validator->addColumn('content')->required()->notEmpty();
		if (!$validator->validate($this->params)) {
			$this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		}
		$params = $this->params;
		// 资讯数据
		$informationId = intval($params['information_id']);
		$information = AdminInformation::getInstance()->findOne($informationId);
		if (empty($information)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 敏感词校验
		$words = $sensitiveWords = AdminSensitive::getInstance()->findAll(['status' => AdminSensitive::STATUS_NORMAL], 'word');
		foreach ($words as $v) {
			if (!empty($v['word']) && strstr($params['content'], $v['word'])) {
				$msg = sprintf(Status::$msg[Status::CODE_ADD_POST_SENSITIVE], $v['word']);
				$this->output(Status::CODE_ADD_POST_SENSITIVE, $msg);
			}
		}
		// 插入数据
		$commentId = AdminInformationComment::getInstance()->insert([
			'user_id' => $authId,
			't_u_id' => $params['t_u_id'],
			'parent_id' => $params['parent_id'],
			'information_id' => $params['information_id'],
			'top_comment_id' => $params['top_comment_id'],
			'content' => base64_encode(addslashes(htmlspecialchars($params['content']))),
		]);
		// 插入失败
		if ($commentId < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 关联数据处理
		$parentId = intval($params['parent_id']);
		// 回复数累加
		TaskManager::getInstance()->async(function () use ($parentId, $informationId) {
			if ($parentId > 0) {
				// 父评论回复数累加
				AdminInformationComment::getInstance()->update(['respon_number' => QueryBuilder::inc(1)], $parentId);
			}
			// 资讯回复数累加
			AdminInformation::getInstance()->update(['respon_number' => QueryBuilder::inc(1)], $informationId);
		});
		TaskManager::getInstance()->async(new SerialPointTask(['task_id' => 4, 'user_id' => $authId]));
		// 防频繁操作
		Cache::set('user_comment_information_' . $authId, 1, 5);
		// 用户消息
		if ($parentId > 0) {
			$comment = AdminInformationComment::getInstance()->where($parentId);
			AdminMessage::getInstance()->insert([
				'type' => 3,
				'item_type' => 4,
				'title' => '资讯回复通知',
				'item_id' => $commentId,
				'did_user_id' => $authId,
				'status' => AdminMessage::STATUS_UNREAD,
				'user_id' => empty($comment['user_id']) ? 0 : intval($comment['user_id']),
			]);
		}
		// 输出封装
		$comment = AdminInformationComment::getInstance()->findOne($commentId);
		$comment = FrontService::handInformationComment([$comment], $authId);
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
		$params = $this->params;
		// 顶级评论数据
		$topCommentId = empty($params['top_comment_id']) || intval($params['top_comment_id']) < 1 ? 0 : intval($params['top_comment_id']);
		$topComment = $topCommentId < 1 ? null : AdminInformationComment::getInstance()->findOne($topCommentId);
		if (empty($topComment)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		$topComment = FrontService::handInformationComment([$topComment], $authId);
		$topComment = empty($topComment[0]) ? [] : $topComment[0];
		// 分页参数
		$page = empty($params['page']) ? $params['page'] : 1;
		$size = isset($params['size']) ? $params['size'] : 1;
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
		// 登录状态判断
		$authId = empty($this->auth['id']) || intval($this->auth['id']) < 1 ? 0 : intval($this->auth['id']); // 当前登录用户ID
		$params = $this->params;
		// 类型
		$type = empty($params['type']) || intval($params['type']) < 1 ? 1 : intval($params['type']);
		// 赛事ID
		$competitionId = empty($params['competition_id']) || intval($params['competition_id']) < 1 ? 0 : intval($params['competition_id']);
		// 分页数据
		$page = empty($params['page']) ? 1 : $params['page'];
		$size = empty($params['size']) ? 10 : $params['size'];
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
			$list = empty($list) ? [] : FrontService::handInformation($list, $authId);
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
			$list = empty($list) ? [] : FrontService::handInformation($list, $authId);
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
		$list = empty($list) ? [] : FrontService::handInformation($list, $authId);
		// 输出数据
		$result = ['banner' => [], 'matches' => $matches, 'information' => ['list' => $list, 'count' => $count]];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
}