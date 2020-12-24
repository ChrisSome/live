<?php

namespace App\HttpController\User;

use App\Common\Time;
use App\Common\AppFunc;
use App\Model\AdminTeam;
use App\Model\AdminUser;
use App\lib\FrontService;
use App\Model\AdminMatch;
use App\Model\AdminMessage;
use easySwoole\Cache\Cache;
use App\Model\AdminUserPost;
use App\Model\AdminSensitive;
use App\Task\SerialPointTask;
use App\Model\AdminInformation;
use App\Model\AdminPostComment;
use App\Model\AdminSysSettings;
use App\Utility\Message\Status;
use App\Model\AdminUserOperate;
use App\Base\FrontUserController;
use EasySwoole\Validate\Validate;
use App\Model\AdminNormalProblems;
use EasySwoole\Mysqli\QueryBuilder;
use App\Model\AdminUserPostsCategory;
use App\Model\AdminInformationComment;
use App\Utility\Message\Status as Statuses;
use EasySwoole\EasySwoole\Task\TaskManager;

class Community extends FrontUserController
{
	protected $isCheckSign = false;
	protected $needCheckToken = false;
	
	/**
	 * 社区首页内容
	 * @throws
	 */
	public function getContent()
	{
		$isRefine = $this->param('is_refine', true);
		$orderType = $this->param('order_type', true, 1);
		$categoryId = $this->param('category_id', true, 1);
		// 分页参数
		$page = $this->param('page', true, 1);
		$size = $this->param('size', true, 15);
		// 获取帖子清单助手
		$getPostListHandler = function ($where) use ($isRefine, $orderType, $page, $size) {
			if ($isRefine > 0) $where['is_refine'] = 1; // 精华
			if ($orderType < 1 || $orderType > 4) return false;
			// 1热度/回复数 2最新发帖 3最早发帖 4最新回复
			$order = $orderType == 1 ? 'respon_number,desc' : ($orderType == 2 ? 'created_at,desc' :
				($orderType == 3 ? 'created_at,asc' : 'last_respon_time,desc'));
			// 分页数据
			[$list, $count] = AdminUserPost::getInstance()->findAll($where, null, $order, true, $page, $size);
			$list = empty($list) ? [] : FrontService::handPosts($list, $this->authId);
			return ['normal_posts' => $list, 'count' => $count];
		};
		// 关注的人 帖子列表
		if ($categoryId == 2) {
			$userIds = $this->authId > 0 ? AppFunc::getUserFollowing($this->authId) : false;
			if (empty($userIds)) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => [], 'count' => 0]);
			$where = [
				'user_id' => [$userIds, 'in'],
				'status' => [[AdminUserPost::NEW_STATUS_NORMAL, AdminUserPost::NEW_STATUS_REPORTED, AdminUserPost::NEW_STATUS_LOCK], 'in'],
			];
			$result = $getPostListHandler($where);
			if (empty($result)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
		}
		// 输出数据
		$result = ['count' => 0, 'title' => [], 'banner' => [], 'top_posts' => [], 'normal_posts' => []];
		// 模块标题
		$result['title'] = AdminUserPostsCategory::getInstance()->findAll(['status' => AdminUserPostsCategory::STATUS_NORMAL], 'id,name,icon');
		// 模块轮播
		$tmp = AdminUserPostsCategory::getInstance()->findOne($categoryId, 'id,dispose');
		if (!empty($tmp['dispose'])) {
			foreach ($tmp['dispose'] as $v) {
				if (Time::isBetween($v['start_time'], $v['end_time'])) $result['banner'][] = $v;
			}
		}
		//置顶帖子
		$where = ['status' => [[AdminUserPost::NEW_STATUS_NORMAL, AdminUserPost::NEW_STATUS_REPORTED, AdminUserPost::NEW_STATUS_LOCK], 'in']];
		if ($categoryId == 1) {
			$where['is_all_top'] = 1;
		} else {
			$where['is_top'] = 1;
			$where['cat_id'] = $categoryId;
		}
		$result['top_posts'] = AdminUserPost::getInstance()->findAll($where, 'id,title', 'created_at,desc');
		//普通帖子
		$where = ['status' => [[AdminUserPost::NEW_STATUS_NORMAL, AdminUserPost::NEW_STATUS_REPORTED, AdminUserPost::NEW_STATUS_LOCK], 'in']];
		if ($categoryId != 1) $where['cat_id'] = $categoryId;
		$result = array_merge($result, $getPostListHandler($where));
		if ($result === false) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 前端模糊搜索
	 * @throws
	 */
	public function getContentByKeyWord()
	{
		// 关键字校验
		$keywords = $this->param('key_word');
		if (empty($keywords)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 类型 1全部 2帖子 3资讯 4赛事 5用户
		$type = $this->param('type', true, 1);
		// 输出数据
		$result = [
			'users' => ['data' => [], 'count' => 0],
			'information' => ['data' => [], 'count' => 0],
			'format_posts' => ['data' => [], 'count' => 0],
			'format_matches' => ['data' => [], 'count' => 0],
		];
		// 分页参数
		$page = $this->param('page', true, 1);
		$size = $this->param('size', true, 10);
		// 帖子
		if ($type == 1 || $type == 2) {
			$statusArr = [AdminUserPost::NEW_STATUS_NORMAL, AdminUserPost::NEW_STATUS_REPORTED, AdminUserPost::NEW_STATUS_LOCK];
			$where = ['status' => [$statusArr, 'in'], 'title' => [$keywords, 'like']];
			[$list, $count] = AdminUserPost::getInstance()
				->findAll($where, null, null, true, $page, $size);
			$result['format_posts']['list'] = $list = FrontService::handPosts($list, $this->authId);
			$result['format_posts']['count'] = $count;
			if ($type == 2) {
				$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => $list, 'count' => $count]);
			}
		}
		// 资讯
		if ($type == 1 || $type == 3) {
			$where = ['status' => AdminInformation::STATUS_NORMAL, 'title' => [$keywords, 'like']];
			[$list, $count] = AdminInformation::getInstance()
				->findAll($where, null, 'created_at,desc', true, $page, $size);
			$result['information']['list'] = $list = FrontService::handInformation($list, $this->authId);
			$result['information']['count'] = $count;
			if ($type == 3) {
				$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => $list, 'count' => $count]);
			}
		}
		//比赛
		if ($type == 1 || $type == 4) {
			$list = [];
			$count = 0;
			$tmp = AdminTeam::getInstance()->findAll(['name_zh' => [$keywords, 'like']], 'team_id');
			if (!empty($tmp)) {
				$teamIdsStr = join(',', array_column($tmp, 'team_id'));
				$where = 'home_team_id in(' . $teamIdsStr . ') or away_team_id in(' . $teamIdsStr . ')';
				[$list, $count] = AdminMatch::getInstance()
					->findAll($where, null, 'match_time,desc', true, $page, $size);
				$result['format_matches']['list'] = $list = FrontService::handMatch($list, 0, true, false, true);
				$result['format_matches']['count'] = $count;
			}
			if ($type == 4) {
				$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => $list, 'count' => $count]);
			}
		}
		// 用户
		if ($type == 1 || $type == 5) {
			$statusArr = [AdminUser::STATUS_NORMAL, AdminUser::STATUS_REPORTED, AdminUser::STATUS_FORBIDDEN];
			$where = ['nickname' => [$keywords, 'like'], 'status' => [$statusArr, 'in']];
			[$list, $count] = AdminUser::getInstance()
				->findAll($where, null, 'created_at,desc', true, $page, $size);
			$result['users']['list'] = $list = FrontService::handUser($list, $this->authId);
			$result['users']['count'] = $count;
			if ($type == 5) {
				$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => $list, 'count' => $count]);
			}
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 我关注的人的帖子列表
	 * @throws
	 */
	public function myFollowUserPosts()
	{
		// 分页参数
		$page = $this->param('page', true, 1);
		$size = $this->param('size', true, 10);
		// 获取用户编号清单
		$userIds = AppFunc::getUserFollowing($this->authId);
		$userIds = empty($userIds) ? [] : array_unique(array_filter($userIds));
		if (empty($userIds)) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => [], 'count' => 0]);
		// 获取分页数据
		$where = ['status' => AdminUserPost::STATUS_EXAMINE_SUCCESS, 'user_id' => [$userIds, 'in']];
		$fields = 'id,cat_id,user_id,title,imgs,created_at,hit,fabolus_number,content,respon_number,collect_number';
		[$list, $count] = AdminUserPost::getInstance()->findAll($where, $fields, 'created_at,desc', true, $page, $size);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => $list, 'count' => $count]);
	}
	
	/**
	 * 发帖
	 * @throws
	 */
	public function postAdd()
	{
		// 登录校验
		if ($this->authId < 1) $this->output(Status::CODE_LOGIN_ERR, Status::$msg[Status::CODE_LOGIN_ERR]);
		// 是否频繁操作
		if (Cache::get('user_publish_post_' . $this->authId)) {
			$this->output(Status::CODE_WRONG_LIMIT, Status::$msg[Status::CODE_WRONG_LIMIT]);
		}
		// 是否发布
		$isPublish = $this->param('is_save', true) > 0;
		// 若已被禁言,无法发布, 否则 若包含敏感词可以保存到草稿箱
		if ($isPublish && $this->auth['status'] == AdminUser::STATUS_FORBIDDEN) {
			$this->output(Status::CODE_STATUS_FORBIDDEN, Status::$msg[Status::CODE_STATUS_FORBIDDEN]);
		}
		// 参数校验
		$validate = new Validate();
		$validate->addColumn('cat_id')->required();
		$validate->addColumn('title')->required()->lengthMin(1);
		$validate->addColumn('content')->required()->lengthMin(1);
		if (!$validate->validate($this->param())) {
			$this->output(Status::CODE_W_PARAM, $validate->getError()->__toString());
		}
		$imgs = $this->param('imgs');
		$postId = $this->param('pid', true);
		$title = $this->param('title');
		$content = $this->param('content');
		$categoryId = $this->param('cat_id', true);
		// 标题校验
		if (AppFunc::have_special_char($title)) {
			$this->output(Status::CODE_UNVALID_CODE, Status::$msg[Status::CODE_UNVALID_CODE]);
		}
		// 帖子数据
		$data = [
			'title' => $title,
			'cat_id' => $categoryId,
			'user_id' => $this->authId,
			'content' => base64_encode(addslashes(htmlspecialchars($content))),
		];
		if (!empty($imgs)) $data['imgs'] = $imgs;
		Cache::set('user_publish_post_' . $this->authId, 1, 10);
		if ($isPublish) {
			// 默认为已发布状态, 若包含敏感词,存入草稿箱
			$data['status'] = AdminUserPost::NEW_STATUS_NORMAL;
			// 敏感词清单
			$words = AdminSensitive::getInstance()->findAll(['status' => AdminSensitive::STATUS_NORMAL], 'word');
			if (!empty($words)) {
				foreach ($words as $v) {
					if (empty($v['word'])) continue;
					if (strpos($content, $v['word']) !== false || strpos($title, $v['word']) !== false) {
						// 帖子已保存,不再发送站内信
						if ($postId > 0) {
							$this->output(Status::CODE_ADD_POST_SENSITIVE, sprintf(Status::$msg[Status::CODE_ADD_POST_SENSITIVE], $v['word']));
						}
						//发送站内信
						$data['status'] = AdminUserPost::NEW_STATUS_SAVE;
						$postId = AdminUserPost::getInstance()->insert($data);
						$message = [
							'type' => 1,
							'status' => 0,
							'post_id' => $postId,
							'title' => '帖子未通过审核',
							'user_id' => $this->authId,
							'content' => sprintf('您发布的帖子【%s】包含敏感词【%s】，未发送成功，已移交至草稿箱，请检查修改后再提交', $data['title'], $v['word']),
						];
						AdminMessage::getInstance()->insert($message);
						$this->output(Status::CODE_ADD_POST_SENSITIVE, sprintf(Status::$msg[Status::CODE_ADD_POST_SENSITIVE], $v['word']));
					}
				}
			}
			if ($postId > 0) AdminUserPost::getInstance()->saveDataById($postId, $data); // 更新贴子
			if ($postId < 1) $postId = AdminUserPost::getInstance()->insert($data); // 插入数据
			TaskManager::getInstance()->async(new SerialPointTask(['task_id' => 2, 'user_id' => $this->authId]));
			// 封装帖子数据
			$tmp = AdminUserPost::getInstance()->findAll($postId);
			$post = FrontService::handPosts($tmp, $this->authId)[0];
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $post);
		}
		// 保存
		$data['status'] = AdminUserPost::NEW_STATUS_SAVE;
		if ($postId < 1 && AdminUserPost::getInstance()->insert($data)) {
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
		}
		// 更新
		if ($postId > 0 && AdminUserPost::getInstance()->saveDataById($postId, $data)) {
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
		}
		$this->output(Status::CODE_ADD_POST, Status::$msg[Status::CODE_ADD_POST]);
	}
	
	/**
	 * 热搜
	 * @throws
	 */
	public function hotSearch()
	{
		$keys = [AdminSysSettings::SETTING_HOT_SEARCH, AdminSysSettings::SETTING_HOT_SEARCH_CONTENT];
		$mapper = AdminSysSettings::getInstance()->findAll(['sys_key' => [$keys, 'in']], 'sys_key,sys_value', null,
			false, 0, 0, 'sys_key,sys_value,true');
		// 输出数据
		$result = [
			'hot_search' => empty($mapper[AdminSysSettings::SETTING_HOT_SEARCH]) ?
				[] : json_decode($mapper[AdminSysSettings::SETTING_HOT_SEARCH], true),
			'default_search_content' => empty($mapper[AdminSysSettings::SETTING_HOT_SEARCH_CONTENT]) ?
				[] : json_decode($mapper[AdminSysSettings::SETTING_HOT_SEARCH_CONTENT], true),
		];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 帖子详情
	 * @throws
	 */
	public function detail()
	{
		// 参数校验
		$postId = $this->param('post_id', true);
		if ($postId < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 排序类型 1热度/回复数 2最新回复 3最早回复
		$orderType = $this->param('order_type', true, 1);
		if ($orderType > 3 || $orderType < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 获取帖子信息
		$post = AdminUserPost::getInstance()->findOne($postId);
		if (empty($post)) $this->output(Status::CODE_ERR, '对应帖子不存在');
		// 若是其他人的帖子,增加点击率
		if ($post['user_id'] != $this->authId) AdminUserPost::getInstance()->saveDataById($postId, ['hit' => QueryBuilder::inc()]);
		// 封装帖子信息
		$post = FrontService::handPosts([$post], $this->authId)[0];
		// 获取最新评论分页数据
		$page = $this->param('page', true, 1);
		$size = $this->param('size', true, 10);
		$where = [
			'post_id' => $postId,
			'top_comment_id' => 0,
			'status' => [[AdminPostComment::STATUS_NORMAL, AdminPostComment::STATUS_REPORTED], 'in'],
		];
		// 只要我的贴子
		$onlyAuthor = $this->param('only_author', true) > 0;
		if ($onlyAuthor) $where['user_id'] = intval($post['user_id']);
		// 获取评论清单
		$order = [['created_at', $orderType == 2 ? 'desc' : 'asc']];
		if ($orderType == 1) array_unshift($order, ['fabolus_number', 'desc']);
		[$list, $count] = AdminPostComment::getInstance()->findAll($where, null, $order, true, $page, $size);
		// 输出数据
		$result = ['count' => $count, 'basic' => $post, 'comment' => []];
		if (empty($list)) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
		// 用户数据映射
		$userIds = array_unique(array_filter(array_column($list, 'user_id')));
		$userMapper = empty($userIds) ? [] : AdminUser::getInstance()
			->findAll(['id' => [$userIds, 'in']], 'id,nickname,photo,is_offical,level', null,
				false, 0, 0, 'id,*,true');
		// 点赞数据映射
		$commentIds = array_column($list, 'id');
		$where = ['item_id' => [$commentIds, 'in'], 'item_type' => 2, 'type' => 1, 'user_id' => $this->authId, 'is_cancel' => 0];
		$operateMapper = empty($commentIds) ? [] : AdminUserOperate::getInstance()->findAll($where, 'item_id', null,
			false, 0, 0, 'item_id,item_id,true');
		// 回复数据映射
		$childGroupMapper = [];
		$statusList = [AdminPostComment::STATUS_NORMAL, AdminPostComment::STATUS_REPORTED];
		$statusStr = join(',', $statusList);
		$subSql = 'select count(*)+1 from admin_user_post_comments x where x.top_comment_id=a.top_comment_id and x.post_id=? and x.status in(' . $statusStr . ') having (count(*)+1)<=3';
		$where = ['post_id' => $postId, 'status' => [$statusList, 'in'], 'top_comment_id' => [$commentIds, 'in'], 'exists' => $subSql];
		$tmp = empty($commentIds) ? [] : AdminPostComment::getInstance()->findAll($where, null, 'created_at desc');
		foreach ($tmp as $v) {
			$id = intval($v['top_comment_id']);
			$childGroupMapper[$id][] = $v;
		}
		// 填充列表数据
		foreach ($list as $v) {
			$id = intval($v['id']);
			$userId = intval($v['user_id']);
			$userInfo = empty($userMapper[$userId]) ? [] : $userMapper[$userId];
			$children = empty($childGroupMapper[$id]) ? [] : $childGroupMapper[$id];
			$childrenCount = empty($childCountMapper[$id]) ? [] : $childCountMapper[$id];
			$result['comment'][] = [
				'id' => $id,
				'user_info' => $userInfo,
				'created_at' => $v['created_at'],
				'respon_number' => $v['respon_number'],
				'child_comment_count' => $childrenCount,
				'fabolus_number' => $v['fabolus_number'],
				'content' => base64_decode($v['content']),
				'is_follow' => AppFunc::isFollow($this->authId, $userId),
				'is_fabolus' => $this->authId > 0 ? !empty($operateMapper[$id]) : false,
				'child_comment_list' => FrontService::handComments($children, $this->authId),
			];
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 帖子二级评论列表
	 * @throws
	 */
	public function getAllChildComments()
	{
		// 参数校验
		$commentId = $this->param('commentId', true);
		if ($commentId < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 获取评论信息
		$comment = AdminPostComment::getInstance()->findOne($commentId);
		if (empty($comment)) $this->output(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
		// 获取一级评论信息
		$topComment = $comment;
		$topCommentId = empty($comment['top_comment_id']) ? 0 : intval($comment['top_comment_id']);
		if ($topCommentId > 0) {
			$where = ['id' => $topCommentId, 'status' => [AdminUserPost::NEW_STATUS_DELETED, '<>']];
			$topComment = AdminPostComment::getInstance()->findOne($where);
		}
		// 输出数据
		$result = ['fatherComment' => [], 'childComment' => [], 'count' => 0];
		// 封装一级评论信息
		$tmp = empty($topComment) ? [] : FrontService::handComments([$topComment], $this->authId);
		if (!empty($tmp)) $result['fatherComment'] = $tmp[0];
		// 封装二级评论信息
		$page = $this->param('page', true, 1);
		$size = $this->param('size', true, 10);
		$where = ['top_comment_id' => $topCommentId, 'status' => [AdminUserPost::STATUS_DEL, '<>']];
		[$list, $result['count']] = AdminPostComment::getInstance()
			->findAll($where, '*', 'created_at,desc', true, $page, $size);
		if (!empty($list)) $result['childComment'] = FrontService::handComments($list, $this->authId);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 1发帖 2回帖 3资讯评论 列表
	 * @throws
	 */
	public function userFirstPage()
	{
		// 用户ID校验
		$userId = $this->param('uid', true, $this->authId);
		// 是否已关注
		$isFollow = AppFunc::isFollow($this->authId, $userId);
		// 输出数据
		$result = ['is_me' => $this->authId == $userId, 'is_follow' => $isFollow, 'list' => ['data' => [], 'count' => 0]];
		// 分页参数
		$page = $this->param('page', true, 1);
		$size = $this->param('size', true, 10);
		// 类型校验 1发帖 2回帖 3资讯评论
		$type = $this->param('type', true);
		switch ($type) {
			case 1: // 发帖
				$where = ['user_id' => $userId, 'status' => [AdminUserPost::SHOW_IN_FRONT, 'in']];
				[$list, $count] = AdminUserPost::getInstance()->findAll($where, null, 'created_at,desc', true, $page, $size);
				$result['list'] = ['count' => $count, 'data' => FrontService::handPosts($list, $this->authId)];
				$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
			case 2: // 回帖
				$where = ['user_id' => $userId, 'status' => [AdminPostComment::SHOW_IN_FRONT, 'in']];
				[$list, $count] = AdminPostComment::getInstance()->findAll($where, null, 'created_at,desc', true, $page, $size);
				$result['list'] = ['count' => $count, 'data' => FrontService::handComments($list, $this->authId)];
				$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
			case 3: // 资讯评论
				$where = ['user_id' => $userId, 'status' => [AdminInformationComment::SHOW_IN_FRONT, 'in']];
				[$list, $count] = AdminInformationComment::getInstance()->findAll($where, null, 'created_at,desc', true, $page, $size);
				$result['list'] = ['count' => $count, 'data' => FrontService::handInformationComment($list, $this->authId)];
				$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 关注及粉丝列表
	 * @throws
	 */
	public function myFollowings()
	{
		// 类型校验 1关注列表 2粉丝列表
		$type = $this->param('type', true);
		if ($type != 1 && $type != 2) $this->output(Status::CODE_W_PARAM, Statuses::$msg[Status::CODE_W_PARAM]);
		// 用户ID校验
		$userId = $this->param('uid', true, $this->authId);
		if ($userId < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 关联用户ID清单
		$userIds = $type == 1 ? AppFunc::getUserFollowing($userId) : AppFunc::getUserFans($userId);
		if (!empty($userIds)) $userIds = array_values(array_unique(array_filter($userIds)));
		$users = empty($userIds) ? [] : AdminUser::getInstance()
			->findAll(['id' => [$userIds, 'in']], 'id,nickname,photo,level,is_offical');
		$users = array_map(function ($v) {
			return [
				'id' => $v['id'],
				'photo' => $v['photo'],
				'level' => $v['level'],
				'nickname' => $v['nickname'],
				'is_me' => $v['id'] == $this->authId,
				'is_offical' => $v['is_offical'],
				'is_follow' => AppFunc::isFollow($this->authId, $v['id']),
			];
		}, $users);
		// 输出数据
		$result = ['count' => count($users), 'data' => $users];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 用户基本资料
	 * @throws
	 */
	public function userInfo()
	{
		// 参数校验
		$userId = $this->param('user_id', true);
		if ($userId < 1) $this->output(Status::CODE_LOGIN_ERR, Status::$msg[Status::CODE_LOGIN_ERR]);
		// 获取用户信息
		$user = AdminUser::getInstance()->findOne($userId, 'id,nickname,photo,level,point,is_offical');
		$user['fans_count'] = count(AppFunc::getUserFans($userId));
		$user['follow_count'] = count(AppFunc::getUserFollowing($userId));
		$user['is_me'] = $user['is_follow'] = false;
		if ($this->authId > 0) {
			$user['is_me'] = $this->authId == $userId;
			$user['is_follow'] = AppFunc::isFollow($this->authId, $userId);
		}
		$tmp = AdminUserPost::getInstance()->findOne(['user_id' => $userId, 'status' => AdminUserPost::NEW_STATUS_NORMAL], 'count(*) total');
		$postCount = empty($tmp[0]['total']) ? 0 : intval($tmp[0]['total']);
		$tmp = AdminUserPost::getInstance()->findOne(['user_id' => $userId, 'status' => [AdminPostComment::STATUS_DEL, '<>']], 'count(*) total');
		$postCommentCount = empty($tmp[0]['total']) ? 0 : intval($tmp[0]['total']);
		$tmp = AdminInformationComment::getInstance()->findOne(['user_id' => $userId, 'status' => [AdminInformationComment::STATUS_DELETE, '<>']], 'count(*) total');
		$informationCommentCount = empty($tmp[0]['total']) ? 0 : intval($tmp[0]['total']);
		$user['item_total'] = [
			'post_total' => $postCount,
			'comment_total' => $postCommentCount,
			'information_comment_total' => $informationCommentCount,
		];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $user);
	}
	
	/**
	 * 常见问题清单
	 * @throws
	 */
	public function normalProblemList()
	{
		// 获取清单
		$result = AdminNormalProblems::getInstance()->all();
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 回复的评论
	 * @throws
	 */
	public function getPostChildComments()
	{
		// 参数校验
		$postId = $this->param('postId', true);
		$commentId = $this->param('commentId', true);
		if ($postId < 1 || $commentId < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 分页参数
		$page = $this->param('page', true, 1);
		$size = $this->param('size', true, 10);
		// 分页数据
		$where = ['parent_id' => $commentId, 'post_id' => $postId, 'status' => 1];
		[$list, $count] = AdminPostComment::getInstance()->findAll($where, null, 'created_at,desc', true, $page, $size);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => $list, 'count' => $count]);
	}
	
	/**
	 * 评论内容详情
	 * @throws
	 */
	public function commentInfo()
	{
		// 参数校验
		$commentId = $this->param('commentId', true);
		if ($commentId < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 获取评论信息
		$comment = AdminPostComment::getInstance()->findOne($commentId);
		if (empty($comment)) $this->output(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
		// 评论已删除
		if ($comment['status'] == AdminPostComment::STATUS_DEL) {
			$this->output(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
		}
		// 封装评论信息
		$tmp = FrontService::handComments([$comment], $this->authId);
		$comment = empty($tmp) ? [] : $tmp[0];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $comment);
	}
}