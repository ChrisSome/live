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
use App\Model\AdminInterestMatches;
use App\Model\AdminManagerList;
use App\Model\AdminMatch;
use App\Storage\OnlineUser;
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
use App\Task\MatchNotice;
use App\Utility\Log\Log;
use App\lib\Tool;
use App\Utility\Message\Status;
use App\GeTui\BatchSignalPush;
use App\WebSocket\WebSocketStatus;
use easySwoole\Cache\Cache;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Task\TaskManager;

/**
 *                             _ooOoo_
 *                            o8888888o
 *                            88" . "88
 *                            (| -_- |)
 *                            O\  =  /O
 *                         ____/`---'\____
 *                       .'  \\|     |//  `.
 *                      /  \\|||  :  |||//  \
 *                     /  _||||| -:- |||||-  \
 *                     |   | \\\  -  /// |   |
 *                     | \_|  ''\---/''  |   |
 *                     \  .-\__  `-`  ___/-. /
 *                   ___`. .'  /--.--\  `. . __
 *                ."" '<  `.___\_<|>_/___.'  >'"".
 *               | | :  `- \`.;`\ _ /`;.`/ - ` : | |
 *               \  \ `-.   \_ __\ /__ _/   .-` /  /
 *          ======`-.____`-.___\_____/___.-`____.-'======
 *                             `=---='
 *          ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
 *                     佛祖保佑        永无BUG
 */
