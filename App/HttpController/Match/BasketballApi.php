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
class BasketballApi extends FrontUserController
{

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
        $matches = AdminMatch::getInstance()
            ->where('match_time', $start, '>=')
            ->where('match_time', $end, '<')
            ->where('status_id', self::STATUS_RESULT, 'in')
            ->where('competition_id', $selectCompetitionIdArr, 'in')
            ->where('is_delete', 0)
            ->order('match_time', 'DESC')->getLimit($page, $size);
        $list = $matches->all();
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
        $order .= 'WHEN `status_id`=13 Then 13 '; //延期
        $order .= 'WHEN `status_id`=14 Then 14 '; //腰斩
        $order .= 'WHEN `status_id`=15 Then 15 ELSE 16 END'; //待定

        $page = !empty($this->params['page']) ? intval($this->params['page']) : 1;
        $size = !empty($this->params['size']) ? (int)$this->params['size'] : 15;
        $userId = !empty($this->auth['id']) ? (int)$this->auth['id'] : 0;

        list($selectCompetitionIdArr, $interestMatchArr) = AdminUser::getUserShowBasketballCompetition($userId);

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
        if (!$match = BasketballMatch::getInstance()->where('match_id', $matchId)->get()) {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
        }

        switch ($type) {
            case 1: //直播 比赛详情
                $basic = FrontService::formatBasketballMatch([$match], $this->auth['id'], []);
                $formatMatch = $basic[0];
                //最后一节技术统计
                if ($basketBallTlive = BasketballMatchTlive::getInstance()->where('match_id', $matchId)->where('is_stop', 1)->get()) {
                    $stats = json_decode($basketBallTlive->stats, true);
                    $matchTrend = json_decode($basketBallTlive->match_trend, true);
                    $formatTlive = json_decode($basketBallTlive->tlive, true);
                } else {
                    $basketBallTlive = Cache::get('basketBall-stats-' . $matchId);
                    $stats = json_decode($basketBallTlive, true);
                    $formatTlive = [];
                    if ($tlive = Cache::get('basketBall-tlive-' . $matchId)) {
                        $formatTlive = json_decode($tlive, true);
                    }
                    $matchTrend = [];
                }
                $info = ['basic' => $formatMatch, 'score' => json_decode($basketBallTlive->score, true), 'tlive' => $formatTlive, 'stats' => $stats, 'match_trend' => $matchTrend];
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $info);

                break;
            case 2: //球员技术统计

                if ($basketBallTlive = BasketballMatchTlive::getInstance()->where('match_id', $matchId)->where('is_stop', 1)->get()) {
                    $players = json_decode($basketBallTlive->players, true);
                    $homePlayerData = $players[0];  //主队球员数据
                    $awayPlayerData = $players[1];  //客队球员数据
                    //获取主队球员得分 篮板 助攻最多
                    $homeFormatItem = [];
                    array_walk($homePlayerData, function ($v, $k) use(&$homeFormatItem) {
                        $homeEx = explode("^", $v[6]);
                        $newItem = [
                            'player_id' => $v[0],
                            'name_zh' => $v[1],
                            'player_logo' => $v[4],
                            'player_number' => $v[5],
                            'score' => $homeEx[13],
                            'bank' => $homeEx[6],
                            'assist' => $homeEx[7],
                        ];
                        $homeFormatItem[] = $newItem;
                        unset($newItem);
                        unset($v);
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
                    $awayFormatItem = [];
                    array_walk($awayPlayerData, function ($v, $k) use(&$awayFormatItem) {
                        $awayEx = explode("^", $v[6]);
                        $newItem = [
                            'player_id' => $v[0],
                            'name_zh' => $v[1],
                            'player_logo' => $v[4],
                            'player_number' => $v[5],
                            'score' => $awayEx[13],
                            'bank' => $awayEx[6],
                            'assist' => $awayEx[7],
                            'is_first' => $awayEx[16], //是否是替补（1-替补，0-首发）
                            'time' => $awayEx[0], //出场时间
                            'shot' => $awayEx[1], //命中次数-投篮次数
                            'three' => $awayEx[2], //三分球投篮命中次数-三分投篮次数
                        ];
                        $awayFormatItem[] = $newItem;
                        unset($newItem);
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
                    $return = [
                        'home' => ['score' => $homeScore, 'bank' => $homeBank, 'assist' => $homeAssist],
                        'away' => ['score' => $awayScore, 'bank' => $awayBank, 'assist' => $awayAssist],
                    ];
                    return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

                }
                    break;
            case 3: //聊天，倒数二十条消息
                $formatMessage = [];
                if ($message = ChatHistory::getInstance()->where('type', 2)->where('match_id', $matchId)->order('created_at', 'DESC')->limit(20)->all()) {
                    $senderUserIds = array_column($message, 'sender_user_id');
                    $atUserIds = array_column($message, 'at_user_id');
                    $userIds = array_merge($senderUserIds, $atUserIds);
                    $senderUsers = AdminUser::getInstance()->where('id', $userIds, 'in')->field(['id', 'nickname', 'level', 'photo'])->all();
                    //用户映射图
                    $formatUsers = [];
                    array_walk($senderUserIds, function ($v, $k) use (&$formatUsers) {
                        $formatUsers[$k] = $v;
                    });
                    $formatUsers = [];
                    array_walk($message, function ($mv, $kv) use(&$formatMessage, $formatUsers) {
                        $senderUserId = $kv['sender_user_id'];
                        $atUserId = $kv['at_user_id'];
                        $senderUserInfo = isset($formatUsers[$senderUserId]) ? ['id' => $formatUsers[$senderUserId]['id'], 'level'=>$formatUsers[$senderUserId]['level'], 'nickname' => $formatUsers[$senderUserId]['nickname'], 'photo' => $formatUsers[$senderUserId]['photo']] : [];
                        $atUserInfo = isset($formatUsers[$atUserId]) ? ['id' => $formatUsers[$atUserId]['id'], 'level'=>$formatUsers[$atUserId]['level'], 'nickname' => $formatUsers[$atUserId]['nickname'], 'photo' => $formatUsers[$atUserId]['photo']] : [];
                        $formatMessageItem = [
                            'content' => $kv['content'],
                            'sender_user_info' => $senderUserInfo,
                            'at_user_info' => $atUserInfo,
                        ];
                        $formatMessage[] = $formatMessageItem;
                    });

                }
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $formatMessage);


                break;
        }


    }


    public function fixMatch()
    {

    }

}