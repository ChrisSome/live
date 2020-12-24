<?php
/**
 * Created by PhpStorm.
 * User: evalor
 * Date: 2018-11-28
 * Time: 20:23
 */

namespace App\Task;

use App\Common\AppFunc;
use App\lib\Tool;
use App\Model\AdminInformation;
use App\Model\AdminInformationComment;
use App\Model\AdminMessage;
use App\Model\AdminPostComment;
use App\Model\AdminUser;
use App\Model\AdminUserPost;
use App\Model\ChatHistory;
use App\Storage\OnlineUser;
use App\WebSocket\WebSocketStatus;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use App\Utility\Log\Log;

/**
 * 发送广播消息
 * Class BroadcastTask
 * @package App\Task
 */
class UserOperateTask implements TaskInterface
{
	protected $taskData;
	
	public function __construct($taskData)
	{
		$this->taskData = $taskData['payload'];
	}
	
	/**
	 * @param int $taskId
	 * @param int $workerIndex
	 * @return bool
	 * @throws
	 */
	function run(int $taskId, int $workerIndex): bool
	{
		$uid = $this->taskData['uid'];
		$type = $this->taskData['type'];
		$itemId = $this->taskData['item_id'];
		$itemType = $this->taskData['item_type'];
		$isCancel = $this->taskData['is_cancel'];
		$authorId = $this->taskData['author_id'];
		
		
		print_r($this->taskData);
		
		if ($itemType == 1) {
			$model = AdminUserPost::getInstance();
			$statusReport = AdminUserPost::NEW_STATUS_REPORTED;
		} elseif ($itemType == 2) {
			$model = AdminPostComment::getInstance();
			$statusReport = AdminPostComment::STATUS_REPORTED;
		} elseif ($itemType == 3) {
			$model = AdminInformation::getInstance();
			$statusReport = AdminInformation::STATUS_REPORTED;
		} elseif ($itemType == 4) {
			$model = AdminInformationComment::getInstance();
			$statusReport = AdminInformationComment::STATUS_REPORTED;
		} elseif ($itemType == 5) {
			$model = AdminUser::getInstance();
			$statusReport = AdminUser::STATUS_REPORTED;
		} else {
			return false;
		}
		switch ($type) {
			case 1:
				$tmp = $model->findOne($itemId, 'fabolus_number');
				$num = empty($tmp['fabolus_number']) || intval($tmp['fabolus_number']) < 1 ? 0 : intval($tmp['fabolus_number']);
				if (!$isCancel) {
					$model->setField('fabolus_number', $num + 1, $itemId);
					if ($authorId != $uid) {
						$where = ['type' => 2, 'user_id' => $authorId, 'item_type' => $itemType, 'item_id' => $itemId, 'did_user_id' => $uid];
						$message = AdminMessage::getInstance()->findOne($where);
						if (!empty($message)) {
							AdminMessage::getInstance()->saveDataById($message['id'], [
								'status' => AdminMessage::STATUS_UNREAD,
								'created_at' => date('Y-m-d H:i:s'),
							]);
						} else {
							//发送消息
							AdminMessage::getInstance()->insert([
								'type' => 2,
								'title' => '点赞通知',
								'item_id' => $itemId,
								'did_user_id' => $uid,
								'user_id' => $authorId,
								'item_type' => $itemType,
								'status' => AdminMessage::STATUS_UNREAD,
							]);
						}
					}
				} else {
					if ($num < 2) $num = 1;
					$model->setField('fabolus_number', $num - 1, $itemId);
					$where = ['type' => 2, 'user_id' => $authorId, 'item_type' => $itemType, 'item_id' => $itemId, 'did_user_id' => $uid];
					$message = $authorId != $uid ? AdminMessage::getInstance()->findOne($where) : null;
					if (!empty($message)) AdminMessage::getInstance()->setField('status', AdminMessage::STATUS_DEL, $message['id']);
				}
				break;
			case 2:
				$tmp = $model->findOne($itemId, 'collect_number');
				$num = empty($tmp['collect_number']) || intval($tmp['collect_number']) < 1 ? 0 : intval($tmp['collect_number']);
				$num = $isCancel ? ($num < 2 ? 0 : ($num - 1)) : ($num + 1);
				$model->setField('collect_number', $num, $itemId);
				break;
			case 3:
				$model->setField('status', $statusReport, $itemId);
				break;
		}
		return true;
	}
	
	function onException(\Throwable $throwable, int $taskId, int $workerIndex)
	{
		throw $throwable;
	}
}