class Crontab extends FrontUserController
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
    protected $player_change_club_history = 'https://open.sportnanoapi.com/api/v4/football/transfer/list?user=%s&secret=%s&time=%s'; //球员转会历史
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
     * 更新球队列表  一天一次
     */
    function getTeamList()
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
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
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
            $home_team = AdminTeam::getInstance()->where('team_id', $data['home_team_id'])->get();
            $away_team = AdminTeam::getInstance()->where('team_id', $data['away_team_id'])->get();
            if (!$home_team || !$away_team) continue;
            $competition = AdminCompetition::getInstance()->where('competition_id', $data['competition_id'])->get();

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
                $signal->referee_id = isset($data['referee_id']) ? intval($data['referee_id']) : 0;
                $signal->round = isset($data['round']) ? json_encode($data['round']) : '';
                $signal->update();

            } else {

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
                    'competition_name' => $competition ? $competition->short_name_zh : '',
                    'competition_color' => $competition ? $competition->primary_color : ''
                ];

                AdminMatch::getInstance()->insert($insertData);

            }

            //更新赛季比赛列表 （有新赛季或者新阶段的时候新增）
            if ($signal_season_match = SeasonMatchList::getInstance()->where('match_id', $data['id'])->get()) {
                $signal_season_match->home_scores = json_encode($data['home_scores']);
                $signal_season_match->away_scores = json_encode($data['away_scores']);
                $signal_season_match->home_position = $data['home_position'];
                $signal_season_match->away_position = $data['away_position'];
                $signal_season_match->environment = isset($data['environment']) ? json_encode($data['environment']) : '';
                $signal_season_match->status_id = $data['status_id'];
                $signal_season_match->updated_at = $data['updated_at'];
                $signal_season_match->match_time = $data['match_time'];
                $signal_season_match->coverage = isset($data['coverage']) ? json_encode($data['coverage']) : '';
                $signal_season_match->referee_id = isset($data['referee_id']) ? intval($data['referee_id']) : 0;
                $signal_season_match->round = isset($data['round']) ? json_encode($data['round']) : '';
                $signal_season_match->environment = isset($data['environment']) ? json_encode($data['environment']) : '';
                $signal_season_match->update();
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
                    'competition_name' => $competition ? $competition->short_name_zh : '',
                    'competition_color' => $competition ? $competition->primary_color : ''
                ];
                SeasonMatchList::getInstance()->insert($insertData);
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
            if (isset($season_id_arr) && in_array($season_id, $season_id_arr)){
                continue;
            }
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
            $season_id_arr[] = $season_id;


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
                        'competition_name' => isset($competition->short_name_zh) ? $competition->short_name_zh : $competition->name_zh,
                        'competition_color' => isset($competition->primary_color) ? $competition->primary_color : ''
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
    function getCompetitiones()
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
    public function getSteam()
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
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function players()
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
     * 赛季同赔信息
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
                        'content' => $content,
                        'item_type' => 1
                    ];
                    if (!$res = AdminNoticeMatch::getInstance()->where('match_id', $match->match_id)->where('type', 10)->get()) {
                        $rs = AdminNoticeMatch::getInstance()->insert($insertData);
                        $info['rs'] = $rs;  //开赛通知
                        $pushInfo = [
                            'title' => $title,
                            'content' => $content,
                            'payload' => ['item_id' => $match->match_id, 'item_type' => 1],
                            'notice_id' => $rs,

                        ];
                        $batchPush->pushMessageToList($cids, $pushInfo);

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

        if (!$decodeDatas) return false;
        $match = AdminMatch::getInstance()->where('match_id', $match_id)->get();
        $statusId = $decodeDatas['score'][1];
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
            'match_trend' => json_encode($match_trend_info),
            'is_stop' => ($statusId == 8) ? 1 : 0
        ];
        if (!$res = AdminMatchTlive::getInstance()->where('match_id', $match_id)->get()) {
            AdminMatchTlive::getInstance()->insert($match_tlive_data);
        } else {
            unset($match_tlive_data['match_id']);
            AdminMatchTlive::create()->update($match_tlive_data, ['match_id' => $decodeDatas['id']]);
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
                    if (!$season = AdminSeason::getInstance()->where('season_id', $item['id'])->get()) {

                        AdminSeason::getInstance()->insert($data);
                    } else {
                        $season->update($data);
                    }

                }
            }
        }
    }

    /**
     * 获取球员能力技术列表
     * 一天一次
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
     * 球员转会历史 一天一次
     * @return bool
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
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
                            //不可能有修改
                        }
                    }
                }
            } else {
                break;
            }
        }
    }

    /**
     * 球队荣誉  一天一次
     * @return bool
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function teamHonor()
    {
        $max = AdminTeamHonor::getInstance()->max('updated_at');
        $url = sprintf($this->team_honor, $this->user, $this->secret, $max + 1);
        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true);

        if ($resp['code'] == 0) {
            if ($resp['query']['total'] == 0) {
                Log::getInstance()->info('球队荣誉更新完成');

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
        } else {
            Log::getInstance()->info('更新球队荣誉通信失效');

        }

    }

    /**
     * 荣誉详情  one day /time
     * @return bool
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
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
     * 阶段列表  一天一次 注意：新增赛季跟新增阶段的时候  都需要同步赛季比赛列表
     * 因为赛季中比赛并非事先完全确定 比如1/8决赛，需要临时更新
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
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
                        //新增阶段
                        $url = sprintf($this->stage_list, $this->user, $this->secret, $max + 1);
                        $res = Tool::getInstance()->postApi($url);
                        $resp = json_decode($res, true);
                        AdminStageList::getInstance()->insert($data);

                        //新增赛季比赛
                        $select_season_id = $data['season_id'];
                        $res = Tool::getInstance()->postApi(sprintf('https://open.sportnanoapi.com/api/v4/football/match/season?user=%s&secret=%s&id=%s', 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', $select_season_id));
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
                                        'home_team_name' => isset($home_team->short_name_zh) ? $home_team->short_name_zh : $home_team->name_zh,
                                        'home_team_logo' => $home_team->logo,
                                        'away_team_name' => isset($away_team->short_name_zh) ? $away_team->short_name_zh : $away_team->name_zh,
                                        'away_team_logo' => $away_team->logo,
                                        'competition_name' => isset($competition->short_name_zh) ? $competition->short_name_zh : $competition->name_zh,
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
     * 球员荣誉列表 一天一次
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
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
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
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





    /**
     * 定时推送脚本 30秒/次  需要重点维护
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function matchTlive()
    {
        Log::getInstance()->info('push match tlive start');
        $res = Tool::getInstance()->postApi(sprintf($this->live_url, $this->user, $this->secret));

        if ($decode = json_decode($res, true)) {
            Log::getInstance()->info('accept data success');

            $match_info = [];
            foreach ($decode as $item) {

                //无效比赛 跳过
                if (!$match = AdminMatch::getInstance()->where('match_id', $item['id'])->get()) {
                    Log::getInstance()->info('match do not exist-' . $item['id']);
                    continue;
                }

                //比赛结束 跳过
                if (AdminMatchTlive::getInstance()->where('match_id', $item['id'])->where('is_stop', 1)->get()) {
                    continue;
                }
                $status = $item['score'][1];
                if (!in_array($status, [2, 3, 4, 5, 7, 8])) { //上半场 / 下半场 / 中场 / 加时赛 / 点球决战 / 结束
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

                Log::getInstance()->info('still have match to push');

                $match_trend_info = [];
                if ($matchTrendRes = AdminMatchTlive::create()->where('match_id', $item['id'])->get()) {
                    $match_trend_info = json_decode($matchTrendRes->match_trend, true);
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
                $corner_count_new = 0;

                $goal_count_new = 0;
                $yellow_card_count_new = 0;
                $red_card_count_new = 0;

                $goal_tlive_total = [];
                $yellow_card_tlive_total = [];
                $red_card_tlive_total = [];
                Log::getInstance()->info('hand tlive column');

                if (isset($item['tlive'])) {

                    //上一次文字总数量
                    $match_tlive_count_old = Cache::get('match_tlive_count' . $item['id']) ?: 0;
                    //上一次的进球数量
                    $goal_count_old = Cache::get('goal_count_' . $item['id']);
                    //上一次的黄牌数量
                    $yellow_card_count_old = Cache::get('yellow_card_count' . $item['id']);
                    //上一次的红牌数量
                    $red_card_count_old = Cache::get('red_card_count' . $item['id']);
                    $match_tlive_count_new = count($item['tlive']);
                    if ($match_tlive_count_new > $match_tlive_count_old) { //直播文字
                        Cache::set('match_tlive_count' . $item['id'], $match_tlive_count_new, 60 * 240);
                        $diff = array_slice($item['tlive'], $match_tlive_count_old);
                        (new WebSocket())->contentPush($diff, $item['id']);
                    }


                    foreach ($item['tlive'] as $signal_tlive) {
                        $format_signal_tlive = ['time' => intval($signal_tlive['time']), 'type' => (int)$signal_tlive['type'], 'position' => (int)$signal_tlive['position']];
                        if ($format_signal_tlive['type'] == 2) { //角球
                            $corner_count_new += 1;
                            $format_signal_corner_tlive = $format_signal_tlive;
                            $corner_count_tlive[] = $format_signal_corner_tlive;
                        } else if ($format_signal_tlive['type'] == 1 || $format_signal_tlive['type'] == 8) { //进球或点球
                            $last_goal_tlive = $format_signal_tlive;
                            $goal_count_new += 1;
                            $goal_tlive_total[] = $format_signal_tlive;
                        } else if ($format_signal_tlive['type'] == 3) { //黄牌
                            $last_yellow_card_tlive = $format_signal_tlive;
                            $yellow_card_count_new += 1;
                            $yellow_card_tlive_total[] = $format_signal_tlive;
                        } else if ($format_signal_tlive['type'] == 4) { //红牌
                            $last_red_card_tlive = $format_signal_tlive;
                            $red_card_count_new += 1;
                            $red_card_tlive_total[] = $format_signal_tlive;
                        }
                        else {
                            continue;
                        }
                    }
                    if ($goal_count_new > $goal_count_old && isset($last_goal_tlive)) {
                        TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'], 'last_incident' => $last_goal_tlive, 'score' => $item['score'], 'type'=>1]));
                        Cache::set('goal_count_' . $item['id'], $goal_count_new, 60 * 240);
                    }

                    if ($yellow_card_count_new > $yellow_card_count_old && isset($last_yellow_card_tlive)) {
                        TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'], 'last_incident' => $last_yellow_card_tlive, 'score' => $item['score'], 'type'=>3]));
                        Cache::set('yellow_card_count' . $item['id'], $yellow_card_count_new, 60 * 240);
                    }

                    if ($red_card_count_new > $red_card_count_old && isset($last_red_card_tlive)) {
                        TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'], 'last_incident' => $last_red_card_tlive, 'score' => $item['score'], 'type'=>4]));
                        Cache::set('red_card_count' . $item['id'], $red_card_count_new, 60 * 240);
                    }

                    Cache::set('match_tlive_' . $item['id'], json_encode($item['tlive']), 60 * 240);

                }


                Log::getInstance()->info('hand matching_info-' . $item['id']);

                $signal_match_info['signal_count'] = ['corner' => $corner_count_tlive, 'goal' => $goal_tlive_total, 'yellow_card' => $yellow_card_tlive_total, 'red_card' => $red_card_tlive_total];
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
                AppFunc::setMatchingInfo($item['id'], json_encode($signal_match_info));
                unset($signal_match_info);
            }

            Log::getInstance()->info('start push matching_info_list');

            /**
             * 异步的话要做进程间通信，本身也有开销，不如做成同步的，push将数据交给底层，本身不等待
             */

            if (!empty($match_info)) {
                $tool = Tool::getInstance();
                $server = ServerManager::getInstance()->getSwooleServer();
                $returnData = [
                    'event' => 'match_update',
                    'match_info_list' => isset($match_info) ? $match_info : []
                ];

                $onlineUsers = OnlineUser::getInstance()->table();
                foreach ($onlineUsers as $fd => $onlineUser) {
                    $connection = $server->connection_info($fd);
                    if (is_array($connection) && $connection['websocket_status'] == 3) {  // 用户正常在线时可以进行消息推送
                        Log::getInstance()->info('push succ' . $fd);
                        $server->push($fd, $tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $returnData));
                    } else {
                        Log::getInstance()->info('lost-connection-' . $fd);
                    }
                }

            } else {
                Log::getInstance()->info('do not have match to hand');

            }

        } else {
            Log::getInstance()->info('accept data failed');

        }
    }
    /**
     * 更新比赛趋势，一分钟一次
     * @throws \Throwable
     */
    public function updateMatchTrend()
    {
        //进行中的比赛
        if ($playingMatches = AdminMatch::create()->field(['match_id'])->where('status_id', FootballApi::STATUS_PLAYING, 'in')->all()) {
            foreach ($playingMatches as $playingMatch) {
                //比赛趋势
                $match_res = Tool::getInstance()->postApi(sprintf($this->trend_detail, 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', $playingMatch['match_id']));
                $match_trend = json_decode($match_res, true);

                if ($match_trend['code'] != 0) {
                    $match_trend_info = [];
                } else {
                    $match_trend_info = $match_trend['results'];
                }

                if ($matchTlive = AdminMatchTlive::create()->where('match_id', $playingMatch['match_id'])->get()) {
                    $matchTlive->match_trend = json_encode($match_trend_info);
                    $matchTlive->update();
                } else {
                    $insertData = [
                        'match_id' => $playingMatch['match_id'],
                        'match_trend' => json_encode($match_trend_info)
                    ];
                    AdminMatchTlive::create()->insert($insertData);
                }
            }

        }

    }

    function test() {

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);


    }


}
