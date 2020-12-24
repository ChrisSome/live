<?php

namespace App\HttpController\Match;
use App\Base\FrontUserController;
use App\lib\FrontService;
use App\lib\Tool;
use App\lib\Utils;
use App\Model\AdminClashHistory;
use App\Model\AdminCompetition;
use App\Model\AdminMatch;
use App\Model\AdminMatchTlive;
use App\Model\AdminPlayer;
use App\Model\AdminSysSettings;
use App\Model\AdminTeam;
use App\Model\AdminUser;
use App\Model\AdminUserInterestCompetition;
use App\Model\ChatHistory;
use App\Model\SeasonAllTableDetail;
use App\Model\SeasonMatchList;
use App\Model\SignalMatchLineUp;
use App\Utility\Log\Log;
use App\Utility\Message\Status;
use App\Model\AdminInterestMatches;
use easySwoole\Cache\Cache;
use EasySwoole\ORM\DbManager;

class FootballApi extends FrontUserController
{
    protected $lineUpDetail = 'https://open.sportnanoapi.com/api/v4/football/match/lineup/detail?user=%s&secret=%s&id=%s';
    protected $urlIntvalRank = 'https://open.sportnanoapi.com/api/v4/football/season/table/detail?user=%s&secret=%s&id=%s';
    protected $matchHistory = 'https://open.sportnanoapi.com/api/v4/football/match/live/history?user=%s&secret=%s&id=%s';



    protected $playerLogo = 'http://cdn.sportnanoapi.com/football/player/';
    public $needCheckToken = false;

    const STATUS_PLAYING = [2, 3, 4, 5, 7];
    const STATUS_SCHEDULE = [0, 1, 9];
    const STATUS_RESULT= [8, 9, 10, 11, 12, 13];

