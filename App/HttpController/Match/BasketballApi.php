<?php
namespace App\HttpController\Match;

use App\Base\FrontUserController;
use App\Common\AppFunc;
use App\lib\FrontService;
use App\Model\AdminInterestMatches;
use App\Model\AdminInterestMatchesBak;
use App\Model\AdminMatch;
use App\Model\AdminMessage;
use App\Model\AdminSysSettings;
use App\Model\AdminUser;
use App\Model\AdminUserInterestCompetition;
use App\Model\BasketBallCompetition;
use App\Model\BasketballHonor;
use App\Model\BasketballMatch;
use App\Model\BasketballMatchSeason;
use App\Model\BasketballMatchTlive;
use App\Model\BasketballPlayer;
use App\Model\BasketballPlayerHonor;
use App\Model\BasketballSeasonAllStatsDetail;
use App\Model\BasketballSeasonList;
use App\Model\BasketballSeasonTable;
use App\Model\BasketballSquadList;
use App\Model\BasketballTeam;
use App\Model\ChatHistory;
use App\Utility\Message\Status;

use easySwoole\Cache\Cache;
use EasySwoole\HttpAnnotation\AnnotationController;
use EasySwoole\HttpAnnotation\AnnotationTag\Api;
use EasySwoole\HttpAnnotation\AnnotationTag\Param;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiDescription;
use EasySwoole\HttpAnnotation\AnnotationTag\Method;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiSuccess;
use function foo\func;

class BasketballApi extends FrontUserController
{
    protected $prefix_logo = 'https://cdn.sportnanoapi.com/basketball/player/';
    const STATUS_PLAYING = [2, 3, 4, 5, 6, 7, 8, 9];
    const STATUS_SCHEDULE = [1, 13, 15];
    const STATUS_RESULT= [10, 11, 12, 14];
    const PLAYER_POSITION = [
        'PG' => '控球后卫',
        'SG' => '得分后卫',
        'SF' => '小前锋',
        'PF' => '大前锋',
        'C' => '中锋',
    ];

    const PRE_PLAYER_LOGO = 'https://cdn.sportnanoapi.com/basketbal'; //球员logo前缀

    /**
     * 篮球进行中赛事
     * @Api(name="篮球进行中的比赛",path="/api/basketball/basketballMatchPlaying",version="3.0")
     * @ApiDescription(value="serverClient for basketballMatchPlaying")
     * @Method(allow="{GET}")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
        "list": [
        {
        "home_team_name": "飞鹰",
        "home_team_logo": "https://cdn.sportnanoapi.com/basketball/team/ca6066668837f1ac2ed35b0610de581a.png",
        "away_team_name": "红龙",
        "away_team_logo": "https://cdn.sportnanoapi.com/basketball/team/f597cd4db582121f59109d69ff6f2df3.png",
        "round": "",
        "competition_id": 3943,
        "competition_name": "中国金龙杯",
        "match_time": "14:30",
        "format_match_time": "2021-01-20 14:30",
        "user_num": 0,
        "match_id": 3581870,
        "is_start": true,
        "status_id": 8,
        "is_interest": false,
        "neutral": 0,
        "matching_time": null,
        "matching_info": null,
        "has_living": 0,
        "living_url": {
        "liveUrl": "",
        "liveUrl2": "",
        "liveUrl3": ""
        },
        "note": "",
        "home_scores": "[20,25,29,0,0]",
        "away_scores": "[22,18,23,0,0]",
        "coverage": "",
        "home_win": 0,
        "home_total": 74,
        "away_total": 63
        },
        {
        "home_team_name": "浙江广厦控股",
        "home_team_logo": "https://cdn.sportnanoapi.com/basketball/team/60c4a3d0b39590e364621b75264f7eda.png",
        "away_team_name": "天津先行者",
        "away_team_logo": "https://cdn.sportnanoapi.com/basketball/team/64f38bbbe7544057f748716ddd8b90c2.png",
        "round": "",
        "competition_id": 3,
        "competition_name": "中国男子篮球联赛",
        "match_time": "12:30",
        "format_match_time": "2021-01-24 12:30",
        "user_num": 0,
        "match_id": 3570012,
        "is_start": true,
        "status_id": 4,
        "is_interest": false,
        "neutral": 0,
        "matching_time": null,
        "matching_info": null,
        "has_living": 0,
        "living_url": {
        "liveUrl": "",
        "liveUrl2": "",
        "liveUrl3": ""
        },
        "note": "",
        "home_scores": "[33,18,0,0,0]",
        "away_scores": "[32,6,0,0,0]",
        "coverage": "",
        "home_win": 0,
        "home_total": 51,
        "away_total": 38
        }
        ],
        "user_interest_count": 0
        }
        })
     */
    public function basketballMatchPlaying() :bool
    {

        $uid = isset($this->auth['id']) ? (int)$this->auth['id'] : 0;
        list($selectCompetitionIdArr, $interestMatchArr) = AdminUser::getUserShowBasketballCompetition($uid);

        $response = ['list' => [], 'user_interest_count' => count($interestMatchArr)];
        if (!$selectCompetitionIdArr)   return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $response);

        $playingMatch = BasketballMatch::create()->where('is_delete', 0)
            ->where('competition_id', $selectCompetitionIdArr, 'in')
            ->where('status_id', self::STATUS_PLAYING, 'in')
            ->order('match_time', 'ASC')
            ->all();

        $formatMatch = FrontService::formatBasketballMatch($playingMatch, $uid, $interestMatchArr);

