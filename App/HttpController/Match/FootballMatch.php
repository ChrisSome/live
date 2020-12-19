<?php
namespace App\HttpController\Match;

use App\Base\FrontUserController;
use App\Common\AppFunc;
use App\HttpController\User\WebSocket;
use App\lib\FrontService;
use App\Model\AdminAlphaMatch;
use App\Model\AdminClashHistory;
use App\Model\AdminCompetition;
use App\Model\AdminCompetitionRuleList;
use App\Model\AdminHonorList;
use App\Model\AdminManagerList;
use App\Model\AdminMatch;
use App\Model\SeasonMatchListOne;
use App\Task\TestTask;
use EasySwoole\Component\Process\Manager;
use App\Model\SeasonAllTableDetail;
use App\Model\AdminMatchTlive;
use App\Model\AdminNoticeMatch;
use App\Model\AdminPlayer;
use App\Model\AdminPlayerChangeClub;
use App\Model\AdminPlayerHonorList;
use App\Model\AdminPlayerStat;
use App\Model\AdminSeason;
use App\Model\AdminStageList;
use App\Model\AdminSteam;
use App\Model\AdminTeam;
use App\Model\AdminTeamHonor;
use App\Model\AdminTeamLineUp;
use App\Model\AdminUser;
use App\Model\AdminUserSetting;
use App\Model\SeasonMatchList;
use App\Model\SeasonTeamPlayer;
use App\Model\SeasonTeamPlayerBak;
use App\Storage\OnlineUser;
use App\Task\MatchNotice;
use App\Utility\Log\Log;
use App\lib\Tool;
use App\Utility\Message\Status;
use App\GeTui\BatchSignalPush;
use App\WebSocket\WebSocketStatus;
use easySwoole\Cache\Cache;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\ORM\DbManager;
use EasySwoole\Redis\Redis as Redis;
use EasySwoole\RedisPool\Redis as RedisPool;
use EasySwoole\EasySwoole\Config;
class FootBallMatch extends FrontUserController
{
    const STATUS_SUCCESS = 0; //请求成功
    protected $isCheckSign = false;
    public $needCheckToken = false;
    public $start_id = 0;
    public $start_time = 0;

    public $taskData = [];
    protected $user = 'mark9527';

    protected $secret = 'dbfe8d40baa7374d54596ea513d8da96';

    protected $url = 'https://open.sportnanoapi.com';

    protected $uriTeamList = '/api/v4/football/team/list?user=%s&secret=%s&time=%s';  //球队列表
    protected $uriTeamList1 = '/api/v4/football/team/list?user=%s&secret=%s&id=%s';  //球队列表

    protected $uriM = 'https://open.sportnanoapi.com/api/v4/football/match/diary?user=%s&secret=%s&date=%s';
    protected $uriCompetition = '/api/v4/football/competition/list?user=%s&secret=%s&time=%s';

    protected $uriStage = '/api/v4/football/stage/list?user=%s&secret=%s&date=%s';

    protected $uriSteam = '/api/sports/stream/urls_free?user=%s&secret=%s'; //直播地址
    protected $uriLineUp = '/api/v4/football/team/squad/list?user=%s&secret=%s&time=%s';  //阵容
    protected $uriPlayer = '/api/v4/football/player/list?user=%s&secret=%s&time=%s';  //球员
    protected $uriCompensation = '/api/v4/football/compensation/list?user=%s&secret=%s&time=%s';  //获取比赛历史同赔统计数据列表
    protected $live_url = 'https://open.sportnanoapi.com/api/sports/football/match/detail_live?user=%s&secret=%s';//比赛列表
    protected $season_url = 'https://open.sportnanoapi.com/api/v4/football/season/list?user=%s&secret=%s&time=%s'; //更新赛季
    protected $player_stat = 'https://open.sportnanoapi.com/api/v4/football/player/list/with_stat?user=%s&secret=%s&time=%s'; //获取球员能力技术列表
    protected $player_change_club_history = 'https://open.sportnanoapi.com/api/v4/football/transfer/list?user=%s&secret=%s&id=%s'; //球员转会历史
    protected $team_honor = 'https://open.sportnanoapi.com/api/v4/football/team/honor/list?user=%s&secret=%s&id=%s'; //球队荣誉
    protected $honor_list = 'https://open.sportnanoapi.com/api/v4/football/honor/list?user=%s&secret=%s&time=%s'; //荣誉详情
    protected $all_stat = 'https://open.sportnanoapi.com/api/v4/football/season/all/stats/detail?user=%s&secret=%s&id=%s'; //获取赛季球队球员统计详情-全量
    protected $stage_list = 'https://open.sportnanoapi.com/api/v4/football/stage/list?user=%s&secret=%s&time=%s'; //获取阶段列表
    protected $manager_list = 'https://open.sportnanoapi.com/api/v4/football/manager/list?user=%s&secret=%s&time=%s'; //教练

    protected $uriDeleteMatch = '/api/v4/football/deleted?user=%s&secret=%s'; //删除或取消的比赛
    protected $player_honor_list = 'https://open.sportnanoapi.com/api/v4/football/player/honor/list?user=%s&secret=%s&time=%s'; //获取球员荣誉列表
    protected $trend_detail = 'https://open.sportnanoapi.com/api/v4/football/match/trend/detail?user=%s&secret=%s&id=%s'; //获取比赛趋势详情
    protected $competition_rule = 'https://open.sportnanoapi.com/api/v4/football/competition/rule/list?user=%s&secret=%s&time=%s'; //获取赛事赛制列表
    protected $history = 'https://open.sportnanoapi.com/api/v4/football/match/live/history?user=%s&secret=%s&id=%s'; //历史比赛数据
    protected $season_all_table_detail = 'https://open.sportnanoapi.com/api/v4/football/season/all/table/detail?user=%s&secret=%s&id=%s'; //获取赛季积分榜数据-全量


    protected $uriPlayerOne = '/api/v4/football/player/list?user=%s&secret=%s&id=%s';  //球员

    /**
     * 获取赛季球队球员统计详情-全量， 一周一次
     */
    public function updateSeasonTeamPlayer()
    {
        $select_season_id = Cache::get('select_season_id') ? Cache::get('select_season_id') : 0;
        $season = AdminSeason::getInstance()->field(['season_id'])->where('season_id', $select_season_id, '>')->all();

        foreach ($season as $item) {
            $url = sprintf($this->all_stat, $this->user, $this->secret, $item->season_id);
            $res = Tool::getInstance()->postApi($url);
            $decodeDatas = json_decode($res, true);
            if ($decodeDatas['code'] == 0) {
                if (!$table = SeasonTeamPlayerBak::getInstance()->where('season_id', $item->season_id)->get()) {
                    $data = [
                        'players_stats' => json_encode($decodeDatas['results']['players_stats']),
                        'shooters' => json_encode($decodeDatas['results']['shooters']),
                        'teams_stats' => json_encode($decodeDatas['results']['teams_stats']),
                        'updated_at' => json_encode($decodeDatas['results']['updated_at']),
                        'season_id' => $item->season_id,
                    ];
                    SeasonTeamPlayerBak::getInstance()->insert($data);
                } else {
                    $table->players_stats = json_encode($decodeDatas['results']['players_stats']);
                    $table->shooters = json_encode($decodeDatas['results']['shooters']);
                    $table->teams_stats = json_encode($decodeDatas['results']['teams_stats']);
                    $table->updated_at = json_encode($decodeDatas['results']['updated_at']);
                    $table->update();
                }
            Cache::set('select_season_id', $item->season_id);
            } else {
                continue;
            }

        }
        return $this->writeJson(Status::CODE_WRONG_MATCH_ORIGIN, Status::$msg[Status::CODE_WRONG_MATCH_ORIGIN], 1);

    }

