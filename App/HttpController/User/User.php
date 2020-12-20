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

/**
 * 前台用户控制器
 * Class User
 * @package App\HttpController\User
 */
class User extends FrontUserController
{
	public $needCheckToken = true;
	public $isCheckSign = false;
	
	/**
	 * 返回用户信息
	 */
	public function info()
	{
		$this->output(Status::CODE_OK, 'ok', AdminUser::getInstance()->findOne($this->auth['id']));
	}
	
	/**
	 * 用户更新相关操作
	 */
	public function operate()
	{
		$actionType = isset($this->params['action_type']) ? $this->params['action_type'] : 'chg_nickname';
		//only check data
		$validate = new Validate();
		switch ($actionType) {
			case 'chg_nickname':
				$validate->addColumn('value', '昵称')->required()->lengthMax(64)->lengthMin(4);
				break;
			case 'chg_photo':
				$validate->addColumn('value', '头像')->required()->lengthMax(128)->lengthMin(6);
				break;
		}
		//昵称去重，头像判断存不存在
		if (!$validate->validate($this->params)) {
			$this->output(Status::CODE_VERIFY_ERR, $validate->getError()->__toString());
		}
		
		if ($actionType == 'chg_nickname') {
			$isExists = AdminUser::create()->where('nickname', $this->params['value'])
				->where('id', $this->auth['id'], '<>')
				->count();
			
			if ($isExists) {
				$this->output(Status::CODE_ERR, '该昵称已存在，请重新设置');
			}
		}
		$this->params['action_type'] = $actionType;
		TaskManager::getInstance()->async(new UserTask([
			'user_id' => $this->auth['id'],
			'params' => $this->params,
		]));
		
		$this->output(Status::CODE_OK, '修改成功');
	}
	
	/**
	 * 微信解绑
	 */
	public function unBindWx()
	{
		if (!$user = AdminUser::getInstance()->where('id', $this->auth['id'])->get()) {
			$this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		} else {
			$user->wx_photo = '';
			$user->wx_name = '';
			$user->third_wx_unionid = '';
			$user->update();
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
		}
	}
	
	//关注用户 / 取消关注
	public function userFollowings()
	{
		$params = $this->params;
		$valitor = new Validate();
		$valitor->addColumn('follow_id')->required();
		$valitor->addColumn('action_type')->required()->inArray(['add', 'del']);
		if (!$valitor->validate($params)) {
			$this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		}
		
		$uid = $this->auth['id'];
		$followUid = $params['follow_id'];
		if ($uid == $followUid) {
			$this->output(Status::CODE_WRONG_USER, Status::$msg[Status::CODE_WRONG_USER], 3);
		}
		$user = AdminUser::getInstance()->field(['id', 'nickname', 'photo'])->get(['id' => $followUid]);
		if (!$user || !$uid) {
			$this->output(Status::CODE_WRONG_USER, Status::$msg[Status::CODE_WRONG_USER], 3);
		}
		
		if ($params['action_type'] == 'add') {
			if (AppFunc::isFollow($uid, $followUid)) {
				$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
			}
			$bool = AppFunc::addFollow($uid, $user->id);
			
			if ($message = AdminMessage::getInstance()->where('user_id', $followUid)->where('item_id', $followUid)->where('did_user_id', $this->auth['id'])->where('type', 4)->where('item_type', 5)->get()) {
				$message->status = AdminMessage::STATUS_UNREAD;
				$message->created_at = date('Y-m-d H:i:s');
				$message->update();
			} else {
				//发送消息
				$data = [
					'status' => AdminMessage::STATUS_UNREAD,
					'user_id' => $followUid,
					'type' => 4,
					'item_type' => 5,
					'item_id' => $followUid,
					'title' => '关注通知',
					'did_user_id' => $this->auth['id'],
				];
				AdminMessage::getInstance()->insert($data);
			}
		} else {
			$bool = AppFunc::delFollow($uid, $user->id);
			//取关删除该条消息
			if ($message = AdminMessage::getInstance()->where('user_id', $followUid)->where('item_id', $followUid)->where('did_user_id', $this->auth['id'])->where('type', 4)->where('item_type', 5)->get()) {
				$message->status = AdminMessage::STATUS_DEL;
				$message->update();
			}
		}
		if ($bool) {
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
		} else {
			$this->output(Status::CODE_USER_FOLLOW, Status::$msg[Status::CODE_USER_FOLLOW]);
		}
	}
	
