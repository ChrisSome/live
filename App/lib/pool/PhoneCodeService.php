<?php
namespace App\lib\pool;

use App\lib\Tool;

class PhoneCodeService{

    const STATUS_SUCCESS = "0";   //发送成功

    const STATE_CODE    = 1;    //验证码短信
    const STATE_MARKING = 2;    //营销短信
    const STATE_VOICE   = 3;    //语音短信

    const REPEAT_NUM    = 3;    //重复次数


    private $url = 'https://api.paasoo.cn/voice?key=%s&secret=%s&from=85299998888&to=%s&lang=zh-cn&text=%s&repeat=%s';              //语音地址
    private $codeUrl = 'https://api.paasoo.com/json?key=%s&secret=%s&from=sdfknsdf&to=86%s&text=%s';    //短息地址
    public  $maxCount = 100;                        //每日最大发送量，后续验证

    private $API_KEY    = 'ybqxenxy';               //语音
    private $API_KEY_MESS = 'taihv6tw';             //短信

    private $API_SERECT = 'bBn2ebt3';               //语音
    private $API_SERECT_MESS = 'vvd4gWnb';          //短信

    public static $copying = '【夜猫体育】尊敬的用户，您好，你这次的验证码是%s，本验证码有效时间为15分钟。';     //短信模板





    /**
     * 发送短信验证码
     * @param $mobile
     * @param $content
     * @return mixed
     */

    public  function sendMess($mobile,$content){

        $url = sprintf($this->codeUrl, $this->API_KEY_MESS, $this->API_SERECT_MESS, $mobile, urlencode($content));

        return json_decode(Tool::getInstance()->postApi($url), true);


    }




}
