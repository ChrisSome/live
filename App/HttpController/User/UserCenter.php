<?php

namespace App\HttpController\User;

use App\Common\AppFunc;
use App\Model\AdminUser;
use App\Utility\Log\Log;
use App\lib\FrontService;
use App\lib\PasswordTool;
use App\Model\ChatHistory;
use App\Model\AdminMessage;
use App\Model\AdminUserPost;
use EasySwoole\ORM\DbManager;
use App\Model\AdminInformation;
use App\Model\AdminPostComment;
use App\Model\AdminUserOperate;
use App\Model\AdminUserSetting;
use App\Utility\Message\Status;
use App\Model\AdminUserFeedBack;
use App\Base\FrontUserController;
use EasySwoole\Validate\Validate;
use App\Model\AdminUserFoulCenter;
use App\Model\AdminUserSerialPoint;
use EasySwoole\Mysqli\QueryBuilder;
use App\Model\AdminInformationComment;

class UserCenter extends FrontUserController
{
	protected $isCheckSign = false;
	protected $needCheckToken = true;
	
	/**
	 * 个人中心首页
	 * @throws
	 */
	public function UserCenter()
	{
		// 用户信息
		$userInfo = AdminUser::getInstance()->findOne($this->authId, 'id,nickname,photo,level,is_offical');
		// 粉丝数
		$fansCount = count(AppFunc::getUserFans($this->authId));
		// 关注数
		$followCount = count(AppFunc::getUserFollowing($this->authId));
		// 获赞数
		$fabolusNumber = AdminUserOperate::getInstance()->findOne(['author_id' => $this->authId, 'type' => 1], 'count');
		// 输出数据
		$result = [
			'user_info' => $userInfo,
			'fans_count' => AppFunc::changeToWan($fansCount, ''),
			'follow_count' => AppFunc::changeToWan($followCount, ''),
			'fabolus_count' => AppFunc::changeToWan($fabolusNumber, ''),
		];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 收藏夹
	 * @throws
	 */
	public function userBookMark()
	{
		// 类型校验
		$type = $this->param('type', true, 1);
		if ($type != 1 && $type != 2) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);;
		// 关键字
		$keywords = $this->param('key_word');
		// 分页参数
		$page = $this->param('page', true, 1);
		$size = $this->param('size', true, 10);
		if ($type == 1) {
			$sqlTemplate = 'select %s ' .
				'from admin_user_operates as a inner join admin_user_posts as b on a.item_id=b.id and a.author_id=b.user_id ' .
				'where a.item_type=1 and a.type=2 and a.user_id=%s and b.status in(1,2,6) and a.is_cancel=0 and b.title like "%s"';
			$list = AdminUserOperate::getInstance()->func(function ($builder) use ($sqlTemplate, $keywords) {
				$fields = 'b.*';
				$builder->raw(sprintf($sqlTemplate . ' order by a.created_at desc', $fields, $this->authId, '%' . $keywords . '%'), []);
				return true;
			});
			$list = empty($list) ? [] : FrontService::handPosts($list, $this->authId);
			$total = AdminUserOperate::getInstance()->func(function ($builder) use ($sqlTemplate, $keywords) {
				$fields = 'count(*) total';
				$builder->raw(sprintf($sqlTemplate, $fields, $this->authId, '%' . $keywords . '%'), []);
				return true;
			});
			$total = empty($total[0]['total']) ? 0 : intval($total[0]['total']);
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => $list, 'total' => $total]);
		}
		// 资讯数据
		$sqlTemplate = 'select %s ' .
			'from admin_user_operates as a inner join admin_information as b on a.item_id=b.id and a.author_id=b.user_id ' .
			'where a.item_type=3 and a.type=2 and a.user_id=%s and a.is_cancel=0 and b.title like "%s"';
		$list = AdminUserOperate::getInstance()->func(function ($builder) use ($sqlTemplate, $keywords) {
			$fields = 'b.*';
			$builder->raw(sprintf($sqlTemplate . ' order by a.created_at desc', $fields, $this->authId, '%' . $keywords . '%'), []);
			return true;
		});
		$list = empty($list) ? [] : FrontService::handInformation($list, $this->authId);
		$total = AdminUserOperate::getInstance()->func(function ($builder) use ($sqlTemplate, $keywords) {
			$fields = 'count(*) total';
			$builder->raw(sprintf($sqlTemplate, $fields, $this->authId, '%' . $keywords . '%'), []);
			return true;
		});
		$total = empty($total[0]['total']) ? 0 : intval($total[0]['total']);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => $list, 'total' => $total]);
	}
	
	/**
	 * 用户资料编辑
	 * @throws
	 */
	public function editUser()
	{
		$user = AdminUser::getInstance()->findOne($this->authId);
		if (empty($user)) $this->output(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
		// 类型校验
		$type = $this->param('type', true, 1);
		if ($type != 1 && $type != 2) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);
		// 更新数据
		$data = [];
		// 数据校验
		$validate = new Validate();
		$nickname = $this->param('nickname');
		if (!empty($nickname) && $type == 1) {
			$tmp = AdminUser::getInstance()->findOne(['nickname' => $nickname, 'id' => [$this->authId, '<>']]);
			if (!empty($tmp)) $this->output(Status::CODE_USER_DATA_EXIST, Status::$msg[Status::CODE_USER_DATA_EXIST]);
			$validate->addColumn('nickname', '申请昵称')->required()->lengthMax(32)->lengthMin(4);
			$data['nickname'] = $nickname;
		}
		$photo = $this->param('photo');
		if (!empty($photo) && $type == 2) {
			$validate->addColumn('photo', '申请头像')->required()->lengthMax(128);
			$data['photo'] = $photo;
		}
		$passwordOld = $this->param('old_password');
		$passwordNew = $this->param('new_password');
		if (!empty($passwordOld) && !empty($passwordNew) && $type == 3) {
			if (!preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,12}$/', $passwordNew)) {
				$this->output(Status::CODE_W_FORMAT_PASS, Status::$msg[Status::CODE_W_FORMAT_PASS]);
			}
			if (!PasswordTool::getInstance()->checkPassword($passwordOld, $user['password_hash'])) {
				$this->output(Status::CODE_W_FORMAT_PASS, '旧密码输入错误');
			}
			$data['password_hash'] = PasswordTool::getInstance()->generatePassword($passwordNew);
		}
		$mobile = $this->param('mobile');
		if (!empty($mobile) && $type == 4) {
			if (!preg_match("/^1[3456789]\d{9}$/", $mobile)) {
				$this->output(Status::CODE_W_PHONE, Status::$msg[Status::CODE_W_PHONE]);
			}
			$user = AdminUser::getInstance()->findOne(['mobile' => $mobile]);
			if (!empty($user)) $this->output(Status::CODE_PHONE_EXIST, Status::$msg[Status::CODE_PHONE_EXIST]);
			$data['mobile'] = $mobile;
		}
		if (empty($data)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 更新数据
		if (AdminUser::getInstance()->saveDataById($this->authId, $data)) {
			// $code = $this->param('code');
			// if (!empty($mobile) && !empty($code)) {
			//	 AdminUserPhonecode::getInstance()
			//		->setField('status',AdminUserPhonecode::STATUS_USED, ['code' => $code, 'mobile' => $mobile]);
			// }
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
		}
		$this->output(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
	}
	
	/**
	 * 消息中心
	 * @throws
	 */
	public function messageCenter()
	{
		$params = $this->param();
		// 类型校验
		$type = empty($params['type']) || intval($params['type']) < 1 ? 0 : intval($params['type']);
		if ($type > 4) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);;
		// 分页参数
		$page = $this->param('page', true, 1);
		$size = $this->param('size', true, 10);
		if ($type < 1) {
			// 输出数据
			$result = [
				'last_sys_message' => [],
				'sys_un_read_count' => 0,//系统消息未读数
				'fabolus_un_read_count' => 0,//点赞未读
				'comment_un_read_count' => 0,//回复未读
				'interest_un_read_count' => 0,//关注未读
			];
			$where = ['user_id' => $this->authId, 'type' => 1, 'status' => AdminMessage::STATUS_UNREAD];
			$result['sys_un_read_count'] = AdminMessage::getInstance()->findOne($where, 'count');
			$where = ['user_id' => $this->authId, 'type' => 2, 'status' => AdminMessage::STATUS_UNREAD];
			$result['fabolus_un_read_count'] = AdminMessage::getInstance()->findOne($where, 'count');
			$where = ['user_id' => $this->authId, 'type' => 3, 'status' => AdminMessage::STATUS_UNREAD];
			$result['comment_un_read_count'] = AdminMessage::getInstance()->findOne($where, 'count');
			$where = ['user_id' => $this->authId, 'type' => 4, 'status' => AdminMessage::STATUS_UNREAD];
			$result['interest_un_read_count'] = AdminMessage::getInstance()->findOne($where, 'count');
			// 首条通知
			$where = ['status' => [AdminMessage::STATUS_DEL, '<>'], 'type' => 1, 'user_id' => $this->authId];
			$result['last_sys_message'] = AdminMessage::getInstance()
				->findOne($where, 'id,content,created_at', 'created_at,desc');
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
		}
		// 系统消息
		if ($type == 1) {
			//我的通知
			$where = ['status' => [AdminMessage::STATUS_DEL, '<>'], 'type' => 1, 'user_id' => $this->authId];
			[$list, $count] = AdminMessage::getInstance()
				->findAll($where, null, 'created_at,desc', true, $page, $size);
			// 帖子映射
			$postIds = array_values(array_unique(array_filter(array_column($list, 'item_id'))));
			$postMapper = empty($postIds) ? [] : AdminUserPost::getInstance()
				->findAll(['id' => [$postIds, 'in']], null, null,
					false, 0, 0, 'id,*,true');
			//系统消息未读
			foreach ($list as $k => $v) {
				$id = intval($v['item_id']);
				$post = empty($postMapper[$id]) ? null : $postMapper[$id];
				$post = empty($post) ? [] : ['id' => $id, 'title' => $post['title'], 'created_at' => $post['created_at']];
				$list[$k] = [
					'message_id' => $id,
					'post_info' => $post,
					'title' => $v['title'],
					'status' => $v['status'],
					'content' => $v['content'],
					'created_at' => $v['created_at'],
				];
			}
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => $list, 'count' => $count]);
		}
		if ($type == 2) {
			$where = ['user_id' => $this->authId, 'type' => 2, 'item_type' => [[1, 2, 4], 'in'], 'status' => [AdminMessage::STATUS_DEL, '<>']];
			[$list, $count] = AdminMessage::getInstance()
				->findAll($where, null, 'created_at,desc', true, $page, $size);
			// 映射数据
			$userIds = $postIds = $postCommentIds = $informationIds = $informationCommentIds = [];
			array_walk($list, function ($v) use (&$userIds, &$postIds, &$postCommentIds, &$informationCommentIds) {
				$id = intval($v['did_user_id']);
				if ($id > 0 && !in_array($id, $userIds)) $userIds[] = $id;
				$id = intval($v['item_id']);
				if ($id > 0) {
					$itemType = intval($v['item_type']);
					if ($itemType == 1 && !in_array($id, $postIds)) $postIds[] = $id;
					if ($itemType == 2 && !in_array($id, $postCommentIds)) $postCommentIds[] = $id;
					if ($itemType == 4 && !in_array($id, $informationCommentIds)) $informationCommentIds[] = $id;
				}
			});
			// 用户映射
			$fields = 'id,nickname,photo,level,is_offical';
			$userMapper = empty($userIds) ? [] : AdminUser::getInstance()
				->findAll(['id' => [$userIds, 'in']], $fields, null, false, 0, 0, 'id,*,true');
			// 帖子回复映射
			$fields = 'id,content';
			$postCommentMapper = empty($postCommentIds) ? [] : AdminPostComment::getInstance()
				->findAll(['id' => [$postCommentIds, 'in']], $fields, null,
					false, 0, 0, 'id,*,true');
			if (!empty($postCommentMapper)) array_walk($postCommentMapper, function ($v, $k) use (&$postIds,&$postCommentMapper) {
				$id = intval($v['post_id']);
				$postCommentMapper[$k]['content'] = empty($v['content']) ? '' : base64_decode($v['content']);
				if ($id > 0 && !in_array($id, $postIds)) $postIds[] = $id;
			});
			// 帖子映射
			$fields = 'id,title,content';
			$postMapper = empty($postIds) ? [] : AdminUserPost::getInstance()
				->findAll(['id' => [$postIds, 'in']], $fields, null, false, 0, 0, 'id,*,true');
			$postMapper = empty($postMapper) ? [] : array_map(function ($v) {
				$v['content'] = empty($v['content']) ? '' : base64_decode($v['content']);
				return $v;
			}, $postMapper);
			// 资讯回复映射
			$fields = 'id,content';
			$informationCommentMapper = empty($informationCommentIds) ? [] : AdminInformationComment::getInstance()
				->findAll(['id' => [$informationCommentIds, 'in']], $fields, null,
					false, 0, 0, 'id,*,true');
			if (!empty($informationCommentMapper)) array_walk($informationCommentMapper, function ($v, $k) use (&$informationIds,&$informationCommentMapper) {
				$id = intval($v['information_id']);
				$informationCommentMapper[$k]['content'] = empty($v['content']) ? '' : base64_decode($v['content']);
				if ($id > 0 && !in_array($id, $informationIds)) $informationIds[] = $id;
			});
			// 资讯映射
			$fields = 'id,title,content';
			$informationMapper = empty($informationIds) ? [] : AdminInformation::getInstance()
				->findAll(['id' => [$informationIds, 'in']], $fields, null,
					false, 0, 0, 'id,*,true');
			// 填充数据
			foreach ($list as $k => $v) {
				$id = intval($v['id']);
				$itemId = intval($v['item_id']);
				$itemType = intval($v['item_type']);
				// 用户数据
				$userId = intval($v['did_user_id']);
				$user = empty($userMapper[$userId]) ? [] : $userMapper[$userId];
				// 帖子数据
				$post = $itemType != 1 || empty($postMapper[$itemId]) ? [] : $postMapper[$itemId];
				// 帖子回复数据
				$postComment = $itemType != 2 || empty($postCommentMapper[$itemId]) ? [] : $postCommentMapper[$itemId];
				// 资讯回复数据
				$informationComment = $itemType != 4 || empty($informationCommentMapper[$itemId]) ? [] : $informationCommentMapper[$itemId];
				if (!empty($informationComment)) $informationComment['content'] = mb_substr($informationComment['content'], 0, 20);
				// 资讯数据
				$informationId = empty($informationComment) ? 0 : intval($informationComment['post_id']);
				$information = $informationId < 1 || empty($informationMapper[$informationId]) ? [] : $informationMapper[$informationId];
				if (!empty($information)) $information['content'] = mb_substr($information['content'], 0, 20);
				$list[$k] = [
					'message_id' => $id,
					'status' => $v['status'],
					'item_type' => $itemType,
					'user_info' => $user, //用户信息
					'post_info' => $post, //帖子信息
					'created_at' => $v['created_at'],
					'information_info' => $information, //资讯信息
					'post_comment_info' => $postComment, //赞帖子回复
					'information_comment_info' => $informationComment, //赞资讯回复
				];
			}
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => $list, 'count' => $count]);
		}
		// 评论与回复
		if ($type == 3) {
			$where = ['user_id' => $this->authId, 'type' => 3, 'status' => [AdminMessage::STATUS_DEL, '<>']];
			[$items, $count] = AdminMessage::getInstance()
				->findAll($where, null, 'created_at,desc', true, $page, $size);
			// 映射数据
			$userIds = $postIds = $postCommentIds = $informationCommentIds = $informationIds = [];
			if (!empty($items)) array_walk($items, function ($v) use (&$userIds, &$postIds, &$postCommentIds, &$informationCommentIds) {
				$id = intval($v['item_id']);
				if ($id > 0) {
					$itemType = intval($v['item_type']);
					if ($itemType == 1 && !in_array($id, $postIds)) $postIds[] = $id;
					if ($itemType == 2 && !in_array($id, $postCommentIds)) $postCommentIds[] = $id;
					if ($itemType == 4 && !in_array($id, $informationCommentIds)) $informationCommentIds[] = $id;
				}
			});
			// 帖子回复映射
			$postCommentMapper = empty($postCommentIds) ? [] : AdminPostComment::getInstance()
				->findAll(['id' => [$postCommentIds, 'in']], null, null,
					false, 0, 0, 'id,*,true');
			$postCommentIds = [];
			if (!empty($postCommentMapper)) array_walk($postCommentMapper,
				function ($v, $k) use (&$postCommentIds, &$postIds, &$userIds, &$postCommentMapper) {
					$id = intval($v['post_id']);
					if ($id > 0 && !in_array($id, $postIds)) $postIds[] = $id;
					$id = intval($v['user_info']);
					if ($id > 0 && !in_array($id, $userIds)) $userIds[] = $id;
					$id = intval($v['parent_id']);
					if ($id > 0 && !in_array($id, $postCommentIds)) $postCommentIds[] = $id;
					$postCommentMapper[$k]['content'] = base64_decode($v['content']);
				});
			$tmp = empty($postCommentIds) ? [] : AdminPostComment::getInstance()
				->findAll(['id' => [$postCommentIds, 'in']], null, null,
					false, 0, 0, 'id,*,true');
			if (!empty($tmp)) array_walk($tmp,
				function ($v, $k) use (&$postCommentMapper) {
					$v['content'] = base64_decode($v['content']);
					$postCommentMapper[$k] = $v;
				});
			// 帖子映射
			$postMapper = empty($postIds) ? [] : AdminUserPost::getInstance()
				->findAll(['id' => [$postIds, 'in']], 'id,title,content,user_id', null,
					false, 0, 0, 'id,*,true');
			if (!empty($postMapper)) array_walk($postMapper,
				function ($v, $k) use (&$postCommentIds, &$postIds, &$userIds, &$postMapper) {
					$id = intval($v['user_id']);
					if ($id > 0 && !in_array($id, $userIds)) $userIds[] = $id;
					$id = intval($v['id']);
					if ($id > 0 && !in_array($id, $postIds)) $postIds[] = $id;
					$postMapper[$k]['content'] = mb_substr(base64_decode($v['content']), 0, 30);
				});
			// 资讯评论映射
			$informationCommentMapper = empty($informationCommentIds) ? [] : AdminInformationComment::getInstance()
				->findAll(['id' => [$informationCommentIds, 'in']], null, null,
					false, 0, 0, 'id,*,true');
			if (!empty($informationCommentMapper)) array_walk($informationCommentMapper,
				function ($v, $k) use (&$informationIds, &$userIds, &$informationCommentMapper) {
					$id = intval($v['user_id']);
					if ($id > 0 && !in_array($id, $userIds)) $userIds[] = $id;
					$id = intval($v['information_id']);
					if ($id > 0 && !in_array($id, $informationIds)) $informationIds[] = $id;
					$informationCommentMapper[$k]['content'] = base64_decode($v['content']);
				});
			// 资讯映射
			$informationMapper = empty($informationIds) ? [] : AdminInformation::getInstance()
				->findAll(['id' => [$informationIds, 'in']], null, null,
					false, 0, 0, 'id,*,true');
			if (!empty($informationMapper)) array_walk($informationMapper,
				function ($v) use (&$userIds) {
					$id = intval($v['user_id']);
					if ($id > 0 && !in_array($id, $userIds)) $userIds[] = $id;
				});
			// 用户映射
			$userMapper = empty($userIds) ? [] : AdminUser::getInstance()
				->findAll(['id' => [$userIds, 'in']], 'id,mobile,photo,nickname,level,is_offical', null,
					false, 0, 0, 'id,*,true');
			$list = [];
			foreach ($items as $v) {
				$messageId = intval($v['id']);
				$id = intval($v['item_id']);
				$itemType = intval($v['item_type']);
				if ($itemType == 1) { // 帖子
					$post = empty($postMapper[$id]) ? null : $postMapper[$id];
					if (empty($post)) continue;
					$userId = intval($post['user_id']);
					$user = empty($userMapper[$userId]) ? [] : $userMapper[$userId];
					$list[] = [
						'message_id' => $messageId,
						'created_at' => $v['created_at'],
						'user_info' => $user,
						'post_info' => $post,
						'status' => $v['status'],
						'item_type' => $itemType,
						'post_comment_info' => [],
					];
				} elseif ($itemType == 2) { // 帖子评论
					$comment = empty($postCommentMapper[$id]) ? null : $postCommentMapper[$id];
					if (empty($comment)) continue;
					$userId = intval($comment['user_id']);
					$user = empty($userMapper[$userId]) ? [] : $userMapper[$userId];
					$id = intval($v['post_id']);
					$post = empty($postMapper[$id]) ? [] : $postMapper[$id];
					$id = intval($v['parent_id']);
					$parent = empty($postCommentMapper[$id]) ? [] : $postCommentMapper[$id];
					$list[] = [
						'message_id' => $messageId,
						'created_at' => $v['created_at'],
						'user_info' => $user,
						'post_info' => $post,
						'status' => $v['status'],
						'item_type' => $itemType,
						'post_comment_info' => $comment,
						'parent_comment_info' => $parent,
					];
				} elseif ($itemType == 4) { // 资讯回复
					$informationComment = empty($informationCommentMapper[$id]) ? null : $informationCommentMapper[$id];
					if (empty($informationComment)) continue;
					$userId = intval($informationComment['user_id']);
					$user = empty($userMapper[$userId]) ? [] : $userMapper[$userId];
					$id = intval($v['information_id']);
					$information = empty($informationMapper[$id]) ? [] : $informationMapper[$id];
					$list[] = [
						'message_id' => $messageId,
						'created_at' => $v['created_at'],
						'user_info' => $user,
						'status' => $v['status'],
						'item_type' => $itemType,
						'information_info' => $information,
						'information_comment_info' => $informationComment,
					];
				}
			}
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => $list, 'count' => $count]);
		}
		// 用户关注我
		if ($type == 4) {
			$where = ['user_id' => $this->authId, 'type' => 4, 'status' => [AdminMessage::STATUS_DEL, '<>']];
			[$list, $count] = AdminMessage::getInstance()
				->findAll($where, null, 'created_at,desc', true, $page, $size);
			// 用户映射
			$userIds = $userMapper = [];
			array_walk($list, function ($v) use (&$userIds) {
				$id = intval($v['did_user_id']);
				if ($id > 0 && !in_array($id, $userIds)) $userIds[] = $id;
			});
			$userMapper = empty($userIds) ? [] : AdminUser::getInstance()
				->findAll(['id' => [$userIds, 'in']], 'id,nickname,photo', null, false, 0, 0, 'id,*,true');
			foreach ($list as $k => $v) {
				$id = intval($v['did_user_id']);
				$user = empty($userMapper[$id]) ? [] : $userMapper[$id];
				$list[$k] = [
					'user_info' => $user,
					'message_id' => $v['id'],
					'status' => $v['status'],
					'created_at' => $v['created_at'],
				];
			}
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => $list, 'count' => $count]);
		}
	}
	
	/**
	 * 读消息
	 * @throws
	 */
	public function readMessage()
	{
		$params = $this->param();
		// 类型
		$type = empty($params['type']) ? 0 : intval($params['type']);
		if ($type != 1 && $type != 2) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 消息ID
		$messageId = empty($params['message_id']) ? 0 : intval($params['message_id']);
		if ($type == 1) {
			// 消息数据
			$message = AdminMessage::getInstance()->findOne($messageId);
			if (empty($message)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
			AdminMessage::getInstance()->setField('status', AdminMessage::STATUS_READ, $messageId);
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
		};
		AdminMessage::getInstance()->setField('status', AdminMessage::STATUS_READ, ['user_id' => $this->authId]);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
	}
	
	/**
	 * 用户设置
	 * @throws
	 */
	public function userSetting()
	{
		$params = $this->param();
		$method = $this->request()->getMethod();;
		Log::getInstance()->info('params-' . json_encode($params));
		// 类型 1notice 2push 3private
		$type = empty($params['type']) ? 0 : intval($params['type']);
		if ($type != 1 && $type != 2 && $type != 3) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 配置数据
		$setting = AdminUserSetting::getInstance()->findOne(['user_id' => $this->authId]);
		if ($method == 'GET') {
			if (empty($setting)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
			if ($type == 1) {
				$data = json_decode($setting['notice'], true);
			} elseif ($type == 2) {
				$data = json_decode($setting['push'], true);
			} else {
				$data = json_decode($setting['private'], true);
			}
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);
		}
		if ($type == 1) {
			$column = 'notice';
			$value = empty($params['notice']) ? '' : $params['notice']; // start goal over only_notice_my_interest
			$tmp = empty($value) ? [] : json_decode($value, true);
			if (!isset($tmp['start']) || !isset($tmp['goal'])
				|| !isset($tmp['only_notice_my_interest'])
				|| !isset($tmp['over'])) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		} elseif ($type == 2) {
			$column = 'push';
			$value = empty($params['push']) ? '' : $params['push']; // start goal over
			$tmp = empty($value) ? [] : json_decode($value, true);
			if (!isset($tmp['start']) || !isset($tmp['goal'])
				|| !isset($tmp['open_push']) || !isset($tmp['information'])
				|| !isset($tmp['over'])) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		} else {
			$column = 'private';
			// see_my_post(1所有 2我关注的 3我的粉丝 4仅自己)
			// see_my_post_comment(1所有 2我关注的 3我的粉丝 4仅自己)
			// see_my_information_comment(1所有 2我关注的 3我的粉丝 4仅自己)
			$value = empty($params['private']) ? '' : $params['private'];
			$tmp = empty($value) ? [] : json_decode($value, true);
			if (!isset($tmp['see_my_post']) || !isset($tmp['see_my_post_comment']) ||
				!isset($tmp['see_my_information_comment'])) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		}
		if (empty($setting)) {
			AdminUserSetting::getInstance()->insert(['user_id' => $this->authId, $column => $value]);
		} else {
			AdminUserSetting::getInstance()->update([$column => $value], ['user_id' => $this->authId]);
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
	}
	
	/**
	 * 修改密码
	 * @throws
	 */
	public function changePassword()
	{
		$params = $this->param();
		// 密码校验
		$password = empty($params['new_pass']) ? null : trim($params['new_pass']);
		if (empty($password)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 格式校验
		$isOk = preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,12}$/', $password);
		if (!$isOk) $this->output(Status::CODE_W_FORMAT_PASS, Status::$msg[Status::CODE_W_FORMAT_PASS]);
		// 验证码状态更新
		// $mobile = empty($params['mobile']) ? null : trim($params['mobile']);
		// $code = empty($params['phone_code']) ? null : trim($params['phone_code']);
		// $phoneCode = empty($mobile) ? null : AdminUserPhonecode::getInstance()->getLastCodeByMobile($mobile);
		// if (empty($phoneCode) || empty($code) || $phoneCode['status'] != 0 || $phoneCode['code'] != $code) {
		// 	$this->output(Status::CODE_W_PHONE_CODE, Status::$msg[Status::CODE_W_PHONE_CODE]);
		// }
		// 更新密码
		$password = PasswordTool::getInstance()->generatePassword($password);
		$isOk = AdminUser::getInstance()->setField('password_hash', $password, $this->authId);
		if ($isOk) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
		$this->output(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
	}
	
	/**
	 * 用户被点赞列表 包括帖子与评论
	 * @throws
	 */
	public function myFabolusInfo()
	{
		// 帖子数据
		$posts = AdminUserOperate::getInstance()->func(function ($builder) {
			$builder->raw('select a.created_at,a.item_type,a.user_id,b.id,b.title ' .
				'from admin_user_operates as a left join admin_user_posts as b on a.author_id=b.user_id ' .
				'where a.item_id=b.id and a.type=1 and a.item_type=1 and a.author_id=?', [$this->authId]);
			return true;
		});
		if (!empty($posts)) {
			// 用户映射
			$userIds = [];
			array_walk($posts, function ($v) use (&$userIds) {
				$id = intval($v['user_id']);
				if ($id > 0 && !in_array($id, $userIds)) $userIds[] = $id;
			});
			$userMapper = AdminUser::getInstance()
				->findAll(['id' => [$userIds, 'in']], 'id,nickname,photo', null,
					false, 0, 0, 'id,*,true');
			foreach ($posts as $k => $v) {
				$id = intval($v['user_id']);
				$posts[$k]['user_info'] = empty($userMapper[$id]) ? [] : $userMapper[$id];
			}
		} else {
			$posts = [];
		}
		//帖子评论
		$postComments = AdminUserOperate::getInstance()->func(function ($builder) {
			$builder->raw('select a.user_id,a.created_at,a.item_type,b.* ' .
				'from admin_user_operates as a left join(select c.id,c.content,d.title ' .
				'from admin_user_post_comments as c left join admin_user_posts as d on c.post_id=d.id) as b ' .
				'on a.item_id=b.id where a.type=1 and a.item_type=2 and a.author_id=?', [$this->authId]);
			return true;
		});
		if (!empty($postComments)) {
			// 用户映射
			$userIds = [];
			array_walk($postComments, function ($v, $k) use (&$userIds) {
				$id = intval($v['user_id']);
				if ($id > 0 && !in_array($id, $userIds)) $userIds[] = $id;
			});
			$userMapper = AdminUser::getInstance()
				->findAll(['id' => [$userIds, 'in']], 'id,nickname,photo', null,
					false, 0, 0, 'id,*,true');
			foreach ($postComments as $k => $v) {
				$id = intval($v['user_id']);
				$postComments[$k]['content'] = base64_decode($v['content']);
				$postComments[$k]['user_info'] = empty($userMapper[$id]) ? [] : $userMapper[$id];
			}
		} else {
			$postComments = [];
		}
		//资讯评论
		$informationComments = AdminUserOperate::getInstance()->func(function ($builder) {
			$builder->raw('select a.user_id,a.created_at,a.item_type,b.* ' .
				'from admin_user_operates as a left join(select c.id,c.content,d.title ' .
				'from admin_information_comments as c left join admin_information as d on c.information_id=d.id) as b ' .
				'on a.item_id=b.id where a.type=? and a.item_type=? and a.author_id=?', [1, 4, $this->authId]);
			return true;
		});
		if (!empty($informationComments)) {
			// 用户映射
			$userIds = [];
			array_walk($informationComments, function ($v) use (&$userIds) {
				$id = intval($v['user_id']);
				if ($id > 0 && !in_array($id, $userIds)) $userIds[] = $id;
			});
			$userMapper = AdminUser::getInstance()
				->findAll(['id' => [$userIds, 'in']], 'id,nickname,photo', null,
					false, 0, 0, 'id,*,true');
			foreach ($informationComments as $k => $v) {
				$id = intval($v['user_id']);
				$informationComments[$k]['content'] = base64_decode($v['content']);
				$informationComments[$k]['user_info'] = empty($userMapper[$id]) ? [] : $userMapper[$id];
			}
		} else {
			$informationComments = [];
		}
		// 清单排序
		$result = array_merge($posts, $postComments, $informationComments);
		usort($result, function ($av, $bv) {
			$as = $av['created_at'];
			$bs = $bv['created_at'];
			return $as > $bs ? -1 : ($as == $bs ? 0 : 1);
		});
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 违规中心
	 * @throws
	 */
	public function foulCenter()
	{
		// 分页参数
		$page = $this->param('page', true, 1);
		$size = $this->param('size', true, 10);
		// 分页数据
		$fields = 'id,reason,info,created_at,item_type,item_id,item_punish_type,user_punish_type';
		[$list, $count] = AdminUserFoulCenter::getInstance()
			->findAll(['user_id' => $this->authId], $fields, 'created_at,desc', true, $page, $size);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => $list, 'count' => $count]);
	}
	
	/**
	 * 违规记录详情
	 * @throws
	 */
	public function foulItemInfo()
	{
		$params = $this->param();
		// 违规数据
		$id = empty($params['operate_id']) || intval($params['operate_id']) < 1 ? 0 : intval($params['operate_id']);
		$operate = $id < 1 ? null : AdminUserFoulCenter::getInstance()->findOne($id);
		if (empty($operate)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 输出数据
		$result = [];
		$type = intval($operate['item_type']);
		$itemId = intval($operate['item_id']);
		if ($type == 1) {
			$post = $itemId < 1 ? null : AdminUserPost::getInstance()->findOne($itemId, 'id,content');
			$title = empty($post['title']) ? '' : $post['title'];
			$content = empty($post['content']) ? '' : base64_decode($post['content']);
			$result = ['item_id' => $id, 'item_type' => $type, 'content' => $content, 'title' => $title];
		} elseif ($type == 2) {
			$postComment = AdminPostComment::getInstance()->findOne($itemId, 'id,content');
			$content = empty($postComment['content']) ? '' : base64_decode($postComment['content']);
			$result = ['item_id' => $id, 'item_type' => $type, 'content' => $content];
		} elseif ($type == 4) {
			$informationComment = AdminInformationComment::getInstance()->findOne($itemId, 'id,content');
			$content = empty($informationComment['content']) ? '' : base64_decode($informationComment['content']);
			$result = ['item_id' => $id, 'item_type' => $type, 'content' => $content];
		} elseif ($type == 5) {
			$message = ChatHistory::getInstance()->findOne($id);
			$content = empty($message['content']) ? '' : $message['content'];
			$result = ['item_id' => $id, 'item_type' => $type, 'content' => $content];
		}
		$result['info'] = $operate['info'];
		$result['reason'] = $operate['reason'];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 草稿箱列表
	 * @throws
	 */
	public function drafts()
	{
		$params = $this->param();;
		// 分页参数
		$page = $this->param('page', true, 1);
		$size = empty($params['size']) ? 20 : $params['size'];
		// 分页数据
		$where = ['status' => AdminUserPost::NEW_STATUS_SAVE, 'user_id' => $this->authId];
		[$list, $count] = AdminUserPost::getInstance()
			->findAll($where, null, 'created_at,desc', true, $page, $size);
		$list = empty($list) ? [] : FrontService::handPosts($list, $this->authId);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => $list, 'count' => $count]);
	}
	
	/**
	 * 删除
	 * @throws
	 */
	public function delItem()
	{
		$params = $this->param();;
		// 类型
		$type = empty($params['type']) ? 0 : intval($params['type']);
		// 选项ID
		$id = empty($params['item_id']) ? 0 : intval($params['item_id']);
		if ($type != 1 && $type != 2 && $type != 3) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		if ($type == 1) { // 删除帖子
			$post = AdminUserPost::getInstance()->findOne(['id' => $id, 'user_id' => $this->authId]);
			if (empty($post)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
			AdminUserPost::getInstance()->setField('status', AdminUserPost::NEW_STATUS_DELETED, $id);
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
		}
		if ($type == 2) { // 帖子评论
			$postComment = AdminPostComment::getInstance()->findOne(['id' => $id, 'user_id' => $this->authId]);
			if (empty($postComment)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
			AdminPostComment::getInstance()->setField('status', AdminPostComment::STATUS_DEL, $id);
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
		}
		// 资讯评论
		$informationComment = AdminInformationComment::getInstance()->findOne(['id' => $id, 'user_id' => $this->authId]);
		if (empty($informationComment)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		AdminInformationComment::getInstance()->setField('status', AdminInformationComment::STATUS_DELETE, $id);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
	}
	
	/**
	 * 任务列表
	 * @throws
	 */
	public function getAvailableTask()
	{
		;
		// 任务清单
		$tasks = AdminUserSerialPoint::USER_TASK;
		foreach ($tasks as $k => $v) {
			if ($v['status'] != AdminUserSerialPoint::TASK_STATUS_NORMAL) continue;
			$id = intval($v['id']);
			$tasks[$k]['done_times'] = $id < 1 ? 0 : AdminUserSerialPoint::getInstance()
				->findOne(['task_id' => $id, 'created_at' => date('Y-m-d'), 'user_id' => $this->authId], 'count');
		}
		// 用户数据
		$user = AdminUser::getInstance()->findOne($this->authId, 'id,photo,level,is_offical,level,point');
		// 输出数据
		$result = ['user_info' => $user, 'task_list' => $tasks];
		$result['d_value'] = AppFunc::getPointsToNextLevel($this->authId);
		$result ['t_value'] = AppFunc::getPointOfLevel($user['level']);
		if (empty($user['third_wx_unionid'])) {
			$result ['special'] = [
				'id' => 4, 'name' => '分享好友', 'status' => 1,
				'times_per_day' => 1, 'points_per_time' => 200,
				'icon' => 'http://test.ymtyadmin.com/image/system/2020/10/7775b4a856bcef57.jpg',
			];
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 做任务加积分，这里只能是每日签到与分享
	 * @throws
	 */
	public function userDoTask()
	{
		$params = $this->param();;
		// 任务ID
		$taskId = empty($params['task_id']) || intval($params['task_id']) < 1 ? 0 : intval($params['task_id']);
		if (!in_array($taskId, [1, 4])) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 任务数据
		$task = AdminUserSerialPoint::USER_TASK;
		$task = empty($task[$taskId]) ? [] : $task[$taskId];
		if (empty($task)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		//
		$where = ['user_id' => $this->authId, 'task_id' => $taskId, 'created_at' => date('Y-m-d')];
		$times = AdminUserSerialPoint::getInstance()->findOne($where, 'count');
		if ($task['times_per_day'] <= $times) $this->output(Status::CODE_TASK_LIMIT, Status::$msg[Status::CODE_TASK_LIMIT]);
		try {
			$times = intval($task['points_per_time']);
			DbManager::getInstance()->startTransaction();
			AdminUserSerialPoint::getInstance()->insert([
				'type' => 1,
				'point' => $times,
				'task_id' => $taskId,
				'user_id' => $this->authId,
				'task_name' => $task['name'],
				'created_at' => date('Y-m-d'),
			]);
			$user = AdminUser::getInstance()->findOne($this->authId, 'point');
			AdminUser::getInstance()->saveDataById($this->authId, [
				'point' => QueryBuilder::inc($times),
				'level' => AppFunc::getUserLvByPoint($user['point']),
			]);
		} catch (\Throwable  $e) {
			DbManager::getInstance()->rollback();
		} finally {
			DbManager::getInstance()->commit();
		}
		$user = AdminUser::getInstance()->findOne($this->authId);
		// 输出数据
		$result = [
			'level' => $user['level'], 'point' => $user['point'],
			'd_value' => AppFunc::getPointsToNextLevel($this->authId),
		];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 积分明细
	 * @throws
	 */
	public function getPointList()
	{
		// 分页参数
		$page = $this->param('page', true, 1);
		$size = $this->param('size', true, 10);
		// 分页数据
		$fields = 'id,task_name,type,point,created_at';
		[$list, $count] = AdminUserSerialPoint::getInstance()
			->findAll(['user_id' => $this->authId], $fields, 'created_at,desc', true, $page, $size);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => $list, 'total' => $count]);
	}
	
	/**
	 * 用户反馈
	 * @throws
	 */
	public function userFeedBack()
	{
		// 参数校验
		$validator = new Validate();
		$validator->addColumn('content')->required();
		if (!$validator->validate($this->param())) {
			$this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		};
		$params = $this->param();
		$imgs = trim($params['img']);
		$content = trim($params['content']);
		if (empty($content)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 插入数据
		$data = [
			'user_id' => $this->authId,
			'content' => addslashes(htmlspecialchars($content)),
		];
		if (!empty($imgs)) $data['img'] = $imgs;
		$tmp = AdminUserFeedBack::getInstance()->insert($data);
		if ($tmp) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
		$this->output(Status::CODE_ERR, '提交失败，请联系客服');
	}
}