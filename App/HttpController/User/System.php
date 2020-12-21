<?php

namespace App\HttpController\User;

use App\Task\MessageTask;
use App\Model\AdminCategory;
use App\Model\AdminSensitive;
use App\Model\AdminSysSettings;
use App\Utility\Message\Status;
use App\Model\AdminAdvertisement;
use App\Base\FrontUserController;
use App\Model\AdminMessage as MessageModel;
use EasySwoole\EasySwoole\Task\TaskManager;

class System extends FrontUserController
{
	protected $isCheckSign = false;
	protected $needCheckToken = false;
	const SYS_KEY_HOT_RELOAD = 'hot_reload';
	const SYS_KEY_SHIELD_LIVE = 'shield_live';
	
	/**
	 * 热重载
	 * @throws
	 */
	function hotreload()
	{
		// 参数校验
		$params = $this->params;
		$version = empty($params['version']) ? null : $this->params['version'];
		$phoneType = empty($params['phone_type']) ? null : $this->params['phone_type'];
		if (empty($version) || empty($phoneType)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 获取配置
		$config = AdminSysSettings::getInstance()->findOne(['sys_key' => self::SYS_KEY_HOT_RELOAD]);
		if (empty($config)) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['is_new' => 1]);
		$config = json_decode($config['sys_value'], true);
		$result['wgt_url'] = $config['wgt_url'];
		$result['accoucement'] = $config['accoucement'];
		$result['is_new'] = version_compare($version, $config['version']);
		//
		$config = AdminSysSettings::getInstance()->findOne(['sys_key' => self::SYS_KEY_SHIELD_LIVE]);
		$config = json_decode($config['sys_value'], true);
		$result['shield_live'] = empty($config[$phoneType]) ? 0 : $config[$phoneType];
		if ($phoneType == 'test') $result['shield_live'] = 1;
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 广告图片
	 * @throws
	 */
	public function adImgs()
	{
		$result = [
			'countDown' => 3,
			'is_open' => false,
			'is_force' => false,
			'url' => 'https://www.baidu.com',
			'img' => 'http://live-broadcast-system.oss-cn-hongkong.aliyuncs.com/e44e1023d520f507.jpg',
		];
		$tmp = AdminSysSettings::getInstance()->findOne(['sys_key' => AdminSysSettings::SETTING_OPEN_ADVER]);
		if (!empty($tmp['sys_value'])) $result = json_decode($tmp['sys_value'], true);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 广告列表
	 * @throws
	 */
	public function advertisement()
	{
		$params = $this->params;
		// 参数校验
		$categoryId = empty($params['cat_id']) || intval($params['cat_id']) < 1 ? 0 : intval($params['cat_id']);
		if ($categoryId < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 获取清单
		$where = ['status' => AdminAdvertisement::STATUS_NORMAL, 'cat_id' => $categoryId];
		$result = AdminAdvertisement::getInstance()->findAll($where, '*');
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 敏感词列表
	 * @throws
	 */
	public function sensitiveWord()
	{
		// 获取清单
		$result = AdminSensitive::getInstance()->findAll(['id' => [0, '>']], 'word');
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * todo ... [获取系统公告]
	 * @throws
	 */
	public function index(): bool
	{
		$params = $this->params;
		$page = empty($params['page']) ? 1 : $params['page'];
		$size = empty($params['offset']) ? 10 : $params['offset'];
		$where = ['cate_id' => AdminCategory::CATEGORY_ANNOUNCEMENT, 'status' => 1];
		[$list, $count] = MessageModel::getInstance()->findAll($where,
			'id,title,cate_name,status,created_at', 'created_at,asc', true, $page, $size);
		$this->output(Status::CODE_OK, 'ok', ['data' => $list, 'count' => $count]);
	}
	
	/**
	 * todo ... [公告详情]
	 * @throws
	 */
	public function detail()
	{
		// 获取详情
		$id = empty($this->params['id']) || intval($this->params['id']) < 1 ? 0 : intval($this->params['id']);
		$info = MessageModel::getInstance()->findOne($id);
		if (empty($info)) $this->output(Status::CODE_ERR, '对应公告不存在');
		//写异步task记录已读
		$auth = $this->auth;
		if (!empty($auth)) TaskManager::getInstance()->async(function ($taskId, $workerIndex) use ($info, $auth) {
			$messageTask = new MessageTask([
				'user_id' => $auth['id'],
				'mobile' => $auth['mobile'],
				'message_id' => $info['id'],
				'message_title' => $info['title'],
			]);
			$messageTask->execData();
		});
		$this->output(Status::CODE_OK, 'ok', $info);
	}
}