    /**
     * 更新球队列表  one day / time
     */
    function teamList()
    {

        while (true) {
            $time_stamp = AdminTeam::getInstance()->max('updated_at');
            $url = sprintf($this->url . $this->uriTeamList, $this->user, $this->secret, $time_stamp+1);
            $res = Tool::getInstance()->postApi($url);
            $teams = json_decode($res, true);

            if ($teams['query']['total'] == 0) {
                break;
            }
            $decodeTeams = $teams['results'];
            foreach ($decodeTeams as $team) {
                $data = [
                    'team_id' => $team['id'],
                    'competition_id' => $team['competition_id'],
                    'country_id' => isset($team['country_id']) ? $team['country_id'] : 0,
                    'name_zh' => $team['name_zh'],
                    'short_name_zh' => $team['short_name_zh'],
                    'name_en' => $team['name_en'],
                    'short_name_en' => $team['short_name_en'],
                    'logo' => isset($team['logo']) ? $team['logo'] : '',
                    'national' => $team['national'],
                    'foundation_time' => $team['foundation_time'],
                    'website' => isset($team['website']) ? $team['website'] : '',
                    'manager_id' => $team['manager_id'],
                    'venue_id' => isset($team['venue_id']) ? $team['venue_id'] : 0,
                    'market_value' => isset($team['market_value']) ? $team['market_value'] : '',
                    'market_value_currency' => isset($team['market_value_currency']) ? $team['market_value_currency'] : '',
                    'country_logo' => isset($team['country_logo']) ? $team['country_logo'] : '',
                    'total_players' => isset($team['total_players']) ? $team['total_players'] : 0,
                    'foreign_players' => isset($team['foreign_players']) ? $team['foreign_players'] : 0,
                    'national_players' => isset($team['national_players']) ? $team['national_players'] : 0,
                    'updated_at' => $team['updated_at'],
                ];
                $exist = AdminTeam::getInstance()->where('team_id', $team['id'])->get();
                if ($exist) {
                    unset($data['team_id']);
                    AdminTeam::getInstance()->update($data, ['team_id' => $team['id']]);
                } else {
                    AdminTeam::getInstance()->insert($data);

                }

            }
        }

    }


    /**
     * 当天比赛 十分钟一次
     * @param int $isUpdateYes
     */
    function getTodayMatches($isUpdateYes = 0)
    {

        if (!empty($isUpdateYes)) {
            $time = date("Ymd", strtotime("-1 day"));
        } else {
            $time = date('Ymd');
        }
        $url = sprintf($this->uriM, $this->user, $this->secret, $time);

        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);

        $decodeDatas = $teams['results'];

        if (!$decodeDatas) {
            Log::getInstance()->info(date('Y-d-d H:i:s') . ' 更新无数据');
            return;
        }

