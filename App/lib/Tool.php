<?php


namespace App\lib;


use App\Utility\Log\Log;
use EasySwoole\Component\Singleton;

class Tool
{
    use Singleton;

    public function postApi($url, $method = 'GET', $params = [], $headers = [])
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_HEADER, 0);//返回response头部信息
            }
            if ($method == 'POST') {
                curl_setopt($ch, CURLOPT_POST, 1);         //发送POST类型数据
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            }
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  //SSL 报错时使用
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);  //SSL 报错时使用
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            return curl_exec($ch);
        } catch (\Exception $e) {
            return false;
        }
    }


    /**
     * 生成手机号验证码
     * @param int $length
     * @return string
     */
    public function generateCode($length = 6)
    {
        $code = '';
        for ($i = 1; $i <= $length; $i++) {
            $code .= mt_rand(0, 9);
        }

        return $code;
    }

    /**
     * 生成随机字符串
     * @param string $length 长度
     * @return string 生成的随机字符串
     */
    public function makeRandomString($length = 1) {

        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol) - 1;

        for($i=0; $i<$length; $i++) {
            $str .= $strPol[rand(0,$max)];//rand($min,$max)生成介于min和max两个数之间的一个随机整数
        }
        return $str;
    }


    /**
     * @param $code
     * @param $msg
     * @param array $data
     * @return false|string
     */
    public function writeJson($code, $msg, $data = [])
    {

        return  json_encode([
            'code' => $code,
            'msg' => $msg,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);

    }

}