        $return = ['list' => $formatMatch, 'user_interest_count' => count($interestMatchArr)];

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);
    }

    /**
     * 篮球赛程比赛
     * @Api(name="篮球赛程比赛",path="/api/basketball/basketballMatchSchedule",version="3.0")
     * @ApiDescription(value="serverClient for basketballMatchSchedule")
     * @Method(allow="{GET}")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
        "list": [
        {
        "home_team_name": "夏洛特黄蜂",
        "home_team_logo": "https://cdn.sportnanoapi.com/basketball/team/f99033ffbcfb4632a135cd022e257644.png",
        "away_team_name": "华盛顿奇才",
        "away_team_logo": "https://cdn.sportnanoapi.com/basketball/team/5430ebfd49544f9ea8959cb7847c8103.png",
        "round": "",
        "competition_id": 1,
        "competition_name": "美国男子职业篮球联赛",
        "match_time": "08:00",
        "format_match_time": "2021-01-21 08:00",
        "user_num": 0,
        "match_id": 3574777,
        "is_start": false,
        "status_id": 13,
        "is_interest": false,
        "neutral": 0,
        "matching_time": null,
        "matching_info": null,
        "has_living": 0,
        "living_url": {
        "liveUrl": "",
        "liveUrl2": "",
        "liveUrl3": ""
        },
        "note": "",
        "home_scores": "[0,0,0,0,0]",
        "away_scores": "[0,0,0,0,0]",
        "coverage": "",
        "home_win": 0,
        "home_total": 0,
        "away_total": 0
        }
        ],
        "count": 16
        }
        })
     */
    public function basketballMatchSchedule() :bool
    {

        $uid = isset($this->auth['id']) ? (int)$this->auth['id'] : 0;
        //需要展示的赛事id 以及用户关注的比赛
        list($selectCompetitionIdArr, $interestMatchArr) = AdminUser::getUserShowBasketballCompetition($uid);
        if (!$selectCompetitionIdArr) {
            $response = ['list' => [], 'count' => []];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $response);
        }
        $page = isset($this->params['page']) ? (int)$this->params['page'] : 1;
        $limit = isset($this->params['size']) ? (int)$this->params['size'] : 20;
        if ($this->params['time'] == date('Y-m-d')) {
            $is_today = true;
        } else {
            $is_today = false;
        }
        $start = strtotime($this->params['time']);
        $end = $start + 60 * 60 * 24;
        $model = BasketballMatch::getInstance()->where('status_id', self::STATUS_SCHEDULE, 'in')
            ->where('match_time', $is_today ? time() : $start, '>=')->where('match_time', $end, '<')
            ->where('is_delete', 0)
            ->where('competition_id', $selectCompetitionIdArr, 'in')
            ->order('match_time', 'ASC')->limit(($page - 1) * $limit, $limit)->withTotalCount();
        $list = $model->all(null);
        $total = $model->lastQueryResult()->getTotalCount();
        $formatMatch = FrontService::formatBasketballMatch($list, $uid, $interestMatchArr);
        $return = ['list' => $formatMatch, 'count' => $total];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);
    }

    /**
     * 篮球赛果比赛
     * @Api(name="篮球赛程比赛",path="/api/basketball/basketballMatchResult",version="3.0")
     * @ApiDescription(value="serverClient for basketballMatchResult")
     * @Method(allow="{GET}")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
        "list": [
        {
        "home_team_name": "夏洛特黄蜂",
        "home_team_logo": "https://cdn.sportnanoapi.com/basketball/team/f99033ffbcfb4632a135cd022e257644.png",
        "away_team_name": "华盛顿奇才",
        "away_team_logo": "https://cdn.sportnanoapi.com/basketball/team/5430ebfd49544f9ea8959cb7847c8103.png",
        "round": "",
        "competition_id": 1,
        "competition_name": "美国男子职业篮球联赛",
        "match_time": "08:00",
        "format_match_time": "2021-01-21 08:00",
        "user_num": 0,
        "match_id": 3574777,
        "is_start": false,
        "status_id": 13,
        "is_interest": false,
        "neutral": 0,
        "matching_time": null,
        "matching_info": null,
        "has_living": 0,
        "living_url": {
        "liveUrl": "",
        "liveUrl2": "",
        "liveUrl3": ""
        },
        "note": "",
        "home_scores": "[0,0,0,0,0]",
        "away_scores": "[0,0,0,0,0]",
        "coverage": "",
        "home_win": 0,
        "home_total": 0,
        "away_total": 0
        }
        ],
        "count": 16
        }
        })
     */
    public function basketballMatchResult() :bool
    {
        if (!isset($this->params['time'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        }
        $uid = isset($this->auth['id']) ? (int)$this->auth['id'] : 0;
        //需要展示的赛事id 以及用户关注的比赛
        list($selectCompetitionIdArr, $interestMatchArr) = AdminUser::getUserShowBasketballCompetition($uid);
        if (!$selectCompetitionIdArr) {
            $response = ['list' => [], 'total' => 0];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $response);
        }

        $page = isset($this->params['page']) ? (int)$this->params['page'] : 1;
        $size = isset($this->params['size']) ? (int)$this->params['size'] : 20;
        $start = strtotime($this->params['time']);

        $end = $start + 60 * 60 * 24;
        $matches = BasketballMatch::getInstance()
            ->where('match_time', $start, '>=')
            ->where('match_time', $end, '<')
            ->where('status_id', self::STATUS_RESULT, 'in')
            ->where('competition_id', $selectCompetitionIdArr, 'in')
            ->where('is_delete', 0)
            ->order('match_time', 'DESC')->getLimit($page, $size);
        $list = $matches->all(null);
        $total = $matches->lastQueryResult()->getTotalCount();

        $formatMatch = FrontService::formatBasketballMatch($list, $uid, $interestMatchArr);
        $return = ['list' => $formatMatch, 'count' => $total];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

    }

    /**
     * 用户关注比赛列表
     * @Api(name="篮球进行中的比赛",path="/api/basketball/basketballMatchPlaying",version="3.0")
     * @ApiDescription(value="serverClient for basketballMatchPlaying")
     * @Method(allow="{GET}")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
        "list": [
        {
        "home_team_name": "飞鹰",
        "home_team_logo": "https://cdn.sportnanoapi.com/basketball/team/ca6066668837f1ac2ed35b0610de581a.png",
        "away_team_name": "红龙",
        "away_team_logo": "https://cdn.sportnanoapi.com/basketball/team/f597cd4db582121f59109d69ff6f2df3.png",
        "round": "",
        "competition_id": 3943,
        "competition_name": "中国金龙杯",
        "match_time": "14:30",
        "format_match_time": "2021-01-20 14:30",
        "user_num": 0,
        "match_id": 3581870,
        "is_start": true,
        "status_id": 8,
        "is_interest": false,
        "neutral": 0,
        "matching_time": null,
        "matching_info": null,
        "has_living": 0,
        "living_url": {
        "liveUrl": "",
        "liveUrl2": "",
        "liveUrl3": ""
        },
        "note": "",
        "home_scores": "[20,25,29,0,0]",
        "away_scores": "[22,18,23,0,0]",
        "coverage": "",
        "home_win": 0,
        "home_total": 74,
        "away_total": 63
        },
        {
        "home_team_name": "浙江广厦控股",
        "home_team_logo": "https://cdn.sportnanoapi.com/basketball/team/60c4a3d0b39590e364621b75264f7eda.png",
        "away_team_name": "天津先行者",
        "away_team_logo": "https://cdn.sportnanoapi.com/basketball/team/64f38bbbe7544057f748716ddd8b90c2.png",
        "round": "",
        "competition_id": 3,
        "competition_name": "中国男子篮球联赛",
        "match_time": "12:30",
        "format_match_time": "2021-01-24 12:30",
        "user_num": 0,
        "match_id": 3570012,
        "is_start": true,
        "status_id": 4,
        "is_interest": false,
        "neutral": 0,
        "matching_time": null,
        "matching_info": null,
        "has_living": 0,
        "living_url": {
        "liveUrl": "",
        "liveUrl2": "",
        "liveUrl3": ""
        },
        "note": "",
        "home_scores": "[33,18,0,0,0]",
        "away_scores": "[32,6,0,0,0]",
        "coverage": "",
        "home_win": 0,
        "home_total": 51,
        "away_total": 38
        }
        ],
        "count": 0
        }
        })
     */
    public function basketballMatchInterest() :bool
    {
        if (!$this->auth['id']) {
            return $this->writeJson(Status::CODE_VERIFY_ERR, '登陆令牌缺失或者已过期');
        }
        $res = AdminInterestMatches::getInstance()->where('uid', $this->auth['id'])->where('type', AdminInterestMatches::BASKETBALL_TYPE)->get();
        $matchIds = isset($res->match_ids) ? json_decode($res->match_ids, true) : [];
        if (!$matchIds) return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);
        $matches = BasketballMatch::getInstance()->where('match_id', $matchIds, 'in')->order('match_time', 'ASC')->all();
        $data = FrontService::formatBasketballMatch($matches, $this->auth['id'], $matchIds);
        $count = count($data);
        $response = ['list' => $data, 'count' => $count];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $response);
    }

    /**
     * 今日所有篮球比赛列表
     * @Api(name="今日所有篮球比赛列表",path="/api/basketball/basketballMatchToday",version="3.0")
     * @ApiDescription(value="serverClient for basketballMatchToday")
     * @Method(allow="{GET}")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
        "list": [
        {
        "home_team_name": "夏洛特黄蜂",
        "home_team_logo": "https://cdn.sportnanoapi.com/basketball/team/f99033ffbcfb4632a135cd022e257644.png",
        "away_team_name": "华盛顿奇才",
        "away_team_logo": "https://cdn.sportnanoapi.com/basketball/team/5430ebfd49544f9ea8959cb7847c8103.png",
        "round": "",
        "competition_id": 1,
        "competition_name": "美国男子职业篮球联赛",
        "match_time": "08:00",
        "format_match_time": "2021-01-21 08:00",
        "user_num": 0,
        "match_id": 3574777,
        "is_start": false,
        "status_id": 13,
        "is_interest": false,
        "neutral": 0,
        "matching_time": null,
        "matching_info": null,
        "has_living": 0,
        "living_url": {
        "liveUrl": "",
        "liveUrl2": "",
        "liveUrl3": ""
        },
        "note": "",
        "home_scores": "[0,0,0,0,0]",
        "away_scores": "[0,0,0,0,0]",
        "coverage": "",
        "home_win": 0,
        "home_total": 0,
        "away_total": 0
        }
        ],
        "count": 16
        }
        })
     */
    public function basketballMatchToday() :bool
    {
        //| ------ | ------------------------------------------------------------------------------
        //| 0      | 比赛异常，说明：暂未判断具体原因的异常比赛，可能但不限于：腰斩、取消等等，建议隐藏处理
        //| 1      | 未开赛
        //| 2      | 第一节
        //| 3      | 第一节完
        //| 4      | 第二节
        //| 5      | 第二节完
        //| 6      | 第三节
        //| 7      | 第三节完
        //| 8      | 第四节
        //| 9      | 加时
        //| 10     | 完场
        //| 11     | 中断
        //| 12     | 取消
        //| 13     | 延期
        //| 14     | 腰斩
        //| 15     | 待定
        $start = strtotime(date('Y-m-d'));
        $end = $start + 60 * 60 * 24;
        $order = 'CASE WHEN `status_id`=9 Then 1 ';  //加时
        $order .= 'WHEN `status_id`=8 Then 2 '; //第四节
        $order .= 'WHEN `status_id`=7 Then 3 '; //第三节完
        $order .= 'WHEN `status_id`=6 Then 4 '; //第三节
        $order .= 'WHEN `status_id`=5 Then 5 '; //第二节完
        $order .= 'WHEN `status_id`=4 Then 6 '; //第二节
        $order .= 'WHEN `status_id`=3 Then 7 '; //第一节完
        $order .= 'WHEN `status_id`=2 Then 8 '; //第一节
        $order .= 'WHEN `status_id`=1 Then 9 '; //完场
        $order .= 'WHEN `status_id`=10 Then 10 '; //未开赛
        $order .= 'WHEN `status_id`=11 Then 11 '; //中断
        $order .= 'WHEN `status_id`=12 Then 12 '; //取消
//        $order .= 'WHEN `status_id`=13 Then 13 '; //延期
        $order .= 'WHEN `status_id`=14 Then 14 '; //腰斩
        $order .= 'WHEN `status_id`=15 Then 15 ELSE 16 END'; //待定

        $page = !empty($this->params['page']) ? intval($this->params['page']) : 1;
        $size = !empty($this->params['size']) ? (int)$this->params['size'] : 15;
        $userId = !empty($this->auth['id']) ? (int)$this->auth['id'] : 0;

        list($selectCompetitionIdArr, $interestMatchArr) = AdminUser::getUserShowBasketballCompetition($userId);
        if (!$selectCompetitionIdArr) return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => [], 'count' => 0, 'user_interest_count' => count($interestMatchArr)]);

        $todayMatch = BasketballMatch::getInstance()->where('match_time', $start, '>=')
            ->where('competition_id', $selectCompetitionIdArr, 'in')
            ->where('status_id', 0, '<>')
            ->where('is_delete', 0)
            ->where('match_time', $end, '<')->order($order, 'ASC')
            ->order('match_time', 'ASC')
            ->page($page, $size)->withTotalCount();
        $list = $todayMatch->all(null);
        $total = $todayMatch->lastQueryResult()->getTotalCount();
        $formatTodayMatch = FrontService::formatBasketballMatch($list, $userId, $interestMatchArr);

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => $formatTodayMatch, 'count' => $total, 'user_interest_count' => count($interestMatchArr)]);

    }

    /**
     * 推荐赛事列表
     * @Api(name="推荐赛事列表",path="/api/basketball/getRecommendCompetition",version="3.0")
     * @ApiDescription(value="serverClient for getRecommendCompetition")
     * @Method(allow="{GET}")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": [
        {
        "short_name_zh": "NBA",
        "logo": "https://cdn.sportnanoapi.com/basketball/competition/aa6ac10ab514aba38a86c57d34e64f31.jpg",
        "competition_id": 1
        },
        {
        "short_name_zh": "WNBA",
        "logo": "https://cdn.sportnanoapi.com/basketball/competition/86f522333da4c3e2c144996fc4d2520b.png",
        "competition_id": 2
        },
        {
        "short_name_zh": "CBA",
        "logo": "https://cdn.sportnanoapi.com/basketball/competition/4bcdfa94d226fd5d7c740b463c182aa0.jpg",
        "competition_id": 3
        },
        {
        "short_name_zh": "NBL",
        "logo": "https://cdn.sportnanoapi.com/basketball/competition/697d591130d4536044eeb4b45ce225cd.png",
        "competition_id": 4
        },
        {
        "short_name_zh": "金龙杯",
        "logo": "",
        "competition_id": 3943
        }
        ]
        })
     */
    public function getRecommendCompetition() :bool
    {
        $recommandCompetitionId = AdminSysSettings::create()->where('sys_key', AdminSysSettings::JSON_BASKETBALL_COMPETITION)->get();
        $userId = !empty($this->auth['id']) ? (int)$this->auth['id'] : 0;
        if (!$userInterestCompetition = AdminUserInterestCompetition::getInstance()->where('user_id', $userId)->where('type', 2)->get()) {
            $userInterestCompetition = [];
        } else {
            $userInterestCompetition = json_decode($userInterestCompetition->competition_ids, true);
        }
        if ($default = json_decode($recommandCompetitionId->sys_value, true)) {
            foreach ($default as $k => $item) {
                if (!$item) continue;
                foreach ($item as $ck => $competitionItem) {
                    if (in_array($competitionItem['competition_id'], $userInterestCompetition)) {
                        $default[$k][$ck]['is_notice'] = true;
                    } else {
                        $default[$k][$ck]['is_notice'] = false;

                    }
                }
            }
        } else {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);

        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $default);

    }

    /**
     * 篮球或者洲际比赛列表
     * @Api(name="篮球或者洲际比赛列表",path="/api/basketball/getCountryCompetition",version="3.0")
     * @ApiDescription(value="serverClient for getCountryCompetition")
     * @Param(name="country_id",type="int",required="",description="国家id")
     * @Param(name="category_id",type="int",required="",description="洲id")
     * @Method(allow="{GET}")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": [
        {
        "competition_id": 14,
        "logo": "https://cdn.sportnanoapi.com/basketball/competition/0e65ef8add56e45233176ae28c5aec53.png",
        "name_zh": "英国篮球超级联赛",
        "short_name_zh": "英篮超"
        }
        ]
        })
     */
    public function getCountryCompetition() :bool
    {
        $countryId = !empty($this->params['country_id']) ? (int)$this->params['country_id'] : 0;
        $categoryId = !empty($this->params['category_id']) ? (int)$this->params['category_id'] : 0;
        if (!$countryId && !$categoryId) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        }

        $competitionModel = BasketBallCompetition::getInstance();
        if ($categoryId) {
            $competitionModel = $competitionModel->where('category_id', $categoryId);
        }
        if ($countryId) {
            $competitionModel = $competitionModel->where('country_id', $countryId);
        }
        $competitionModel = $competitionModel->field(['competition_id', 'logo', 'name_zh', 'short_name_zh'])->all();
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $competitionModel);
    }

    /**
     * 球员详情
     * @Api(name="球员详情",path="/api/basketball/getPlayerInfo",version="3.0")
     * @ApiDescription(value="serverClient for getPlayerInfo")
     * @Param(name="type",type="int",required="",description="查询数据类型 1基本资料 2数据统计")
     * @Param(name="player_id",type="int",required="",description="球员id")
     * @Method(allow="{GET}")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
        "player_id": 10517,
        "logo": "c04a517cd18abe2ce9384d7acacad52a.png",
        "short_name_zh": "詹姆斯",
        "name_zh": "勒布朗·詹姆斯",
        "team_info": {
        "team_id": 10149,
        "name_zh": "洛杉矶湖人",
        "short_name_zh": "湖人",
        "logo": "https://cdn.sportnanoapi.com/basketball/team/c2abc5f988be409792d1f7bbc8c9c7ba.png"
        },
        "height": 206,
        "weight": 113,
        "age": 36,
        "birthday": "1984-12-30",
        "salary": "3,922万",
        "position": "小前锋",
        "honorList": [
        {
        "honor": {
        "id": 1,
        "title_zh": "NBA最佳第三阵容"
        },
        "season": "2019",
        "team_id": 10149,
        "competition_id": 1
        }        ]
        }
        })
     */
    public function getPlayerInfo() :bool
    {
        $playerId = !empty($this->params['player_id']) ? (int)$this->params['player_id'] : 0;
        if (!$playerId) return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        if ($player = BasketballPlayer::getInstance()->where('player_id', $playerId)->get()) {
            $type = !empty($this->params['type']) ? (int)$this->params['type'] : 1; //1基本资料 2数据统计 3数据对比
            if ($type == 1) {//基本资料
                if ($playerHonorRes = BasketballPlayerHonor::getInstance()->where('player_id', $player->player_id)->get()) {
                    $playerHonor = json_decode($playerHonorRes->honors, true);
                } else {
                    $playerHonor = [];
                }
                $basic = [
                    'player_id' => $player->player_id,
                    'logo' => $player->logo,
                    'short_name_zh' => $player->short_name_zh,
                    'name_zh' => $player->name_zh,
                    'team_info' => $player->teamInfo(),
                    'height' => $player->height, //cm
                    'weight' => $player->weight, //kg
                    'age' => $player->age,
                    'birthday' => date('Y-m-d', $player->birthday),
                    'salary' => AppFunc::changeToWan($player->salary),
                    'position' => self::PLAYER_POSITION[$player->position],
                    'honorList' => $playerHonor
                ];

                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $basic);
            } else if ($type == 2) { //数据统计

                if ($seasonList = $player->getSeasonList()) {
                    $seasonIds = array_column($seasonList, 'season_id');
                    //重新映射，减少查询
                    foreach ($seasonList as $seasonItem) {
                        $sortSeasonList[$seasonItem['season_id']] = $seasonItem;
                    }
                } else {
                    return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);
                }

                if ($teamList = $player->getTeamList()) {
                    foreach ($teamList as $itemTeam) {
                        $sortTeamList[$itemTeam['team_id']] = $itemTeam;
                    }

                }
                if (!isset($sortSeasonList)) return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);
                $res = BasketballSeasonAllStatsDetail::getInstance()->where('season_id', $seasonIds, 'in')->all();
                $wholeSeason = $preliminary = $group = $preSeason = $regular = $playoff = [];
                foreach ($res as $item) {
                    $year = $sortSeasonList[$item['season_id']]['year'];

                    if ($player_stats = json_decode($item['player_stats'], true)) {
                        foreach ($player_stats as $player_stat) {
                            $player_stat['year'] = $year;
                            $player_stat['team_short_name_zh'] = !empty($sortTeamList[$player_stat['team_id']]['short_name_zh']) ? $sortTeamList[$player_stat['team_id']]['short_name_zh']  : '';
                            if ($player_stat['player_id'] != $player->player_id) continue;
                            switch ($player_stat['scope']) {
                                case 1:
                                    $wholeSeason[] = $player_stat;
                                    break;
                                case 2:
                                    $preliminary[] = $player_stat;
                                    break;
                                case 3:
                                    $group[] = $player_stat;
                                    break;
                                case 4:
                                    $preSeason[] = $player_stat;
                                    break;
                                case 5:
                                    $regular[] = $player_stat;
                                    break;
                                case 6:
                                    $playoff[] = $player_stat;
                                    break;
                            }
                        }
                    }
                }
                $return = [
                    'wholeSeason' => $wholeSeason,  //全赛季
                    'preliminary' => $preliminary, //预选赛
                    'group' => $group, //小组赛
                    'preSeason' => $preSeason, //季前赛
                    'regular' => $regular, //常规赛
                    'playoff' => $playoff //季后赛
                ];
                return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES], $return);
            }

        } else {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
        }


    }


    /**
     * 球队详情
     * @Api(name="球队详情",path="/api/basketball/teamInfo",version="3.0")
     * @ApiDescription(value="serverClient for teamInfo")
     * @Method(allow="{GET}")
     * @Param(name="team_id",type="int",required="",description="球队id")
     * @Param(name="type",type="int",required="",description="数据类型 1基本信息 2积分 3赛季赛程 4数据 5阵容")
     * @ApiSuccess({"code":0,"msg":"验证码以发送至尾号0962手机","data":72})
     */
    public function teamInfo() :bool
    {

        $teamId = isset($this->params['team_id']) ? (int)$this->params['team_id'] : 0;
        $type = isset($this->params['type']) ? (int)$this->params['type'] : 1;
        if (!$teamId || !$type) return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        if (!$team = BasketballTeam::getInstance()->where('team_id', $teamId)->get()) {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
        }
        $seasonList = $team->competitionInfo()->getSeasonList();
        $basic = [
            'name_zh' => $team['name_zh'],
            'short_name_zh' => $team['short_name_zh'],
            'logo' => $team['logo'],
            'seasonList' => $seasonList
        ];

        switch ($type) {
            case 1: //基本信息
                $selectSeasonId = end($seasonList)['season_id'];
                $teamRankInfo = $teamRankInfos = [];
                if ($seasonTable = BasketballSeasonTable::getInstance()->where('season_id', $selectSeasonId)->get()) {
                    $table = json_decode($seasonTable->tables, true);
                    foreach ($table as $tableItem) {
                        if ($tableItem['scope'] != 5) {
                            continue;
                        }
                        $rows = $tableItem['rows'];
                        foreach ($rows as $row) {
                            if ($row['team_id'] == $teamId) {
                                $teamRankInfo['info'] = $row;
                                $teamRankInfo['describe'] = ['scope' => $tableItem['scope'], 'name' => $tableItem['name']];
                                break;

                            }

                        }
                    }
                }
                $basic['teamRank'] = $teamRankInfo;
                return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES], $basic);

                break;
            case 2: //积分 ，只有常规赛
                $selectSeasonId = !empty($this->params['select_season_id']) ? (int)$this->params['select_season_id'] :end($seasonList)['season_id'];
                $sortTable = [];
                if ($seasonTable = BasketballSeasonTable::getInstance()->where('season_id', $selectSeasonId)->get()) {
                    $table = json_decode($seasonTable->tables, true);
                    foreach ($table as $tableItem) {
                        if ($tableItem['scope'] == 5) {
                            $sortTable[] = $tableItem;
                        }

                    }
                }
                return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES], $sortTable);

                break;
            case 3://赛季赛程
                $selectSeasonId = end($seasonList)['season_id'];
                if (!$seasonMatchList = BasketballMatchSeason::getInstance()->where('season_id', $selectSeasonId)->where('(home_team_id=' . $teamId . ' or away_team_id=' . $teamId . ')')->all()) {
                    return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM], []);
                } else {
                    $matchList = FrontService::formatBasketballMatch($seasonMatchList, 0, []);
                }
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $matchList);
                break;
            case 4: //数据
                $selectSeasonId = !empty($this->params['select_season_id']) ? (int)$this->params['select_season_id'] :end($seasonList)['season_id'];
                $scope = !empty($this->params['scope']) ? (int)$this->params['scope'] : 5; //常规赛
                $formatTeamStat = $formatPlayerStats = $return =[];
                if ($res = BasketballSeasonAllStatsDetail::getInstance()->where('season_id', $selectSeasonId)->get()) {
                    $teamStats = json_decode($res->team_stats, true);

                    foreach ($teamStats as $teamStat) {
                        if ($teamStat['scope'] == $scope && $teamStat['team_id'] == $teamId) {
                            $formatTeamStat = $teamStat;
                        }
                    }

                    $playerStats = json_decode($res->player_stats, true);
                    //球员映射图
                    $players = BasketballPlayer::getInstance()->where('team_id', $teamId)->all();
                    foreach ($players as $player) {
                        $playersMap[$player->player_id] = ['player_id' => $player->player_id, 'logo' => $player->logo, 'short_name_zh' => $player->short_name_zh, 'name_zh' => $player->name_zh];
                    }
                    if (!isset($playersMap)) {
                        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);

                    }
                    foreach ($playerStats as $playerStat) {
                        $playerStat['player_info'] = $playersMap[$playerStat['player_id']];
                        $playerStat['point_per_match'] = number_format($playerStat['point']/$playerStat['matches'], 1);
                        $playerStat['rebounds_per_match'] = number_format($playerStat['rebounds']/$playerStat['matches'], 1);
                        $playerStat['assists_per_match'] = number_format($playerStat['assists']/$playerStat['matches'], 1);
                        $playerStat['steals_per_match'] = number_format($playerStat['steals']/$playerStat['matches'], 1);
                        $playerStat['blocks_per_match'] = number_format($playerStat['blocks']/$playerStat['matches'], 1);
                        $playerStat['turnovers_per_match'] = number_format($playerStat['turnovers']/$playerStat['matches'], 1);
                        $playerStat['personal_fouls_per_match'] = number_format($playerStat['personal_fouls']/$playerStat['matches'], 1);
                        if ($playerStat['scope'] == $scope && $playerStat['team_id'] == $teamId) {
                            $formatPlayerStats[] = $playerStat;
                        }
                    }


                    //最佳球员 得分 篮板 助攻 抢断 盖帽
                    $pointPerMatch  = array_column($formatPlayerStats, 'point_per_match');
                    $maxPointPerMatchKey = array_search(max($pointPerMatch), $pointPerMatch);

                    $reboundsPerMatch  = array_column($formatPlayerStats, 'rebounds_per_match');
                    $maxReboundPerMatchKey = array_search(max($reboundsPerMatch), $reboundsPerMatch);

                    $assistsPerMatch  = array_column($formatPlayerStats, 'assists_per_match');
                    $maxAssistsPerMatchKey = array_search(max($assistsPerMatch), $assistsPerMatch);

                    $stealsPerMatch  = array_column($formatPlayerStats, 'steals_per_match');
                    $maxStealsPerMatchKey = array_search(max($stealsPerMatch), $stealsPerMatch);

                    $blocksPerMatch  = array_column($formatPlayerStats, 'blocks_per_match');
                    $maxBlocksPerMatchKey = array_search(max($blocksPerMatch), $blocksPerMatch);
                    //场均得分 篮板 抢断 助攻 盖帽第一的球员
                    $formatTableRank['maxPointPerMatch'] = ['player_info' => $playersMap[$formatPlayerStats[$maxPointPerMatchKey]['player_id']], 'data' => $formatPlayerStats[$maxPointPerMatchKey]['point_per_match']];
                    $formatTableRank['maxReboundPerMatch'] = ['player_info' => $playersMap[$formatPlayerStats[$maxReboundPerMatchKey]['player_id']], 'data' => $formatPlayerStats[$maxReboundPerMatchKey]['rebounds_per_match']];
                    $formatTableRank['maxAssistPerMatch'] = ['player_info' => $playersMap[$formatPlayerStats[$maxAssistsPerMatchKey]['player_id']], 'data' => $formatPlayerStats[$maxAssistsPerMatchKey]['assists_per_match']];
                    $formatTableRank['maxStealsPerMatch'] = ['player_info' => $playersMap[$formatPlayerStats[$maxStealsPerMatchKey]['player_id']], 'data' => $formatPlayerStats[$maxStealsPerMatchKey]['steals_per_match']];
                    $formatTableRank['maxBlocksPerMatch'] = ['player_info' => $playersMap[$formatPlayerStats[$maxBlocksPerMatchKey]['player_id']], 'data' => $formatPlayerStats[$maxBlocksPerMatchKey]['blocks_per_match']];
                    //所有球员的数据排名
                    //场均得分
                    $pointKey = array_column($formatPlayerStats,'point_per_match');
                    array_multisort($pointKey,SORT_DESC,$formatPlayerStats);
                    $pointPerMatchRank = $formatPlayerStats;

                    //场均篮板
                    $reboundsKey = array_column($formatPlayerStats,'rebounds_per_match');
                    array_multisort($reboundsKey,SORT_DESC,$formatPlayerStats);
                    $reboundsPerMatchRank = $formatPlayerStats;

                    //场均助攻
                    $assistsKey = array_column($formatPlayerStats,'assists_per_match');
                    array_multisort($assistsKey,SORT_DESC,$formatPlayerStats);
                    $assistsPerMatchRank = $formatPlayerStats;

                    //场均抢断
                    $stealsKey = array_column($formatPlayerStats,'steals_per_match');
                    array_multisort($stealsKey,SORT_DESC,$formatPlayerStats);
                    $stealsPerMatchRank = $formatPlayerStats;

                    //场均封盖
                    $blocksKey = array_column($formatPlayerStats,'blocks_per_match');
                    array_multisort($blocksKey,SORT_DESC,$formatPlayerStats);
                    $blocksPerMatch = $formatPlayerStats;

                    //场均失误
                    $turnoversKey = array_column($formatPlayerStats,'turnovers_per_match');
                    array_multisort($turnoversKey,SORT_DESC,$formatPlayerStats);
                    $turnoversPerMatch = $formatPlayerStats;

                    //场均犯规
                    $personFoulsKey = array_column($formatPlayerStats,'personal_fouls_per_match');
                    array_multisort($personFoulsKey,SORT_DESC,$formatPlayerStats);
                    $personFoulsPerMatch = $formatPlayerStats;
                    $totalTable = [
                        'pointPerMatchRank' => $pointPerMatchRank,
                        'reboundsPerMatchRank' => $reboundsPerMatchRank,
                        'assistsPerMatchRank' => $assistsPerMatchRank,
                        'stealsPerMatchRank' => $stealsPerMatchRank,
                        'blocksPerMatch' => $blocksPerMatch,
                        'turnoversPerMatch' => $turnoversPerMatch,
                        'personFoulsPerMatch' => $personFoulsPerMatch,
                        ];

                    $return = [
                        'teamStats' => $formatTeamStat,
                        'formatTableRank' => $formatTableRank,
                        'totalTable' => $totalTable
                    ];

                }
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

                break;
            case 5://阵容
                $formatSquad = [];
                if ($squadRes = BasketballSquadList::getInstance()->where('team_id', $teamId)->get()) {
                    $squad = json_decode($squadRes->squad, true);
                    //球队映射图
                    $playerIds = array_column(array_column($squad, 'player'), 'id');
                    $playersMap = BasketballPlayer::getInstance()->where('player_id', $playerIds, 'in')->all();
                    foreach ($playersMap as $playerItem) {
                        $formatPlayersMap[$playerItem['player_id']] = $playerItem->toArray();
                    }

                    $formatSquad = [];
                    array_walk($squad, function ($v, $k) use (&$formatPlayersMap, &$formatSquad) {
                        $v['player']['logo'] = self::PRE_PLAYER_LOGO . $formatPlayersMap[$v['player']['id']]['logo'];
                        $formatSquad[] = $v;
                    });

                }
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $formatSquad);
                break;
        }
    }

    public function getMatchInfo()
    {

        $type = isset($this->params['type']) ? (int)$this->params['type'] : 1;
        if (!$matchId = (int)$this->params['match_id']) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        }
        if (!$match = BasketballMatchSeason::getInstance()->where('match_id', $matchId)->get()) {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
        }
        $userId = isset($this->auth['id']) ? (int)$this->auth['id'] : 0;

        switch ($type) {
            case 1: //直播 比赛详情
                if ($interetRes = AdminInterestMatches::getInstance()->where('type', 2)->where('uid', $userId)->get()) {
                    $userInterestMatch = json_decode($interetRes->match_ids, true);
                } else {
                    $userInterestMatch = [];
                }
                $basic = FrontService::formatBasketballMatch([$match], $userId, $userInterestMatch);
                $formatMatch = $basic[0];
                //最后一节技术统计
                if ($basketBallTlive = BasketballMatchTlive::getInstance()->where('match_id', $matchId)->where('is_stop', 1)->get()) {
                    $formatStats = json_decode($basketBallTlive->stats, true);
                    $formatScore = json_decode($basketBallTlive->score, true);
                    $matchTrend = json_decode($basketBallTlive->match_trend, true);
                    $formatTlive = json_decode($basketBallTlive->tlive, true);
                } else {

                    $formatTlive = $formatStats = $formatScore = [];
                    if ($tlive = Cache::get('basketball-match-tlive-' . $matchId)) {
                        $formatTlive = json_decode($tlive, true);
                    }
                    if ($stats = Cache::get('basketball-match-stats-' . $matchId)) {
                        $formatStats = json_decode($stats, true);
                    }
                    if ($score = Cache::get('basketball-match-score-' . $matchId)) {
                        $formatScore = json_decode($score, true);
                    }
                    $matchTrend = [];
                }
                $info = [
                    'basic' => $formatMatch,
                    'score' => $formatScore,
                    'tlive' => $formatTlive,
                    'stats' => $formatStats,
                    'match_trend' => $matchTrend
                ];
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $info);

                break;
            case 3: //球员技术统计
                if ($basketBallTlive = BasketballMatchTlive::getInstance()->where('match_id', $matchId)->where('is_stop', 1)->get()) {
                    if ($players = json_decode($basketBallTlive->players, true)) {
                        $homePlayerData = $players[0];  //主队球员数据
                        $awayPlayerData = $players[1];  //客队球员数据
                        $homeTeamData = $players[2]; //主队球队数据
                        $awayTeamData = $players[3]; //客队球队数据

                        //获取主队球员得分 篮板 助攻最多
                        $homeFormatItem = $homePlayerFullData = [];
                        array_walk($homePlayerData, function ($v, $k) use(&$homeFormatItem, &$homePlayerFullData) {
                            $homeEx = explode("^", $v[6]);
                            $newItem = [
                                'player_id' => $v[0],
                                'name_zh' => $v[1],
                                'player_logo' => $this->prefix_logo . $v[4],
                                'player_number' => $v[5],
                                'score' => $homeEx[13],
                                'bank' => $homeEx[6],
                                'assist' => $homeEx[7],
                            ];
                            $fullHomeDataItem = [
                                'player_id' => $v[0],
                                'name_zh' => $v[1],
                                'player_data' => $homeEx,
                            ];
                            $homeFormatItem[] = $newItem;
                            $homePlayerFullData[] = $fullHomeDataItem;
                            unset($newItem);
                            unset($fullDataItem);
                        });

                        //主队得分王
                        $lastScore = array_column($homeFormatItem,'score');
                        array_multisort($lastScore, SORT_DESC, $homeFormatItem);
                        $homeScore = $homeFormatItem[0];
                        //主队篮板王
                        $lastBank = array_column($homeFormatItem,'bank');
                        array_multisort($lastBank, SORT_DESC, $homeFormatItem);
                        $homeBank = $homeFormatItem[0];
                        //主队助攻王
                        $lastAssist = array_column($homeFormatItem,'assist');
                        array_multisort($lastAssist, SORT_DESC, $homeFormatItem);
                        $homeAssist = $homeFormatItem[0];

                        //获取客队球员得分 篮板 助攻最多
                        $awayFormatItem = $awayPlayerFullData = [];
                        array_walk($awayPlayerData, function ($v, $k) use(&$awayFormatItem, &$awayPlayerFullData) {
                            $awayEx = explode("^", $v[6]);
                            $newItem = [
                                'player_id' => $v[0],
                                'name_zh' => $v[1],
                                'player_logo' => $this->prefix_logo . $v[4],
                                'player_number' => $v[5],
                                'score' => $awayEx[13],
                                'bank' => $awayEx[6],
                                'assist' => $awayEx[7],
                            ];
                            $fullAwayDataItem = [
                                'player_id' => $v[0],
                                'name_zh' => $v[1],
                                'player_data' => $awayEx,
                            ];
                            $awayFormatItem[] = $newItem;
                            $awayPlayerFullData[] = $fullAwayDataItem;
                            unset($newItem);
                            unset($fullAwayDataItem);
                        });
                        //主队得分王
                        $lastScore = array_column($awayFormatItem, 'score');
                        array_multisort($lastScore, SORT_DESC, $awayFormatItem);
                        $awayScore = $awayFormatItem[0];
                        //主队篮板王
                        $lastBank = array_column($awayFormatItem,'bank');
                        array_multisort($lastBank, SORT_DESC, $awayFormatItem);
                        $awayBank = $awayFormatItem[0];
                        //主队助攻王
                        $lastAssist = array_column($awayFormatItem,'assist');
                        array_multisort($lastBank, SORT_DESC, $awayFormatItem);
                        $awayAssist = $awayFormatItem[0];
                        $best = [
                            'home' => ['score' => $homeScore, 'bank' => $homeBank, 'assist' => $homeAssist],
                            'away' => ['score' => $awayScore, 'bank' => $awayBank, 'assist' => $awayAssist],
                        ];
                        //两队球员技术统计
                        $teamPlayerData = ['home' => $homePlayerFullData, 'away' => $awayPlayerFullData];
                        //两队球队技术统计
                        $teamData = ['home' => $homeTeamData, 'away' => $awayTeamData];
                        $returnData = ['best' => $best, 'playerData' => $teamPlayerData, 'teamData' => $teamData];
                        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);

                    } else {
                        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], null);

                    }


                } else {
                    return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);

                }
                    break;
            case 2: //聊天，倒数二十条消息
                $formatMessage = [];
                if ($message = ChatHistory::getInstance()->where('sport_type', 2)->where('match_id', $matchId)->order('created_at', 'ASC')->limit(20)->all()) {
                    $senderUserIds = array_column($message, 'sender_user_id');
                    $atUserIds = array_column($message, 'at_user_id');
                    $userIds = array_merge($senderUserIds, $atUserIds);
                    $users = AdminUser::getInstance()->where('id', $userIds, 'in')->field(['id', 'nickname', 'level', 'photo'])->all();
                    //用户映射图
                    $formatUsers = $formatMessage = [];
                    array_walk($users, function ($v, $k) use (&$formatUsers) {
                        $formatUsers[$v->id] = ['id' => $v->id, 'nickname' => $v->nickname, 'level' => $v->level, 'photo' => $v->photo];
                    });
                    array_walk($message, function ($mv, $kv) use(&$formatMessage, $formatUsers) {
                        $senderUserId = $mv['sender_user_id'];
                        $atUserId = $mv['at_user_id'];
                        $senderUserInfo = isset($formatUsers[$senderUserId]) ? ['id' => $formatUsers[(string)$senderUserId]['id'], 'level'=>$formatUsers[(string)$senderUserId]['level'], 'nickname' => $formatUsers[(string)$senderUserId]['nickname'], 'photo' => $formatUsers[(string)$senderUserId]['photo']] : null;
                        $atUserInfo = isset($formatUsers[(string)$atUserId]) ? ['id' => $formatUsers[(string)$atUserId]['id'], 'level'=>$formatUsers[(string)$atUserId]['level'], 'nickname' => $formatUsers[(string)$atUserId]['nickname'], 'photo' => $formatUsers[(string)$atUserId]['photo']] : null;
                        $formatMessageItem = [
                            'message_info' => ['id' => $mv['id'], 'content' => $mv['content'], 'match_id' => $mv['match_id']],
                            'sender_user_info' => $senderUserInfo,
                            'at_user_info' => $atUserInfo,
                        ];
                        $formatMessage[] = $formatMessageItem;
                    });

                }
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $formatMessage);
                break;
            case 4: //数据

                //获取赛季id  比赛->赛事->当前赛季id
                $homeTeamId = $match->home_team_id;
                $awayTeamId = $match->away_team_id;
                $matchInSeason = BasketballMatchSeason::getInstance()->where('match_id', $match->match_id)->get();

                $seasonId = $matchInSeason->season_id;
                $prepareHandleTableItem = $homeTeamInfo = $awayTeamInfo = null;
                //赛季积分榜 只取常规赛数据
                if ($seasonTable = BasketballSeasonTable::getInstance()->where('season_id', $seasonId)->get()) {
                    $tableInfo = json_decode($seasonTable->tables, true);
                    $prepareHandleTableItem = null;
                    foreach ($tableInfo as $itemTable) {
                        //常规赛
                        if ($itemTable['scope'] == 5) {
                            foreach ($itemTable['rows'] as $k=>$item) {
                                if ($item['team_id'] == $match->home_team_id || $item['team_id'] == $match->away_team_id) {
                                    $info['won'] = $itemTable['rows'][$k]['won'];
                                    $info['won_rate'] = $itemTable['rows'][$k]['won_rate'];
                                    $info['lost'] = $itemTable['rows'][$k]['lost'];
                                    $info['last_10'] = $itemTable['rows'][$k]['last_10'];
                                    $info['streaks'] = $itemTable['rows'][$k]['streaks'];
                                    $info['home'] = $itemTable['rows'][$k]['home'];
                                    $info['away'] = $itemTable['rows'][$k]['away'];
                                    $info['position'] = $itemTable['rows'][$k]['position'];
                                }
                            }
                            $desc = ['name' => $itemTable['name'], 'stage_id' => $itemTable['stage_id']];
                            $prepareHandleTableItem[] = ['info' => $info, 'desc' => $desc];

                        }
                    }

                    //场均数据
                    //赛季球队球员信息
                    $seasonStatsDetail = BasketballSeasonAllStatsDetail::getInstance()->where('season_id', $seasonId)->get();
                    $statsDetailInfo = json_decode($seasonStatsDetail->team_stats, true);

                    if (!$statsDetailInfo) return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);
                    array_walk($statsDetailInfo, function ($v, $k) use($homeTeamId, $awayTeamId, &$homeTeamInfo, &$awayTeamInfo) {
                        if ($v['team_id'] == $homeTeamId) {
                            $homeTeamInfo = $v;
                        } else if ($v['team_id'] == $awayTeamId) {
                            $awayTeamInfo = $v;
                        }
                    });
                }

                //历史交锋是否同主客
                $isHistorySameHomeAway = isset($this->params['is_history_same_home_away']) ? (int)$this->params['is_history_same_home_away'] : 0;
                //历史交锋 与 近期战绩
                $seasonMatchesList = BasketballMatchSeason::getInstance()
