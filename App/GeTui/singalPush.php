<?php
//消息推送Demo
header("Content-Type: text/html; charset=utf-8");
require_once(dirname(__FILE__) . '/' . 'IGt.Push.php');

//采用"PHP SDK 快速入门"， "第二步 获取访问凭证 "中获得的应用配置
define('APPKEY','1pzc0QJtjG6UyddwH404c9');
define('APPID','LE2EByDFzB8t1zhfgTZdk8');
define('MASTERSECRET','0QMqcC8YkF6xiQNA0sGdM2');
define('HOST','http://api.getui.com/apiex.htm');
define('CID','7645b2d25d0ca893d1c8a5be200148bb');

class singalPush {
    const LOGO = 'http://live-broadcast-system.oss-cn-hongkong.aliyuncs.com/37e1e9e01586030a.jpg';
    //单推接口案例
    function pushMessageToSingle(){
        $igt = new IGeTui(HOST,APPKEY,MASTERSECRET);

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
        $target->set_clientId('7645b2d25d0ca893d1c8a5be200148bb');
//    $target->set_alias(Alias);

        try {
            $rep = $igt->pushMessageToSingle($message, $target);
            print_r($rep);
//        file_put_contents('./log.log', json_encode($rep));


        }catch(RequestException $e){
            $requstId =$e->getRequestId();
            //失败时重发
            $rep = $igt->pushMessageToSingle($message, $target,$requstId);
//            var_dump($e->getMessage());
            echo ("<br><br>");
        }
    }

    function IGtNotyPopLoadTemplateDemo(){
        $template =  new IGtNotyPopLoadTemplate();
        $template ->set_appId('LE2EByDFzB8t1zhfgTZdk8');                      //应用appid
        $template ->set_appkey('1pzc0QJtjG6UyddwH404c9');                    //应用appkey
        //通知栏
        $template ->set_notyTitle("通知标题");                 //通知栏标题
        $template ->set_notyContent("通知内容"); //通知栏内容
        $template ->set_notyIcon("http://live-broadcast-system.oss-cn-hongkong.aliyuncs.com/37e1e9e01586030a.jpg");                      //通知栏logo
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
        $template ->set_loadIcon("http://live-broadcast-system.oss-cn-hongkong.aliyuncs.com/37e1e9e01586030a.jpg");                      //弹框图片
        $template ->set_loadTitle("请填写下载标题");
        $template ->set_loadUrl("请填写下载地址");
        $template ->set_isAutoInstall(false);
        $template ->set_isActived(true);

        //设置通知定时展示时间，结束时间与开始时间相差需大于6分钟，消息推送后，客户端将在指定时间差内展示消息（误差6分钟）
        //$begin = "XXXX-XX-XX XX:XX:XX";
        //$end = "XXXX-XX-XX XX:XX:XX";
        //$template->set_duration($begin,$end);
        return $template;
    }

    function pushMessageToApp(){
        $igt = new IGeTui(HOST,APPKEY,MASTERSECRET);
        // STEP2：选择通知模板
        //定义透传模板，设置透传内容，和收到消息是否立即启动启用
        $template = $this->IGtNotificationTemplateDemo();
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

        print_r($rep);
        echo ("<br><br>");
    }

    function IGtNotificationTemplateDemo(){
        $template =  new IGtNotificationTemplate();
        $template->set_appId('LE2EByDFzB8t1zhfgTZdk8');                   //应用appid
        $template->set_appkey('1pzc0QJtjG6UyddwH404c9');                 //应用appkey
        $template->set_transmissionType(1);            //透传消息类型
        $template->set_transmissionContent("测试离线");//透传内容
        // STEP3：设置推送标题、推送内容
        $template->set_title("请输入通知栏标题");      //通知栏标题
        $template->set_text("请输入通知栏内容");     //通知栏内容
        $template->set_logo("");                       //通知栏logo
        $template->set_logoURL("");                    //通知栏logo链接
        $template->set_isRing(true);                   //是否响铃
         $template->set_isVibrate(true);                //是否震动
        $template->set_isClearable(true);              //通知栏是否可清除

    return $template;
}
}
$a = new singalPush();
$a->pushMessageToSingle();
?>