    const STATUS_NO_START = 1;
    const hotCompetition = [
        'hot' => [['competition_id' => 45, 'short_name_zh' => '欧洲杯'],
            ['competition_id'=>47, 'short_name_zh' =>'欧联杯'],
            ['competition_id'=>542, 'short_name_zh' =>'中超']],
        'A' => [
            ['competition_id' => 595, 'short_name_zh' => '澳南超'],
            ['competition_id' => 600, 'short_name_zh' => '澳南甲'],
            ['competition_id' => 1689, 'short_name_zh' => '阿尔U21'],
            ['competition_id' => 1858, 'short_name_zh' => '澳昆U20'],
            ['competition_id' => 1850, 'short_name_zh' => '澳南后备'],
        ],
        'B' => [
            ['competition_id' => 3007, 'short_name_zh' => '巴基挑杯'],
            ['competition_id' => 282, 'short_name_zh' => '冰岛乙'],
            ['competition_id' => 284, 'short_name_zh' => '冰岛杯'],
            ['competition_id' => 436, 'short_name_zh' => '巴西乙'],
            ['competition_id' => 1821, 'short_name_zh' => '巴丙'],
        ],
        'D' => [
            ['competition_id' => 1675, 'short_name_zh' => '德堡州联'],
            ['competition_id' => 132, 'short_name_zh' => '德地区北'],
        ],
        'E' => [
            ['competition_id' => 238, 'short_name_zh' => '俄超'],
            ['competition_id' => 241, 'short_name_zh' => '俄青联'],
            ['competition_id' => 240, 'short_name_zh' => '俄乙'],

        ],
        'F' => [
            ['competition_id' => 195, 'short_name_zh' => '芬超'],
            ['competition_id' => 1940, 'short_name_zh' => '芬丙'],
            ['competition_id' => 3053, 'short_name_zh' => '斐济女联'],
            ['competition_id' => 1932, 'short_name_zh' => '斐济杯'],
        ],
        'G' => [
            ['competition_id' => 486, 'short_name_zh' => '哥斯甲'],
            ['competition_id' => 385, 'short_name_zh' => '格鲁甲'],
            ['competition_id' => 386, 'short_name_zh' => '格鲁乙'],
        ],
        'H' => [
            ['competition_id' => 356, 'short_name_zh' => '哈萨超'],
            ['competition_id' => 357, 'short_name_zh' => '哈萨甲'],
        ],
        'J' => [
            ['competition_id' => 2984, 'short_name_zh' => '加拿职'],
        ],
        'K' => [
            ['competition_id' => 1785, 'short_name_zh' => '卡塔乙'],
        ],
        'L' => [
            ['competition_id' => 271, 'short_name_zh' => '罗乙'],
        ],
        'M' => [
            ['competition_id' => 465, 'short_name_zh' => '墨西超'],
            ['competition_id' => 466, 'short_name_zh' => '墨西乙'],
            ['competition_id' => 2115, 'short_name_zh' => '墨女超'],
        ],
        'N' => [
            ['competition_id' => 716, 'short_name_zh' => '南非超'],
            ['competition_id' => 203, 'short_name_zh' => '挪乙'],
        ],
        'O' => [
            ['competition_id' => 53, 'short_name_zh' => '欧青U19'],
        ],
        'Q' => [
            ['competition_id' => 24, 'short_name_zh' => '球会友谊'],
        ],
        'R' => [
            ['competition_id' => 568, 'short_name_zh' => '日职乙'],
            ['competition_id' => 569, 'short_name_zh' => '日足联'],
            ['competition_id' => 572, 'short_name_zh' => '日职丙'],
            ['competition_id' => 567, 'short_name_zh' => '日职联'],
        ],
        'S' => [
            ['competition_id' => 616, 'short_name_zh' => '沙特乙'],
            ['competition_id' => 615, 'short_name_zh' => '沙特甲'],
            ['competition_id' => 3164, 'short_name_zh' => '所罗岛杯'],
        ],
        'T' => [
            ['competition_id' => 1842, 'short_name_zh' => '泰乙'],
            ['competition_id' => 317, 'short_name_zh' => '土乙红'],
            ['competition_id' => 318, 'short_name_zh' => '土丙C'],
        ],
        'W' => [
            ['competition_id' => 674, 'short_name_zh' => '乌兹超'],
            ['competition_id' => 675, 'short_name_zh' => '乌兹甲'],
            ['competition_id' => 1736, 'short_name_zh' => '乌拉乙'],
        ],
        'X' => [
            ['competition_id' => 547, 'short_name_zh' => '香港甲'],
            ['competition_id' => 1732, 'short_name_zh' => '香港乙'],
        ],
        'Y' => [
            ['competition_id' => 349, 'short_name_zh' => '以乙北'],
            ['competition_id' => 491, 'short_name_zh' => '亚冠杯'],
        ],
        'Z' => [
            ['competition_id' => 543, 'short_name_zh' => '中甲'],
            ['competition_id' => 544, 'short_name_zh' => '中乙'],
        ],
    ];

