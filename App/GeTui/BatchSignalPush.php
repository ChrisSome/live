<?php
namespace App\GeTui;
require_once __DIR__ . '/IGt.Batch.php';
include_once __DIR__ . '/IGt.Push.php';
use App\Model\AdminNoticeMatch;
use App\Utility\Log\Log;


class BatchSignalPush{
    const APPKEY = '1pzc0QJtjG6UyddwH404c9';
    const APPID = 'LE2EByDFzB8t1zhfgTZdk8';
    const MASTERSECRET = '0QMqcC8YkF6xiQNA0sGdM2';
    const HOST = 'http://sdk.open.api.igexin.com/apiex.htm';
    public function __construct()
    {
        //批量单推Demo
//        header("Content-Type: text/html; charset=utf-8");


    }


    //多推接口案例
    function pushMessageToList($cids, $info){
        putenv("gexin_pushList_needDetails=true");
        $igt = new \IGeTui(self::HOST, self::APPKEY, self::MASTERSECRET);
        $notice_id = $info['notice_id'];
        unset($info['notice_id']);
        $template = $this->IGtTransmissionTemplateDemo($info);

        //定义"ListMessage"信息体
        $message = new \IGtListMessage();
        $message->set_isOffline(true);//是否离线
        $message->set_offlineExpireTime(3600*12*1000);//离线时间
        $message->set_data($template);//设置推送消息类型
        $message->set_PushNetWorkType(0);//设置是否根据WIFI推送消息，1为wifi推送，0为不限制推送，在wifi条件下能帮用户充分节省流量
        $contentId = $igt->getContentId($message);
        $targetList = [];
        //接收方1
        foreach ($cids as $cid) {
            $target = new \IGtTarget();
            $target->set_appId(self::APPID);
            $target->set_clientId($cid);
            $targetList[] = $target;
            unset($target);

        }
        try {
            $rep = $igt->pushMessageToList($contentId, $targetList);
            if ($rep['result'] == 'ok') {
                AdminNoticeMatch::getInstance()->update([
                    'is_notice' => 1
                ], ['id' => $notice_id]);
            }
            Log::getInstance()->info('submit res succ' . json_encode($rep));
            return $rep;
        }catch(\Exception $e){
            Log::getInstance()->info('submit res fail' . json_encode($e->getMessage()));

        }

    }

    function IGtTransmissionTemplateDemo($info){
//        $transmisstionContent = json_encode(['title' => '开赛了', 'content' => '比赛马上开始', 'payload' => ['item_id' => 3295894, 'item_type' => 1]]);
        $template =  new \IGtTransmissionTemplate();
        //应用appid
        $template->set_appId(self::APPID);
        //应用appkey
        $template->set_appkey(self::APPKEY);
        //透传消息类型
        $template->set_transmissionType(1);
        //透传内容
        $template->set_transmissionContent(json_encode($info));

        return $template;
    }



}