	/**
	 * 用户点赞 收藏 举报    帖子 评论 资讯评论 用户
	 * @return bool
	 */
	public function informationOperate()
	{
		if (!$this->auth['id']) {
			$this->output(Status::CODE_LOGIN_ERR, Status::$msg[Status::CODE_LOGIN_ERR]);
		}
		
		if (Cache::get('user_operate_information_' . $this->auth['id'] . '-type-' . $this->params['type'])) {
			$this->output(Status::CODE_WRONG_LIMIT, Status::$msg[Status::CODE_WRONG_LIMIT]);
		}
		$validate = new Validate();
		//1. 点赞   2收藏， 3， 举报
		$validate->addColumn('type')->required()->inArray([1, 2, 3]);
		$validate->addColumn('item_type')->required()->inArray([1, 2, 3, 4, 5]); //1帖子 2帖子评论 3资讯 4资讯评论 5直播间发言
		$validate->addColumn('item_id')->required();
		$validate->addColumn('author_id')->required();
		$validate->addColumn('is_cancel')->required();
		if (!$validate->validate($this->params)) {
			$this->output(Status::CODE_ERR, $validate->getError()->__toString());
		}
		if (!empty($this->params['remark']) && AppFunc::have_special_char($this->params['remark'])) {
			$this->output(Status::CODE_UNVALID_CODE, Status::$msg[Status::CODE_UNVALID_CODE]);
		}
		$item_id = $this->params['item_id'];
		$type = $this->params['type'];
		$item_type = $this->params['item_type'];
		if ($operate = AdminUserOperate::getInstance()->where('item_id', $this->params['item_id'])->where('item_type', $this->params['item_type'])->where('user_id', $this->auth['id'])->where('type', $this->params['type'])->get()) {
			if ($this->params['is_cancel'] == $operate->is_cancel) {
				$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
			} else {
				$operate->is_cancel = $this->params['is_cancel'];
				$operate->update();
			}
		} else {
			$data = [
				'user_id' => $this->auth['id'],
				'item_type' => $this->params['item_type'],
				'type' => $this->params['type'],
				'content' => !empty($this->params['content']) ? addslashes(htmlspecialchars(trim($this->params['content']))) : '',
				'remark' => !empty($this->params['remark']) ? addslashes(htmlspecialchars(trim($this->params['remark']))) : '',
				'item_id' => $this->params['item_id'] ?: 0,
				'author_id' => $this->params['author_id'] ?: 0,
			];
			AdminUserOperate::getInstance()->insert($data);
		}
		$author_id = $this->params['author_id'];
		$uid = $this->auth['id'];
		$is_cancel = $this->params['is_cancel'];
		
		$task_data = [
			'item_type' => $item_type,
			'type' => $type,
			'item_id' => $item_id,
			'uid' => $uid,
			'is_cancel' => $is_cancel,
			'author_id' => $author_id,
		];
		TaskManager::getInstance()->async(new UserOperateTask(['payload' => $task_data]));
		//        TaskManager::getInstance()->async(function () use($item_type, $type, $item_id, $author_id, $uid, $is_cancel) {
		//
		//            if ($item_type == 1) {
		//                $model = AdminUserPost::getInstance();
		//                $status_report = AdminUserPost::NEW_STATUS_REPORTED;
		//            } else if ($item_type == 2) {
		//                $model = AdminPostComment::getInstance();
		//                $status_report = AdminPostComment::STATUS_REPORTED;
		//
		//            } else if ($item_type == 3) {
		//                $model = AdminInformation::getInstance();
		//                $status_report = AdminInformation::STATUS_REPORTED;
		//
		//            } else if ($item_type == 4) {
		//                $model = AdminInformationComment::getInstance();
		//                $status_report = AdminInformationComment::STATUS_REPORTED;
		//
		//            } else if ($item_type == 5) {
		//                $model = AdminUser::getInstance();
		//                $status_report = AdminUser::STATUS_REPORTED;
		//            } else {
		//                return false;
		//            }
		//
		//            switch ($type) {
		//                case 1:
		//                    if (!$is_cancel) {
		//                        $model->update(['fabolus_number' => QueryBuilder::inc(1)], ['id' => $item_id]);
		//                        if ($author_id != $uid) {
		//                            if ($message = AdminMessage::getInstance()->where('user_id',  $author_id)->where('type', 2)->where('item_type', $item_type)->where('item_id', $item_id)->where('did_user_id', $uid)->get()) {
		//                                $message->status = AdminMessage::STATUS_UNREAD;
		//                                $message->created_at = date('Y-m-d H:i:s');
		//                                $message->update();
		//                            } else {
		//                                //发送消息
		//                                $data = [
		//                                    'status' => AdminMessage::STATUS_UNREAD,
		//                                    'user_id' => $author_id,
		//                                    'type' => 2,
		//                                    'item_type' => $item_type,
		//                                    'item_id' => $item_id,
		//                                    'title' => '点赞通知',
		//                                    'did_user_id' => $uid
		//                                ];
		//                                AdminMessage::getInstance()->insert($data);
		//                            }
		//
		//                        }
		//                    } else {
		//                        $model->update(['fabolus_number' => QueryBuilder::dec(1)], ['id' => $item_id]);
		//                        if ($author_id != $uid && $message = AdminMessage::getInstance()->where('user_id',  $author_id)->where('type', 2)->where('item_type', $item_type)->where('item_id', $item_id)->where('did_user_id', $uid)->get()) {
		//                            $message->status = AdminMessage::STATUS_DEL;
		//                            $message->update();
		//                        }
		//                    }
		//
		//
		//
		//                    break;
		//                case 2:
		//                    if (!$is_cancel) {
		//                        $model->update(['collect_number' => QueryBuilder::inc(1)], ['id' => $item_id]);
		//
		//                    } else {
		//                        $model->update(['collect_number' => QueryBuilder::dec(1)], ['id' => $item_id]);
		//
		//                    }
		//
		//                    break;
		//                case 3:
		//                    $model->update(['status', $status_report], ['id' => $item_id]);
		//                    break;
		//
		//            }
		//        });
		
		Cache::set('user_operate_information_' . $this->auth['id'] . '-type-' . $this->params['type'], 1, 2);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
	}
	