        foreach ($decodeDatas as $data) {

            if ($signal = AdminMatch::getInstance()->where('match_id', $data['id'])->get()) {
                $signal->home_scores = json_encode($data['home_scores']);
                $signal->away_scores = json_encode($data['away_scores']);
                $signal->home_position = $data['home_position'];
                $signal->away_position = $data['away_position'];
                $signal->environment = isset($data['environment']) ? json_encode($data['environment']) : '';
                $signal->status_id = $data['status_id'];
                $signal->updated_at = $data['updated_at'];
                $signal->match_time = $data['match_time'];
                $signal->coverage = isset($data['coverage']) ? json_encode($data['coverage']) : '';
                $signal->referee_id = isset($data['referee_id']) ? json_encode($data['referee_id']) : 0;
                $signal->round = isset($data['round']) ? json_encode($data['round']) : '';
                $signal->environment = isset($data['environment']) ? json_encode($data['environment']) : '';
                $signal->update();

            } else {
                $home_team = AdminTeam::getInstance()->where('team_id', $data['home_team_id'])->get();
                $away_team = AdminTeam::getInstance()->where('team_id', $data['away_team_id'])->get();
                if (!$home_team || !$away_team) continue;
                $competition = AdminCompetition::getInstance()->where('competition_id', $data['competition_id'])->get();

                $insertData = [
                    'match_id' => $data['id'],
                    'competition_id' => $data['competition_id'],
                    'home_team_id' => $data['home_team_id'],
                    'away_team_id' => $data['away_team_id'],
                    'match_time' => $data['match_time'],
                    'neutral' => $data['neutral'],
                    'note' => $data['note'],
                    'season_id' => $data['season_id'],
                    'home_scores' => json_encode($data['home_scores']),
                    'away_scores' => json_encode($data['away_scores']),
                    'home_position' => $data['home_position'],
                    'away_position' => $data['away_position'],
                    'coverage' => isset($data['coverage']) ? json_encode($data['coverage']) : '',
                    'venue_id' => isset($data['venue_id']) ? $data['venue_id'] : 0,
                    'referee_id' => isset($data['referee_id']) ? $data['referee_id'] : 0,
                    'round' => isset($data['round']) ? json_encode($data['round']) : '',
                    'environment' => isset($data['environment']) ? json_encode($data['environment']) : '',
                    'status_id' => $data['status_id'],
                    'updated_at' => $data['updated_at'],
                    'home_team_name' => $home_team->short_name_zh ? $home_team->short_name_zh : $home_team->name_zh,
                    'home_team_logo' => $home_team->logo,
                    'away_team_name' => $away_team->short_name_zh ? $away_team->short_name_zh : $away_team->name_zh,
                    'away_team_logo' => $away_team->logo,
                    'competition_name' => $competition->short_name_zh ? $competition->short_name_zh : $competition->name_zh,
                    'competition_color' => $competition->primary_color
                ];
                AdminMatch::create()->data($insertData, false)->save();

                Log::getInstance()->info('insert_match_id-1-' . $data['id']);
            }
        }
        if ($isUpdateYes) {
            Log::getInstance()->info(date('Y-d-d H:i:s') . ' 昨日比赛更新完成');

        } else {
            Log::getInstance()->info(date('Y-d-d H:i:s') . ' 当天比赛更新完成');

        }

    }


    /**
     * 昨天的比赛 十分钟一次  凌晨0-3
     */
    public function updateYesMatch()
    {

        $this->getTodayMatches(1);
    }

    /**
     * 凌晨五点跑一次
     * 更新赛季球队球员统计详情-全量
     * 更新赛季积分榜
     */
    public function updateYesterdayMatch()
    {

        $time = date("Ymd", strtotime("-1 day"));
        $timestamp = strtotime($time);
        $end_timestamp = $timestamp + 60 * 60 *24;
        $match = AdminMatch::getInstance()->where('match_time', $timestamp, '>=')->where('match_time', $end_timestamp, '<')->where('status_id', 8)->all();
        foreach ($match as $match_item) {
            $season_id = $match_item->season_id;
            //更新赛季球队球员统计详情-全量
            $url = sprintf($this->all_stat, $this->user, $this->secret, $season_id);
            $res = Tool::getInstance()->postApi($url);
            $decodeDatas = json_decode($res, true);
            if ($decodeDatas['code'] == 0) {
                if (!$table = SeasonTeamPlayer::getInstance()->where('season_id', $season_id)->get()) {
                    $data = [
                        'players_stats' => json_encode($decodeDatas['results']['players_stats']),
                        'shooters' => json_encode($decodeDatas['results']['shooters']),
                        'teams_stats' => json_encode($decodeDatas['results']['teams_stats']),
                        'updated_at' => json_encode($decodeDatas['results']['updated_at']),
                        'season_id' => $season_id,
                    ];
                    SeasonTeamPlayer::getInstance()->insert($data);
                } else {
                    $table->players_stats = json_encode($decodeDatas['results']['players_stats']);
                    $table->shooters = json_encode($decodeDatas['results']['shooters']);
                    $table->teams_stats = json_encode($decodeDatas['results']['teams_stats']);
                    $table->updated_at = json_encode($decodeDatas['results']['updated_at']);
                    $table->update();
                }

            }
            //更新赛季积分榜

            $url = sprintf($this->season_all_table_detail, $this->user, $this->secret, $season_id);
            $res = Tool::getInstance()->postApi($url);
            $decodeDatas = json_decode($res, true);
            if ($decodeDatas['code'] == 0) {
                if (!$table = SeasonAllTableDetail::getInstance()->where('season_id', $season_id)->get()) {
                    $data = [
                        'promotions' => json_encode($decodeDatas['results']['promotions']),
                        'tables' => json_encode($decodeDatas['results']['tables']),
                        'season_id' => $season_id,
                    ];
                    SeasonAllTableDetail::getInstance()->insert($data);
                } else {
                    $table->promotions = json_encode($decodeDatas['results']['promotions']);
                    $table->tables = json_encode($decodeDatas['results']['tables']);
                    $table->update();
                }

            }


        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);


    }



    /**
     * 未来一周比赛列表 30 min / time
     */
    function getWeekMatches()
    {
        $weeks = FrontService::getWeek();
        foreach ($weeks as $week) {
            $url = sprintf($this->uriM, $this->user, $this->secret, $week);
            $res = Tool::getInstance()->postApi($url);
            $teams = json_decode($res, true);
            $decodeDatas = $teams['results'];
            if (!$decodeDatas) {
                return;
            }
            foreach ($decodeDatas as $data) {

                if ($signal = AdminMatch::getInstance()->where('match_id', $data['id'])->get()) {
                    $signal->home_scores = json_encode($data['home_scores']);
                    $signal->away_scores = json_encode($data['away_scores']);
                    $signal->home_position = $data['home_position'];
                    $signal->away_position = $data['away_position'];
                    $signal->environment = isset($data['environment']) ? json_encode($data['environment']) : '';
                    $signal->status_id = $data['status_id'];
                    $signal->updated_at = $data['updated_at'];
                    $signal->match_time = $data['match_time'];
                    $signal->coverage = isset($data['coverage']) ? json_encode($data['coverage']) : '';
                    $signal->referee_id = isset($data['referee_id']) ? json_encode($data['referee_id']) : 0;
                    $signal->round = isset($data['round']) ? json_encode($data['round']) : '';
                    $signal->environment = isset($data['environment']) ? json_encode($data['environment']) : '';
                    $signal->update();

                } else {
                    $home_team = AdminTeam::getInstance()->where('team_id', $data['home_team_id'])->get();
                    $away_team = AdminTeam::getInstance()->where('team_id', $data['away_team_id'])->get();
                    if (!$home_team || !$away_team) continue;
                    $competition = AdminCompetition::getInstance()->where('competition_id', $data['competition_id'])->get();
                    $insertData = [
                        'match_id' => $data['id'],
                        'competition_id' => $data['competition_id'],
                        'home_team_id' => $data['home_team_id'],
                        'away_team_id' => $data['away_team_id'],
                        'match_time' => $data['match_time'],
                        'neutral' => $data['neutral'],
                        'note' => $data['note'],
                        'season_id' => $data['season_id'],
                        'home_scores' => json_encode($data['home_scores']),
                        'away_scores' => json_encode($data['away_scores']),
                        'home_position' => $data['home_position'],
                        'away_position' => $data['away_position'],
                        'coverage' => isset($data['coverage']) ? json_encode($data['coverage']) : '',
                        'venue_id' => isset($data['venue_id']) ? $data['venue_id'] : 0,
                        'referee_id' => isset($data['referee_id']) ? $data['referee_id'] : 0,
                        'round' => isset($data['round']) ? json_encode($data['round']) : '',
                        'environment' => isset($data['environment']) ? json_encode($data['environment']) : '',
                        'status_id' => $data['status_id'],
                        'updated_at' => $data['updated_at'],
                        'home_team_name' => $home_team->short_name_zh ? $home_team->short_name_zh : $home_team->name_zh,
                        'home_team_logo' => $home_team->logo,
                        'away_team_name' => $away_team->short_name_zh ? $away_team->short_name_zh : $away_team->name_zh,
                        'away_team_logo' => $away_team->logo,
                        'competition_name' => $competition->short_name_zh ? $competition->short_name_zh : $competition->name_zh,
                        'competition_color' => $competition->primary_color
                    ];

                    Log::getInstance()->info('insert_match_id-1-' . $data['id']);

                    AdminMatch::getInstance()->insert($insertData);
                }
            }
        }
        Log::getInstance()->info(date('Y-d-d H:i:s') . ' 未来一周比赛更新完成');

    }

    /**
     * one day / time 赛事列表
     */
    function competitionList()
    {
        $max_updated_at = AdminCompetition::getInstance()->max('updated_at');
        $url = sprintf($this->url . $this->uriCompetition, $this->user, $this->secret, $max_updated_at + 1);

        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);

        if (!$teams['results']) {
            Log::getInstance()->info(date('Y-m-d H:i:s') . ' 更新赛季');
            return;
        }
        $datas = $teams['results'];

        foreach ($datas as $data) {
            $insertData = [
                'competition_id' => $data['id'],
                'category_id' => $data['category_id'],
                'country_id' => $data['country_id'],
                'name_zh' => $data['name_zh'],
                'short_name_zh' => $data['short_name_zh'],
                'type' => $data['type'],
                'cur_season_id' => $data['cur_season_id'],
                'cur_stage_id' => $data['cur_stage_id'],
                'cur_round' => $data['cur_round'],
                'round_count' => $data['round_count'],
                'logo' => $data['logo'],
                'title_holder' => isset($data['title_holder']) ? json_encode($data['title_holder']) : '',
                'most_titles' => isset($data['most_titles']) ? json_encode($data['most_titles']) : '',
                'newcomers' => isset($data['newcomers']) ? json_encode($data['newcomers']) : '',
                'divisions' => isset($data['divisions']) ? json_encode($data['divisions']) : '',
                'host' => isset($data['host']) ? json_encode($data['host']) : '',
                'primary_color' => isset($data['primary_color']) ? $data['primary_color'] : '',
                'secondary_color' => isset($data['secondary_color']) ? $data['secondary_color'] : '',
                'updated_at' => $data['updated_at'],
            ];
            $exist = AdminCompetition::getInstance()->where('competition_id', $data['id'])->get();
            if ($exist) {
                unset($insertData['competition_id']);
                AdminCompetition::getInstance()->update($insertData, ['competition_id' => $data['id']]);
            } else {
                AdminCompetition::getInstance()->insert($insertData);
            }
        }
    }


    /**
     * 直播地址  10min/次
     */
    public function steamList()
    {
        $url = sprintf($this->url . $this->uriSteam, $this->user, $this->secret);
        $res = Tool::getInstance()->postApi($url);
        $steam = json_decode($res, true)['data'];

        if (!$steam) {
            return;
        }
        foreach ($steam as $item) {
            $data = [
                'sport_id' => $item['sport_id'],
                'match_id' => $item['match_id'],
                'match_time' => $item['match_time'],
                'comp' => $item['comp'],
                'home' => $item['home'],
                'away' => $item['away'],
                'mobile_link' => $item['mobile_link'],
                'pc_link' => $item['pc_link'],
            ];

            if (AdminSteam::getInstance()->where('match_id', $item['match_id'])->get()) {
                AdminSteam::getInstance()->update($data, ['match_id' => $item['match_id']]);
            } else {
                AdminSteam::getInstance()->insert($data);

            }
        }
        Log::getInstance()->info('视频直播源更新完毕');

    }


    /**
     * 阵容  one hour / time
     */
    public function getLineUp()
    {
        $time = AdminTeamLineUp::getInstance()->max('updated_at');
        $url = sprintf($this->url . $this->uriLineUp, $this->user, $this->secret, $time);
        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true);
        if (!$resp['results']) {
            return $this->writeJson(Status::CODE_OK, '更新完成');

        }
        foreach ($resp['results'] as $item) {
            $inert = [
                'team_id' => $item['id'],
                'team' => json_encode($item['team']),
                'squad' => json_encode($item['squad']),
                'updated_at' => $item['updated_at'],
            ];
            if (AdminTeamLineUp::getInstance()->where('team_id', $item['id'])->get()) {
                AdminTeamLineUp::getInstance()->update($inert, ['team_id' => $item['id']]);
            } else {
                AdminTeamLineUp::getInstance()->insert($inert);
            }
        }

    }


    /**
     * 更新球员列表  one day / time
     * @return bool
     */
    public function getPlayers()
    {
        while (true) {
            $max_updated_at = AdminPlayer::getInstance()->max('updated_at');

            $url = sprintf($this->url . $this->uriPlayer, $this->user, $this->secret, $max_updated_at + 1);
            $res = Tool::getInstance()->postApi($url);
            $resp = json_decode($res, true);

            if ($resp['code'] == 0) {
                if ($resp['query']['total'] == 0) {
                    break;
                } else {
                    foreach ($resp['results'] as $item) {
                        $inert = [
                            'player_id' => $item['id'],
                            'team_id' => $item['team_id'],
                            'birthday' => $item['birthday'],
                            'age' => $item['age'],
                            'weight' => $item['weight'],
                            'height' => $item['height'],
                            'nationality' => $item['nationality'],
                            'market_value' => $item['market_value'],
                            'market_value_currency' => $item['market_value_currency'],
                            'contract_until' => $item['contract_until'],
                            'position' => $item['position'],
                            'name_zh' => $item['name_zh'],
                            'name_en' => $item['name_en'],
                            'logo' => $item['logo'],
                            'country_id' => $item['country_id'],
                            'preferred_foot' => $item['preferred_foot'],
                            'updated_at' => $item['updated_at'],
                        ];
                        if (AdminPlayer::getInstance()->where('player_id', $item['id'])->get()) {
                            AdminPlayer::getInstance()->update($inert, ['player_id' => $item['id']]);
                        } else {
                            AdminPlayer::getInstance()->insert($inert);

                        }
                    }
                }
            } else {
                break;
            }

        }


    }

    /**
     * 每天凌晨十二点半一次
     */
    public function clashHistory()
    {
        while (true) {
            $timestamp = AdminClashHistory::getInstance()->max('updated_at');
            $url = sprintf($this->url . $this->uriCompensation, $this->user, $this->secret, $timestamp + 1);
            $res = json_decode(Tool::getInstance()->postApi($url), true);
            if ($res['code'] == 0) {
                if ($res['query']['total'] == 0) {
                    return $this->writeJson(Status::CODE_OK, '更新完成');

                } else {
                    foreach ($res['results'] as $item) {
                        $insert = [
                            'match_id' => $item['id'],
                            'history' => json_encode($item['history']),
                            'recent' => json_encode($item['recent']),
                            'similar' => json_encode($item['similar']),
                            'updated_at' => $item['updated_at'],
                        ];
                        if (AdminClashHistory::getInstance()->where('match_id', $item['id'])->get()) {
                            AdminClashHistory::getInstance()->update($insert, ['match_id' => $item['id']]);
                        } else {
                            AdminClashHistory::getInstance()->insert($insert);
                        }
                    }
                }

            } else {
                break;

            }
        }


    }


    /**
     * 每分钟一次
     * 通知用户关注比赛即将开始 提前十五分钟通知
     */
    public function noticeUserMatch()
    {
        $matches = AdminMatch::getInstance()->where('match_time', time() + 60 * 15, '>')->where('match_time', time() + 60 * 16, '<=')->where('status_id', 1)->all();
        if ($matches) {
            foreach ($matches as $match) {
                if (AdminNoticeMatch::getInstance()->where('match_id', $match->id)->where('is_notice', 1)->get()) {
                    continue;
                }
                if (!$prepareNoticeUserIds = AppFunc::getUsersInterestMatch($match->match_id)) {
                    continue;
                } else {
                    $users = AdminUser::getInstance()->where('id', $prepareNoticeUserIds, 'in')->field(['cid', 'id'])->all();

                    foreach ($users as $k => $user) {
                        $userSetting = AdminUserSetting::getInstance()->where('user_id', $user['id'])->get();
                        $startSetting = json_decode($userSetting->push, true)['start'];
                        if (!$userSetting || !$startSetting) {
                            unset($users[$k]);
                        }
                    }
                    $uids = array_column($users, 'id');
                    $cids = array_column($users, 'cid');

                    if (!$uids) {
                        return;
                    }

                    $title = '开赛通知';
                    $content = sprintf('您关注的【%s联赛】%s-%s将于5分钟后开始比赛，不要忘了哦', $match->competitionName()->short_name_zh, $match->homeTeamName()->name_zh, $match->awayTeamName()->name_zh);;
                    $batchPush = new BatchSignalPush();
                    $insertData = [
                        'uids' => json_encode($uids),
                        'match_id' => $match->match_id,
                        'type' => 10,
                        'title' => $title,
                        'content' => $content
                    ];
                    if (!$res = AdminNoticeMatch::getInstance()->where('match_id', $match->match_id)->where('type', 10)->get()) {
                        $rs = AdminNoticeMatch::getInstance()->insert($insertData);
                        $info['rs'] = $rs;  //开赛通知
                        $pushInfo = [
                            'title' => $title,
                            'content' => $content,
                            'payload' => ['item_id' => $match->match_id, 'type' => 1],
                            'notice_id' => $rs,

                        ];
                        $batchPush->pushMessageToList($cids, $pushInfo);


                    } else {
                        //推送失败 直接就不推了
                        return;
                        if ($res->is_notice == 1) {
                            return;
                        }
                        $info['rs'] = $res->id;
                        $batchPush = new BatchSignalPush();

                        $batchPush->pushMessageToList($cids, $info);
                    }
                }


            }
        }
    }

    /**
     * 取消或者删除的比赛   5min/次
     */
    public function deleteMatch()
    {
        $url = sprintf($this->url . $this->uriDeleteMatch, $this->user, $this->secret);
        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true);

        if ($resp['code'] == 0) {
            $dMatches = $resp['results']['match'];
            if ($dMatches) {

                foreach ($dMatches as $dMatch) {
                    if ($match = AdminMatch::getInstance()->where('match_id', $dMatch)->get()) {
                        $match->is_delete = 1;
                        $match->update();
                    }
                }
            }
        }

        Log::getInstance()->info(date('Y-m-d H:i:s') . ' 删除或取消比赛完成');


    }

    /**
     * alpha match 更新直播地址，one minute/time
     */
    public function updateAlphaMatch()
    {
        $params = [
            'matchType' => 'football',
            'matchDate' => date('Ymd')
        ];
        $header = [
            'xcode: ty019'
        ];
        $res = Tool::getInstance()->postApi('http://www.xsports-live.com:8086/live/sport/getLiveInfo', 'GET', $params, $header);
        $decode = json_decode($res, true);
        if ($decode['code'] == 200) {
            $decode_data = $decode['data'];

            foreach ($decode_data as $datum) {
                $data['timeFormart'] = $datum['timeFormart'];
                $data['ligaEn'] = $datum['ligaEn'];
                $data['teams'] = $datum['teams'];
                $data['liga'] = $datum['liga'];
                $data['sportType'] = $datum['sportType'];
                $data['teamsEn'] = $datum['teamsEn'];
                $data['matchTime'] = $datum['matchTime'];
                $data['liveUrl2'] = $datum['liveUrl2'];
                $data['liveUrl3'] = $datum['liveUrl3'];
                $data['liveUrl'] = $datum['liveUrl'];
                $data['matchId'] = $datum['matchId'];
                $data['liveStatus'] = $datum['liveStatus'];
                $data['status'] = $datum['status'];

                if ($signal = AdminAlphaMatch::getInstance()->where('matchId', $datum['matchId'])->get()) {
                    $signal->matchTime = $datum['matchTime'];
                    $signal->liveUrl2 = $datum['liveUrl2'];
                    $signal->liveUrl3 = $datum['liveUrl3'];
                    $signal->liveUrl = $datum['liveUrl'];
                    $signal->liveStatus = $datum['liveStatus'];
                    $signal->status = $datum['status'];
                    $signal->update();
                } else {
                    AdminAlphaMatch::getInstance()->insert($data);
                }

            }
        }
    }






    public function fixMatch()
    {

        $match_id = $this->params['match_id'];
        $url = sprintf('https://open.sportnanoapi.com/api/v4/football/match/live/history?user=%s&secret=%s&id=%s', $this->user, $this->secret, $match_id);

        $res = Tool::getInstance()->postApi($url);
        $decode = json_decode($res, true);
        $decodeDatas = $decode['results'];
//        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $decodeDatas);

        if (!$decodeDatas) return false;
        $match = AdminMatch::getInstance()->where('match_id', $match_id)->get();
        $match->home_scores = json_encode($decodeDatas['score'][2]);
        $match->away_scores = json_encode($decodeDatas['score'][3]);
        $match->status_id = $decodeDatas['score'][1];
        $match->update();
        //比赛趋势
        $match_res = Tool::getInstance()->postApi(sprintf($this->trend_detail, 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', $match_id));
        $match_trend = json_decode($match_res, true);
        if ($match_trend['code'] != 0) {
            $match_trend_info = [];
        } else {
            $match_trend_info = $match_trend['results'];
        }
        $match_tlive_data = [
            'stats' => isset($decodeDatas['stats']) ? json_encode($decodeDatas['stats']) : '',
            'score' => isset($decodeDatas['score']) ? json_encode($decodeDatas['score']) : '',
            'incidents' => isset($decodeDatas['incidents']) ? json_encode($decodeDatas['incidents']) : '',
            'tlive' => isset($decodeDatas['tlive']) ? json_encode($decodeDatas['tlive']) : '',
            'match_id' => $decodeDatas['id'],
            'match_trend' => json_encode($match_trend_info)
        ];
        if (!$res = AdminMatchTlive::getInstance()->where('match_id', $match_id)->get()) {
            AdminMatchTlive::getInstance()->insert($match_tlive_data);
        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);
    }

    /**
     * 更新赛季 1hour/次
     */
    public function updateSeason()
    {

        $max_updated_at = AdminSeason::getInstance()->max('updated_at');

        $url = sprintf($this->season_url, $this->user, $this->secret, $max_updated_at + 1);

        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true);

        if ($resp['code'] == 0) {
            if ($resp['query']['total'] == 0) {
                return;
            }
            $decode = $resp['results'];
            if ($decode) {
                foreach ($decode as $item) {


                    if (!$season = AdminSeason::getInstance()->where('season_id', $item['id'])->get()) {
                        $data = [
                            'season_id' => $item['id'],
                            'competition_id' => $item['competition_id'],
                            'year' => $item['year'],
                            'updated_at' => $item['updated_at'],
                            'start_time' => $item['start_time'],
                            'end_time' => $item['end_time'],
                            'competition_rule_id' => $item['competition_rule_id'],
                            'has_player_stats' => $item['has_player_stats'],
                            'has_team_stats' => $item['has_team_stats'],
                            'has_table' => $item['has_table'],
                            'is_current' => $item['is_current'],
                        ];
                        AdminSeason::getInstance()->insert($data);
                    } else {
                        $season->competition_id = $item['competition_id'];
                        $season->year = $item['year'];
                        $season->updated_at = $item['updated_at'];
                        $season->start_time = $item['start_time'];
                        $season->end_time = $item['end_time'];
                        $season->competition_rule_id = $item['competition_rule_id'];
                        $season->has_player_stats = $item['has_player_stats'];
                        $season->has_team_stats = $item['has_team_stats'];
                        $season->has_player_stats = $item['has_player_stats'];
                        $season->has_table = $item['has_table'];
                        $season->is_current = $item['is_current'];
                        $season->update();


                    }

                }
            }
        }
    }

    /**
     * 获取球员能力技术列表 1hour/次
     */
    public function updatePlayerStat()
    {
        while (true){

            $max = AdminPlayerStat::getInstance()->max('updated_at');

            $url = sprintf($this->player_stat, $this->user, $this->secret, $max+1);
            $res = Tool::getInstance()->postApi($url);
            $resp = json_decode($res, true);

            if ($resp['code'] == 0) {
                if ($resp['query']['total'] == 0) {
                    return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

                }
                $decode = $resp['results'];

                if ($decode) {

                    foreach ($decode as $item) {
                        $data = [
                            'player_id' => $item['id'],
                            'team_id' => $item['team_id'],
                            'birthday' => $item['birthday'],
                            'age' => $item['age'],
                            'weight' => $item['weight'],
                            'height' => $item['height'],
                            'nationality' => $item['nationality'],
                            'market_value' => $item['market_value'],
                            'market_value_currency' => $item['market_value_currency'],
                            'contract_until' => $item['contract_until'],
                            'position' => $item['position'],
                            'name_zh' => $item['name_zh'],
                            'short_name_zh' => $item['short_name_zh'],
                            'name_en' => $item['name_en'],
                            'short_name_en' => $item['short_name_en'],
                            'logo' => $item['logo'],
                            'country_id' => $item['country_id'],
                            'preferred_foot' => $item['preferred_foot'],
                            'updated_at' => $item['updated_at'],
                            'ability' => !isset($item['ability']) ? '' : json_encode($item['ability']),
                            'characteristics' => !isset($item['characteristics']) ? '' : json_encode($item['characteristics']),
                            'positions' => !isset($item['positions']) ? '' : json_encode($item['positions']),
                        ];
                        if (!$player = AdminPlayerStat::getInstance()->where('player_id', $item['id'])->get()) {

                            AdminPlayerStat::getInstance()->insert($data);
                        } else {
                            AdminPlayerStat::getInstance()->update($data, ['player_id' => $item['id']]);
                        }
                    }
                }
            } else {
                break;
            }
        }
    }


    /**
     * 球员转会历史  one day / time
     * @return bool
     */
    public function playerChangeClubHistory()
    {
        while(true){
            $max = AdminPlayerChangeClub::getInstance()->max('updated_at');

            $url = sprintf($this->player_change_club_history, $this->user, $this->secret, $max+1);
            $res = Tool::getInstance()->postApi($url);
            $resp = json_decode($res, true);

            if ($resp['code'] == 0) {
                if ($resp['query']['total'] == 0) {
                    return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

                }
                $decode = $resp['results'];

                if ($decode) {

                    foreach ($decode as $item) {
                        $data = [
                            'id' => $item['id'],
                            'player_id' => $item['player_id'],
                            'from_team_id' => $item['from_team_id'],
                            'from_team_name' => $item['from_team_name'],
                            'to_team_id' => $item['to_team_id'],
                            'to_team_name' => $item['to_team_name'],
                            'transfer_type' => $item['transfer_type'],
                            'transfer_time' => $item['transfer_time'],
                            'transfer_fee' => $item['transfer_fee'],
                            'transfer_desc' => $item['transfer_desc'],
                            'updated_at' => $item['updated_at'],
                        ];
                        if (!AdminPlayerChangeClub::getInstance()->where('id', $item['id'])->get()) {

                            AdminPlayerChangeClub::getInstance()->insert($data);
                        } else {
                            unset($data['id']);
                            AdminPlayerChangeClub::getInstance()->update($data, ['id' => $item['id']]);
                        }
                    }
                }
            } else {
                break;
            }
        }
    }

    /**
     * 球队荣誉  one day / time
     * @return bool
     */
    public function teamHonor()
    {

        while (true){
            $max = AdminTeamHonor::getInstance()->max('updated_at');
            $url = sprintf($this->team_honor, $this->user, $this->secret, $max + 1);
            $res = Tool::getInstance()->postApi($url);
            $resp = json_decode($res, true);

            if ($resp['code'] == 0) {
                if ($resp['query']['total'] == 0) {
                    return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

                }
                $decode = $resp['results'];
                foreach ($decode as $item) {
                    $data = [
                        'team_id' => $item['id'],
                        'honors' => json_encode($item['honors']),
                        'team' => json_encode($item['team']),
                        'update_at' => $item['updated_at']
                    ];
                    if (!AdminTeamHonor::getInstance()->where('team_id', $item['id'])->get()) {
                        AdminTeamHonor::getInstance()->insert($data);
                    } else {
                        $team_id = $data['team_id'];
                        unset($data['team_id']);
                        AdminTeamHonor::getInstance()->update($data, ['team_id'=> $team_id]);
                    }
                }
                $this->start_id = $resp['query']['max_id'];
            } else {
                break;
            }
        }

    }

    /**
     * 荣誉详情  one day /time
     * @return bool
     */
    public function honorList()
    {


        while (true){
            $max = AdminHonorList::getInstance()->max('updated_at');
            $url = sprintf($this->honor_list, $this->user, $this->secret, $max + 1);
            $res = Tool::getInstance()->postApi($url);
            $resp = json_decode($res, true);

            if ($resp['code'] == 0) {
                if ($resp['query']['total'] == 0) {
                    return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

                }
                $decode = $resp['results'];
                foreach ($decode as $item) {
                    $data = [
                        'id' => $item['id'],
                        'title_zh' => $item['title_zh'],
                        'logo' => $item['logo'],
                        'updated_at' => $item['updated_at']
                    ];
                    if (!AdminHonorList::getInstance()->where('id', $item['id'])->get()) {
                        AdminHonorList::getInstance()->insert($data);
                    } else {
                        $id = $data['id'];
                        unset($data['id']);
                        AdminHonorList::getInstance()->update($data, ['id' => $id]);
                    }
                }
            } else {
                break;
            }
        }

    }

    /**
     * 阶段列表  one day /time
     */
    public function stageList()
    {

        while (true){
            $max = AdminStageList::getInstance()->max('updated_at');
            $url = sprintf($this->stage_list, $this->user, $this->secret, $max + 1);
            $res = Tool::getInstance()->postApi($url);
            $resp = json_decode($res, true);

            if ($resp['code'] == 0) {
                if ($resp['query']['total'] == 0) {
                    break;

                }
                $decode = $resp['results'];
                foreach ($decode as $item) {
                    $data = [
                        'stage_id' => $item['id'],
                        'season_id' => $item['season_id'],
                        'name_zh' => $item['name_zh'],
                        'name_zht' => $item['name_zht'],
                        'name_en' => $item['name_en'],
                        'mode' => $item['mode'],
                        'group_count' => $item['group_count'],
                        'round_count' => $item['round_count'],
                        'order' => $item['order'],
                        'updated_at' => $item['updated_at'],
                    ];
                    if (!AdminStageList::getInstance()->where('stage_id', $item['id'])->get()) {
                        AdminStageList::getInstance()->insert($data);
                    } else {

                        AdminStageList::getInstance()->update($data, ['stage_id'=>$item['id']]);
                    }
                }
                $this->start_id = $resp['query']['max_id'];
            } else {
                break;
            }
        }

    }

    /**
     * 教练列表  one day /time
     */
    public function managerList()
    {


        while (true){
            $manager_id = AdminManagerList::getInstance()->max('updated_at');

            $url = sprintf($this->manager_list, $this->user, $this->secret, $manager_id);
            $res = Tool::getInstance()->postApi($url);
            $resp = json_decode($res, true);

            if ($resp['code'] == 0) {
                if ($resp['query']['total'] == 0) {
                    break;
                }
                $decode = $resp['results'];
                foreach ($decode as $item) {

                    $data = [
                        'manager_id' => $item['id'],
                        'team_id' => $item['team_id'],
                        'name_zh' => $item['name_zh'],
                        'name_en' => $item['name_en'],
                        'logo' => $item['logo'],
                        'age' => $item['age'],
                        'birthday' => $item['birthday'],
                        'preferred_formation' => $item['preferred_formation'],
                        'nationality' => $item['nationality'],
                        'updated_at' => $item['updated_at'],
                    ];
                    if (!AdminManagerList::getInstance()->where('manager_id', $item['id'])->get()) {
                        AdminManagerList::getInstance()->insert($data);
                    } else {
                        AdminManagerList::getInstance()->update($data, ['manager_id' => $item['id']]);
                    }
                }
            } else {
                break;
            }
        }

    }


    /**
     * 球员荣誉列表
     */
    public function playerHonorList()
    {

        while (true){
            $max = AdminPlayerHonorList::getInstance()->max('updated_at');

            $url = sprintf($this->player_honor_list, $this->user, $this->secret, $max + 1);
            $res = Tool::getInstance()->postApi($url);
            $resp = json_decode($res, true);

            if ($resp['code'] == 0) {
                if ($resp['query']['total'] == 0) {
                    break;

                }
                $decode = $resp['results'];

                foreach ($decode as $item) {

                    $data = [
                        'player_id' => $item['id'],
                        'player' => json_encode($item['player']),
                        'honors' => json_encode($item['honors']),
                        'updated_at' => $item['updated_at'],
                    ];
                    if (!AdminPlayerHonorList::getInstance()->where('player_id', $item['id'])->get()) {
                        AdminPlayerHonorList::getInstance()->insert($data);
                    } else {

                        AdminPlayerHonorList::getInstance()->update($data, ['player_id' => $item['id']]);
                    }
                }
            } else {
                break;
            }
        }
    }

    /**
     * 赛制列表 one day /time
     */
    public function competitionRule()
    {

        while (true){
            $start_id = AdminCompetitionRuleList::getInstance()->max('updated_at');

            $url = sprintf($this->competition_rule, $this->user, $this->secret, $start_id + 1);
            $res = Tool::getInstance()->postApi($url);
            $resp = json_decode($res, true);

            if ($resp['code'] == 0) {
                if ($resp['query']['total'] == 0) {
                    break;
                }
                $decode = $resp['results'];
                foreach ($decode as $item) {

                    $data = [
                        'id' => $item['id'],
                        'competition_id' => $item['competition_id'],
                        'season_ids' => json_encode($item['season_ids']),
                        'text' => $item['text'],
                        'updated_at' => $item['updated_at'],
                    ];
                    if (!AdminCompetitionRuleList::getInstance()->where('id', $item['id'])->get()) {
                        AdminCompetitionRuleList::getInstance()->insert($data);
                    } else {

                        AdminCompetitionRuleList::getInstance()->update($data, ['id' => $item['id']]);
                    }
                }
            } else {
                break;
            }
        }
    }




    public function matchTlive()
    {
        $res = Tool::getInstance()->postApi(sprintf($this->live_url, $this->user, $this->secret));

        if ($decode = json_decode($res, true)) {
            $match_info = [];
            foreach ($decode as $item) {

                //无效比赛 跳过
                if (!$match = AdminMatch::getInstance()->where('match_id', $item['id'])->get()) {
                    continue;
                }

                //比赛结束 跳过
                if (AdminMatchTlive::getInstance()->where('match_id', $item['id'])->get()) {
                    continue;
                }
                $status = $item['score'][1];
                if (!in_array($status, [1, 2, 3, 4, 5, 7, 8])) { //上半场 / 下半场 / 中场 / 加时赛 / 点球决战 / 结束
                    continue;
                }

                //比赛结束通知
                if ($item['score'][1] == 8) { //结束
                    TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'],  'item' => $item,'score' => $item['score'],  'type'=>12]));
                }

                //不在热门赛事中  跳过
                if (!AppFunc::isInHotCompetition($match->competition_id)) {
                    continue;
                }

                //比赛趋势
                $match_res = Tool::getInstance()->postApi(sprintf($this->trend_detail, 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', $item['id']));
                $match_trend = json_decode($match_res, true);
                if ($match_trend['code'] != 0) {
                    $match_trend_info = [];
                } else {
                    $match_trend_info = $match_trend['results'];
                }
                //设置比赛进行时间
                AppFunc::setPlayingTime($item['id'], $item['score']);
                //比赛开始的通知
                if ($item['score'][1] == 2 && !Cache::get('match_notice_start:' . $item['id'])) { //开始
                    TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'], 'score' => $item['score'],'item' => $item,  'type'=>10]));
                    Cache::set('match_notice_start:' . $item['id'], 1, 60 * 240);
                }
                $matchStats = [];
                if (isset($item['stats'])) {
                    foreach ($item['stats'] as $ki => $vi) {
                        // 21：射正 22：射偏  23:进攻  24危险进攻 25：控球率
                        if ($vi['type'] == 21 || $vi['type'] == 22 || $vi['type'] == 23 || $vi['type'] == 24 || $vi['type'] == 25) {
                            $matchStats[] = $vi;
                        }

                    }
                    Cache::set('match_stats_' . $item['id'], json_encode($matchStats), 60 * 240);

                }
                $corner_count_tlive = [];
                $match_tlive_count_new = 0;

                if (isset($item['tlive'])) {
                    $match_tlive_count_old = Cache::get('match_tlive_count' . $item['id']) ?: 0;
                    $match_tlive_count_new = count($item['tlive']);
                    if ($match_tlive_count_new > $match_tlive_count_old) { //直播文字
                        Cache::set('match_tlive_count' . $item['id'], $match_tlive_count_new, 60 * 240);
                        $diff = array_slice($item['tlive'], $match_tlive_count_old);
                        (new WebSocket())->contentPush($diff, $item['id']);
                    }

                    $corner_count_new = 0;

                    foreach ($item['tlive'] as $signal_tlive) {

                        if ($signal_tlive['type'] == 2) { //角球
                            $corner_count_new += 1;
                            $format_signal_corner_tlive = ['time' => intval($signal_tlive['time']), 'type' => $signal_tlive['type'], 'position' => $signal_tlive['position']];
                            $corner_count_tlive[] = $format_signal_corner_tlive;
                        } else {
                            continue;
                        }
                    }
                    Cache::set('match_tlive_' . $item['id'], json_encode($item['tlive']), 60 * 240);

                }
                $last_goal_tlive = [];
                $last_yellow_card_tlive = [];
                $last_red_card_tlive = [];

                $goal_count_new = 0;
                $yellow_card_count_new = 0;
                $red_card_count_new = 0;

                $goal_tlive_total = [];
                $yellow_card_tlive_total = [];
                $red_card_tlive_total = [];

                if (isset($item['incidents'])) {

                    $goal_count_old = Cache::get('goal_count_' . $item['id']);
                    $yellow_card_count_old = Cache::get('yellow_card_count' . $item['id']);
                    $red_card_count_old = Cache::get('red_card_count' . $item['id']);

                    foreach ($item['incidents'] as $item_tlive) {
                        $match_tlive_count_new += 1;
                        $signal_incident = [
                            'time' => intval($item_tlive['time']),
                            'type' => $item_tlive['type'],
                            'position' => $item_tlive['position'],
                        ];
                        if ($signal_incident['type'] == 1 || $signal_incident['type'] == 8) { //进球 /点球
                            $last_goal_tlive = $signal_incident;
                            $goal_count_new += 1;
                            $goal_tlive_total[] = $signal_incident;
                        } else if ($signal_incident['type'] == 3) { //黄牌
                            $last_yellow_card_tlive = $signal_incident;
                            $yellow_card_count_new += 1;
                            $yellow_card_tlive_total[] = $signal_incident;
                        }else if ($signal_incident['type'] == 4) { //红牌
                            $last_red_card_tlive = $signal_incident;
                            $red_card_count_new += 1;
                            $red_card_tlive_total[] = $signal_incident;
                        } else {
                            continue;
                        }

                    }

                    if ($goal_count_new > $goal_count_old) {//进球
                        TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'], 'last_incident' => $last_goal_tlive, 'score' => $item['score'], 'type'=>1]));
                        Cache::set('goal_count_' . $item['id'], $goal_count_new, 60 * 240);
                    }

                    if ($yellow_card_count_new > $yellow_card_count_old) {//黄牌

                        TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'], 'last_incident' => $last_yellow_card_tlive, 'score' => $item['score'], 'type'=>3]));
                        Cache::set('yellow_card_count' . $item['id'], $yellow_card_count_new, 60 * 240);

                    }

                    if ($red_card_count_new > $red_card_count_old) {//红牌

                        TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'], 'last_incident' => $last_red_card_tlive, 'score' => $item['score'], 'type'=>4]));
                        Cache::set('red_card_count' . $item['id'], $red_card_count_new, 60 * 240);

                    }


                }
                $signal_match_info['signal_count'] = ['corner' => $corner_count_tlive, 'goal' => $goal_tlive_total, 'yellow' => $yellow_card_tlive_total, 'red' => $red_card_tlive_total];
                $signal_match_info['match_trend'] = $match_trend_info;
                $signal_match_info['match_id'] = $item['id'];
                $signal_match_info['time'] = AppFunc::getPlayingTime($item['id']);
                $signal_match_info['status'] = $status;
                $signal_match_info['match_stats'] = $matchStats;
                $signal_match_info['score'] = [
                    'home' => $item['score'][2],
                    'away' => $item['score'][3]
                ];

                $match_info[] = $signal_match_info;
                Cache::set('match_data_info' .$item['id'], json_encode($signal_match_info), 60 * 240);
                unset($signal_match_info);
            }
            /**
             * 异步的话要做进程间通信，本身也有开销，不如做成同步的，push将数据交给底层，本身不等待
             */
//            $update_task_status = TaskManager::getInstance()->async(new MatchUpdate(['match_info_list' => $match_info]));
//            if ($update_task_status <= 0) {
//                Log::getInstance()->info('delivery failed, match list info-' . json_encode($match_info));
//            }

            if (!empty($match_info)) {
                $tool = Tool::getInstance();
                $server = ServerManager::getInstance()->getSwooleServer();
                $start_fd = 0;
                $returnData = [
                    'event' => 'match_update',
                    'match_info_list' => isset($match_info) ? $match_info : []
                ];
                while (true) {
                    $conn_list = $server->getClientList($start_fd, 10);
                    if (!$conn_list || count($conn_list) === 0) {
                        break;
                    }
                    $start_fd = end($conn_list);

                    foreach ($conn_list as $fd) {
                        $connection = $server->connection_info($fd);
                        if (is_array($connection) && $connection['websocket_status'] == 3) {  // 用户正常在线时可以进行消息推送

                            Log::getInstance()->info('push succ' . $fd);
                            $server->push($fd, $tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $returnData));
                        } else {
                            Log::getInstance()->info('lost-connection-' . $fd);
                        }
                    }
                }
            } else {
                Log::getInstance()->info('333333333');

            }

        }
    }

    /**
     * 赛季比赛列表  一天一次
     */
    public function updateMatchSeason1()
    {

//        $season_id = Cache::get('update_season_id');
        $season_id = SeasonMatchList::getInstance()->max('season_id');
        $select_season_id = isset($season_id) ? $season_id : 0;
        $season = AdminSeason::getInstance()->field(['season_id'])->where('season_id', $select_season_id, '>')->limit(2000)->all();

        foreach ($season as $item) {
            $res = Tool::getInstance()->postApi(sprintf('https://open.sportnanoapi.com/api/v4/football/match/season?user=%s&secret=%s&id=%s', 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', $item['season_id']));
            $decode = json_decode($res, true);

            if ($decode['code'] == 0) {
                if ($decode['total'] == '0') {
                    continue;
                }
                if ($results = $decode['results']) {

                    foreach ($results as $data) {
                        if (SeasonMatchList::getInstance()->get(['match_id' => $data['id']])) continue;
                        $home_team = AdminTeam::getInstance()->where('team_id', $data['home_team_id'])->get();
                        $away_team = AdminTeam::getInstance()->where('team_id', $data['away_team_id'])->get();
                        $competition = AdminCompetition::getInstance()->where('competition_id', $data['competition_id'])->get();
                        $insertData = [
                            'match_id' => $data['id'],
                            'competition_id' => $data['competition_id'],
                            'home_team_id' => $data['home_team_id'],
                            'away_team_id' => $data['away_team_id'],
                            'match_time' => $data['match_time'],
                            'neutral' => $data['neutral'],
                            'note' => $data['note'],
                            'season_id' => $data['season_id'],
                            'home_scores' => json_encode($data['home_scores']),
                            'away_scores' => json_encode($data['away_scores']),
                            'home_position' => $data['home_position'],
                            'away_position' => $data['away_position'],
                            'coverage' => isset($data['coverage']) ? json_encode($data['coverage']) : '',
                            'venue_id' => isset($data['venue_id']) ? $data['venue_id'] : 0,
                            'referee_id' => isset($data['referee_id']) ? $data['referee_id'] : 0,
                            'round' => isset($data['round']) ? json_encode($data['round']) : '',
                            'environment' => isset($data['environment']) ? json_encode($data['environment']) : '',
                            'status_id' => $data['status_id'],
                            'updated_at' => $data['updated_at'],
                            'home_team_name' => $home_team->short_name_zh ? $home_team->short_name_zh : $home_team->name_zh,
                            'home_team_logo' => $home_team->logo,
                            'away_team_name' => $away_team->short_name_zh ? $away_team->short_name_zh : $away_team->name_zh,
                            'away_team_logo' => $away_team->logo,
                            'competition_name' => $competition->short_name_zh ? $competition->short_name_zh : $competition->name_zh,
                            'competition_color' => $competition->primary_color
                        ];
                        SeasonMatchList::getInstance()->insert($insertData);
                    }
                } else {
                    continue;
                }

            } else {
                continue;
            }
        }

    }


    /**
     * 赛季比赛列表  一天一次
     */
    public function updateMatchSeason()
    {

        $season_id = Cache::get('update_season_id');
        $season_id = SeasonMatchList::getInstance()->max('season_id');
        $select_season_id = isset($season_id) ? $season_id : 0;
        $season = AdminSeason::getInstance()->field(['season_id'])->where('season_id', $select_season_id, '>')->limit(2000)->all();

        foreach ($season as $item) {
            $res = Tool::getInstance()->postApi(sprintf('https://open.sportnanoapi.com/api/v4/football/match/season?user=%s&secret=%s&id=%s', 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', $item['season_id']));
            $decode = json_decode($res, true);

            if ($decode['code'] == 0) {
                if ($decode['total'] == '0') {
                    continue;
                }
                if ($results = $decode['results']) {

                    foreach ($results as $data) {
                        if (SeasonMatchList::getInstance()->get(['match_id' => $data['id']])) continue;
                        $home_team = AdminTeam::getInstance()->where('team_id', $data['home_team_id'])->get();
                        $away_team = AdminTeam::getInstance()->where('team_id', $data['away_team_id'])->get();
                        $competition = AdminCompetition::getInstance()->where('competition_id', $data['competition_id'])->get();
                        $insertData = [
                            'match_id' => $data['id'],
                            'competition_id' => $data['competition_id'],
                            'home_team_id' => $data['home_team_id'],
                            'away_team_id' => $data['away_team_id'],
                            'match_time' => $data['match_time'],
                            'neutral' => $data['neutral'],
                            'note' => $data['note'],
                            'season_id' => $data['season_id'],
                            'home_scores' => json_encode($data['home_scores']),
                            'away_scores' => json_encode($data['away_scores']),
                            'home_position' => $data['home_position'],
                            'away_position' => $data['away_position'],
                            'coverage' => isset($data['coverage']) ? json_encode($data['coverage']) : '',
                            'venue_id' => isset($data['venue_id']) ? $data['venue_id'] : 0,
                            'referee_id' => isset($data['referee_id']) ? $data['referee_id'] : 0,
                            'round' => isset($data['round']) ? json_encode($data['round']) : '',
                            'environment' => isset($data['environment']) ? json_encode($data['environment']) : '',
                            'status_id' => $data['status_id'],
                            'updated_at' => $data['updated_at'],
                            'home_team_name' => $home_team->short_name_zh ? $home_team->short_name_zh : $home_team->name_zh,
                            'home_team_logo' => $home_team->logo,
                            'away_team_name' => $away_team->short_name_zh ? $away_team->short_name_zh : $away_team->name_zh,
                            'away_team_logo' => $away_team->logo,
                            'competition_name' => $competition->short_name_zh ? $competition->short_name_zh : $competition->name_zh,
                            'competition_color' => $competition->primary_color
                        ];
                        SeasonMatchList::getInstance()->insert($insertData);
                    }
                } else {
                    continue;
                }

            } else {
                continue;
            }
        }

    }


    public function test()
    {
        ini_set("max_execution_time", 1000);
//        $season_id = Cache::get('season_match_list_seasonId-');
        $season_id = SeasonMatchListOne::getInstance()->max('season_id');
        $season = AdminSeason::getInstance()->field(['season_id'])->where('season_id', $season_id, '>')->limit(2000)->all();
        foreach ($season as $item) {
            Cache::set('season_match_list_seasonId-', $item['id']);
            $res = Tool::getInstance()->postApi(sprintf('https://open.sportnanoapi.com/api/v4/football/match/season?user=%s&secret=%s&id=%s', 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', $item['season_id']));
            $decode = json_decode($res, true);
            if ($decode['code'] == 0) {

                if ($results = $decode['results']) {

                    foreach ($results as $data) {

                        if (SeasonMatchListOne::getInstance()->get(['match_id' => $data['id']])) continue;
                        $home_team = AdminTeam::getInstance()->where('team_id', $data['home_team_id'])->get();
                        $away_team = AdminTeam::getInstance()->where('team_id', $data['away_team_id'])->get();
                        $competition = AdminCompetition::getInstance()->where('competition_id', $data['competition_id'])->get();
                        $insertData = [
                            'match_id' => $data['id'],
                            'competition_id' => $data['competition_id'],
                            'home_team_id' => $data['home_team_id'],
                            'away_team_id' => $data['away_team_id'],
                            'match_time' => $data['match_time'],
                            'neutral' => $data['neutral'],
                            'note' => $data['note'],
                            'season_id' => $data['season_id'],
                            'home_scores' => json_encode($data['home_scores']),
                            'away_scores' => json_encode($data['away_scores']),
                            'home_position' => $data['home_position'],
                            'away_position' => $data['away_position'],
                            'coverage' => isset($data['coverage']) ? json_encode($data['coverage']) : '',
                            'venue_id' => isset($data['venue_id']) ? $data['venue_id'] : 0,
                            'referee_id' => isset($data['referee_id']) ? $data['referee_id'] : 0,
                            'round' => isset($data['round']) ? json_encode($data['round']) : '',
                            'environment' => isset($data['environment']) ? json_encode($data['environment']) : '',
                            'status_id' => $data['status_id'],
                            'updated_at' => $data['updated_at'],
                            'home_team_name' => $home_team->short_name_zh ? $home_team->short_name_zh : $home_team->name_zh,
                            'home_team_logo' => $home_team->logo,
                            'away_team_name' => $away_team->short_name_zh ? $away_team->short_name_zh : $away_team->name_zh,
                            'away_team_logo' => $away_team->logo,
                            'competition_name' => $competition->short_name_zh ? $competition->short_name_zh : $competition->name_zh,
                            'competition_color' => $competition->primary_color
                        ];
                        SeasonMatchListOne::getInstance()->insert($insertData);
                    }
                } else {
                    continue;
                }

            } else {
                continue;
            }
        }

return ;



//        $code = Tool::getInstance()->generateCode();
//        //异步task
//
//        $res = TaskManager::getInstance()->async(new TestTask([
//            'code' => $code,
//            'mobile' => '15670660962',
//            'name' => '短信验证码'
//        ]));
//
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);


    }

}
