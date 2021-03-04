<?php

namespace App\Model;

use App\Base\BaseModel;
use App\lib\Tool;
use App\Utility\Log\Log;
use EasySwoole\Mysqli\QueryBuilder;

class AdminUser extends BaseModel
{
    protected $tableName = "admin_user";

    const USER_TOKEN_KEY = 'user:token:%s';   //token

    const STATUS_BAN = 0; //封禁
    const STATUS_NORMAL = 1;   //正常
    const STATUS_REPORTED = 2; //被举报
    const STATUS_CANCEL = 3; //注销
    const STATUS_FORBIDDEN = 4; //禁言

    const STATUS_PRE_INIT = 1;      //用户信息审核状态
    public function findAll($page, $limit)
    {
        return $this->order('created_at', 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->all();
    }
    public function getLimit($page, $limit)
    {
        return $this->order('created_at', 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->withTotalCount();
    }

    /**
     * @param $id
     * @param $data
     * @return bool
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function saveIdData($id, $data)
    {
        return self::update($data, ['id' => $id]);
    }


    /**
     * 通过微信token以及openid获取用户信息
     * @param $access_token
     * @param $openId
     * @return bool|string
     */
    public function getWxUser($access_token , $openId)
    {
        $url = sprintf("https://api.weixin.qq.com/sns/userinfo?access_token=%s&openid=%s&lang=zh_CN", $access_token, $openId);

        return Tool::getInstance()->postApi($url);
    }



    /**
     * 某人的评论数
     * @return mixed
     */
    public function commentCount()
    {


        return $this->hasMany(AdminPostComment::class, function(QueryBuilder $queryBuilder) {
            $queryBuilder->where('status', AdminPostComment::STATUS_NORMAL);
        }, 'id', 'user_id');

    }

    /**
     * 收藏数
     */
    public function collectCount()
    {
        return $this->hasMany(AdminPostOperate::class, function(QueryBuilder $queryBuilder)  {
            $queryBuilder->where('action_type', AdminPostOperate::ACTION_TYPE_COLLECT);
            $queryBuilder->where('comment_id', 0);
        }, 'id', 'user_id');

    }

    /**
     * 我的发帖
     * @return mixed
     */
    public function postCount() {
        return $this->hasMany(AdminUserPost::class, function(QueryBuilder $queryBuilder) {
            $queryBuilder->where('status', AdminUserPost::STATUS_EXAMINE_SUCC);
        }, 'id', 'user_id');
    }

    /**
     *
     */
    public function userSetting()
    {
        return $this->hasOne(AdminUserSetting::class, null, 'id', 'user_id');
    }

    /**
     * 需要给用户展示的赛事id以及用户关注的比赛id
     * @param $uid
     * @return array
     * @throws \Throwable
     */
    public static function getUserShowCompetitionId($uid)
    {
        //默认赛事
        $recommandCompetitionId = AdminSysSettings::create()->where('sys_key', AdminSysSettings::COMPETITION_ARR)->get();
        $default = json_decode($recommandCompetitionId->sys_value, true);
        if (!$uid) return [$default, []];
        //用户关注赛事 与 比赛
        $resMatch = AdminInterestMatches::getInstance()->where('uid', $uid)->where('type', AdminInterestMatches::FOOTBALL_TYPE)->get();
        $interestMatchArr = isset($resMatch->match_ids) ? json_decode($resMatch->match_ids, true) : [];
        $resCompetition = AdminUserInterestCompetition::getInstance()->where('user_id', $uid)->where('type', AdminUserInterestCompetition::FOOTBALL_TYPE)->get();
        $userInterestCompetition = isset($resCompetition->competition_ids) ? json_decode($resCompetition->competition_ids, true) : [];

        if ($userInterestCompetition) {
            $selectCompetitionIdArr = array_intersect($default, $userInterestCompetition);
        } else {
            $selectCompetitionIdArr = $default;
        }
        return [array_values($selectCompetitionIdArr), $interestMatchArr];
    }

    /**
     * 需要给用户展示的篮球赛事id和用户关注的篮球比赛id
     * @param $uid
     * @param $type
     */
    public static function getUserShowBasketballCompetition($uid)
    {
        //默认赛事
        $recommandCompetitionId = AdminSysSettings::create()->where('sys_key', AdminSysSettings::BASKETBALL_COMPETITION)->get();
        $default = json_decode($recommandCompetitionId->sys_value, true);
        if (!$uid) return [$default, []];
        //用户关注赛事 与 比赛
        $resMatch = AdminInterestMatches::getInstance()->where('uid', $uid)->where('type', 2)->get();
        $interestMatchArr = isset($resMatch->match_ids) ? json_decode($resMatch->match_ids, true) : [];
        $resCompetition = AdminUserInterestCompetition::getInstance()->where('user_id', $uid)->where('type', 2)->get();
        $userInterestCompetition = isset($resCompetition->competition_ids) ? json_decode($resCompetition->competition_ids, true) : [];
        if ($userInterestCompetition) {
            $selectCompetitionIdArr = array_intersect($default, $userInterestCompetition);
        } else {
            $selectCompetitionIdArr = $default;
        }

        return [array_values($selectCompetitionIdArr), $interestMatchArr];
    }


}
