<?php

namespace App\HttpController\User;

use App\lib\Utils;
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
	 * 社区首页
	 * @throws
	 */
	public function getContent(): bool
	{
		// 参数过滤
		$params = $this->params;
		$categoryId = empty($params['category_id']) || intval($params['category_id']) < 1 ? 1 : intval($params['category_id']);
		$orderType = empty($params['order_type']) || intval($params['order_type']) < 1 ? 1 : intval($params['order_type']);
		$isRefine = empty($params['is_refine']) || intval($params['is_refine']) < 1 ? false : true;
		$page = empty($params['page']) || intval($params['page']) < 1 ? 1 : intval($params['page']);
		$size = empty($params['size']) || intval($params['size']) < 1 ? 15 : intval($params['size']);
		// 当前登录用户ID
		$authId = empty($this->auth['id']) || intval($this->auth['id']) < 1 ? 0 : intval($this->auth['id']);
		// 状态条件
		$statusStr = AdminUserPost::NEW_STATUS_NORMAL . ',' . AdminUserPost::NEW_STATUS_REPORTED . ',' . AdminUserPost::NEW_STATUS_LOCK;
		// 获取帖子清单助手
		$getPostListHandler = function ($where) use ($isRefine, $orderType, $authId, $page, $size) {
			if ($isRefine > 0) $where .= ' and is_refine=1'; // 精华
			if ($orderType < 1 || $orderType > 4) return false;
			// 1热度/回复数 2最新发帖 3最早发帖 4最新回复
			$order = $orderType == 1 ? 'respon_number desc' : ($orderType == 2 ? 'created_at desc' :
				($orderType == 3 ? 'created_at asc' : 'last_respon_time desc'));
			$data = Utils::queryHandler(AdminUserPost::getInstance(), $where, null,
				'*', false, $order, null, $page, $size);
			$list = FrontService::handPosts($data['list'], $authId);
			return ['normal_posts' => $list, 'count' => $data['total']];
		};
		// 关注的人 帖子列表
		if ($categoryId == 2) {
			$userIds = $authId > 0 ? AppFunc::getUserFollowing($authId) : false;
			if (empty($userIds)) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => [], 'count' => 0]);
			$where = 'status in(' . $statusStr . ') and user_id in(' . join(',', $userIds) . ')';
			$result = $getPostListHandler($where);
			if ($result === false) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
		}
		// 输出数据
		$result = ['count' => 0, 'title' => [], 'banner' => [], 'top_posts' => [], 'normal_posts' => []];
		// 模块标题
		$result['title'] = Utils::queryHandler(AdminUserPostsCategory::getInstance(),
			'status=?', AdminUserPostsCategory::STATUS_NORMAL, 'id,name,icon', false);
		// 模块轮播
		$tmp = Utils::queryHandler(AdminUserPostsCategory::getInstance(), 'id=?', $categoryId, 'id,dispose');
		if (!empty($tmp['dispose'])) {
			$tmp = json_decode($tmp['dispose'], true);
			foreach ($tmp as $v) {
				if (Time::isBetween($v['start_time'], $v['end_time'])) $result['banner'][] = $v;
			}
		}
		//置顶帖子
		$where = 'status in (' . $statusStr . ') and ' . ($categoryId == 1 ? 'is_all_top=1' : 'is_top=1 and cat_id=' . $categoryId);
		$result['top_posts'] = Utils::queryHandler(AdminUserPost::getInstance(),
			$where, null, 'id,title', false, 'created_at desc');
		//普通帖子
		$where = 'status in (' . $statusStr . ')';
		if ($categoryId != 1) $where .= ' and cat_id=' . $categoryId;
		$result = array_merge($result, $getPostListHandler($where));
		if ($result === false) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 前端模糊搜索
	 * @throws
	 */
	public function getContentByKeyWord(): bool
	{
		$params = $this->params;
		// 关键字校验
		$keywords = empty($params['key_word']) || empty(trim($params['key_word'])) ? '' : trim($params['key_word']);
		if (empty($keywords)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 登录用户ID
		$authId = empty($this->auth['id']) ? 0 : intval($this->auth['id']);
		// 类型 1全部 2帖子 3资讯 4赛事 5用户
		$type = empty($params['type']) || intval($params['page']) < 1 ? 1 : intval($params['type']);
		// 输出数据
		$result = [
			'users' => ['data' => [], 'count' => 0],
			'information' => ['data' => [], 'count' => 0],
			'format_posts' => ['data' => [], 'count' => 0],
			'format_matches' => ['data' => [], 'count' => 0],
		];
		// 分页参数
		$page = empty($params['page']) ? 1 : $params['page'];
		$size = empty($params['size']) ? 10 : $params['size'];
		// 帖子
		if ($type == 1 || $type == 2) {
			$statusArr = [AdminUserPost::NEW_STATUS_NORMAL, AdminUserPost::NEW_STATUS_REPORTED, AdminUserPost::NEW_STATUS_LOCK];
			$where = ['status' => [$statusArr, 'in'], 'title' => [$keywords, 'like']];
			[$list, $count] = AdminUserPost::getInstance()
				->findAll($where, '*', null, true, $page, $size);
			$result['format_posts']['list'] = $list = FrontService::handPosts($list, $authId);
			$result['format_posts']['count'] = $count;
			if ($type == 2) {
				$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => $list, 'count' => $count]);
			}
		}
		// 资讯
		if ($type == 1 || $type == 3) {
			$where = ['status' => AdminInformation::STATUS_NORMAL, 'title' => [$keywords, 'like']];
			[$list, $count] = AdminInformation::getInstance()
				->findAll($where, '*', 'created_at,desc', true, $page, $size);
			$result['information']['list'] = $list = FrontService::handInformation($list, $authId);
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
					->findAll($where, '*', 'match_time,desc', true, $page, $size);
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
				->findAll($where, '*', 'created_at,desc', true, $page, $size);
			$result['users']['list'] = $list = FrontService::handUser($list, $authId);
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
	public function myFollowUserPosts(): bool
	{
		$params = $this->params;
		$authId = empty($this->auth['id']) ? 0 : intval($this->auth['id']);
		$page = empty($params['page']) || intval($params['page']) < 1 ? 1 : intval($params['page']);
		$size = empty($params['size']) || intval($params['size']) < 1 ? 10 : intval($params['size']);
		// 获取用户编号清单
		$userIds = AppFunc::getUserFollowing($authId);
		$userIds = empty($userIds) ? [] : array_unique(array_filter($userIds));
		if (empty($userIds)) {
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => [], 'count' => 0]);
		}
		$userIds = array_map(function ($v) {
			return intval($v);
		}, $userIds);
		$model = AdminUserPost::getInstance()
			->field(['id', 'cat_id', 'user_id', 'title', 'imgs', 'created_at', 'hit', 'fabolus_number', 'content', 'respon_number', 'collect_number'])
			->where('status', AdminUserPost::STATUS_EXAMINE_SUCCESS)->where('user_id', $userIds, 'in')
			->getLimit($page, $size);
		$list = $model->all();
		// 输出数据
		$result = [
			'count' => $model->lastQueryResult()->getTotalCount(),
			'data' => FrontService::handPosts($list, $authId),
		];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 发帖
	 * @throws
	 */
	public function postAdd(): bool
	{
		// 登录校验
		$authId = empty($this->auth['id']) ? 0 : intval($this->auth['id']);
		if ($authId < 1) {
			$this->output(Status::CODE_LOGIN_ERR, Status::$msg[Status::CODE_LOGIN_ERR]);
		}
		// 是否频繁操作
		if (Cache::get('user_publish_post_' . $authId)) {
			$this->output(Status::CODE_WRONG_LIMIT, Status::$msg[Status::CODE_WRONG_LIMIT]);
		}
		$params = $this->params;
		// 是否发布
		$isPublish = empty($params['is_save']) || intval($params['is_save']) < 1 ? false : true;
		// 若已被禁言,无法发布, 否则 若包含敏感词可以保存到草稿箱
		if ($isPublish && $this->auth['status'] == AdminUser::STATUS_FORBIDDEN) {
			$this->output(Status::CODE_STATUS_FORBIDDEN, Status::$msg[Status::CODE_STATUS_FORBIDDEN]);
		}
		// 参数校验
		$validate = new Validate();
		$validate->addColumn('cat_id')->required();
		$validate->addColumn('title')->required()->lengthMin(1);
		$validate->addColumn('content')->required()->lengthMin(1);
		if (!$validate->validate($this->params)) {
			$this->output(Status::CODE_W_PARAM, $validate->getError()->__toString());
		}
		// 标题校验
		if (AppFunc::have_special_char($params['title'])) {
			$this->output(Status::CODE_UNVALID_CODE, Status::$msg[Status::CODE_UNVALID_CODE]);
		}
		// 帖子ID
		$postId = empty($params['pid']) || intval($params['pid']) < 1 ? 0 : intval($params['pid']);
		// 帖子数据
		$data = [
			'user_id' => $authId,
			'title' => $params['title'],
			'cat_id' => $params['cat_id'],
			'content' => base64_encode(addslashes(htmlspecialchars($params['content']))),
		];
		if (!empty($params['imgs'])) $data['imgs'] = $params['imgs'];
		Cache::set('user_publish_post_' . $authId, 1, 10);
		if ($isPublish) {
			// 默认为已发布状态, 若包含敏感词,存入草稿箱
			$data['status'] = AdminUserPost::NEW_STATUS_NORMAL;
			// 敏感词清单
			$words = AdminSensitive::getInstance()->where('status', AdminSensitive::STATUS_NORMAL)->field(['word'])->all();
			if (!empty($words)) {
				foreach ($words as $v) {
					if (empty($v['word'])) continue;
					if (strstr($params['content'], $v['word']) || strstr($params['title'], $v['word'])) {
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
							'user_id' => $authId,
							'title' => '帖子未通过审核',
							'content' => sprintf('您发布的帖子【%s】包含敏感词【%s】，未发送成功，已移交至草稿箱，请检查修改后再提交', $data['title'], $v['word']),
						];
						AdminMessage::getInstance()->insert($message);
						$this->output(Status::CODE_ADD_POST_SENSITIVE, sprintf(Status::$msg[Status::CODE_ADD_POST_SENSITIVE], $v['word']));
					}
				}
			}
			if ($postId > 0) AdminUserPost::getInstance()->update($data, $postId); // 更新贴子
			if ($postId < 1) $postId = AdminUserPost::getInstance()->insert($data); // 插入数据
			$data['task_id'] = 2;
			$data['user_id'] = $authId;
			TaskManager::getInstance()->async(new SerialPointTask($data));
			// 封装帖子数据
			$tmp = AdminUserPost::getInstance()->where('id', $postId)->all();
			$post = FrontService::handPosts($tmp, $authId)[0];
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $post);
		}
		// 保存
		$data['status'] = AdminUserPost::NEW_STATUS_SAVE;
		if ($postId < 1 && AdminUserPost::getInstance()->insert($data)) {
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
		}
		// 更新
		if ($postId > 0 && AdminUserPost::getInstance()->update($data, $postId)) {
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
		}
		$this->output(Status::CODE_ADD_POST, Status::$msg[Status::CODE_ADD_POST]);
	}
	
	/**
	 * 帖子详情
	 * @throws
	 */
	public function detail(): bool
	{
		// 参数校验
		$params = $this->params;
		$postId = empty($params['post_id']) || intval($params['post_id']) < 1 ? 0 : intval($params['post_id']);
		if ($postId < 1) {
			$this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		}
		// 获取帖子信息
		$post = AdminUserPost::getInstance()->get($postId);
		if (empty($post)) {
			$this->output(Status::CODE_ERR, '对应帖子不存在');
		}
		// 若是其他人的帖子,增加点击率
		$authId = empty($this->auth['id']) ? 0 : $this->auth['id'];
		if ($post['user_id'] != $authId) {
			$post->update(['hit' => QueryBuilder::inc()], $postId);
		}
		// 封装帖子信息
		$post = FrontService::handPosts([$post], $authId)[0];
		// 获取最新评论分页数据
		$page = empty($params['page']) || intval($params['page']) < 1 ? 1 : intval($params['page']);
		$size = empty($params['size']) || intval($params['size']) < 1 ? 10 : intval($params['size']);
		$model = AdminPostComment::getInstance()->where('post_id', $postId)
			->where('status', [AdminPostComment::STATUS_NORMAL, AdminPostComment::STATUS_REPORTED], 'in')
			->where('top_comment_id', 0);
		// 只要我的贴子
		$onlyAuthor = empty($params['only_author']) || intval($params['only_author']) < 1 ? false : true;
		if ($onlyAuthor) $model = $model->where('user_id', $post['user_id']);
		// 排序类型 1热度/回复数 2最新回复 3最早回复
		$orderType = empty($params['order_type']) || intval($params['order_type']) < 1 ? 1 : intval($params['order_type']);
		if ($orderType > 3 || $orderType < 1) {
			$this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		}
		if ($orderType == 1) $model = $model->order('fabolus_number', 'DESC');
		$model = $model->order('created_at', $orderType == 2 ? 'DESC' : 'ASC')
			->limit(($page - 1) * $size, $size)->withTotalCount();
		$list = $model->all();
		// 输出数据
		$result = ['count' => $model->lastQueryResult()->getTotalCount(), 'basic' => $post, 'comment' => []];
		if (!empty($list)) {
			$commentIdsStr = join(',', array_column($list, 'id'));
			$userIdsStr = join(',', array_unique(array_filter(array_column($list, 'user_id'))));
			// 用户数据映射
			$userMapper = empty($userIdsStr) ? [] : Utils::queryHandler(AdminUser::getInstance(),
				'id in(' . $userIdsStr . ')', null,
				'id,nickname,photo,is_offical,level', false, null, 'id,*,1');
			// 点赞数据映射
			$operateMapper = Utils::queryHandler(AdminUserOperate::getInstance(),
				'item_type=2 and item_id in(' . $commentIdsStr . ') and type=1 and user_id=? and is_cancel=0', $authId,
				'item_id', false, null, 'item_id,item_id,1');
			// 回复数据映射
			$childGroupMapper = [];
			$statusStr = AdminPostComment::STATUS_NORMAL . ',' . AdminPostComment::STATUS_REPORTED;
			$subSql = 'select count(*)+1 from admin_user_post_comments x where x.top_comment_id=a.top_comment_id and x.post_id=? and x.status in(' . $statusStr . ') having (count(*)+1)<=3';
			$tmp = Utils::queryHandler(AdminPostComment::getInstance(),
				'post_id=? and status in(' . $statusStr . ') and top_comment_id in(' . $commentIdsStr . ') and exists(' . $subSql . ')',
				[$postId, $postId],
				'*', false, 'a.created_at desc');
			foreach ($tmp as $v) {
				$id = intval($v['top_comment_id']);
				$childGroupMapper[$id][] = $v;
			}
			// 填充列表数据
			foreach ($list as $v) {
				$id = intval($v['id']);
				$userId = intval($v['user_id']);
				$children = empty($childGroupMapper[$id]) ? [] : $childGroupMapper[$id];
				$childrenCount = empty($childCountMapper[$id]) ? [] : $childCountMapper[$id];
				$userInfo = empty($userMapper[$userId]) ? [] : $userMapper[$userId];
				$result['comment'][] = [
					'id' => $id,
					'user_info' => $userInfo,
					'created_at' => $v['created_at'],
					'respon_number' => $v['respon_number'],
					'child_comment_count' => $childrenCount,
					'fabolus_number' => $v['fabolus_number'],
					'content' => base64_decode($v['content']),
					'is_follow' => AppFunc::isFollow($authId, $userId),
					'child_comment_list' => FrontService::handComments($children, $authId),
					'is_fabolus' => $authId > 0 ? !empty($operateMapper[$id]) : false,
				];
			}
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 热搜
	 * @throws
	 */
	public function hotSearch(): bool
	{
		$keys = [AdminSysSettings::SETTING_HOT_SEARCH, AdminSysSettings::SETTING_HOT_SEARCH_CONTENT];
		$tmp = AdminSysSettings::getInstance()->field('sys_key,sys_value')
			->where('sys_key', $keys, 'in')->indexBy('sys_key');
		// 输出数据
		$result = [
			'hot_search' => empty($tmp[AdminSysSettings::SETTING_HOT_SEARCH]) ?
				[] : json_decode($tmp[AdminSysSettings::SETTING_HOT_SEARCH], true),
			'default_search_content' => empty($tmp[AdminSysSettings::SETTING_HOT_SEARCH_CONTENT]) ?
				[] : json_decode($tmp[AdminSysSettings::SETTING_HOT_SEARCH_CONTENT], true),
		];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 帖子二级评论列表
	 * @throws
	 */
	public function getAllChildComments(): bool
	{
		// 参数校验
		$params = $this->params;
		$commentId = empty($params['comment_id']) || intval($params['comment_id']) < 1 ? 0 : intval($params['comment_id']);
		if ($commentId < 1) {
			$this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		}
		// 获取评论信息
		$comment = AdminPostComment::getInstance()->findOne($commentId);
		if (empty($comment)) {
			$this->output(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
		}
		// 获取一级评论信息
		$authId = empty($this->auth['id']) ? 0 : $this->auth['id'];
		$topComment = $comment;
		$topCommentId = empty($comment['top_comment_id']) || $comment['top_comment_id'] < 1 ? 0 : $comment['top_comment_id'];
		if ($topCommentId > 0) {
			$topComment = AdminPostComment::getInstance()
				->where('id', $topCommentId)
				->where('status', AdminUserPost::NEW_STATUS_DELETED, '<>')->get();
		}
		// 输出数据
		$result = ['fatherComment' => [], 'childComment' => [], 'count' => 0];
		// 封装一级评论信息
		$tmp = empty($topComment) ? [] : FrontService::handComments([$topComment], $authId);
		if (!empty($tmp)) $result['fatherComment'] = $tmp[0];
		// 封装二级评论信息
		$page = empty($params['page']) || intval($params['page']) < 1 ? 1 : intval($params['page']);
		$size = empty($params['size']) || intval($params['size']) < 1 ? 10 : intval($params['size']);
		$model = AdminPostComment::getInstance()->where('top_comment_id', $topCommentId)
			->where('status', AdminUserPost::STATUS_DEL, '<>')->getAll($page, $size);
		$list = $model->all();
		if (!empty($list)) $result['childComment'] = FrontService::handComments($list, $authId);
		$result['count'] = $model->lastQueryResult()->getTotalCount();
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 1发帖 2回帖 3资讯评论 列表
	 * @throws
	 */
	public function userFirstPage(): bool
	{
		$params = $this->params;
		// 用户ID校验
		$authId = empty($this->auth['id']) ? 0 : intval($this->auth['id']);
		$userId = empty($params['uid']) || intval($params['uid']) < 1 ? $authId : intval($params['uid']);
		// 是否已关注
		$isFollow = AppFunc::isFollow($authId, $userId);
		// 输出数据
		$result = ['is_me' => $authId == $userId, 'is_follow' => $isFollow, 'list' => ['data' => [], 'count' => 0]];
		// 获取数据清单
		$page = empty($params['page']) || intval($params['page']) < 1 ? 1 : intval($params['page']);
		$size = empty($params['size']) || intval($params['size']) < 1 ? 10 : intval($params['size']);
		// 类型校验 1发帖 2回帖 3资讯评论
		$type = empty($params['type']) || intval($params['type']) < 1 ? 0 : intval($params['type']);
		switch ($type) {
			case 1: // 发帖
				$model = AdminUserPost::getInstance()->where('user_id', $userId)
					->where('status', AdminUserPost::SHOW_IN_FRONT, 'in')->getLimit($page, $size);
				$list = $model->all();
				$result['list'] = [
					'count' => $model->lastQueryResult()->getTotalCount(),
					'data' => FrontService::handPosts($list, $authId),
				];
				$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
			case 2: // 回帖
				$model = AdminPostComment::getInstance()->where('user_id', $userId)
					->where('status', AdminPostComment::SHOW_IN_FRONT, 'in')->getAll($page, $size);
				$list = $model->all();
				$result['list'] = [
					'count' => $model->lastQueryResult()->getTotalCount(),
					'data' => FrontService::handComments($list, $authId),
				];
				$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
			case 3: // 资讯评论
				$model = AdminInformationComment::getInstance()->where('user_id', $userId)
					->where('status', AdminInformationComment::SHOW_IN_FRONT, 'in')->getLimit($page, $size);
				$list = $model->all();
				$result['list'] = [
					'count' => $model->lastQueryResult()->getTotalCount(),
					'data' => FrontService::handInformationComment($list, $authId),
				];
				$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 关注及粉丝列表
	 * @throws
	 */
	public function myFollowings(): bool
	{
		$params = $this->params;
		// 类型校验 1关注列表 2粉丝列表
		$type = empty($params['type']) || intval($params['type']) < 1 ? 0 : intval($params['type']);
		if ($type != 1 && $type != 2) {
			$this->output(Status::CODE_W_PARAM, Statuses::$msg[Status::CODE_W_PARAM]);
		}
		// 用户ID校验
		$authId = empty($this->auth['id']) ? 0 : intval($this->auth['id']);
		$userId = empty($params['uid']) || intval($params['uid']) < 1 ? $authId : intval($params['uid']);
		if ($userId < 1) {
			$this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		}
		// 关联用户ID清单
		$userIds = $type == 1 ? AppFunc::getUserFollowing($userId) : AppFunc::getUserFans($userId);
		if (!empty($userIds)) $userIds = array_values(array_unique(array_filter($userIds)));
		$userIds = empty($userIds) ? [] : array_map(function ($v) {
			return intval($v);
		}, $userIds);
		$users = empty($userIds) ? [] : AdminUser::getInstance()->where('id', $userIds, 'in')
			->field(['id', 'nickname', 'photo', 'level', 'is_offical'])->all();
		$users = array_map(function ($v) use ($authId) {
			return [
				'id' => $v['id'],
				'photo' => $v['photo'],
				'level' => $v['level'],
				'nickname' => $v['nickname'],
				'is_me' => $v['id'] == $authId,
				'is_offical' => $v['is_offical'],
				'is_follow' => AppFunc::isFollow($authId, $v['id']),
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
	public function userInfo(): bool
	{
		// 参数校验
		$userId = empty($this->params['user_id']) || intval($this->params['user_id']) < 1 ? 0 : intval($this->params['user_id']);
		if ($userId < 1) {
			$this->output(Status::CODE_LOGIN_ERR, Status::$msg[Status::CODE_LOGIN_ERR]);
		}
		$authId = empty($this->auth['id']) ? 0 : intval($this->auth['id']);
		// 获取用户信息
		$userInfo = AdminUser::getInstance()->field(['id', 'nickname', 'photo', 'level', 'point', 'is_offical'])->get($userId);
		$userInfo['fans_count'] = count(AppFunc::getUserFans($userId));
		$userInfo['follow_count'] = count(AppFunc::getUserFollowing($userId));
		$userInfo['is_me'] = $userInfo['is_follow'] = false;
		if ($authId > 0) {
			$userInfo['is_me'] = $authId == $userId;
			$userInfo['is_follow'] = AppFunc::isFollow($authId, $userId);
		}
		$userInfo['item_total'] = [
			'post_total' => AdminUserPost::getInstance()
				->where('user_id', $userId)->where('status', AdminUserPost::NEW_STATUS_DELETED, '<>')->count(),
			'comment_total' => AdminPostComment::getInstance()
				->where('user_id', $userId)->where('status', AdminPostComment::STATUS_DEL, '<>')->count(),
			'information_comment_total' => AdminInformationComment::getInstance()
				->where('user_id', $userId)->where('status', AdminInformationComment::STATUS_DELETE, '<>')->count(),
		];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $userInfo);
	}
	
	/**
	 * 常见问题清单
	 * @throws
	 */
	public function normalProblemList(): bool
	{
		$result = AdminNormalProblems::getInstance()->all();
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * todo ... [回复的评论]
	 * @throws
	 */
	public function getPostChildComments(): bool
	{
		// 参数校验
		$params = $this->params;
		$postId = empty($params['pid']) || intval($params['pid']) < 1 ? 0 : intval($params['pid']);
		$commentId = empty($params['comment_id']) || intval($params['comment_id']) < 1 ? 0 : intval($params['comment_id']);
		if ($postId < 1 || $commentId < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 获取最新评论分页数据
		$page = empty($params['page']) || intval($params['page']) < 1 ? 1 : $params['page'];
		$size = empty($params['size']) || intval($params['size']) < 1 ? 10 : $params['size'];
		$where = ['parent_id' => $commentId, 'post_id' => $postId, 'status' => 1];
		// 获取分页数据
		[$list, $count] = AdminPostComment::getInstance()
			->findAll($where, '*', 'created_at,desc', true, $page, $size);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => $list, 'count' => $count]);
	}
	
	/**
	 * todo ... [评论内容详情]
	 * @throws
	 */
	public function commentInfo(): bool
	{
		// 参数校验
		$params = $this->params;
		$commentId = empty($params['comment_id']) || intval($params['comment_id']) < 1 ? 0 : intval($params['comment_id']);
		if ($commentId < 1) {
			$this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		}
		// 获取评论信息
		$comment = AdminPostComment::getInstance()->findOne($commentId);
		if (empty($comment)) {
			$this->output(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
		}
		// 评论已删除
		if ($comment['status'] == AdminPostComment::STATUS_DEL) {
			$this->output(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
		}
		// 封装评论信息
		$authId = empty($this->auth['id']) ? 0 : $this->auth['id'];
		$tmp = FrontService::handComments([$comment], $authId);
		$comment = empty($tmp) ? [] : $tmp[0];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $comment);
	}
}