	/**
	 * 回复我的列表 帖子和评论
	 * @throws
	 */
	public function myReplys()
	{
		$authId = empty($this->auth['id']) || intval($this->auth['id']) < 1 ? 0 : intval($this->auth['id']);
		// 分页参数
		$params = $this->params;
		$page = empty($params['page']) ? 1 : $params['page'];
		$size = empty($params['size']) ? 10 : $params['size'];
		// 分页数据
		$where = ['t_u_id' => $authId, 'status' => [AdminPostComment::STATUS_DEL, '<>']];
		[$tmp, $count] = AdminPostComment::getInstance()
			->findAll($where, '*', 'created_at,desc', true, $page, $size);
		$data = ['posts' => [], 'comments' => []];
		// 封装数据
		foreach ($tmp as $v) {
			$user = $v->uInfo();
			if (empty($v['parent_id'])) { //我的帖子的回复
				$post = $v->postInfo();
				$data['posts'][] = [
					'title' => empty($post['title']) ? '' : $post['title'],
					'p_created_at' => empty($post['created_at']) ? '' : $post['created_at'],
					'content' => empty($v['content']) ? '' : mb_substr(base64_decode($v['content']), 0, 30, 'utf-8'),
					'nickname' => empty($user['nickname']) ? '' : $user['nickname'],
					'photo' => empty($user['photo']) ? '' : $user['photo'],
					'created_at' => $v['created_at'],
				];
			} else { //我的评论的回复
				$data['comments'][] = [
					'title' => '',
					'p_created_at' => '',
					'content' => empty($v['content']) ? '' : mb_substr(base64_decode($v['content']), 0, 30, 'utf-8'),
					'nickname' => empty($user['nickname']) ? '' : $user['nickname'],
					'photo' => empty($user['photo']) ? '' : $user['photo'],
					'created_at' => $v['created_at'],
				];
			}
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['count' => $count, 'data' => $data]);
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
	
	/**
	 * 用户消息详情
	 * @return bool
	 */
	public function userMessageInfo()
	{
		$validator = new Validate();
		$validator->addColumn('mid')->required();
		if (!$validator->validate($this->params)) {
			$this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		}
		$mid = $this->params['mid'];
		
		$res = AdminMessage::getInstance()->get($mid);
		$returnData['title'] = $res['title'];
		$returnData['content'] = $res['content'];
		$returnData['created_at'] = $res['created_at'];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);
	}
	
