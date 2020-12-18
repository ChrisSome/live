<?php


namespace App\HttpController\User;


use App\Base\FrontUserController;
use App\HttpController\Admin\User\User;
use App\lib\PasswordTool;
use App\Model\AdminOption as OptionModel;
use App\Task\LoginTask;
use App\Utility\Log\Log;
use easySwoole\Cache\Cache;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use App\Utility\Message\Status;
use EasySwoole\Validate\Validate;

class Option extends FrontUserController
{
    protected $isCheckSign = true;
    public $needCheckToken = true;

    public function add()
    {
        $data = $this->fieldInfo();
        if (!$data) {
            return;
        }
        $data['content'] = addslashes(htmlspecialchars($data['content'])); //防sql注入以及xss等
        $data['user_id'] = $this->auth['id'];
        $data['nickname'] = $this->auth['nickname'];
        if ($id = OptionModel::getInstance()->insert($data)) {
            $this->writeJson(Status::CODE_OK, 'OK', ['id' => $id]);
        } else {
            var_dump(OptionModel::getInstance()->tError());
            $this->writeJson(Status::CODE_ERR, '保存失败');
            Log::getInstance()->error("option--addData:" . json_encode($data, JSON_UNESCAPED_UNICODE) . "投诉失败");
        }
    }

    // 获取修改 和 添加的数据 并判断是否完整
    private function fieldInfo($isEdit = false)
    {
        $request = $this->request();
        $data = $request->getRequestParam('content', 'phone');

        $validate = new \EasySwoole\Validate\Validate();
        $validate->addColumn('content')->required();
        $validate->addColumn('phone')->required();
        $validate->addColumn('phone')->regex('/^1\d{10}$/');

        if (!$validate->validate($data)) {
            var_dump($validate->getError()->__toString());
            $this->writeJson(\App\Utility\Message\Status::CODE_ERR, '请勿乱操作');
            return;
        }

        return $data;
    }
    public function getList()
    {

    }
}