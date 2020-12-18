<?php

namespace App\Common;


use App\HttpController\Match\FootballApi;
use App\lib\FrontService;
use App\Model\AdminAlphaMatch;
use App\Model\AdminInterestMatches;
use App\Model\AdminMatch;
use App\Model\AdminSeason;
use App\Model\AdminSysSettings;
use App\Model\AdminUser;
use App\Model\AdminUserSetting;
use App\Model\AdminZoneList;
use App\Storage\OnlineUser;
use easySwoole\Cache\Cache;
use EasySwoole\Redis\Redis as Redis;
use EasySwoole\RedisPool\Redis as RedisPool;

class AppFunc
{

    const USER_FOLLOWS = "user_follows:uid:%s";  //用户关注列表
    const USER_FANS = 'user_fans:uid:%s'; //用户粉丝列表


    const USER_MESS = 'user_messageCount:uid:%s'; //哈希表 用来存放用户消息的数量
    const USER_MESS_TYPE_COUNT = 'user_message:type_%s';  //type=4的消息的未读数

    const USER_MESS_TYPE_TABLE = 'user_message_number:uid:%s';  //用与存各类消息数量的哈希表

    const USER_INTEREST_MATCH = 'user_insterest_match:match_id:%s';  //关注此场比赛的用户
    const USER_BLACK_LIST = 'user_black_list:%s';
    const USERS_IN_ROOM = 'users_in_room:%s'; //该房间下的用户  roomid

    const MATCH_INFO = 'match_info_match_id_%s'; //某场比赛的乱七八糟的信息 进攻/危险进攻/控球率/射正/射偏  从stats中提取

    // 二维数组 转 tree
    public static function arrayToTree($list, $pid = 'pid')
    {
        $map = [];
        if (is_array($list)) {
            foreach ($list as $k => $v) {
                $map[$v[$pid]][] = $v; // 同一个pid 放在同一个数组中
            }
        }

        return self::makeTree($map);
    }

    private static function makeTree($list, $parent_id = 0)
    {
        $items = isset($list[$parent_id]) ? $list[$parent_id] : [];
        if (!$items) {
            return null;
        }

        $trees = [];
        foreach ($items as $k => $v) {
            $children = self::makeTree($list, $v['id']); // 找到以这个id 为pid 的数据
            if ($children) {
                $v['children'] = $children;
            }
            $trees[] = $v;
        }

        return $trees;
    }

    /**
     * / 规则 |--- 就分的
     * @param  [type] $tree_list [树 数组]
     * @param  [type] &$tree     [返回的二维数组]
     * @param  [type] $name      [那个字段进行 拼接|-- ]
     * @param  string $pre       [前缀]
     * @param  string $child     [树 的子分支]
     * @return [type]            [description]
     */
    public static function treeRule($tree_list, &$tree, $pre = '', $name = 'name', $child = 'children')
    {
        if (is_array($tree_list)) {
            foreach ($tree_list as $k => $v) {
                $v[$name] = $pre . $v[$name];
                $tree[]    = $v;
                if (isset($v[$child])) {
                    self::treeRule($v[$child], $tree, $pre . '&nbsp;|------&nbsp;');
                }
            }
        }
    }

    /**
     * / 规则 |--- 就分的
     * @param  [type] $tree_list [树 数组]
     * @param  [type] &$tree     [返回的二维数组]
     * @param  [type] $name      [那个字段进行 拼接|-- ]
     * @param  string $pre       [前缀]
     * @param  string $child     [树 的子分支]
     * @return [type]            [description]
     */
    public static function treeRules($tree_list, &$tree, $pre = '', $name = 'name', $child = 'children')
    {

        if (is_array($tree_list)) {
            foreach ($tree_list as $k => $v) {
                $v[$name] = $pre . $v[$name];
                $tree[$v['id']]    = $v['name'];
                if (isset($v[$child])) {
                    self::treeRules($v[$child], $tree, $pre . '|-----|------');
                }
            }
        }
    }