//                    ->where('match_time', time(), '<')
                    ->where('status_id', [1,2,3,4,5,6,7,8,9,10], 'in')
                    ->where('(home_team_id=' . $homeTeamId . ' or away_team_id=' . $homeTeamId . ' or home_team_id=' . $awayTeamId . ' or away_team_id=' . $awayTeamId . ')')
                    ->order('match_time', 'DESC')
                    ->limit(200)
                    ->all();

                //近期战绩
                $formatMatchList = FrontService::formatBasketballMatch($seasonMatchesList, 0, []);
                if (!$formatMatchList) return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);

                $isRecentlySameHomeAway = isset($this->params['is_recently_same_home_away']) ? (int)$this->params['is_recently_same_home_away'] : 0;
                $formatMatch = ['homeRecently' => [], 'awayRecently'=>[], 'homeSchedule' => [], 'awaySchedule' => [], 'history' => []];

                array_walk($formatMatchList, function ($v, $k) use($homeTeamId, $awayTeamId, $isHistorySameHomeAway, $isRecentlySameHomeAway, &$formatMatch) {
                    //历史交锋
                    if (!$isHistorySameHomeAway && count($formatMatch['history']) <= 10 && (($v['home_team_id'] == $homeTeamId && $v['away_team_id'] == $awayTeamId) || ($v['home_team_id'] == $awayTeamId && $v['away_team_id'] == $homeTeamId))) {
                        $formatMatch['history'][] = $v;
                    } else if ($isHistorySameHomeAway && count($formatMatch['history']) <= 10 && ($v['home_team_id'] == $homeTeamId && $v['away_team_id'] == $awayTeamId)) {
                        $formatMatch['history'][] = $v;
                    }
                    //近期战绩
                    if ($isRecentlySameHomeAway) { //同主客
                        //主队近期战绩
                        if ($v['home_team_id'] == $homeTeamId && count($formatMatch['homeRecently']) <= 10) {
                            $formatMatch['homeRecently'][] = $v;

                        }
                        if ($v['away_team_id'] == $awayTeamId && count($formatMatch['awayRecently']) <= 10) {
                            $formatMatch['awayRecently'][] = $v;

                        }
                    } else {
                        if (($v['home_team_id'] == $homeTeamId || $v['away_team_id'] == $homeTeamId) && count($formatMatch['homeRecently']) <= 10) {
                            $formatMatch['homeRecently'][] = $v;

                        }
                        if (($v['home_team_id'] == $awayTeamId || $v['away_team_id'] == $awayTeamId) && count($formatMatch['awayRecently']) <= 10) {
                            $formatMatch['awayRecently'][] = $v;

                        }
                    }
                    //近期赛程
                    if (in_array($v['status_id'], [1,2,3,4,5,6,7,8,9,10])) {
                        //主队近期赛程
                        if (($v['home_team_id'] == $homeTeamId || $v['away_team_id'] == $homeTeamId) && count($formatMatch['homeSchedule']) <= 10) {
                            $formatMatch['homeSchedule'][] = $v;

                        }
                        if (($v['home_team_id'] == $awayTeamId || $v['away_team_id'] == $awayTeamId) && count($formatMatch['awaySchedule']) <= 10) {
                            $formatMatch['awaySchedule'][] = $v;

                        }
                    }

                });
                $return = ['table' => $prepareHandleTableItem, 'teamStats' => ['home' => $homeTeamInfo, 'away' => $awayTeamInfo], 'matchList' => $formatMatch];
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

                break;
        }


    }

    //篮球数据中心首页
    public function basketballDataCenter()
    {
        //获取title栏
        $setting = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::BASKETBALL_COMPETITION)->get();
        $formatCompetition = [];
        if ($competitionIds = json_decode($setting->sys_value)) {
            $competitions = BasketBallCompetition::getInstance()->field(['competition_id', 'short_name_zh', 'logo'])->where('competition_id', $competitionIds, 'in')->all();
            $seasons = BasketballSeasonList::getInstance()->field(['year', 'competition_id', 'has_player_stats', 'has_team_stats', 'season_id'])->where('competition_id', $competitionIds, 'in')->all();

            foreach ($competitions as $competition) {
                $competition = $competition->toArray();
                $competition['season'] = null;
                array_walk($seasons, function ($v, $k) use(&$competition) {
                    if ($v['competition_id'] == $competition['competition_id']) {
                        $competition['season'][] = $v;
                    }
                });
                $formatCompetition[] = $competition;
            }
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $formatCompetition);
    }
    public function competitionInfo()
    {
        $competitionId = isset($this->params['competition_id']) ? (int)$this->params['competition_id'] : 0;
        $selectSeasonId = isset($this->params['season_id']) ? (int)$this->params['season_id'] : 0;
        $type = isset($this->params['type']) ? (int)$this->params['type'] : 1;
        if (!$competitionId) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        }
        if (!$selectSeasonId) {
            if ($seasonList = BasketballSeasonList::getInstance()->where('competition_id', $competitionId)->where('is_current', 1)->get()) {
                $selectSeasonId = $seasonList->season_id;
            } else {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
            }
        }
        switch ($type) {
            case 1:
                //排名
                if (!$basketballSeasonTable = BasketballSeasonTable::getInstance()->where('season_id', $selectSeasonId)->get()) {
                    return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES], []);
                } else {
                    $tableInfo = json_decode($basketballSeasonTable->tables, true);
                    $teams = BasketballTeam::getInstance()->field(['team_id', 'short_name_zh', 'logo'])->where('competition_id', $competitionId)->all();
                    array_walk($teams, function ($v, $k) use (&$formatTeams) {
                        $formatTeams[$v['team_id']] = $v;
                    });
                    $regular = $playoff = [];
                    foreach ($tableInfo as $itemTable) {
                        foreach ($itemTable['rows'] as $ik => $item) {
                            $itemTable['rows'][$ik]['teamInfo'] = $formatTeams[$item['team_id']];
                        }
                        if ($itemTable['scope'] == 5) { //常规赛
                            $regular[] = $itemTable;
                        }
                    }
                    return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $regular);

                }
                break;
            case 2: //比赛
                $page = isset($this->params['page']) ? (int)$this->params['page'] : 1;
                $size = isset($this->params['size']) ? (int)$this->params['size'] : 20;
                $uid = isset($this->auth['id']) ? (int)$this->auth['id'] : 0;
                $seasonMatchList = BasketballMatchSeason::getInstance()->where('competition_id', $competitionId)->where('season_id', $selectSeasonId)->getLimit($page, $size, 'match_time', 'ASC');
                $list = $seasonMatchList->all(null);
                $count = $seasonMatchList->lastQueryResult()->getTotalCount();
                $formatBasketBallMatch = FrontService::formatBasketballMatch($list, $uid, []);
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK],['list' => $formatBasketBallMatch, 'count' => $count]);
                break;
            case 3: //球员排行榜
                if ($seasonStats = BasketballSeasonAllStatsDetail::getInstance()->where('season_id', $selectSeasonId)->get()) {
                    $playerStats = json_decode($seasonStats->player_stats, true);
                    //球员映射图
                    $playerIds = array_column($playerStats, 'player_id');
                    $players = BasketballPlayer::getInstance()->field(['player_id', 'short_name_zh', 'logo'])->where('player_id', $playerIds, 'in')->all();
                    $formatUsers = [];
                    array_walk($players, function($v, $k) use(&$formatUsers) {
                        $formatUsers[$v->player_id] = $v;
                    });
                    //球队映射图
                    $teamIds = array_column($playerStats, 'team_id');
                    $teams = BasketballTeam::getInstance()->field(['team_id', 'logo', 'short_name_zh'])->where('team_id', $teamIds, 'in')->all();
                    $formatTeams = [];
                    array_walk($teams, function ($tv, $tk) use (&$formatTeams) {
                        $formatTeams[$tv->team_id] = $tv;
                    });

                    $formatPlayerStats = [];
                    foreach ($playerStats as $k => $playerStat) {
                        if ($playerStat['scope'] != 5) continue;
                        $playerStat['player_info'] = isset($formatUsers[$playerStat['player_id']]) ? $formatUsers[$playerStat['player_id']] : [];
                        $playerStat['team_info'] = isset($formatTeams[$playerStat['team_id']]) ? $formatTeams[$playerStat['team_id']] : [];
                        $formatPlayerStats[] = $playerStat;
                    }
                    $formatTable = [];
                    //得分排序
                    $point = array_column($formatPlayerStats,'point');
                    array_multisort($point,SORT_DESC,$formatPlayerStats);
                    $point_table = array_slice($formatPlayerStats, 0, 100);

                    //篮板排序
                    $rebounds = array_column($formatPlayerStats,'rebounds');
                    array_multisort($rebounds,SORT_DESC,$formatPlayerStats);
                    $rebounds_table = array_slice($formatPlayerStats, 0, 100);

                    //助攻排序
                    $assists = array_column($formatPlayerStats,'assists');
                    array_multisort($assists,SORT_DESC,$formatPlayerStats);
                    $assists_table = array_slice($formatPlayerStats, 0, 100);

                    //抢断排序
                    $steals = array_column($formatPlayerStats,'steals');
                    array_multisort($steals,SORT_DESC,$formatPlayerStats);
                    $steals_table = array_slice($formatPlayerStats, 0, 100);

                    //盖帽排序
                    $blocks = array_column($formatPlayerStats,'blocks');
                    array_multisort($blocks,SORT_DESC,$formatPlayerStats);
                    $blocks_table = array_slice($formatPlayerStats, 0, 100);

                    //犯规排序
                    $personal_fouls = array_column($formatPlayerStats,'personal_fouls');
                    array_multisort($personal_fouls,SORT_DESC,$formatPlayerStats);
                    $personal_fouls_table = array_slice($formatPlayerStats, 0, 100);

                    //命中率排序
                    $field_goals_accuracy = array_column($formatPlayerStats,'field_goals_accuracy');
                    array_multisort($field_goals_accuracy,SORT_DESC,$formatPlayerStats);
                    $field_goals_accuracy_table = array_slice($formatPlayerStats, 0, 100);

                    //二分命中数排序
                    $two_points_total = array_column($formatPlayerStats,'two_points_total');
                    array_multisort($two_points_total,SORT_DESC,$formatPlayerStats);
                    $two_points_total_table = array_slice($formatPlayerStats, 0, 100);

                    //三分命中数排序
                    $three_points_total = array_column($formatPlayerStats,'three_points_total');
                    array_multisort($three_points_total,SORT_DESC,$formatPlayerStats);
                    $three_points_total_table = array_slice($formatPlayerStats, 0, 100);

                    //发球数排序
                    $free_throws_total = array_column($formatPlayerStats,'free_throws_total');
                    array_multisort($free_throws_total,SORT_DESC,$formatPlayerStats);
                    $free_throws_total_table = array_slice($formatPlayerStats, 0, 100);

                    //失误排序
                    $turnovers = array_column($formatPlayerStats,'turnovers');
                    array_multisort($turnovers,SORT_DESC,$formatPlayerStats);
                    $turnovers_table = array_slice($formatPlayerStats, 0, 100);
                    //上场场次排序
                    $court = array_column($formatPlayerStats,'court');
                    array_multisort($court,SORT_DESC,$formatPlayerStats);
                    $court_table = array_slice($formatPlayerStats, 0, 100);

                    //比赛场次
                    $matches = array_column($formatPlayerStats,'matches');
                    array_multisort($matches,SORT_DESC,$formatPlayerStats);
                    $matches_table = array_slice($formatPlayerStats, 0, 100);
                    //point-得分榜 rebounds篮板榜 assists助攻榜 steals抢断榜 blocks盖帽榜 personal_fouls犯规帮 field_goals_accuracy投篮命中率
                    //two_points_total两分球总数   three_points_total 三分球总数 free_throws_total罚球总数 turnovers失误  court上场场次 matches比赛场次
                    for ($i = 0; $i <= 100; $i++) {
                        if (isset($point_table[$i])) {
                            $formatTable['point'][] = ['player_info' => $point_table[$i]['player_info'], 'team_info' => $point_table[$i]['team_info'], 'point' => $point_table[$i]['point']];
                        }
                        if (isset($rebounds_table[$i])) {
                            $formatTable['rebounds'][] = ['player_info' => $rebounds_table[$i]['player_info'], 'team_info' => $rebounds_table[$i]['team_info'], 'rebounds' => $rebounds_table[$i]['rebounds']];
                        }

                        if (isset($assists_table[$i])) {
                            $formatTable['assists'][] = ['player_info' => $assists_table[$i]['player_info'], 'team_info' => $assists_table[$i]['team_info'], 'assists' => $assists_table[$i]['assists']];
                        }
                        if (isset($steals_table[$i])) {
                            $formatTable['steals'][] = ['player_info' => $steals_table[$i]['player_info'], 'team_info' => $steals_table[$i]['team_info'], 'steals' => $steals_table[$i]['steals']];
                        }

                        if (isset($blocks_table[$i])) {
                            $formatTable['blocks'][] = ['player_info' => $blocks_table[$i]['player_info'], 'team_info' => $blocks_table[$i]['team_info'], 'point' => $blocks_table[$i]['point']];
                        }

                        if (isset($personal_fouls_table[$i])) {
                            $formatTable['personal_fouls'][] = ['player_info' => $personal_fouls_table[$i]['player_info'], 'team_info' => $personal_fouls_table[$i]['team_info'], 'personal_fouls' => $personal_fouls_table[$i]['personal_fouls']];
                        }

                        if (isset($field_goals_accuracy_table[$i])) {
                            $formatTable['field_goals_accuracy'][] = ['player_info' => $field_goals_accuracy_table[$i]['player_info'], 'team_info' => $field_goals_accuracy_table[$i]['team_info'], 'field_goals_accuracy' => $field_goals_accuracy_table[$i]['field_goals_accuracy']];
                        }

                        if (isset($two_points_total_table[$i])) {
                            $formatTable['two_points_total'][] = ['player_info' => $two_points_total_table[$i]['player_info'], 'team_info' => $two_points_total_table[$i]['team_info'], 'two_points_total' => $two_points_total_table[$i]['two_points_total']];
                        }

                        if (isset($three_points_total_table[$i])) {
                            $formatTable['three_points_total'][] = ['player_info' => $three_points_total_table[$i]['player_info'], 'team_info' => $three_points_total_table[$i]['team_info'], 'three_points_total' => $three_points_total_table[$i]['three_points_total']];
                        }

                        if (isset($free_throws_total_table[$i])) {
                            $formatTable['free_throws_total'][] = ['player_info' => $free_throws_total_table[$i]['player_info'], 'team_info' => $free_throws_total_table[$i]['team_info'], 'free_throws_total' => $free_throws_total_table[$i]['free_throws_total']];
                        }

                        if (isset($turnovers_table[$i])) {
                            $formatTable['turnovers'][] = ['player_info' => $turnovers_table[$i]['player_info'], 'team_info' => $turnovers_table[$i]['team_info'], 'turnovers' => $turnovers_table[$i]['turnovers']];
                        }

                        //上场场次
                        if (isset($court_table[$i])) {
                            $formatTable['court'][] = ['player_info' => $court_table[$i]['player_info'], 'team_info' => $court_table[$i]['team_info'], 'court' => $court_table[$i]['court']];
                        }

                        if (isset($matches_table[$i])) {
                            $formatTable['matches'][] = ['player_info' => $personal_fouls_table[$i]['player_info'], 'team_info' => $personal_fouls_table[$i]['team_info'], 'matches' => $matches_table[$i]['matches']];
                        }

                    }

                    return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $formatTable);

                } else {
                    return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);
                }
                break;
            case 4:
                //球队排行

                if ($seasonStats = BasketballSeasonAllStatsDetail::getInstance()->where('season_id', $selectSeasonId)->get()) {
                    $teamStats = json_decode($seasonStats->team_stats, true);
                    $teamIds = array_column($teamStats, 'team_id');
                    //球队映射图
                    $teams = BasketballTeam::getInstance()->field(['team_id', 'short_name_zh', 'logo'])->where('team_id', $teamIds, 'in')->all();
                    $formatTeams = [];

                    array_walk($teams, function ($v, $k) use (&$formatTeams) {
                        $formatTeams[$v->team_id] = $v;
                    });
                    $formatTeamStats = [];
                    foreach ($teamStats as $teamStat) {
                        if ($teamStat['scope'] != 5) continue;
                        $teamStat['team_info'] = isset($formatTeams[$teamStat['team_id']]) ? $formatTeams[$teamStat['team_id']] : [];
                        $formatTeamStats[] = $teamStat;
                    }
                    if (!$formatTeamStats) return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);

                    //得分排序
                    $points = array_column($formatTeamStats,'points');
                    array_multisort($points,SORT_DESC,$formatTeamStats);
                    $points_table = array_slice($formatTeamStats, 0, 100);

                    //失分排序
                    $points_against = array_column($formatTeamStats,'points_against');
                    array_multisort($points_against,SORT_DESC,$formatTeamStats);
                    $points_against_table = array_slice($formatTeamStats, 0, 100);


                    //前场篮板排序
                    $offensive_rebounds = array_column($formatTeamStats,'offensive_rebounds');
                    array_multisort($offensive_rebounds,SORT_DESC,$formatTeamStats);
                    $offensive_rebounds_table = array_slice($formatTeamStats, 0, 100);

                    //后场篮板排序
                    $defensive_rebounds = array_column($formatTeamStats,'defensive_rebounds');
                    array_multisort($defensive_rebounds,SORT_DESC,$formatTeamStats);
                    $defensive_rebounds_table = array_slice($formatTeamStats, 0, 100);

                    //助攻排序
                    $assists = array_column($formatTeamStats,'assists');
                    array_multisort($assists,SORT_DESC,$formatTeamStats);
                    $assists_table = array_slice($formatTeamStats, 0, 100);


                    //抢断排序
                    $steals = array_column($formatTeamStats,'steals');
                    array_multisort($steals,SORT_DESC,$formatTeamStats);
                    $steals_table = array_slice($formatTeamStats, 0, 100);


                    //盖帽排序
                    $blocks = array_column($formatTeamStats,'blocks');
                    array_multisort($blocks,SORT_DESC,$formatTeamStats);
                    $blocks_table = array_slice($formatTeamStats, 0, 100);

                    //犯规排序
                    $total_fouls = array_column($formatTeamStats,'total_fouls');
                    array_multisort($total_fouls,SORT_DESC,$formatTeamStats);
                    $total_fouls_table = array_slice($formatTeamStats, 0, 100);

                    //投篮命中率
                    $field_goals_accuracy = array_column($formatTeamStats,'field_goals_accuracy');
                    array_multisort($field_goals_accuracy,SORT_DESC,$formatTeamStats);
                    $field_goals_accuracy_table = array_slice($formatTeamStats, 0, 100);

                    //投篮出手数排行
                    $field_goals_total = array_column($formatTeamStats,'field_goals_total');
                    array_multisort($field_goals_total,SORT_DESC,$formatTeamStats);
                    $field_goals_total_table = array_slice($formatTeamStats, 0, 100);

                    //投篮命中数排行
                    $field_goals_scored = array_column($formatTeamStats,'field_goals_scored');
                    array_multisort($field_goals_scored,SORT_DESC,$formatTeamStats);
                    $field_goals_scored_table = array_slice($formatTeamStats, 0, 100);

                    //三分命中率
                    $three_pointers_accuracy = array_column($formatTeamStats,'three_pointers_accuracy');
                    array_multisort($three_pointers_accuracy,SORT_DESC,$formatTeamStats);
                    $three_pointers_accuracy_table = array_slice($formatTeamStats, 0, 100);

                    //三分出手
                    $three_pointers_total = array_column($formatTeamStats,'three_pointers_total');
                    array_multisort($three_pointers_total,SORT_DESC,$formatTeamStats);
                    $three_pointers_total_table = array_slice($formatTeamStats, 0, 100);

                    //三分命中
                    $three_pointers_scored = array_column($formatTeamStats,'three_pointers_scored');
                    array_multisort($three_pointers_scored,SORT_DESC,$formatTeamStats);
                    $three_pointers_scored_table = array_slice($formatTeamStats, 0, 100);

                    //罚球总数
                    $free_throws_total = array_column($formatTeamStats,'free_throws_total');
                    array_multisort($free_throws_total,SORT_DESC,$formatTeamStats);
                    $free_throws_total_table = array_slice($formatTeamStats, 0, 100);

                    //罚球命中率
                    $free_throws_accuracy = array_column($formatTeamStats,'free_throws_accuracy');
                    array_multisort($free_throws_accuracy,SORT_DESC,$formatTeamStats);
                    $free_throws_accuracy_table = array_slice($formatTeamStats, 0, 100);

                    for ($i = 0; $i <= 100; $i++) {
                        // points-得分，points_against-失分，offensive_rebounds-进攻篮板，defensive_rebounds-防守篮板，assists-助攻，steals-抢断
                        //blocks-盖帽 ，total_fouls-犯规数，field_goals_accuracy-投篮命中率， field_goals_total-投篮出手总数，field_goals_scored-投篮命中数
                        //three_pointers_accuracy-三分命中率，three_pointers_total-三分出手数，three_pointers_scored-三分命中数
                        // free_throws_total-罚球总数，free_throws_accuracy-罚球命中率
                        if (isset($points_table[$i])) {
                            $formatTable['points'][] = ['team_info' => $formatTeams[$points_table[$i]['team_id']], 'points' => $points_table[$i]['points']];
                        }
                        //失分
                        if (isset($points_against_table[$i])) {
                            $formatTable['points_against'][] = ['team_info' => $formatTeams[$points_against_table[$i]['team_id']], 'offensive_rebounds' => $points_against_table[$i]['points_against']];
                        }
                        //进攻篮板
                        if (isset($offensive_rebounds_table[$i])) {
                            $formatTable['offensive_rebounds'][] = ['team_info' => $formatTeams[$offensive_rebounds_table[$i]['team_id']], 'offensive_rebounds' => $offensive_rebounds_table[$i]['offensive_rebounds']];
                        }

                        //防守篮板
                        if (isset($defensive_rebounds_table[$i])) {
                            $formatTable['defensive_rebounds'][] = ['team_info' => $formatTeams[$defensive_rebounds_table[$i]['team_id']], 'defensive_rebounds' => $defensive_rebounds_table[$i]['defensive_rebounds']];
                        }

                        //助攻
                        if (isset($assists_table[$i])) {
                            $formatTable['assists'][] = ['team_info' => $formatTeams[$assists_table[$i]['team_id']], 'assists' => $assists_table[$i]['assists']];
                        }

                        //抢断
                        if (isset($steals_table[$i])) {
                            $formatTable['steals'][] = ['team_info' => $formatTeams[$steals_table[$i]['team_id']], 'steals' => $steals_table[$i]['steals']];
                        }

                        //盖帽
                        if (isset($blocks_table[$i])) {
                            $formatTable['blocks'][] = ['team_info' => $formatTeams[$blocks_table[$i]['team_id']], 'blocks' => $blocks_table[$i]['blocks']];
                        }

                        //犯规
                        if (isset($total_fouls_table[$i])) {
                            $formatTable['total_fouls'][] = ['team_info' => $formatTeams[$total_fouls_table[$i]['team_id']], 'total_fouls' => $total_fouls_table[$i]['total_fouls']];
                        }

                        //投篮命中率
                        if (isset($field_goals_accuracy_table[$i])) {
                            $formatTable['field_goals_accuracy'][] = ['team_info' => $formatTeams[$field_goals_accuracy_table[$i]['team_id']], 'blocks' => $field_goals_accuracy_table[$i]['blocks']];
                        }

                        //投篮总数
                        if (isset($field_goals_total_table[$i])) {
                            $formatTable['field_goals_total'][] = ['team_info' => $formatTeams[$field_goals_total_table[$i]['team_id']], 'field_goals_total' => $field_goals_total_table[$i]['field_goals_total']];
                        }

                        //投篮命中数
                        if (isset($field_goals_scored_table[$i])) {
                            $formatTable['field_goals_scored'][] = ['team_info' => $formatTeams[$field_goals_scored_table[$i]['team_id']], 'field_goals_scored' => $field_goals_scored_table[$i]['field_goals_scored']];
                        }

                        //三分命中率
                        if (isset($three_pointers_accuracy_table[$i])) {
                            $formatTable['three_pointers_accuracy'][] = ['team_info' => $formatTeams[$three_pointers_accuracy_table[$i]['team_id']], 'three_pointers_accuracy' => $three_pointers_accuracy_table[$i]['three_pointers_accuracy']];
                        }

                        //三分出手数
                        if (isset($three_pointers_total_table[$i])) {
                            $formatTable['three_pointers_total'][] = ['team_info' => $formatTeams[$three_pointers_total_table[$i]['team_id']], 'three_pointers_total' => $three_pointers_total_table[$i]['three_pointers_total']];
                        }

                        //三分命中数
                        if (isset($three_pointers_scored_table[$i])) {
                            $formatTable['three_pointers_scored'][] = ['team_info' => $formatTeams[$three_pointers_scored_table[$i]['team_id']], 'three_pointers_scored' => $three_pointers_scored_table[$i]['three_pointers_scored']];
                        }

                        //罚球总数
                        if (isset($free_throws_total_table[$i])) {
                            $formatTable['free_throws_total'][] = ['team_info' => $formatTeams[$free_throws_total_table[$i]['team_id']], 'free_throws_total' => $free_throws_total_table[$i]['field_goals_accuracy']];
                        }

                        //罚球命中率
                        if (isset($free_throws_accuracy_table[$i])) {
                            $formatTable['free_throws_accuracy'][] = ['team_info' => $formatTeams[$free_throws_accuracy_table[$i]['team_id']], 'free_throws_total' => $free_throws_accuracy_table[$i]['free_throws_accuracy']];
                        }
                    }
                    return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $formatTable);

                } else {
                    return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);

                }

                break;


        }

    }


}