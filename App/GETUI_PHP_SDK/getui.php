<?php




//error_reporting(0);
header("Content-Type: text/html; charset=utf-8");
require_once(dirname(__FILE__) . '/' . 'IGt.Push.php');
require_once(dirname(__FILE__) . '/' . 'igetui/IGt.AppMessage.php');
require_once(dirname(__FILE__) . '/' . 'igetui/IGt.TagMessage.php');
require_once(dirname(__FILE__) . '/' . 'igetui/IGt.APNPayload.php');
require_once(dirname(__FILE__) . '/' . 'igetui/template/IGt.BaseTemplate.php');
require_once(dirname(__FILE__) . '/' . 'IGt.Batch.php');
require_once(dirname(__FILE__) . '/' . 'igetui/utils/AppConditions.php');
require_once(dirname(__FILE__) . '/' . 'igetui/template/notify/IGt.Notify.php');
require_once(dirname(__FILE__) . '/' . 'igetui/IGt.MultiMedia.php');
require_once(dirname(__FILE__) . '/' . 'payload/VOIPPayload.php');
require_once ('igetui/template/IGt.RevokeTemplate.php');
require_once ('igetui/template/IGt.StartActivityTemplate.php');

define('APPKEY','1pzc0QJtjG6UyddwH404c9');
define('APPID','LE2EByDFzB8t1zhfgTZdk8');
define('MASTERSECRET','0QMqcC8YkF6xiQNA0sGdM2');
define('CID','');
define('HOST',"http://sdk.open.api.igexin.com/apiex.htm");
signalPush();
//群推接口案例
function pushMessageToApp(){
    $igt = new IGeTui(HOST,APPKEY,MASTERSECRET);
    // STEP2：选择通知模板
    //定义透传模板，设置透传内容，和收到消息是否立即启动启用
    $template = IGtNotificationTemplateDemo();
    // STEP5：定义"AppMessage"类型消息对象，设置消息内容模板、发送的目标App列表、是否支持离线发送、以及离线消息有效期(单位毫秒)
    $message = new IGtAppMessage();
    $message->set_isOffline(true);
    $message->set_offlineExpireTime(10 * 60 * 1000);//离线时间单位为毫秒，例，两个小时离线为3600*1000*2
    $message->set_data($template);

    $appIdList=array(APPID);
    $phoneTypeList=array('ANDROID');
    $provinceList=array('浙江');
    $tagList=array('haha');

    $message->set_appIdList($appIdList);
    //$message->set_conditions($cdt->getCondition());
    // STEP6：执行推送
    $rep = $igt->pushMessageToApp($message,"任务组名");
//\App\Utility\Log\Log::getInstance()->info('push res : ' . json_encode($rep));
    file_put_contents('./log.log', json_encode($rep));

}

function IGtNotificationTemplateDemo(){
    $logo = 'http://live-broadcast-system.oss-cn-hongkong.aliyuncs.com/37e1e9e01586030a.jpg';

    $template =  new IGtNotificationTemplate();
    $template->set_appId(APPID);                   //应用appid
    $template->set_appkey(APPKEY);                 //应用appkey
    $template->set_transmissionType(1);            //透传消息类型
    $template->set_transmissionContent("测试离线");//透传内容
    // STEP3：设置推送标题、推送内容
    $template->set_title("比赛通知");      //通知栏标题
    $template->set_text("您关注的**比赛即将开始了666777");     //通知栏内容
    $template->set_logo('37e1e9e01586030a.jpg');                       //通知栏logo
    $template->set_logoURL($logo);                    //通知栏logo链接
    //STEP4：设置响铃、震动等推送效果
    $template->set_isRing(true);                   //是否响铃
    $template->set_isVibrate(true);                //是否震动
    $template->set_isClearable(true);              //通知栏是否可清除

    return $template;
}

function signalPush(){
    $logo = 'http://live-broadcast-system.oss-cn-hongkong.aliyuncs.com/37e1e9e01586030a.jpg';
    $igt = new IGeTui(HOST,APPKEY,MASTERSECRET);

    $template =  new IGtNotificationTemplate();
    // 设置APPID与APPKEY
    $template->set_appId(APPID);//应用appid
    $template->set_appkey(APPKEY);//应用appkey
    //设置模板参数
    $template->set_transmissionType(1);//透传消息类型
    $template->set_transmissionContent("测试离线");//透传内容
    $template->set_title("单推通知标题");//通知栏标题
    $template->set_text("单推通知内容");//通知栏内容
    $template->set_logo($logo);//通知栏logo
    $template->set_isRing(true);//是否响铃
    $template->set_isVibrate(true);//是否震动
    $template->set_isClearable(true);//通知栏是否可清除

    // STEP2：设置推送其他参数
    $message = new IGtSingleMessage();
    $message->set_isOffline(true);
    $message->set_offlineExpireTime(60 * 60 * 1000);
    $message->set_data($template);

    $target = new IGtTarget();
    $target->set_appId("APPID");
    $target->set_clientId("ffc202f27c3f1fa972d10f602e0fca89");

    // STEP3：执行推送
    $ret = $igt->pushMessageToSingle($message, $target);
    file_put_contents('./log.log', json_encode($ret));

}