    /**
     * 获得随机字符串
     * @param $len             需要的长度
     * @param $special        是否需要特殊符号
     * @return string       返回随机字符串
     */
    public static function getRandomStr($len, $special = true)
    {
        $chars = [
            "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
            "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
            "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
            "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
            "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
            "3", "4", "5", "6", "7", "8", "9"
        ];

        if ($special) {
            $chars = array_merge($chars, [
                "!", "@", "#", "$", "?", "|", "{", "/", ":", ";",
                "%", "^", "&", "*", "(", ")", "-", "_", "[", "]",
                "}", "<", ">", "~", "+", "=", ",", "."
            ]);
        }

        $charsLen = count($chars) - 1;
        shuffle($chars); //打乱数组顺序
        $str = '';
        for ($i = 0; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $charsLen)]; //随机取出一位
        }
        return $str;
    }

    /**
     * easyswoole where 条件不支持or，此处改造
     * @param string $col
     * @param array $where
     * @return string
     */
    public static function getWhereArray(string $col, array $where)
    {
        if (!$where) return '';

        $str = '';
        foreach ($where as $v) {
            $str .= ($col . '=' . $v . ' or ');
        }
        return '(' . rtrim($str, 'or ') . ')';
    }

    /**
     * 验证必须存在
     * @param $col
     */
    public static function validateRequired($col)
    {
        if (is_array($col)) {
            foreach ($col as $item) {

            }
        }
    }

    /**
     * @param $col
     * @param $item
     * @return string
     */
    public static function whereLike($col, $item)
    {
        return $col .  "like '%" . $item . "%'";
    }


    /**
     * 获取汉字字符串首字母
     * @param $str
     * @return string|null
     */
    public static function  getFirstCharters($str)
    {
        if (empty($str)) {
            return '';
        }
        //取出参数字符串中的首个字符
        $temp_str = substr($str,0,1);
        if(ord($temp_str) > 127){
            $str = substr($str,0,3);
        }else{
            $str = $temp_str;
            $fchar = ord($str{0});
            if ($fchar >= ord('A') && $fchar <= ord('z')){
                return strtoupper($temp_str);
            }else{
                return null;
            }
        }
        $s1 = iconv('UTF-8', 'gb2312//IGNORE', $str);
        if(empty($s1)){
            return null;
        }
        $s2 = iconv('gb2312', 'UTF-8', $s1);
        if(empty($s2)){
            return null;
        }
        $s = $s2 == $str ? $s1 : $str;
        $asc = ord($s{0}) * 256 + ord($s{1}) - 65536;
        if ($asc >= -20319 && $asc <= -20284)
            return 'A';
        if ($asc >= -20283 && $asc <= -19776)
            return 'B';
        if ($asc >= -19775 && $asc <= -19219)
            return 'C';
        if ($asc >= -19218 && $asc <= -18711)
            return 'D';
        if ($asc >= -18710 && $asc <= -18527)
            return 'E';
        if ($asc >= -18526 && $asc <= -18240)
            return 'F';
        if ($asc >= -18239 && $asc <= -17923)
            return 'G';
        if ($asc >= -17922 && $asc <= -17418)
            return 'H';
        if ($asc >= -17417 && $asc <= -16475)
            return 'J';
        if ($asc >= -16474 && $asc <= -16213)
            return 'K';
        if ($asc >= -16212 && $asc <= -15641)
            return 'L';
        if ($asc >= -15640 && $asc <= -15166)
            return 'M';
        if ($asc >= -15165 && $asc <= -14923)
            return 'N';
        if ($asc >= -14922 && $asc <= -14915)
            return 'O';
        if ($asc >= -14914 && $asc <= -14631)
            return 'P';
        if ($asc >= -14630 && $asc <= -14150)
            return 'Q';
        if ($asc >= -14149 && $asc <= -14091)
            return 'R';
        if ($asc >= -14090 && $asc <= -13319)
            return 'S';
        if ($asc >= -13318 && $asc <= -12839)
            return 'T';
        if ($asc >= -12838 && $asc <= -12557)
            return 'W';
        if ($asc >= -12556 && $asc <= -11848)
            return 'X';
        if ($asc >= -11847 && $asc <= -11056)
            return 'Y';
        if ($asc >= -11055 && $asc <= -10247)
            return 'Z';
        return 'hot';
    }


    /**
     * 将整数转化为 x亿x千万
     * @param $number
     * @return mixed
     */
    public static function formatValue($number)
    {

        $wan_int = substr($number, -8);

        $length = strlen($number);  //数字长度
        if($length > 8){ //亿单位
            $yi = substr_replace(strstr($number,substr($number,-7),' '),'.',-1,0);
            $yi_str = floor($yi) . '亿';
            $wan = substr_replace(strstr($wan_int,substr($wan_int,-3),' '),'.',-1,0);
            if (floor($wan) == 0) {
                $wan_str = '1万';

            } else {
                $wan_str = floor($wan) . '万';

            }
            return $yi_str . $wan_str . '欧';

        }elseif($length >4){ //万单位
            $wan = substr_replace(strstr($wan_int,substr($wan_int,-3),' '),'.',-1,0);
            $wan_str = floor($wan) . '万欧';
            return $wan_str;
        }else{
            return '';

        }


    }

    /**
     * 将整数转为万
     * @param $number
     * @param string $unit
     * @return int|string
     */
    public static function changeToWan($number, $unit = '万')
    {
        if (!$number) {
            return 0;
        }
        $length = strlen($number);  //数字长度
        if ($length < 5) {
            return $number;
        } else {
            $wan_str = number_format($number/10000,0);
            return $wan_str . $unit;
        }

    }

    public static function getUserLvByPoint($point)
    {

        if (0 <= $point && $point < 15000) {
            return floor($point/500) + 1;
        } else if (15000 <= $point && $point < 45000) {
            return floor(($point-15000)/1000) + 30;
        }

    }

    /**
     * @param $level
     * @return int
     */
    public static function getPointOfLevel($level)
    {
        if ($level >= 0 && $level<30) {
            return 500;
        } else if ($level >= 30 && $level < 60) {
            return 1000;
        }
    }

    /**
     * 距离下一级相差多少分
     * @param $uid
     * @return float|int
     */
    public static function getPointsToNextLevel($uid)
    {
        $user = AdminUser::getInstance()->where('id', $uid)->get();

        $level = $user->level;
        $point = $user->point;
        if ($level < 30) {
            $D_value = $level * 500 - $point;
        } else if ($level >= 30 && $level < 60) {
            $D_value = $level * 1000 - $point;
        }
        return $D_value;
    }

    /**
     * 总比分
     * @param $home_score
     * @param $away_score
     * @return array
     */
    public static function getFinalScore($home_score, $away_score)
    {
        /**
         * 主客场加时比分不为零 总比分 = 加时比分 + 点球大战比分
         * 主客场加时比分为零 总比分 = 常规时间比分 + 点球大战比分
         */
        //[0,0,0,2,2,0,0]  比分(常规时间) / 半场比分  /红牌  /黄牌 /角球 /加时比分(120分钟)加时赛才有 /点球大战比分，点球大战才有
        if (!$home_score[5] && !$away_score[5]){
            $home_total_score = $home_score[0] + $home_score[6];
            $away_total_score = $away_score[0] + $away_score[6];
        } else {
            $home_total_score = $home_score[5] + $home_score[6];
            $away_total_score = $away_score[6] + $away_score[6];

        }
        return [$home_total_score, $away_total_score];
    }

    /**
     * 获取黄牌数
     * @param $home_score
     * @param $away_score
     * @return array
     */
    public static function getYellowCard($home_score, $away_score)
    {
        return [$home_score[3], $away_score[3]];
    }

    /**
     * 获取红牌数量
     * @param $home_score
     * @param $away_score
     * @return array
     */
    public static function getRedCard($home_score, $away_score)
    {
        return [$home_score[2], $away_score[2]];

    }

    /**
     * 获取角球数量
     * @param $home_score
     * @param $away_score
     * @return array
     */
    public static function getCorner($home_score, $away_score)
    {
        return [$home_score[4], $away_score[4]];

    }


    public static function getAllScoreType($home_score, $away_score)
    {
        //半场
        $home_half_score = $home_score[1];
        $away_half_score = $away_score[1];
        $home_corner = $home_score[4];
        $away_corner = $away_score[4];

        //总比分
        if (!$home_score[5] && !$away_score[5]){
            $home_total_score = $home_score[0] + $home_score[6];
            $away_total_score = $away_score[0] + $away_score[6];
        } else {
            $home_total_score = $home_score[5] + $home_score[6];
            $away_total_score = $away_score[6] + $away_score[6];

        }

        return [$home_total_score, $away_total_score, $home_half_score, $away_half_score, $home_corner, $away_corner];
    }

    /**
     * 半场比分
     * @param $home_score
     * @param $away_score
     * @return array
     */
    public static function getHalfScore($home_score, $away_score)
    {

        if (isset($home_score[1])) {
            $home_half_score = $home_score[1];
        } else {
            $home_half_score = 0;
        }

        if (isset($away_score[1])) {
            $away_half_score = $away_score[1];
        } else {
            $away_half_score = 0;
        }

        return [$home_half_score, $away_half_score];
    }




    /**
     * 是否关注
     * @param $uid
     * @param $followId
     * @return bool
     */
    public static function isFollow($uid, $followId)
    {
        if (!$uid || !$followId) {
            return false;
        }
        RedisPool::invoke('redis', function(Redis $redis) use ($uid, &$id_arr) {
            $id_arr = $redis->sMembers(sprintf(self::USER_FOLLOWS, $uid));
        });
        if (in_array($followId, $id_arr)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 用户点击关注 增加关注列表 增加被关注人粉丝列表
     * @param $uid
     * @param $followId
     * @return bool
     */
    public static function addFollow($uid, $followId)
    {
        if (!$uid || !$followId)
        {
            return false;
        }
        RedisPool::invoke('redis', function(Redis $redis) use ($uid, $followId) {

            $redis->sAdd(sprintf(self::USER_FOLLOWS, $uid), $followId);
            $redis->sAdd(sprintf(self::USER_FANS, $followId), $uid);
        });
        return true;

    }

    /**
     * 取消关注
     * @param $uid
     * @param $followId
     * @return bool
     */
    public static function delFollow($uid, $followId)
    {
        if (!$uid || !$followId)
        {
            return false;
        }
        RedisPool::invoke('redis', function(Redis $redis) use ($uid, $followId) {
            $redis->sRem(sprintf(self::USER_FOLLOWS, $uid), $followId);
            $redis->sRem(sprintf(self::USER_FANS, $followId), $uid);
        });
        return true;
    }

    /**
     * 用户关注列表
     * @param $uid
     * @return array
     */
    public static function getUserFollowing($uid)
    {
        if (!$uid) {
            return [];
        } else {
            RedisPool::invoke('redis', function(Redis $redis) use ($uid, &$id_arr) {
                $id_arr = $redis->sMembers(sprintf(self::USER_FOLLOWS, $uid));
            });

            return !empty($id_arr) ? $id_arr : [];
        }
    }

    /**
     * 用户粉丝列表
     * @param $uid
     * @return array
     */
    public static function getUserFans($uid)
    {
        if (!$uid) {
            return [];
        } else {
            RedisPool::invoke('redis', function(Redis $redis) use ($uid, &$id_arr) {
                $id_arr = $redis->sMembers(sprintf(self::USER_FANS, $uid));
            });
            return !empty($id_arr) ? $id_arr : [];

        }
    }



    /**
     * 获取此比赛所有的关注用户
     * @param $match_id
     * @return array
     */
    public static function getUsersInterestMatch($match_id)
    {
        if (!$match_id || !AdminMatch::getInstance()->where('match_id')->get()) {
            return [];
        } else {
            RedisPool::invoke('redis', function(Redis $redis) use ($match_id, &$uids) {
                $uids = $redis->sMembers(sprintf(self::USER_INTEREST_MATCH, $match_id));
            });
            return $uids;
        }
    }





    public static function getTestDomain()
    {
        return 'http://test.ymtyadmin.com';
    }

    public function getFormalDomain()
    {
        return 'http://www.yemaoty.cn';
    }

    /**
     * 是否在推荐赛事中
     * @param $competitionId
     * @return bool
     */
    public static function isInHotCompetition($competitionId)
    {
        if ($setting = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::COMPETITION_ARR)->get()) {
            $com_arr = json_decode($setting->sys_value, true);
            if (in_array($competitionId, $com_arr)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }

    }


    /**
     * 是否该提示
     * @param $uid
     * @return bool
     */
    public static function onlyNoticeMyInterest($uid)
    {
        if (!$uid) {
            return false;
        } else {

            if(!$userSetting = AdminUserSetting::getInstance()->where('user_id', $uid)->get()) {
                return false;
            } else {
                if (!json_decode($userSetting->notice, true)['only_notice_my_interest']) {
                    return false;
                } else {
                   return true;
                }
            }

        }
    }

    /**
     * 是否需要提示
     * @param $user_id
     * @param $match_id
     * @param $type
     * @return bool
     */
    public static function isNotice($user_id, $match_id, $type)
    {
        if (!$user_id || !$match_id) return true;
        if ($user_setting = AdminUserSetting::getInstance()->where('user_id', $user_id)->get()) {
            if (!$notice = json_decode($user_setting->notice, true)) {
                return true;
            } else {
                if (!$notice['only_notice_my_interest']) return true;
                //仅提示关注的比赛
                if (!$interest = AdminInterestMatches::getInstance()->where('uid', $user_id)->get()) {
                    return false;
                } else {

                    $match_ids = json_decode($interest->match_ids, true);
                    if (!in_array($match_id, $match_ids)) {
                        return false;
                    } else {
                        $bool = false;
                        switch ($type) {
                            case 1: //进球
                                if ($notice['goal']) $bool = true;
                                break;
                            case 10://开始
                                if ($notice['start']) $bool = true;
                                break;
                            case 12: //结束
                                if ($notice['over']) $bool = true;
                                break;

                            case 3: //黄牌
                                if ($notice['yellow_card']) $bool = true;
                                break;

                            case 4: //红牌

                                if ($notice['red_card']) $bool = true;
                                break;

                        }
                        return $bool;
                    }

                }
            }

        } else {
            return false;
        }

    }




    /**
     * 获取房间内fd
     * @param $match_id
     * @return array
     */
    public static function getUsersInRoom($match_id)
    {
        if (!$match_id || !AdminMatch::getInstance()->where('match_id')->get()) {
            return [];
        } else {
            RedisPool::invoke('redis', function(Redis $redis) use ($match_id, &$fd_arr) {
                $fd_arr = $redis->sMembers(sprintf(self::USERS_IN_ROOM, $match_id));
            });
            return $fd_arr;
        }
    }

    /**
     * 用户进入房间
     * @param $match_id
     * @param $fd
     * @return array
     */
    public static function userEnterRoom($match_id, $fd)
    {
        if (!$match_id || !AdminMatch::getInstance()->where('match_id')->get()) {
            return [];
        } else {
            RedisPool::invoke('redis', function(Redis $redis) use ($match_id, $fd) {
                $redis->sAdd(sprintf(self::USERS_IN_ROOM, $match_id), $fd);
            });
        }
    }

    /**
     * 用户退出房间
     * @param $match_id
     * @param $fd
     * @return bool
     */
    public static function userOutRoom($match_id, $fd)
    {
        if (!$match_id || !AdminMatch::getInstance()->where('match_id')->get()) {
            return false;
        } else {
            OnlineUser::getInstance()->update($fd, ['match_id' => 0]);
            RedisPool::invoke('redis', function(Redis $redis) use ($match_id, $fd) {
                $redis->sRem(sprintf(self::USERS_IN_ROOM, $match_id), $fd);
            });
            return true;
        }
    }

    /**
     * 获取正在进行中的比赛的进行时间
     * @param $match_id
     * @return int
     */


    public static function getPlayingTime($match_id)
    {
        $time = Cache::get('match_time_' . $match_id);
        return $time ?: 0;
    }

    public static function setPlayingTime($match_id, $score)
    {
        if (!$score) {
            return 0;
        }
        $status = $score[1];
        if ($status == 2) {//上半场
            $time = floor((time() - $score[4]) / 60 + 1);
        } else if ($status == 4) {//下半场
            $time = floor((time() - $score[4]) / 60 + 45 +1);
        } else {
            $time = 0;
        }
        Cache::set('match_time_' . $match_id, $time, 60 * 240);

        return $time;
    }


    /**
     * 用户关注比赛
     * @param $match_id
     * @param $uid
     * @return bool
     */
    public static function userDoInterestMatch($match_id, $uid)
    {
        if ($matchRes = AdminInterestMatches::getInstance()->where('uid', $uid)->get()) {
            $match_ids = json_decode($matchRes->match_ids, true);
            if ($match_ids) {
                if (in_array($match_id, $match_ids)) {
                    return true;
                }
                array_push($match_ids, $match_id);
                $update_match = json_encode($match_ids);
            } else {
                $update_match = json_encode([$match_id]);

            }
            $matchRes->match_ids = $update_match;
            RedisPool::invoke('redis', function(Redis $redis) use ($match_id, $uid) {
                $redis->sAdd(sprintf(self::USER_INTEREST_MATCH, $match_id), $uid);
            });
            if ($matchRes->update()) {
                return true;
            } else {
                return false;
            }
        } else {
            $insert = ['uid' => $uid, 'match_ids' => json_encode([$match_id])];
            if (AdminInterestMatches::getInstance()->insert($insert)) {

                return true;
            } else {
                return false;
            }
        }
    }


    /**
     * 用户取消关注比赛
     * @param $match_id
     * @param $uid
     * @return bool
     */
    public static function userDelInterestMatch($match_id, $uid)
    {
        if ($match = AdminInterestMatches::getInstance()->where('uid', $uid)->get()) {
            $match_ids = json_decode($match->match_ids, true);
            $data = [];
            foreach ($match_ids as $id) {
                if ($match_id == $id) {
                    continue;
                } else {
                    $data[] = $id;
                }
            }
            $match->match_ids = json_encode($data);
            RedisPool::invoke('redis', function(Redis $redis) use ($match_id, $uid) {
                $redis->sRem(sprintf(self::USER_INTEREST_MATCH, $match_id), $uid);
            });
            if ($match->update()) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public static function getMatchTeamName($match_id)
    {
        if ($match = AdminMatch::getInstance()->where('match_id', $match_id)->get()) {
            $home_name_zh = $match->homeTeamName()->name_zh;
            $away_name_zh = $match->awayTeamName()->name_zh;
            return [$home_name_zh, $away_name_zh];
        } else {
            return ['', ''];
        }
    }

    public static function getBasic($match_id)
    {
        if ($match = AdminMatch::getInstance()->where('match_id', $match_id)->get()) {
            $format = FrontService::handMatch([$match], 0, true);
            if (isset($format[0])) {
                return $format[0];
            } else {
                return [];
            }
        } else {
            return [];
        }
    }


    public static function changeArrToStr(array $arr)
    {
        if (!$arr) return '';
        $str = '(';
        foreach ($arr as $item) {
            $str .= $item . ',';
        }
        $str = rtrim($str, ',') . ')';
        return $str;
    }


    public static function getAlphaLiving($home_team_en, $away_team_en)
    {
        if (!$home_team_en || !$away_team_en) {
            return false;
        }
        $str_like = trim($home_team_en, ' FC') . '%' . trim($away_team_en, ' FC') . '%';
        return AdminAlphaMatch::getInstance()->where('teamsEn', $str_like, 'like')->get();
    }

    public static function getProvinceAndCityCode($province, $city)
    {
        $provinceCode = '';
        $cityCode = '';
        if ($province = AdminZoneList::getInstance()->where('name', $province . '%', 'like')->get()) {
            $provinceCode = $province->id;
        }
        if ($city = AdminZoneList::getInstance()->where('name', $city . '%', 'like')->get()) {
            $cityCode = $city->id;
        }
        return [$provinceCode, $cityCode];
    }


    public static function is_utf8($string)
    {
        return mb_detect_encoding($string, 'UTF-8') === 'UTF-8';
    }

    static function have_special_char($str)
    {
        $length = mb_strlen($str);
        $array = [];
        for ($i=0; $i<$length; $i++) {
            $array[] = mb_substr($str, $i, 1, 'utf-8');
            if( strlen($array[$i]) >= 4 ){
                return true;

            }
        }
        return false;
    }


    public static function redisSetStr($key, $value)
    {
        if (!$key || !$key) {
            return false;
        }
        RedisPool::invoke('redis', function(Redis $redis) use ($key, $value) {
           $redis->set($key, $value);
        });
        return true;
    }


    public static function redisGetKey($key)
    {
        if ($key) return false;
        RedisPool::invoke('redis', function(Redis $redis) use ($key, &$value) {
            $value = $redis->set($key, $value);
        });
        return $value;
    }

    public static function getPlayerSeasons($seasons)
    {


        if (!$seasons) return [];
        $data = [];
        foreach ($seasons as $season) {
            $season_info = AdminSeason::getInstance()->where('season_id', $season)->get();

            $competition_info = $season_info->getCompetition();
            $season_item = ['season_id' => $season_info->season_id, 'year' => $season_info->year];


            if (isset($data[$competition_info->competition_id])) {
                array_push($data[$competition_info->competition_id]['season_list'], $season_item);
            } else {
                $data[$competition_info->competition_id] = [
                    'competition_info' => ['competition_id' => $competition_info->competition_id, 'short_name_zh' => $competition_info->short_name_zh],
                    'season_list' => [$season_item],
                ];
            }


        }
        $return = [];
        if ($data) {
            foreach ($data as $datum) {
                $return[] = $datum;
            }
        }

        return $return;
    }

    public static function getAverageData($data, $match)
    {
        if (!$match || !$data) return '0';
        return number_format($data/$match,1);
    }

}
