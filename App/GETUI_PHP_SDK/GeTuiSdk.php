<?php
namespace App\GETUI_PHP_SDK;
header("Content-Type: text/html; charset=utf-8");
require_once(dirname(__FILE__) . '/' . 'protobuf/pb_message.php');
require_once(dirname(__FILE__) . '/' . 'igetui/IGt.Req.php');
require_once(dirname(__FILE__) . '/' . 'igetui/IGt.Message.php');
require_once(dirname(__FILE__) . '/' . 'igetui/IGt.AppMessage.php');
require_once(dirname(__FILE__) . '/' . 'igetui/IGt.ListMessage.php');
require_once(dirname(__FILE__) . '/' . 'igetui/IGt.SingleMessage.php');
require_once(dirname(__FILE__) . '/' . 'igetui/IGt.Target.php');
require_once(dirname(__FILE__) . '/' . 'igetui/template/IGt.BaseTemplate.php');
require_once(dirname(__FILE__) . '/' . 'igetui/template/IGt.LinkTemplate.php');
require_once(dirname(__FILE__) . '/' . 'igetui/template/IGt.NotificationTemplate.php');
require_once(dirname(__FILE__) . '/' . 'igetui/template/IGt.TransmissionTemplate.php');
require_once(dirname(__FILE__) . '/' . 'igetui/template/IGt.NotyPopLoadTemplate.php');
require_once(dirname(__FILE__) . '/' . 'igetui/template/IGt.APNTemplate.php');
require_once(dirname(__FILE__) . '/' . 'igetui/utils/GTConfig.php');
require_once(dirname(__FILE__) . '/' . 'igetui/utils/HttpManager.php');
require_once(dirname(__FILE__) . '/' . 'igetui/utils/ApiUrlRespectUtils.php');
require_once(dirname(__FILE__) . '/' . 'igetui/utils/LangUtils.php');
require_once(dirname(__FILE__) . '/' . 'exception/GtException.php');
require_once(dirname(__FILE__) . '/' . 'exception/RequestException.php');
require_once(dirname(__FILE__) . '/' . 'IGt.Push.php');

//use App\GETUI_PHP_SDK\IGeTui;
use App\GETUI_PHP_SDK\igetui\IGtSingleMessage;
use App\GETUI_PHP_SDK\igetui\template\IGtNotyPopLoadTemplate;
use App\GETUI_PHP_SDK\igetui\IGtTarget;
class GeTuiSdk{

    private $APPKEY = '1pzc0QJtjG6UyddwH404c9';
    private $APPID = 'LE2EByDFzB8t1zhfgTZdk8';
    private $HOST = 'http://sdk.open.api.igexin.com/apiex.htm';
    private $MASTERSECRET = '0QMqcC8YkF6xiQNA0sGdM2';
    const LOGO = 'http://live-broadcast-system.oss-cn-hongkong.aliyuncs.com/37e1e9e01586030a.jpg';
    public function __construct()
    {


    }

    function pushMessageToSingle(){
        $igt = new IGeTui('http://sdk.open.api.igexin.com/apiex.htm','1pzc0QJtjG6UyddwH404c9','0QMqcC8YkF6xiQNA0sGdM2');

        //消息模版：
        // 4.NotyPopLoadTemplate：通知弹框下载功能模板
        $template = $this->IGtNotyPopLoadTemplateDemo();
        //定义"SingleMessage"
        $message = new IGtSingleMessage();

        $message->set_isOffline(true);//是否离线
        $message->set_offlineExpireTime(3600*12*1000);//离线时间
        $message->set_data($template);//设置推送消息类型

        //$message->set_PushNetWorkType(0);//设置是否根据WIFI推送消息，2为4G/3G/2G，1为wifi推送，0为不限制推送
        //接收方
        $target = new IGtTarget();
        $target->set_appId('LE2EByDFzB8t1zhfgTZdk8');
        $target->set_clientId('ffc202f27c3f1fa972d10f602e0fca89');
//    $target->set_alias(Alias);

        try {

            $rep = $igt->pushMessageToSingle($message, $target);
            file_put_contents('./log.log', 'push res' . json_encode($rep));

        }catch(RequestException $e){
            $requstId = $e->getRequestId();
            //失败时重发
            $rep = $igt->pushMessageToSingle($message, $target,$requstId);
//            file_put_contents('./log.log', 'repush res' . json_encode($rep));

        }
    }

    function IGtNotyPopLoadTemplateDemo(){
        $template =  new IGtNotyPopLoadTemplate();
        $template ->set_appId('LE2EByDFzB8t1zhfgTZdk8');                      //应用appid
        $template ->set_appkey('1pzc0QJtjG6UyddwH404c9');                    //应用appkey
        //通知栏
        $template ->set_notyTitle("通知标题");                 //通知栏标题
        $template ->set_notyContent("通知内容"); //通知栏内容
        $template ->set_notyIcon(self::LOGO);                      //通知栏logo
        $template ->set_isBelled(true);                    //是否响铃
        $template ->set_isVibrationed(true);               //是否震动
        $template ->set_isCleared(true);                   //通知栏是否可清除
        //弹框
        $template ->set_popTitle("弹框标题");              //弹框标题
        $template ->set_popContent("弹框内容");            //弹框内容
        $template ->set_popImage("");                      //弹框图片
        $template ->set_popButton1("下载");                //左键
        $template ->set_popButton2("取消");                //右键
        //下载
        $template ->set_loadIcon(self::LOGO);                      //弹框图片
        $template ->set_loadTitle("下载标题");
        $template ->set_loadUrl("请填写下载地址");
        $template ->set_isAutoInstall(false);
        $template ->set_isActived(true);

        //设置通知定时展示时间，结束时间与开始时间相差需大于6分钟，消息推送后，客户端将在指定时间差内展示消息（误差6分钟）
        //$begin = "XXXX-XX-XX XX:XX:XX";
        //$end = "XXXX-XX-XX XX:XX:XX";
        //$template->set_duration($begin,$end);
        return $template;
    }

    public function pushGroup()
    {

    }
}

$model = new GeTuiSdk();
$model->pushMessageToSingle();