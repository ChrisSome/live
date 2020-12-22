<?php

namespace App\HttpController\User;

use App\Base\FrontUserController;
use App\Common\AppFunc;
use App\lib\FrontService;
use App\lib\PasswordTool;
use App\Model\AdminInformation;
use App\Model\AdminInformationComment;
use App\Model\AdminMessage;
use App\Model\AdminPostComment;
use App\Model\AdminUser;
use App\Model\AdminUserInterestCompetition;
use App\Model\AdminUserOperate;
use App\Model\AdminUserPost;
use App\Task\SerialPointTask;
use App\Task\UserOperateTask;
use App\Utility\Log\Log;
use App\Utility\Message\Status;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\Validate\Validate;
use App\Task\UserTask;
use easySwoole\Cache\Cache;

class User extends FrontUserController
{
	protected $isCheckSign = false;
	protected $needCheckToken = true;
	
	/**
	 * 返回用户信息
	 * @throws
	 */
	public function info()
	{
		// 当前登录用户ID
		$authId = empty($this->auth['id']) || intval($this->auth['id']) < 1 ? 0 : intval($this->auth['id']);
		$result = $authId > 0 ? AdminUser::getInstance()->findOne($authId) : [];
		if (empty($result)) $result = [];
		$this->output(Status::CODE_OK, 'ok', $result);
	}
	
	/**
	 * 关注用户/取消关注
	 * @throws
	 */
	public function userFollowings()
	{
		// 参数校验
		$validator = new Validate();
		$validator->addColumn('follow_id')->required();
		$validator->addColumn('action_type')->required()->inArray(['add', 'del']);
		if (!$validator->validate($this->params)) {
			$this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		}
		$params = $this->params;
		// 登录用户ID
		$authId = intval($this->auth['id']);
		if ($authId < 1) $this->output(Status::CODE_WRONG_USER, Status::$msg[Status::CODE_WRONG_USER], 3);
		// 关注用户ID
		$followUserId = intval($params['follow_id']);
		if ($authId == $followUserId) $this->output(Status::CODE_WRONG_USER, Status::$msg[Status::CODE_WRONG_USER], 3);
		// 获取关注人信息
		$user = AdminUser::getInstance()->findOne($followUserId, 'id,nickname,photo');
		if (empty($user)) $this->output(Status::CODE_WRONG_USER, Status::$msg[Status::CODE_WRONG_USER], 3);
		// 是否为关注
		$isAdd = $params['action_type'] == 'add';
		if ($isAdd) {
			// 已关注的,忽略
			if (AppFunc::isFollow($authId, $followUserId)) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
			// 关注操作
			$result = AppFunc::addFollow($authId, $user['id']);
			// 发送消息
			$where = ['user_id' => $followUserId, 'item_id' => $followUserId, 'did_user_id' => $this->auth['id'], 'type' => 4, 'item_type' => 5];
			$message = AdminMessage::getInstance()->findOne($where);
			if (!empty($message)) {
				$message->saveDataById($message['id'], ['status' => AdminMessage::STATUS_UNREAD, 'created_at' => date('Y-m-d H:i:s')]);
			} else {
				$data = [
					'type' => 4,
					'item_type' => 5,
					'title' => '关注通知',
					'item_id' => $followUserId,
					'user_id' => $followUserId,
					'did_user_id' => $this->auth['id'],
					'status' => AdminMessage::STATUS_UNREAD,
				];
				AdminMessage::getInstance()->insert($data);
			}
		} else {
			// 取消关注操作
			$result = AppFunc::delFollow($authId, $user['id']);
			// 获取消息
			$where = ['user_id' => $followUserId, 'item_id' => $followUserId, 'did_user_id' => $this->auth['id'], 'type' => 4, 'item_type' => 5];
			$message = AdminMessage::getInstance()->findOne($where);
			// 删除该条消息
			if (!empty($message)) $message->setField('status', AdminMessage::STATUS_DEL);
		}
		if ($result) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
		$this->output(Status::CODE_USER_FOLLOW, Status::$msg[Status::CODE_USER_FOLLOW]);
	}
	