	public function userInterestCompetition()
	{
		if (!isset($this->params['competition_id']) || !$this->params['competition_id']) {
			$this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		}
		if (!$this->auth['id']) {
			$this->output(Status::CODE_LOGIN_ERR, Status::$msg[Status::CODE_LOGIN_ERR]);
		}
		$uComs = AdminUserInterestCompetition::getInstance()->where('user_id', $this->auth['id'])->get();
		if ($uComs) {
			$uComs->competition_ids = $this->params['competition_id'];
			$bool = $uComs->update();
		} else {
			$data = [
				'competition_ids' => $this->params['competition_id'],
				'user_id' => $this->auth['id'],
			];
			$bool = AdminUserInterestCompetition::getInstance()->insert($data);
		}
		
		if (!$bool) {
			$this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		} else {
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
		}
	}
	
	/**
	 * 用户关注比赛
	 * @return bool
	 */
	public function userInterestMatch()
	{
		if (!$this->auth['id']) {
			$this->output(Status::CODE_VERIFY_ERR, '登陆令牌缺失或者已过期');
		}
		
		$validate = new Validate();
		$validate->addColumn('match_id')->required();
		$validate->addColumn('type')->required()->inArray(['add', 'del']);
		if ($validate->validate($this->params)) {
			$uid = $this->auth['id'];
			$match_id = $this->params['match_id'];
			if ($this->params['type'] == 'add') {
				$res = AppFunc::userDoInterestMatch($match_id, $uid);
			} elseif ($this->params['type'] == 'del') {
				$res = AppFunc::userDelInterestMatch($match_id, $uid);
			} else {
				$this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
			}
			if ($res) {
				$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
			} else {
				$this->output(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
			}
		} else {
			$this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		}
	}
	
	/**
	 * 用户用户注册完的首次密码设定
	 */
	public function setPassword()
	{
		$user = AdminUser::getInstance()->findOne($this->auth['id']);
		if (!$user || $user->status == 0) {
			$this->output(Status::CODE_W_STATUS, Status::$msg[Status::CODE_W_STATUS]);
		}
		$password = $this->params['password'];
		$res = preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,12}$/', $password);
		if (!$res) {
			$this->output(Status::CODE_W_FORMAT_PASS, Status::$msg[Status::CODE_W_FORMAT_PASS]);
		}
		
		$password_hash = PasswordTool::getInstance()->generatePassword($password);
		$user->password_hash = $password_hash;
		if (!$user->update()) {
			$this->output(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
		} else {
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
		}
	}
	
	/**
	 * 发表帖子评论
	 * @return bool
	 * @throws \EasySwoole\Mysqli\Exception\Exception
	 * @throws \EasySwoole\ORM\Exception\Exception
	 * @throws \Throwable
	 */
	public function doComment()
	{
		if ($this->auth['status'] == AdminUser::STATUS_FORBIDDEN) {
			$this->output(Status::CODE_STATUS_FORBIDDEN, Status::$msg[Status::CODE_STATUS_FORBIDDEN]);
		}
		if (Cache::get('userCom' . $this->auth['id'])) {
			$this->output(Status::CODE_WRONG_LIMIT, Status::$msg[Status::CODE_WRONG_LIMIT]);
		}
		$id = $this->request()->getRequestParam('post_id');
		$validate = new Validate();
		$validate->addColumn('content')->required();
		
		if (!$validate->validate($this->params)) {
			$this->output(Status::CODE_W_PARAM, $validate->getError()->__toString());
		}
		
		$info = AdminUserPost::getInstance()->findOne($id);
		if (!$info) {
			$this->output(Status::CODE_WRONG_RES, '对应帖子不存在');
		}
		
		if ($info['status'] != AdminUserPost::NEW_STATUS_NORMAL) {
			$this->output(Status::CODE_WRONG_RES, '该帖不可评论');
		}
		
		$parent_id = isset($this->params['parent_id']) ? $this->params['parent_id'] : 0;
		$top_comment_id = isset($this->params['top_comment_id']) ? $this->params['top_comment_id'] : 0; //一级回复的id
		if ($parent_id) {
			$parentComment = AdminPostComment::getInstance()->get(['id' => $parent_id]);
			if (!$parentComment || $parentComment['status'] != AdminPostComment::STATUS_NORMAL) {
				$this->output(Status::CODE_WRONG_RES, '原始评论参数不正确');
			}
		}
		
		$taskData = [
			'user_id' => $this->auth['id'],
			'post_id' => $this->params['post_id'],
			'content' => base64_encode(htmlspecialchars(addslashes($this->params['content']))),
			'parent_id' => $parent_id,
			't_u_id' => $parent_id ? $parentComment->user_id : $info->user_id,
			'top_comment_id' => $top_comment_id,
		];
		
		//插入一条评论
		$insertId = AdminPostComment::getInstance()->insert($taskData);
		$new_comment = AdminPostComment::getInstance()->where('id', $insertId)->get();
		$format_comment = FrontService::handComments([$new_comment], $this->auth['id'])[0];
		
		if ($top_comment_id) {
			AdminPostComment::create()->update([
				'respon_number' => QueryBuilder::inc(1),
			], [
				'id' => $parent_id,
			]);
		}
		
		if ($parent_id) {
			$author_id = AdminPostComment::getInstance()->where('id', $parent_id)->get()->user_id;
			$item_type = 2;
			$item_id = $insertId;
		} else {
			$author_id = AdminUserPost::getInstance()->where('id', $this->params['post_id'])->get()->user_id;
			$item_type = 1;
			$item_id = $insertId;
		}
		
		AdminUserPost::create()->update([
			'respon_number' => QueryBuilder::inc(1),
		], [
			'id' => $this->params['post_id'],
		]);
		
		if ($author_id != $this->auth['id']) {
			//发送消息
			$data = [
				'status' => AdminMessage::STATUS_UNREAD,
				'user_id' => $author_id,
				'type' => 3,
				'item_type' => $item_type,
				'item_id' => $item_id,
				'title' => '帖子回复通知',
				'did_user_id' => $this->auth['id'],
			];
			AdminMessage::getInstance()->insert($data);
		}
		
		//积分任务
		$data['task_id'] = 3;
		$data['user_id'] = $this->auth['id'];
		
		TaskManager::getInstance()->async(new SerialPointTask($data));
		Log::getInstance()->info('do-comment4');
		
		Cache::set('userCom' . $this->auth['id'], 1, 5);
		$info->last_respon_time = date('Y-m-d H:i:s');
		$info->update();
		
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $format_comment);
	}
	
	public function checkUserStatus()
	{
		$uid = $this->auth['id'];
		$bool = false;
		if ($user = AdminUser::getInstance()->where('id', $uid)->get()) {
			if (in_array($user->status, [AdminUser::STATUS_NORMAL, AdminUser::STATUS_REPORTED])) {
				$bool = true;
			} else {
				$bool = false;
			}
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $bool);
	}
}