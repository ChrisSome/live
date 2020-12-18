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

    /**
     * 批量推
     * @param array $cids
     * @param $info
     * @return mixed
     * @throws \Exception
     */
    function pushMessageToSingleBatch(array $cids, $info)
    {
        $cids = array_unique($cids);
        foreach ($cids as $cid) {
            $notice['cid'] = $cid;
            $notice['title'] = $info['title'];
            $notice['content'] = $info['content'];
            $notice['type'] = $info['type'];
            $notice['transmissionParams'] = ['match_id' => $info['match_id'],'type' => $info['type']];
            $notices[] = $notice;
            unset($notice);
        }
        $igt = new \IGeTui(self::HOST, self::APPKEY, self::MASTERSECRET);
        $batch = new \IGtBatch(self::APPKEY, $igt);
        $batch->setApiUrl(self::HOST);
        if (!isset($notices)) return;
        //$template = IGtNotyPopLoadTemplateDemo();
        foreach ($notices as $item) {
            $templateNoti = $this->IGtNotificationTemplateDemo($item);
            $templateNoti->set_transmissionType(1);//透传消息类型
            //个推信息体
            $messageNoti = new \IGtSingleMessage();
            $messageNoti->set_isOffline(true);//是否离线
            $messageNoti->set_offlineExpireTime(12 * 1000 * 3600);//离线时间
            $messageNoti->set_data($templateNoti);//设置推送消息类型
            $targetNoti = new \IGtTarget();
            $targetNoti->set_appId(self::APPID);
            $targetNoti->set_clientId($item['cid']);
            $batch->add($messageNoti, $targetNoti);

        }


        try {
            $rep = $batch->submit();
            if ($rep['result'] == 'ok') {
                AdminNoticeMatch::getInstance()->update([
                    'is_notice' => 1
                ], ['id' => $info['rs']]);
            }

            Log::getInstance()->info('submit res succ' . json_encode($rep));
            return $rep;


        }catch(Exception $e){
            $rep = $batch->submit();
            Log::getInstance()->info('submit res fail' . json_encode($rep));

            return $rep;

        }
    }

    function IGtNotificationTemplateDemo($val){
        $template =  new \IGtNotificationTemplate();
        $template->set_appId(self::APPID);//应用appid
        $template->set_appkey(self::APPKEY);//应用appkey
        $template->set_transmissionType(2);//透传消息类型
        $transmissionParams = $val['transmissionParams'];

        $template->set_transmissionContent(json_encode($transmissionParams));//透传内容
        $template->set_title($val['title']);//通知栏标题
        $template->set_text($val['content']);//通知栏内容
        $template->set_logo("http://live-broadcast-system.oss-cn-hongkong.aliyuncs.com/37e1e9e01586030a.jpg");//通知栏logo
        $template->set_isRing(true);//是否响铃
        $template->set_isVibrate(true);//是否震动
        $template->set_isClearable(true);//通知栏是否可清除
        //$template->set_duration(BEGINTIME,ENDTIME); //设置ANDROID客户端在此时间区间内展示消息
        return $template;
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

    function IGtNotificationTemplateDemo1($info){
        $transmisstionContent = json_encode(['title' => '开赛了', 'content' => '比赛马上开始', 'payload' => ['match_id' => 3295894, 'type' => 1]]);

        $template = new \IGtNotificationTemplate();
        $template->set_appId(self::APPID);                      //应用appid
        $template->set_appkey(self::APPKEY);                    //应用appkey
        $template->set_transmissionType(1);               //透传消息类型
        $template->set_transmissionContent($transmisstionContent);   //透传内容
        $template->set_title($info['title']);                     //通知栏标题
        $template->set_text($info['content']);        //通知栏内容
        $template->set_logo("http://live-broadcast-system.oss-cn-hongkong.aliyuncs.com/37e1e9e01586030a.jpg");                  //通知栏logo
        $template->set_isRing(true);                      //是否响铃
        $template->set_isVibrate(true);                   //是否震动
        $template->set_isClearable(true);                 //通知栏是否可清除
        //$template->set_notifyId(12345678);
        return $template;
    }

    function IGtTransmissionTemplateDemo($info){
//        $transmisstionContent = json_encode(['title' => '开赛了', 'content' => '比赛马上开始', 'payload' => ['item_id' => 3295894, 'type' => 1]]);
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