	/**
	 * 用户点赞 收藏 举报 帖子 评论 资讯评论 用户
	 * @throws
	 */
	public function informationOperate()
	{
		// 登录用户ID
		$authId = intval($this->auth['id']);
		if ($authId < 1) $this->output(Status::CODE_LOGIN_ERR, Status::$msg[Status::CODE_LOGIN_ERR]);
		// 参数校验
		$validate = new Validate();
		$validate->addColumn('item_id')->required();
		$validate->addColumn('author_id')->required();
		$validate->addColumn('is_cancel')->required();
		$validate->addColumn('type')->required()->inArray([1, 2, 3]); // 1点赞 2收藏 3举报
		$validate->addColumn('item_type')->required()->inArray([1, 2, 3, 4, 5]); // 1帖子 2帖子评论 3资讯 4资讯评论 5直播间发言
		if (!$validate->validate($this->params)) $this->output(Status::CODE_ERR, $validate->getError()->__toString());
		$params = $this->params;
		$type = intval($params['type']);
		if (Cache::get('user_operate_information_' . $authId . '-type-' . $type)) {
			$this->output(Status::CODE_WRONG_LIMIT, Status::$msg[Status::CODE_WRONG_LIMIT]);
		}
		if (!empty($params['remark']) && AppFunc::have_special_char($params['remark'])) {
			$this->output(Status::CODE_UNVALID_CODE, Status::$msg[Status::CODE_UNVALID_CODE]);
		}
		$itemId = intval($params['item_id']);
		$itemType = intval($params['item_type']);
		$authorId = intval($params['author_id']);
		$isCancel = intval($params['is_cancel']) > 0 ? 1 : 0;
		$operate = AdminUserOperate::getInstance()->findOne(['item_id' => $itemId, 'item_type' => $itemType, 'user_id' => $authId, 'type' => $type]);
		if (!empty($operate)) {
			if ($isCancel == $operate['is_cancel']) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
			$operate->setField('is_cancel', $isCancel);
		} else {
			$data = [
				'type' => $type,
				'user_id' => $authId,
				'item_id' => $itemId,
				'item_type' => $itemType,
				'author_id' => $authorId,
				'remark' => empty($params['remark']) ? '' : addslashes(htmlspecialchars(trim($params['remark']))),
				'content' => empty($params['content']) ? '' : addslashes(htmlspecialchars(trim($params['content']))),
			];
			AdminUserOperate::getInstance()->insert($data);
		}
		$taskData = [
			'type' => $type,
			'uid' => $authId,
			'item_id' => $itemId,
			'item_type' => $itemType,
			'is_cancel' => $isCancel,
			'author_id' => $authorId,
		];
		TaskManager::getInstance()->async(new UserOperateTask(['payload' => $taskData]));
		//		TaskManager::getInstance()->async(function () use ($itemType, $type, $itemId, $authorId, $authId, $isCancel) {
		//			if ($itemType == 1) {
		//				$model = AdminUserPost::getInstance();
		//				$status = AdminUserPost::NEW_STATUS_REPORTED;
		//			} elseif ($itemType == 2) {
		//				$model = AdminPostComment::getInstance();
		//				$status = AdminPostComment::STATUS_REPORTED;
		//			} elseif ($itemType == 3) {
		//				$model = AdminInformation::getInstance();
		//				$status = AdminInformation::STATUS_REPORTED;
		//			} elseif ($itemType == 4) {
		//				$model = AdminInformationComment::getInstance();
		//				$status = AdminInformationComment::STATUS_REPORTED;
		//			} else {
		//				$model = AdminUser::getInstance();
		//				$status = AdminUser::STATUS_REPORTED;
		//			}
		//			if ($type == 1) {
		//				if (!$isCancel) {
		//					$model->setField('fabolus_number', QueryBuilder::inc(1), $itemId);
		//					if ($authorId != $authId) {
		//						$where = ['user_id' => $authorId, 'type' => 2, 'item_type' => $itemType, 'item_id' => $itemId, 'did_user_id' => $authId];
		//						$message = AdminMessage::getInstance()->findOne($where);
		//						if (!empty($message)) { //更新消息状态
		//							$data = ['status' => AdminMessage::STATUS_UNREAD, 'created_at' => date('Y-m-d H:i:s')];
		//							$message->saveDataById($message['id'], $data);
		//						} else { //发送消息
		//							AdminMessage::getInstance()->insert([
		//								'status' => AdminMessage::STATUS_UNREAD,
		//								'user_id' => $authorId,
		//								'type' => 2,
		//								'item_type' => $itemType,
		//								'item_id' => $itemId,
		//								'title' => '点赞通知',
		//								'did_user_id' => $authId,
		//							]);
		//						}
		//					}
		//				} else {
		//					$model->setField('fabolus_number', QueryBuilder::dec(1), $itemId);
		//					$where = ['user_id' => $authorId, 'type' => 2, 'item_type' => $itemType, 'item_id' => $itemId, 'did_user_id' => $authId];
		//					$message = $authorId != $authId ? AdminMessage::getInstance()->findOne($where) : null;
		//					if (!empty($message)) $message->setField('status', AdminMessage::STATUS_DEL);
		//				}
		//			} elseif ($type == 2) {
		//				$number = $isCancel ? QueryBuilder::dec(1) : QueryBuilder::inc(1);
		//				$model->setField('collect_number', $number, $itemId);
		//			} elseif ($type == 3) {
		//				$model->setField('status', $status, $itemId);
		//			}
		//		});
		Cache::set('user_operate_information_' . $authId . '-type-' . $type, 1, 2);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
	}
	
