<?php


namespace App\HttpController\User;

use App\Model\AdminAdvertisement;
use App\Model\AdminCategory;
use App\Model\AdminMessage as MessageModel;
use App\Base\FrontUserController;
use App\Model\AdminSensitive;
use App\Model\AdminSysSettings;
use App\Model\AdminSystemAnnoucement;
use App\Task\MessageTask;
use App\Utility\Log\Log;
use App\Utility\Message\Status;
use EasySwoole\EasySwoole\Task\TaskManager;

use EasySwoole\HttpAnnotation\AnnotationController;
use EasySwoole\HttpAnnotation\AnnotationTag\Api;
use EasySwoole\HttpAnnotation\AnnotationTag\Param;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiDescription;
use EasySwoole\HttpAnnotation\AnnotationTag\Method;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiSuccess;

class System extends FrontUserController
{
    public $needCheckToken = false;
    public $isCheckSign = false;

    const SYS_KEY_HOT_RELOAD = 'hot_reload';
    const SYS_KEY_SHIELD_LIVE = 'shield_live';

    const NOTICE = [
        'only_notice_my_interest' => 0,  //仅提示我关注的
        'start' => 1, //比赛开始   1声音震动 2声音 3震动 0关闭
        'goal' => 1, //进球
        'over' => 1, //结束
        'red_card' => 1, //红牌
        'yellow_card' => 1, //黄牌
        'show_time_axis' => 1 //显示时间轴  1开启 0关闭
    ];

    const BASKETBALL_NOTICE = [
        'only_notice_my_interest' => 0,  //仅提示我关注的
        'start' => 1, //比赛开始 1声音震动 2声音 3震动 0关闭
        'over' => 1, //结束
    ];

    const PUSH = ['start' => 1, 'goal' => 1, 'over' => 1,  'open_push' => 1, 'information' => 1];
    const BASKETBALL_PUSH = ['start' => 1, 'over' => 1,  'open_push' => 1];
    const PRIVATE = ['see_my_post' => 1, 'see_my_post_comment' => 1, 'see_my_information_comment' => 1];
    /**
     * 获取系统公告
     */
    public function index()
    {
        $params = $this->request()->getQueryParams();
        $query = MessageModel::getInstance()->where('cate_id', AdminCategory::CATEGORY_ANNOCEMENT)
            ->where('status', 1);

        $count = $query->count();
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = isset($params['offset']) ? $params['offset'] : 10;
        $data = $query->field('id, title, cate_name, status,created_at')->order('created_at', 'desc')->findAll($page, $limit);
        return $this->writeJson(Status::CODE_OK, 'ok', [
            'data' => $data,
            'count' => $count
        ]);
    }

    /**
     * 公告详情
     * @param $id
     */
    public function detail()
    {
        $id = $this->request()->getRequestParam('id');
        $info = MessageModel::getInstance()->find($id);
        if (!$info) {
            $this->writeJson(Status::CODE_ERR, '对应公告不存在');
            return ;
        }
        //写异步task记录已读
        $auth = $this->auth;
        TaskManager::getInstance()->async(function ($taskId, $workerIndex) use ($info, $auth){
            $messageTask = new MessageTask([
                'message_id' => $info['id'],
                'message_title' => $info['title'],
                'user_id' => $auth['id'],
                'mobile' => $auth['mobile'],
            ]);
            $messageTask->execData();
        });
        return $this->writeJson(Status::CODE_OK, 'ok', $info);
    }

    /**
     * 热更新
     * @Api(name="热更新",path="/api/system/hotreload",version="3.0")
     * @ApiDescription(value="serverClient for systemHotReload")
     * @Method(allow="{GET}")
     * @Param(name="version",type="string",required="",description="用户版本号")
     * @Param(name="phone_type",type="string",required="",description="手机型号")
     * @ApiSuccess({"code":0,"msg":"ok","data":{"is_new":-1,"accoucement":{"title":"dfsdfsdfsdfrtereree","content":"dfsdfsdfsdfdsfg","created_at":"2020-12-23 23:22:00"},"shield_live":0,"wgt_url":"http://download.yemaoty.cn/WGT/__UNI__0AC1311.wgt"}})
     */
    function hotreload()
    {

        if (isset($this->params['version']) && isset($this->params['phone_type'])) {
            $package = AdminSysSettings::create()->get(['sys_key' => self::SYS_KEY_HOT_RELOAD]);

            if (!$package) {
                $data['is_new'] = 1;
            } else {
                $value = json_decode($package->sys_value, true);
                $version = $this->params['version'];
                $sysVer = $value['version'];
                $idff = version_compare($version, $sysVer);
                $data['is_new'] = $idff;
                $data['accoucement'] = $value['accoucement'];
                $shield_live = AdminSysSettings::create()->get(['sys_key' => self::SYS_KEY_SHIELD_LIVE]);
                $phoneType = $this->params['phone_type'];
                $sys_value_decode = json_decode($shield_live['sys_value'], true);
                $phoneTypeSetting = isset($sys_value_decode[$phoneType]) ? $sys_value_decode[$phoneType] : '';
                $data['shield_live'] = $phoneTypeSetting ? $phoneTypeSetting : 0;
                if ($this->params['phone_type'] == 'test') {
                    $data['shield_live'] = 1;
                }
                $data['wgt_url'] = $value['wgt_url'];

            }
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

        } else {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
    }

    /**
     * 开屏广告
     * @Api(name="开屏广告",path="/api/system/adImgs",version="3.0")
     * @ApiDescription(value="serverClient for adImgs")
     * @Method(allow="{GET}")
     * @ApiSuccess({"code":0,"msg":"ok","data":{"img":"http://backgroundtest.ymtyadmin.com/upload/config/3cd584757b512f32cac661becc178384.jpeg","url":"https://www.baidu.com","countDown":5,"is_open":true,"is_force":true}})
     */
    public function adImgs()
    {
        if ($res = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::SETTING_OPEN_ADVER)->get()) {
            $data = json_decode($res->sys_value, true);
        } else {
            $data = [
                'img' => 'http://live-broadcast-system.oss-cn-hongkong.aliyuncs.com/e44e1023d520f507.jpg',
                'url' => 'https://www.baidu.com',
                'countDown' => 3,
                'is_force' => false,
                'is_open' => false,
            ];
        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

    }

    /**
     * 广告列表
     */
    public function advertisement()
    {
        if (!isset($this->params['cat_id'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

        $ads = AdminAdvertisement::getInstance()->where('status', AdminAdvertisement::STATUS_NORMAL)->where('cat_id', $this->params['cat_id'])->all();
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $ads);

    }

    /**
     * 敏感词
     * @Api(name="敏感词",path="/api/system/sensitiveWord",version="3.0")
     * @ApiDescription(value="serverClient for sensitiveWord")
     * @Method(allow="{GET}")
     * @ApiSuccess({"code":0,"msg":"ok","data":[{"word":"兼职"},{"word":"招聘"}]})
     */
    public function sensitiveWord()
    {
        $words = AdminSensitive::getInstance()->where('id', 0, '>')->field(['word'])->all();
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $words);

    }

}