    public function getCompetition()
    {
        $recommend = [];
        if ($arr = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::RECOMMEND_COM)->get()) {
            $recommend = json_decode($arr->sys_value, true);
        }
        $uid = isset($this->auth['id']) ? $this->auth['id'] : 0;
        $user_interest = [];
        if ($res = AdminUserInterestCompetition::getInstance()->where('user_id', $uid)->get()) {
            $user_interest = json_decode($res['competition_ids'], true);
        }
        foreach ($recommend as $k => $items) {
            foreach ($items as $sk => $item) {
                if (in_array($item['competition_id'], $user_interest)) {
                    $recommend[$k][$sk]['is_notice'] = true;
                } else {
                    $recommend[$k][$sk]['is_notice'] = false;

                }
            }
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $recommend);


    }

    /**
     * @return bool
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function matchListPlaying()
    {
        $uid = isset($this->auth['id']) ? (int)$this->auth['id'] : 0;

        list($selectCompetitionIdArr, $interestMatchArr) = AdminUser::getUserShowCompetitionId($uid);
        if (!$selectCompetitionIdArr)   return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

        $playingMatch = AdminMatch::create()->where('is_delete', 0)
            ->where('competition_id', $selectCompetitionIdArr, 'in')
            ->where('status_id', self::STATUS_PLAYING, 'in')
            ->all();

        $formatMatch = FrontService::formatMatchThree($playingMatch, $uid, $interestMatchArr);

        $return = ['list' => $formatMatch, 'user_interest_count' => count($interestMatchArr)];

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);
    }

    public function matchSchedule()
    {
        $uid = isset($this->auth['id']) ? (int)$this->auth['id'] : 0;
        //需要展示的赛事id 以及用户关注的比赛
        list($selectCompetitionIdArr, $interestMatchArr) = AdminUser::getUserShowCompetitionId($uid);

        $page = isset($this->params['page']) ? (int)$this->params['page'] : 1;
        $limit = isset($this->params['size']) ? (int)$this->params['size'] : 20;
        if ($this->params['time'] == date('Y-m-d')) {
            $is_today = true;
        } else {
            $is_today = false;
        }
        $start = strtotime($this->params['time']);
        $end = $start + 60 * 60 * 24;
        $model = AdminMatch::getInstance()->where('status_id', self::STATUS_SCHEDULE, 'in')
            ->where('match_time', $is_today ? time() : $start, '>=')->where('match_time', $end, '<')
            ->where('is_delete', 0)
            ->where('competition_id', $selectCompetitionIdArr, 'in')
            ->order('match_time', 'ASC')->limit(($page - 1) * $limit, $limit)->withTotalCount();
        $list = $model->all(null);
        $total = $model->lastQueryResult()->getTotalCount();
        $formatMatch = FrontService::formatMatchThree($list, $uid, $interestMatchArr);
        $return = ['list' => $formatMatch, 'count' => $total];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);


    }


    /**
     * time 2020-08-19
     * 赛果列表
     * @return bool
     */
    public function matchResult()
    {

        if (!isset($this->params['time'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        }
        $uid = isset($this->auth['id']) ? (int)$this->auth['id'] : 0;
        //需要展示的赛事id 以及用户关注的比赛
        list($selectCompetitionIdArr, $interestMatchArr) = AdminUser::getUserShowCompetitionId($uid);
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

        $formatMatch = FrontService::formatMatchThree($list, $uid, $interestMatchArr);
        $return = ['list' => $formatMatch, 'count' => $total];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);


    }




    public function userInterestMatchList()
    {
        if (!$this->auth['id']) {
            return $this->writeJson(Status::CODE_VERIFY_ERR, '登陆令牌缺失或者已过期');

        }
        $res = AdminInterestMatches::getInstance()->where('uid', $this->auth['id'])->get();
        $matchIds = json_decode($res->match_ids, true);
        if (!$matchIds) return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);

        $matches = AdminMatch::getInstance()->where('match_id', $matchIds, 'in')->where('is_delete', 0)->order('match_time', 'ASC')->all();
        $data = FrontService::formatMatchThree($matches, $this->auth['id'], $matchIds);
        $count = count($data);
        $response = ['list' => $data, 'count' => $count];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $response);


    }

    /**
     * 单场比赛阵容详情
     * @return bool
     * @throws
     */
    public function lineUpDetail()
    {
        $match_id = $this->params['match_id'] ?: 0;
        if (!$match_id) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

        $homeFirstPlayers = $homeAlternatePlayers = $awayFirstPlayers = $awayAlternatePlayers = [];

        if ($signalMatchLineUp = SignalMatchLineUp::getInstance()->where('match_id', $match_id)->get()) {
            $homeFormation = json_decode($signalMatchLineUp->home_formation, true);
            $awayFormation = json_decode($signalMatchLineUp->away_formation, true);
            $home = json_decode($signalMatchLineUp->home, true);
            $away = json_decode($signalMatchLineUp->away, true);
        } else {
            $res = Tool::getInstance()->postApi(sprintf($this->lineUpDetail, 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', $match_id));
            $decode = json_decode($res, true);

            if ($decode['code'] == 0 && $decode['results']) {
                $homeFormation = $decode['results']['home_formation'];
                $awayFormation = $decode['results']['away_formation'];
                $home = $decode['results']['home'];
                $away = $decode['results']['away'];
                //入库
                $signalMatchLineUp = SignalMatchLineUp::create();
                $signalMatchLineUp->home_formation = json_encode($homeFormation);
                $signalMatchLineUp->away_formation = json_encode($awayFormation);
                $signalMatchLineUp->home = json_encode($home);
                $signalMatchLineUp->away = json_encode($away);
                $signalMatchLineUp->match_id = $match_id;
                $signalMatchLineUp->confirmed = $decode['results']['confirmed'];
                $signalMatchLineUp->save();
            } else {
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);
            }
        }

        if (!empty($home)) {
            foreach ($home as $homeItem)
            {
                $homePlayer['player_id'] = $homeItem['id'];
                $homePlayer['name'] = $homeItem['name'];
                $homePlayer['logo'] = isset($home['logo']) ? $this->playerLogo . $homeItem['logo'] : '';
                $homePlayer['position'] = $homeItem['position'];
                $homePlayer['shirt_number'] = $homeItem['shirt_number'];
                if ($homeItem['first']) {
                    $homeFirstPlayers[] = $homePlayer; //首发
                } else {
                    $homeAlternatePlayers[] = $homePlayer; //替补
                }
                unset($homePlayer);
            }
        }

        if (!empty($away)) {
            foreach ($away as $awayItem) {
                $awayPlayer['player_id'] = $awayItem['id'];
                $awayPlayer['name'] = $awayItem['name'];
                $awayPlayer['logo'] = $awayItem['logo'] ? $this->playerLogo . $awayItem['logo'] : '';
                $awayPlayer['position'] = $awayItem['position'];
                $awayPlayer['shirt_number'] = $awayItem['shirt_number'];
                if ($awayItem['first']) {
                    $awayFirstPlayers[] = $awayPlayer; //首发
                } else {
                    $awayAlternatePlayers[] = $awayPlayer; //替补
                }
                unset($awayPlayer);
            }
        }

        $homeTeamInfo['firstPlayers'] = $homeFirstPlayers;
        $homeTeamInfo['alternatePlayers'] = $homeAlternatePlayers;
        $homeTeamInfo['homeFormation'] = $homeFormation;

        $awayTeamInfo['firstPlayers'] = $awayFirstPlayers;
        $awayTeamInfo['alternatePlayers'] = $awayAlternatePlayers;
        $awayTeamInfo['awayFormation'] = $awayFormation;

        $data = [
            'home' => $homeTeamInfo,
            'away' => $awayTeamInfo,
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);


    }


    /**
     * 历史交锋
     * @return bool
     */
    public function getClashHistory()
    {
        if (!isset($this->params['match_id'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $matchId = $this->params['match_id'];
        $sensus = AdminClashHistory::getInstance()->where('match_id', $this->params['match_id'])->get();



        if (!$matchInfo = SeasonMatchList::getInstance()->where('match_id', $matchId)->where('is_delete', 0)->get()) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }


        //积分排名
        $currentSeasonId = $matchInfo->competitionName()->cur_season_id;

        if (!$currentSeasonId) {
            $intvalRank = [];
        } else {
            $res = SeasonAllTableDetail::getInstance()->where('season_id', $currentSeasonId)->get();

            $decode = json_decode($res->tables, true);
            $promotions = json_decode($res->promotions, true);

            $homeIntvalRank = $awayIntvalRank = [];
            if ($promotions) {
                $rows = isset($decode[0]['rows']) ? $decode[0]['rows'] : [];
                foreach ($rows as $item) {
                    if ($item['team_id'] == $matchInfo->home_team_id) {
                        $homeIntvalRank = $item;
                    } else if ($item['team_id'] == $matchInfo->away_team_id) {
                        $awayIntvalRank = $item;
                    }
                    if (!empty($homeIntvalRank) && !empty($awayIntvalRank)) break;
                }

            } else {

                foreach ($decode as $item_row) {
                    foreach ($item_row['rows'] as $k_row) {
                        $team_ids[] = $k_row['team_id'];
                        if ($k_row['team_id'] == $matchInfo->home_team_id) {
                            $homeIntvalRank = $k_row;
                        }

                        if ($k_row['team_id'] == $matchInfo->away_team_id) {
                            $awayIntvalRank = $k_row;
                        }

                        if (!empty($homeIntvalRank) && !empty($awayIntvalRank)) {

                            break;
                        }
                    }
                }
            }

            $intvalRank = ['homeIntvalRank' => $homeIntvalRank, 'awayIntvalRank' => $awayIntvalRank];

        }

        $homeTid = $matchInfo->home_team_id;
        $awayTid = $matchInfo->away_team_id;


        //历史交锋 与 近期战绩
        $match = SeasonMatchList::create()->where('status_id', 8)->where('home_team_id='.$homeTid. ' or away_team_id='.$homeTid . ' or home_team_id='.$awayTid. ' or away_team_id='.$awayTid)
            ->where('is_delete', 0)->order('match_time', 'DESC')->all();
        $formatHistoryMatches = $homeRecentMatches = $awayRecentMatches = [];
        foreach ($match as $itemMatch) {
            if (($itemMatch['home_team_id'] == $homeTid && $itemMatch['away_team_id'] == $awayTid) || ($itemMatch['home_team_id'] == $awayTid && $itemMatch['away_team_id'] == $homeTid)) {
                $formatHistoryMatches[] = $itemMatch;
            }

            if ($itemMatch['home_team_id'] == $homeTid || $itemMatch['away_team_id'] == $homeTid) {
                $homeRecentMatches[] = $itemMatch;
            }

            if ($itemMatch['home_team_id'] == $awayTid || $itemMatch['away_team_id'] == $awayTid) {
                $awayRecentMatches[] = $itemMatch;
            }
        }
        $homeRecentSchedule = $awayRecentSchedule = [];
        //近期赛程
        $matchSchedule = SeasonMatchList::create()->where('status_id', self::STATUS_SCHEDULE, 'in')->where('home_team_id='.$homeTid. ' or away_team_id='.$homeTid . ' or home_team_id='.$awayTid. ' or away_team_id='.$awayTid)
            ->where('is_delete', 0)->order('match_time', 'DESC')->all();

        foreach ($matchSchedule as $scheduleItem) {
            if ($scheduleItem['home_team_id'] == $homeTid || $scheduleItem['away_team_id'] == $awayTid) {
                $homeRecentSchedule[] = $scheduleItem;
            }
            if ($scheduleItem['home_team_id'] == $awayTid || $scheduleItem['home_team_id'] == $homeTid) {
                $awayRecentSchedule[] = $scheduleItem;
            }
        }


        $returnData = [
            'intvalRank' => $intvalRank, //积分排名
            'historyResult' => !empty($sensus['history']) ? json_decode($sensus['history'], true) : [],
            'recentResult' => !empty($sensus['recent']) ? json_decode($sensus['recent'], true) : [],
            'history' => array_slice($formatHistoryMatches, 9), //历史交锋
            'homeRecent' => FrontService::formatMatchThree(array_slice($homeRecentMatches, 0, 10), 0, []),//主队近期战绩
            'awayRecent' => FrontService::formatMatchThree(array_slice($awayRecentMatches, 0, 10), 0, []),//客队近期战绩
            'homeRecentSchedule' => FrontService::formatMatchThree(array_slice($homeRecentSchedule, 0, 10), 0, []),//主队近期赛程
            'awayRecentSchedule' => FrontService::formatMatchThree(array_slice($awayRecentSchedule, 0, 10), 0, []),//客队近期赛程
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);

    }

    public function getClashHistoryBak()
    {
        if (!isset($this->params['match_id'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $matchId = $this->params['match_id'];
        $sensus = AdminClashHistory::getInstance()->where('match_id', $this->params['match_id'])->get();



        if (!$matchInfo = SeasonMatchList::getInstance()->where('match_id', $matchId)->where('is_delete', 0)->get()) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }


        //积分排名
        $currentSeasonId = $matchInfo->competitionName()->cur_season_id;

        if (!$currentSeasonId) {
            $intvalRank = [];
        } else {
            $res = SeasonAllTableDetail::getInstance()->where('season_id', $currentSeasonId)->get();

            $decode = json_decode($res->tables, true);
            $promotions = json_decode($res->promotions, true);

            $homeIntvalRank = $awayIntvalRank = [];
            if ($promotions) {
                $rows = isset($decode[0]['rows']) ? $decode[0]['rows'] : [];
                foreach ($rows as $item) {
                    if ($item['team_id'] == $matchInfo->home_team_id) {
                        $homeIntvalRank = $item;
                    } else if ($item['team_id'] == $matchInfo->away_team_id) {
                        $awayIntvalRank = $item;
                    }
                    if (!empty($homeIntvalRank) && !empty($awayIntvalRank)) break;
                }

            } else {

                foreach ($decode as $item_row) {
                    foreach ($item_row['rows'] as $k_row) {
                        $team_ids[] = $k_row['team_id'];
                        if ($k_row['team_id'] == $matchInfo->home_team_id) {
                            $homeIntvalRank = $k_row;
                        }

                        if ($k_row['team_id'] == $matchInfo->away_team_id) {
                            $awayIntvalRank = $k_row;
                        }

                        if (!empty($homeIntvalRank) && !empty($awayIntvalRank)) {

                            break;
                        }
                    }
                }
            }

            $intvalRank = ['homeIntvalRank' => $homeIntvalRank, 'awayIntvalRank' => $awayIntvalRank];

        }


        $homeTid = $matchInfo->home_team_id;
        $awayTid = $matchInfo->away_team_id;

        //历史交锋
        $matches = SeasonMatchList::getInstance()->where('status_id', 8)->where('((home_team_id=' . $homeTid . ' and away_team_id=' . $awayTid . ') or (home_team_id='.$awayTid.' and away_team_id='.$homeTid.'))')
            ->where('is_delete', 0)->order('match_time', 'DESC')->limit(10)->all();

        $formatHistoryMatches = FrontService::formatMatchThree($matches, 0, []);

        //近期战绩

        $homeRecentMatches = SeasonMatchList::getInstance()->where('status_id', 8)->where('home_team_id='.$homeTid. ' or away_team_id='.$homeTid)
            ->where('is_delete', 0)->order('match_time', 'DESC')->limit(10)->all();
        $awayRecentMatches = SeasonMatchList::getInstance()->where('status_id', 8)->where('home_team_id='.$awayTid. ' or away_team_id='.$awayTid)
            ->where('is_delete', 0)->order('match_time', 'DESC')->limit(10)->all();
        //历史交锋 与 近期战绩
//        $match = SeasonMatchList::create()->where('status_id', 8)->where('home_team_id='.$homeTid. ' or away_team_id='.$homeTid . ' or home_team_id='.$awayTid. ' or away_team_id='.$awayTid)
//            ->where('is_delete', 0)->order('match_time', 'DESC')->all();
//        $formatHistoryMatches = $homeRecentMatches = $awayRecentMatches = [];
//        foreach ($match as $itemMatch) {
//            if (($itemMatch['home_team_id'] == $homeTid && $itemMatch['away_team_id'] == $awayTid) || ($itemMatch['home_team_id'] == $awayTid && $itemMatch['away_team_id'] == $homeTid)) {
//                $formatHistoryMatches[] = $itemMatch;
//            }
//
//            if ($itemMatch['home_team_id'] == $homeTid || $itemMatch['away_team_id'] == $homeTid) {
//                $homeRecentMatches[] = $itemMatch;
//            }
//
//            if ($itemMatch['home_team_id'] == $awayTid || $itemMatch['away_team_id'] == $awayTid) {
//                $awayRecentMatches[] = $itemMatch;
//            }
//        }



        //近期赛程
//        $homeRecentSchedule = AdminMatch::getInstance()->where('status_id', self::STATUS_SCHEDULE, 'in')
//            ->where('(home_team_id = ' . $homeTid . ' or away_team_id = ' . $homeTid . ')')->where('is_delete', 0)->order('match_time', 'ASC')->all();
//        $awayRecentSchedule = AdminMatch::getInstance()->where('status_id', self::STATUS_SCHEDULE, 'in')
//            ->where('(home_team_id = ' . $awayTid . ' or away_team_id = ' . $awayTid . ')')->where('is_delete', 0)->order('match_time', 'ASC')->all();

        //近期赛程
        $matchSchedule = SeasonMatchList::create()->where('status_id', self::STATUS_SCHEDULE, 'in')->where('home_team_id='.$homeTid. ' or away_team_id='.$homeTid . ' or home_team_id='.$awayTid. ' or away_team_id='.$awayTid)
            ->where('is_delete', 0)->order('match_time', 'DESC')->all();

        foreach ($matchSchedule as $scheduleItem) {
            if ($scheduleItem['home_team_id'] == $homeTid || $scheduleItem['away_team_id'] == $awayTid) {
                $homeRecentSchedule[] = $scheduleItem;
            }
            if ($scheduleItem['home_team_id'] == $awayTid || $scheduleItem['home_team_id'] == $homeTid) {
                $awayRecentSchedule[] = $scheduleItem;
            }
        }




        $returnData = [
            'intvalRank' => $intvalRank, //积分排名
            'historyResult' => !empty($sensus['history']) ? json_decode($sensus['history'], true) : [],
            'recentResult' => !empty($sensus['recent']) ? json_decode($sensus['recent'], true) : [],
            'history' => array_slice($formatHistoryMatches, 9), //历史交锋
            'homeRecent' => FrontService::formatMatchThree(array_slice($homeRecentMatches, 9), 0, []),//主队近期战绩
            'awayRecent' => FrontService::formatMatchThree(array_slice($awayRecentMatches, 9), 0, []),//客队近期战绩
            'homeRecentSchedule' => FrontService::formatMatchThree($homeRecentSchedule, 0, []),//主队近期赛程
            'awayRecentSchedule' => FrontService::formatMatchThree($awayRecentSchedule, 0, []),//客队近期赛程
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);

    }
    /**
     * 直播间公告
     * @return bool
     */
    public function noticeInMatch()
    {
        $setting = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::SETTING_MATCH_NOTICEMENT)->get();

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], isset($setting->sys_value) ? $setting->sys_value : []);

    }

    public function test()
    {
        $time = Cache::get('time');
        $count = Cache::get('count');
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], [$time, $count]);


    }


    public function getMatchInfo()
    {
        if (!isset($this->params['match_id'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        } else if (!$match = AdminMatch::getInstance()->where('match_id', $this->params['match_id'])->get()) {

            return $this->writeJson(Status::CODE_WRONG_MATCH, Status::$msg[Status::CODE_WRONG_MATCH]);

        }

        $formatMatch = FrontService::formatMatchThree([$match], $this->auth['id'], []);
        if (!$return = $formatMatch[0]) {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

        }
        $return = isset($formatMatch[0]) ? $formatMatch[0] : [];
        $competition_id = $return['competition_id'];
        $return['competition_type'] = 0;
        if ($competition = AdminCompetition::create()->where('competition_id', $competition_id)->get()) {
            $return['competition_type'] = $competition->type;
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

    }


    public function getMatchHistory()
    {


        $url = sprintf($this->matchHistory, 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', 3478550);

        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true)['results'];


        if (!$matchTlive = AdminMatchTlive::getInstance()->where('match_id', $resp['id'])->get()) {
            $data = [
                'match_id' => $resp['id'],
                'score' => json_encode($resp['score']),
                'stats' => json_encode($resp['stats']),
                'incidents' => json_encode($resp['incidents']),
                'tlive' => json_encode($resp['tlive']),
            ];
            AdminMatchTlive::getInstance()->insert($data);
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $resp);

    }

}