	/**
	 * 发表帖子评论
	 * @throws
	 */
	public function doComment()
	{
		// 当前登录用户已被禁用
		$isForbidden = empty($this->auth) || $this->auth['status'] == AdminUser::STATUS_FORBIDDEN;
		if ($isForbidden) $this->output(Status::CODE_STATUS_FORBIDDEN, Status::$msg[Status::CODE_STATUS_FORBIDDEN]);
		// 当前登录用户ID
		$authId = intval($this->auth['id']);
		// 限制频繁操作
		if (Cache::get('userCom' . $authId)) {
			$this->output(Status::CODE_WRONG_LIMIT, Status::$msg[Status::CODE_WRONG_LIMIT]);
		}
		// 参数校验
		$validate = new Validate();
		$validate->addColumn('post_id')->min(1)->required();
		$validate->addColumn('content')->required();
		if (!$validate->validate($this->params)) {
			$this->output(Status::CODE_W_PARAM, $validate->getError()->__toString());
		}
		$params = $this->params;
		// 获取帖子信息
		$postId = intval($params('post_id'));
		$post = AdminUserPost::getInstance()->findOne($postId);
		if (empty($post)) $this->output(Status::CODE_WRONG_RES, '对应帖子不存在');
		// 未发布的帖子禁止评论
		if ($post['status'] != AdminUserPost::NEW_STATUS_NORMAL) {
			$this->output(Status::CODE_WRONG_RES, '该帖不可评论');
		}
		// 获取父级评论信息
		$parentId = empty($params['parent_id']) || intval($params['parent_id']) < 1 ? 0 : intval($params['parent_id']);
		if ($parentId > 0) {
			$parentComment = AdminPostComment::getInstance()->findOne($parentId);
			if (empty($parentComment) || $parentComment['status'] != AdminPostComment::STATUS_NORMAL) {
				$this->output(Status::CODE_WRONG_RES, '原始评论参数不正确');
			}
		}
		// 顶级回复的ID
		$topCommentId = empty($params['top_comment_id']) || intval($params['top_comment_id']) < 1 ? 0 : intval($params['top_comment_id']);
		$commentId = AdminPostComment::getInstance()->insert([
			'user_id' => $authId,
			'post_id' => $postId,
			'parent_id' => $parentId,
			'top_comment_id' => $topCommentId,
			'content' => base64_encode(htmlspecialchars(addslashes($params['content']))),
			't_u_id' => empty($parentComment['user_id']) ? intval($post['user_id']) : intval($parentComment['user_id']),
		]);
		$comment = AdminPostComment::getInstance()->findOne($commentId);
		$comment = FrontService::handComments([$comment], $authId)[0];
		if ($topCommentId > 0) AdminPostComment::create()->setField('respon_number', QueryBuilder::inc(1), $parentId);
		if (!empty($parentComment)) {
			$commentAuthorId = AdminPostComment::getInstance()->findOne($parentId)->user_id;
			$itemType = 2;
		} else {
			$commentAuthorId = AdminUserPost::getInstance()->findOne($postId)->user_id;
			$itemType = 1;
		}
		// 累加回复数
		AdminUserPost::create()->setField('respon_number', QueryBuilder::inc(), $postId);
		// 发送消息
		if ($commentAuthorId != $authId) AdminMessage::getInstance()->insert([
			'type' => 3,
			'title' => '帖子回复通知',
			'item_id' => $commentId,
			'did_user_id' => $authId,
			'item_type' => $itemType,
			'user_id' => $commentAuthorId,
			'status' => AdminMessage::STATUS_UNREAD,
		]);
		// 积分任务
		TaskManager::getInstance()->async(new SerialPointTask(['task_id' => 3, 'user_id' => $authId]));
		// 防频繁操作
		Cache::set('userCom' . $authId, 1, 5);
		// 帖子最新回复时间更新
		$post->setField('last_respon_time', date('Y-m-d H:i:s'));
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $comment);
	}
	
	/**
	 * 用户关注比赛
	 * @throws
	 */
	public function userInterestMatch()
	{
		// 参数校验
		$validate = new Validate();
		$validate->addColumn('match_id')->required();
		$validate->addColumn('type')->required()->inArray(['add', 'del']);
		if (!$validate->validate($this->params)) {
			$this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		}
		$params = $this->params;
		// 当前登录用户ID
		$authId = intval($this->auth['id']);
		$matchId = $params['match_id'];
		if ($params['type'] == 'add') {
			$res = AppFunc::userDoInterestMatch($matchId, $authId);
		} else {
			$res = AppFunc::userDelInterestMatch($matchId, $authId);
		}
		// 操作失败
		if ($res) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
		$this->output(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
	}
	
	/**
	 * 用户关注赛事
	 * @throws
	 */
	public function userInterestCompetition()
	{
		$params = $this->params;
		// 参数校验
		$competitionId = empty($params['competition_id']) || intval($params['competition_id']) < 1 ? 0 : intval($params['competition_id']);
		if ($competitionId < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 当前登录用户ID
		$authId = intval($this->auth['id']);
		$competition = AdminUserInterestCompetition::getInstance()->findOne(['user_id' => $authId]);
		if (!empty($competition)) {
			$result = $competition->setField('competition_ids', $competitionId);
		} else {
			$result = AdminUserInterestCompetition::getInstance()->insert([
				'user_id' => $authId,
				'competition_ids' => $competitionId,
			]);
		}
		// 操作失败
		if (empty($result)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
	}
	
	/**
	 * 微信解绑
	 * @throws
	 */
	public function unBindWx()
	{
		// 获取用户信息
		$authId = intval($this->auth['id']);
		$user = $authId > 0 ? AdminUser::getInstance()->findOne($authId) : null;
		if (empty($user)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 更新用户数据
		AdminUser::getInstance()->saveDataById($authId, ['wx_photo' => '', 'wx_name' => '', 'third_wx_unionid' => '']);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
	}
	
	/**
	 * 检查用户状态
	 * @throws
	 */
	public function checkUserStatus()
	{
		// 获取用户信息
		$authId = intval($this->auth['id']);
		$user = $authId > 0 ? AdminUser::getInstance()->findOne($authId) : null;
		$status = empty($user) || !in_array($user['status'], [AdminUser::STATUS_NORMAL, AdminUser::STATUS_REPORTED]);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $status);
	}
	
	/**
	 * 用户消息列表
	 * @throws
	 */
	public function userMessageList()
	{
		$authId = empty($this->auth['id']) || intval($this->auth['id']) < 1 ? 0 : intval($this->auth['id']);
		// 分页参数
		$params = $this->params;
		$page = empty($params['page']) ? 1 : $params['page'];
		$size = empty($params['size']) ? 10 : $params['size'];
		// 分页数据
		$where = ['status' => [AdminMessage::STATUS_DEL, '<>'], 'user_id' => $authId];
		[$list, $count] = AdminMessage::getInstance()
			->findAll($where, '*', [['status', 'asc'], ['created_at,desc']], true, $page, $size);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => $list, 'count' => $count]);
	}
}