<?php

namespace App\HttpController\Match;


use App\Base\BaseModel;
use App\Base\FrontUserController;
use App\Common\AppFunc;
use App\lib\FrontService;
use App\lib\Tool;
use App\lib\Utils;
use App\Model\AdminCompetition;
use App\Model\AdminCompetitionRuleList;
use App\Model\AdminCountryList;
use App\Model\AdminHonorList;
use App\Model\AdminManagerList;
use App\Model\AdminMatch;
use App\Model\AdminCountryCategory;
use App\Model\AdminPlayer;
use App\Model\AdminPlayerChangeClub;
use App\Model\AdminPlayerHonorList;
use App\Model\AdminPlayerStat;
use App\Model\AdminSeason;
use App\Model\AdminStageList;
use App\Model\AdminSysSettings;
use App\Model\AdminTeam;
use App\Model\AdminTeamHonor;
use App\Model\AdminTeamLineUp;
use App\Model\SeasonAllTableDetail;
use App\Model\SeasonMatchList;
use App\Model\SeasonTeamPlayer;
use App\Utility\Log\Log;
use App\Utility\Message\Status;
use easySwoole\Cache\Cache;
use EasySwoole\ORM\AbstractModel;
use EasySwoole\ORM\Exception\Exception;
use EasySwoole\Redis\Redis as Redis;
use EasySwoole\RedisPool\Redis as RedisPool;
use Illuminate\Support\Facades\App;
use Throwable;

class DataApi extends FrontUserController
{

    protected $user = 'mark9527';

    protected $secret = 'dbfe8d40baa7374d54596ea513d8da96';

    public $team_logo = 'https://cdn.sportnanoapi.com/football/team/';
    public $player_logo = 'https://cdn.sportnanoapi.com/football/player/';

    protected $FIFA_male_rank = 'https://open.sportnanoapi.com/api/v4/football/ranking/fifa/men?user=%s&secret=%s'; //FIFA男子排名
    protected $FIFA_female_rank = 'https://open.sportnanoapi.com/api/v4/football/ranking/fifa/women?user=%s&secret=%s'; //FIFA女子子排名


    public function competitionDes()
    {
        $competition_id = $this->params[''];
    }

    /**
     * 数据中心推荐热门赛事
     */
    public function getHotCompetition()
    {
        $hot_competition = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::SETTING_DATA_COMPETITION)->get();
        $competitionIds = json_decode($hot_competition['sys_value'], true);
        $return = [];
        foreach ($competitionIds as $k => $competitionId) {
            if (!$competition = AdminCompetition::getInstance()->where('competition_id', $competitionId)->get()) {
                continue;
            } else {
                $data['competition_id'] = $competition['competition_id'];
                $data['logo'] = $competition['logo'];
                $data['short_name_zh'] = $competition['short_name_zh'];
                $data['seasons'] = $competition->getSeason();
                $return[] = $data;
                unset($data);
            }
        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);


    }


    /**
     * 国家分类赛事
     * @return bool
     */
    public function getCompetitionByCountry()
    {
        $type = $this->params['type'];
        if (!$type) {
            $category_id = $this->params['category_id'];

            $country_list = AdminCountryList::getInstance()->where('category_id', $category_id)->field(['id', 'name_zh', 'logo'])->all();
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $country_list);
        } else {
            $category_id = $this->params['category_id'];
            $country_id = $this->params['country_id'];

            if ($category_id && !$country_id) {
                $country_list = AdminCountryList::getInstance()->where('category_id', $category_id)->field('country_id', 'name_zh')->all();
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $country_list);

            }

            if ($category_id && $country_id) {
                $competition = AdminCompetition::getInstance()->where('country_id', $country_id)->where('category_id', $category_id)->field(['competition_id', 'short_name_zh', 'logo'])->all();
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $competition);

            }
        }

        return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
    }

    /**
     * 最新FIFA女子男子排名
     */
    public function FIFAMaleRank()
    {
        //区域id，1-欧洲足联、2-南美洲足联、3-中北美洲及加勒比海足协、4-非洲足联、5-亚洲足联、6-大洋洲足联
        $region_id = isset($this->params['region_id']) ? $this->params['region_id'] : 0;
        $is_male = isset($this->params['is_male']) ? $this->params['is_male'] : 0;
        $matchSeason = Tool::getInstance()->postApi(sprintf($is_male ? $this->FIFA_male_rank : $this->FIFA_female_rank, $this->user, $this->secret));
        $teams = json_decode($matchSeason, true);
        $decodeDatas = $teams['results'];

        foreach ($decodeDatas['items'] as $k => $decodeData) {
            $decodeData['team']['country_logo'] = $this->team_logo . $decodeData['team']['country_logo'];
            if ($region_id) {
                if ($region_id == $decodeData['region_id']) {
                    $datas[] = $decodeData;

                } else {
                    continue;
                }
            } else {
                $datas[] = $decodeData;

            }

        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $datas);

    }


    public function FIFAFemaleRank()
    {
    }


    /**
     * 全部赛事 国家分类
     * @return bool
     */
    public function CategoryCountry()
    {
        $categorys = AdminCountryCategory::getInstance()->all();
        foreach ($categorys as $category) {
            $countrys = AdminCountryList::getInstance()->where('category_id', $category->category_id)->field(['country_id', 'name_zh', 'logo'])->all();
            $cate_info = [
                'category_id' => $category->category_id,
                'category_name_zh' => $category->name_zh,
                'country' => $countrys,

            ];

            $return[] = $cate_info;
            unset($cate_info);
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

    }


    /**
     * 获取赛事
     * @return bool
     */
    public function competitionByCid()
    {
        $country_id = $this->params['country_id'];
        $competitions = AdminCompetition::getInstance()
            ->field(['competition_id', 'short_name_zh', 'logo'])
            ->where('country_id', $country_id)
            ->where('type', [1, 2], 'in')
            ->all();

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $competitions);

    }

    public function formatValue()
    {
//        $match = AdminMatch::getInstance()->where('competition_id', 82)->all();
//        $format = FrontService::handMatch($match, 0, true);
//        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $format);

        $matchSeason = Tool::getInstance()->postApi(sprintf('https://open.sportnanoapi.com/api/v4/football/match/competition?user=%s&secret=%s&id=%s', $this->user, $this->secret, 1));
        $teams = json_decode($matchSeason, true);
        $decodeDatas = $teams['results'];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $decodeDatas);

        foreach ($decodeDatas as $item) {
            $data = [
                'match_time' => date('Y-m-d H:i:s', $item['match_time']),
                'home_team' => (AdminTeam::getInstance()->where('team_id', $item['home_team_id'])->get())['name_zh'],
                'away_team' => (AdminTeam::getInstance()->where('team_id', $item['away_team_id'])->get())['name_zh'],
            ];

            $times[] = $data;
        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $times);


    }

    /**
     * 球员详情
     * @return bool
     */
    public function getPlayerInfo()
    {
        $player_id = $this->params['player_id'];
        if (!$player_id) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        }

        //基本信息
        $basic = AdminPlayer::getInstance()->where('player_id', $player_id)->get();

	if (!$basic) {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
        }
        $type = !empty($this->params['type']) ? $this->params['type'] : 1;
        if ($type == 1) {
            if ($team = $basic->getTeam()) {
                $team_info = ['name_zh' => $team->name_zh, 'logo' => $team->logo];
            } else {
                $team_info = [];
            }
            $country = $basic->getCountry();
            //转会
            $format_history = [];
            if ($history = AdminPlayerChangeClub::getInstance()->field(['player_id', 'from_team_id', 'to_team_id', 'transfer_type'])->where('player_id', $player_id)->all()) {
                foreach ($history as $k => $item) {
                    $from_team = $item->fromTeamInfo();
                    if ($from_team) {
                        $from_team_info = ['name_zh' => $from_team->name_zh, 'logo' => $from_team->logo, 'team_id' => $from_team->team_id];
                    }
                    $to_team = $item->ToTeamInfo();
                    if ($to_team) {
                        $to_team_info = ['name_zh' => $to_team->name_zh, 'logo' => $to_team->logo, 'team_id' => $to_team->team_id];

                    }
                    $data['transfer_time'] = date('Y-m-d', $item['transfer_time']);
                    $data['transfer_type'] = $item->transfer_type; //转会类型，1-租借、2-租借结束、3-转会、4-退役、5-选秀、6-已解约、7-已签约、8-未知
                    $data['from_team_info'] = isset($from_team_info) ? $from_team_info : [];
                    $data['to_team_info'] = isset($to_team_info) ? $to_team_info : [];
                    $format_history[] = $data;
                    unset($data);
                }
            }
            $format_player_honor = [];
            if ($player_honor = AdminPlayerHonorList::getInstance()->field(['honors', 'player_id'])->where('player_id', $basic->player_id)->get()) {
                $format_player_honor = json_decode($player_honor->honors, true);
            }
            //获取球员参加的所有赛季
            $season = AppFunc::getPlayerSeasons(json_decode($basic->seasons, true));
            $player_info = [
                'team_info' => $team_info,
                'contract_until' => date('Y-m-d', $basic->contract_until),
                'country_info' => ['name_zh' => $country->name_zh, 'logo' => $country->logo],
                'user_info' => [
                    'name_zh' => $basic->name_zh,
                    'logo' => $basic->logo,
                    'market_value' => AppFunc::changeToWan($basic->market_value),
                    'age' => $basic->age,
                    'weight' => $basic->weight,
                    'height' => $basic->height,
                    'preferred_foot' => $basic->preferred_foot,
                    'position' => $basic->position,
                    'birthday' => $basic->birthday ? date('Y-m-d', $basic->birthday) : '',
                ],
                'change_club_history' => isset($format_history) ? $format_history : [],
                'player_honor' => $format_player_honor,
                'season_list' => $season ?: [],
            ];
            $return_data = $player_info;
        } elseif ($type == 2) {
            //技术统计
            $return_data = [];

            $stat_data = [];

            $select_season_id = $this->params['select_season_id'];
            if (!$select_season_id) {
                return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES], []);

            }
            $res = SeasonTeamPlayer::getInstance()->where('season_id', $select_season_id)->get();

            if ($res && $players_stats = json_decode($res->players_stats, true)) {
                foreach ($players_stats as $players_stat) {
                    if ($players_stat['player']['id'] == $player_id) {
                        //比赛
                        $stat_data['match']['matches'] = $players_stat['matches']; //出场
                        $stat_data['match']['first'] = $players_stat['first']; //首发
                        $stat_data['match']['minutes_played'] = $players_stat['minutes_played'];//出场时间
                        $stat_data['match']['minutes_played_per_match'] = AppFunc::getAverageData($players_stat['minutes_played'], $players_stat['matches']);//场均时间
                        //进攻
                        $stat_data['goal']['goals'] = $players_stat['goals'];//进球
                        $stat_data['goal']['penalty'] = $players_stat['penalty'];//点球
                        $stat_data['goal']['penalty_per_match'] = AppFunc::getAverageData($players_stat['penalty'], $players_stat['matches']);//场均点球


                        $stat_data['goal']['goals_per_match'] = AppFunc::getAverageData($players_stat['goals'], $players_stat['matches']);//场均进球


                        $stat_data['goal']['cost_time_per_goal'] = AppFunc::getAverageData($players_stat['minutes_played'], $players_stat['goals']);//每球耗时


                        $stat_data['goal']['shots_per_match'] = AppFunc::getAverageData($players_stat['shots'], $players_stat['matches']);//场均射门


                        $stat_data['goal']['shots'] = $players_stat['shots'];//射门总数
                        $stat_data['goal']['was_fouled'] = $players_stat['was_fouled'];//被犯规
                        $stat_data['goal']['shots_on_target_per_match'] = AppFunc::getAverageData($players_stat['shots_on_target'], $players_stat['matches']);//场均射正


                        //组织
                        $stat_data['pass']['assists'] = $players_stat['assists'];//助攻
                        $stat_data['pass']['assists_per_match'] = AppFunc::getAverageData($players_stat['assists'], $players_stat['matches']);//场均助攻


                        $stat_data['pass']['key_passes_per_match'] = AppFunc::getAverageData($players_stat['key_passes'], $players_stat['matches']);//场均关键传球


                        $stat_data['pass']['key_passes'] = $players_stat['key_passes'];//关键传球
                        $stat_data['pass']['passes'] = $players_stat['passes'];//传球
                        $stat_data['pass']['passes_per_match'] = AppFunc::getAverageData($players_stat['passes'], $players_stat['matches']);//传球


                        $stat_data['pass']['passes_accuracy_per_match'] = AppFunc::getAverageData($players_stat['passes_accuracy'], $players_stat['matches']);//场均成功传球


                        $stat_data['pass']['passes_accuracy'] = $players_stat['passes_accuracy'];//成功传球

                        //防守
                        $stat_data['defense']['tackles_per_match'] = AppFunc::getAverageData($players_stat['tackles'], $players_stat['matches']);//场均抢断


                        $stat_data['defense']['tackles'] = $players_stat['tackles'];//场均抢断
                        $stat_data['defense']['interceptions_per_match'] = AppFunc::getAverageData($players_stat['interceptions'], $players_stat['matches']);//场均拦截


                        $stat_data['defense']['interceptions'] = $players_stat['interceptions'];//场均拦截
                        $stat_data['defense']['clearances_per_match'] = AppFunc::getAverageData($players_stat['clearances'], $players_stat['matches']);//场均解围


                        $stat_data['defense']['clearances'] = $players_stat['clearances'];//场均解围
                        $stat_data['defense']['blocked_shots'] = $players_stat['blocked_shots'];//有效阻挡
                        $stat_data['defense']['blocked_shots_per_match'] = AppFunc::getAverageData($players_stat['blocked_shots'], $players_stat['matches']);//场均解围

                        //其他
                        $stat_data['other']['dribble_succ_per_match'] = AppFunc::getAverageData($players_stat['dribble_succ'], $players_stat['matches']); //场均过人成功


                        $stat_data['other']['duels_won_succ_per_match'] = AppFunc::getAverageData($players_stat['duels_won'], $players_stat['matches']); //场均1对1拼抢成功
                        $stat_data['other']['fouls_per_match'] = AppFunc::getAverageData($players_stat['fouls'], $players_stat['matches']); //场均犯规
                        $stat_data['other']['was_fouled_per_match'] = AppFunc::getAverageData($players_stat['was_fouled'], $players_stat['matches']); //场均被犯规
                        $stat_data['other']['yellow_cards_per_match'] = AppFunc::getAverageData($players_stat['yellow_cards'], $players_stat['matches']); //黄牌场均
                        $stat_data['other']['red_cards_per_match'] = AppFunc::getAverageData($players_stat['red_cards'], $players_stat['matches']); //红牌场均
                        break;

                    } else {
                        continue;
                    }
                }
                $return_data['stat_data'] = $stat_data;
            }
        } else {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return_data);

    }

    /**
     * 球队数据
     * @return bool
     * @throws Throwable
     */
    public function teamInfo(): bool
    {
        // 球队信息
        $teamId = empty($this->params['team_id']) ? 0 : intval($this->params['team_id']);
        $team = $teamId > 0 ? Utils::queryHandler(AdminTeam::getInstance(), 'team_id=?', $teamId) : false;
        if (empty($team)) return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
        // 赛季 & 当前赛季
        $season = [];
        $currentSeasonId = $selectSeasonId = 0;
        $competitionId = intval($this->params['competition_id']);
        $competition = Utils::queryHandler(AdminCompetition::getInstance(), 'competition_id=?', $competitionId);
        if (!empty($competition)) {
            $season = Utils::queryHandler(AdminSeason::getInstance(), 'competition_id=?', $competitionId, 'id, season_id, year', false);
            $currentSeasonId = $selectSeasonId = $competition['cur_season_id'];
        }
        if (!empty($this->params['select_season_id'])) $selectSeasonId = $this->params['select_season_id'];
		// 类型
		$type = 1;
		if (!empty($this->params['type'])) $type = intval($this->params['type']);
        switch ($type) {
            case 1:
                // 球队荣誉
                $honors = $honorIds = $honorMapper = $honorIdGroup = [];
                $tmp = Utils::queryHandler(AdminTeamHonor::getInstance(), 'team_id=?', $teamId);
                $tmp = empty($tmp) ? [] : json_decode($tmp['honors'], true);
                foreach ($tmp as $v) {
                    $id = intval($v['honor']['id']);
                    if ($id > 0 && !in_array($id, $honorIds)) $honorIds[] = $id;
                }
                // 球队荣誉信息映射
                if (!empty($honorIds)) $honorMapper = Utils::queryHandler(AdminHonorList::getInstance(),
                    'id IN (' . join(',', $honorIds) . ')', null,
                    'id,logo', false, null, 'id,logo,1');
                // 球队荣誉信息 分组统计 & 补充数据
                foreach ($tmp as $v) {
                    $honor = $v['honor'];
                    $honor['logo'] = '';
                    $id = intval($honor['id']);
                    if (!empty($honorMapper[$id])) $honor['logo'] = $honorMapper[$id];
                    // 分组统计
                    if (!in_array($id, $honorIdGroup)) {
                        $honors[$id]['honor'] = $honor;
                        $honors[$id]['count'] = 1;
                        $honorIdGroup[] = $id;
                    } else {
                        $honors[$id]['count'] += 1;
                    }
                    $honors[$id]['season'][] = $v['season'];
                }
                // 转会记录
                $lastYearTimestamp = strtotime(date('Y-m-d 00:00:00', strtotime('-1 year')));
                $changeInPlayers = Utils::queryHandler(AdminPlayerChangeClub::getInstance(),
                    'to_team_id=? and transfer_time>?', [$teamId, $lastYearTimestamp],
                    '*', false, 'transfer_time desc');
                $formatChangeInPlayers = FrontService::handChangePlayer($changeInPlayers);
                $changeOutPlayers = Utils::queryHandler(AdminPlayerChangeClub::getInstance(),
                    'from_team_id=? and transfer_time>?', [$teamId, $lastYearTimestamp],
                    '*', false, 'transfer_time desc');
                $formatChangeOutPlayers = FrontService::handChangePlayer($changeOutPlayers);
                //
                $countryInfo = Utils::queryHandler(AdminCountryList::getInstance(), 'country_id=?', $team['country_id'], 'name_zh');
                $managerInfo = Utils::queryHandler(AdminManagerList::getInstance(), 'manager_id=?', $team['manager_id'], 'name_zh');
                // 输出数据
                $result = [
                    'basic' => [
                        'logo' => $team['logo'],
                        'name_zh' => $team['name_zh'],
                        'website' => $team['website'],
                        'current_season_id' => $currentSeasonId,
                        'foundation_time' => $team['foundation_time'],
                        'foreign_players' => $team['foreign_players'],
                        'national_players' => $team['national_players'],
                        'country' => empty($countryInfo['name_zh']) ? '' : $countryInfo['name_zh'],
                        'manager_name_zh' => empty($managerInfo['name_zh']) ? '' : $managerInfo['name_zh'],
                    ], // 球队基本资料
                    'format_change_in_players' => $formatChangeInPlayers,
                    'format_change_out_players' => $formatChangeOutPlayers,
                    'format_honors' => $honors,
                    'season' => $season,
                ];
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
            case 2:
                $tableData = $promotion = [];
                $tmp = Utils::queryHandler(SeasonAllTableDetail::getInstance(), 'season_id=?', $selectSeasonId);
                if (!empty($tmp)) {
                    $tables = json_decode($tmp['tables'], true);
                    // 晋升信息映射
                    $promotionMapper = [];
                    $tmp = json_decode($tmp['promotions'], true);
                    $tmp = (empty($tmp) || !is_array($tmp)) ? [] : $tmp;
                    foreach ($tmp as $v) {
                        $id = intval($v['id']);
                        $promotionMapper[$id] = $v['name_zh'];
                    }
                    //
                    $promotion = empty($promotionMapper) ? 0 : 1;
                    if ($promotion > 0) {
                        // 球队信息映射
                        $teamMapper = $teamIds = [];
                        $rows = empty($tables[0]['rows']) || !is_array($tables[0]['rows']) ? [] : $tables[0]['rows'];
                        foreach ($rows as $v) {
                            $id = intval($v['team_id']);
                            if ($id > 0 && !in_array($id, $teamIds)) $teamIds[] = $id;
                        }
                        if (!empty($teamIds)) $teamMapper = Utils::queryHandler(AdminTeam::getInstance(),
                            'team_id in (' . join(',', $teamIds) . ')', null,
                            '*', false, null, 'team_id,*,1');

                        foreach ($rows as $v) {
                            $id = intval($v['team_id']);
                            $promotionId = intval($v['promotion_id']);
                            $promotionName = empty($promotionMapper[$promotionId]) ? '' : $promotionMapper[$promotionId];
                            $team = empty($teamMapper[$id]) ? false : $teamMapper[$id];
                            $tableData[] = [
                                'won' => $v['won'],
                                'draw' => $v['draw'],
                                'loss' => $v['loss'],
                                'total' => $v['total'],
                                'goals' => $v['goals'],
                                'points' => $v['points'],
                                'goals_against' => $v['goals_against'],
                                'team_id' => $id,
                                'logo' => empty($team) ? '' : $team['logo'],
                                'name_zh' => empty($team) ? '' : $team['name_zh'],
                                'promotion_id' => $promotionId,
                                'promotion_name_zh' => $promotionName,
                            ];
                        }
                    } else {
                        // 球队信息映射
                        $teamMapper = $teamIds = [];
                        foreach ($tables as $v) {
                            foreach ($v['rows'] as $vv) {
                                $id = intval($vv['team_id']);
                                if ($id > 0 && !in_array($id, $teamIds)) $teamIds[] = $id;
                            }
                        }
                        if (!empty($teamIds)) $teamMapper = Utils::queryHandler(AdminTeam::getInstance(),
                            'team_id in (' . join(',', $teamIds) . ')', null,
                            '*', false, null, 'team_id,*,1');
                        //
                        foreach ($tables as $v) {
                            $rows = empty($v['rows']) || !is_array($v['rows']) ? [] : $v['rows'];
                            $items = [];
                            foreach ($rows as $vv) {
                                $id = intval($vv['team_id']);
                                $team = empty($teamMapper[$id]) ? false : $teamMapper[$id];
                                $items[] = [
                                    'team_id' => $id,
                                    'logo' => empty($team) ? '' : $team['logo'],
                                    'name_zh' => empty($team) ? '' : $team['name_zh'],
                                    'total' => $vv['total'],
                                    'won' => $vv['won'],
                                    'draw' => $vv['draw'],
                                    'loss' => $vv['loss'],
                                    'goals' => $vv['goals'],
                                    'points' => $vv['points'],
                                    'goals_against' => $vv['goals_against'],
                                ];
                            }
                            $tableData[] = ['group' => $v['group'], 'list' => $items];
                        }
                    }
                }
                //赛制说明
                $competitionDescribe = Utils::queryHandler(AdminCompetitionRuleList::getInstance(),
                    "json_contains(season_ids,'{$selectSeasonId}') and competition_id=?", $competitionId, 'text');
                $competitionDescribe = empty($competitionDescribe['text']) ? '' : $competitionDescribe['text'];
                // 输出数据
                $result = [
                    'table' => $tableData,
                    'competition_describe' => $competitionDescribe,
                    'current_season_id' => $currentSeasonId,
                    'promotion' => $promotion,
                ];
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
            case 3:
                // 输出数据
                $result = [];
                $tmp = Utils::queryHandler(SeasonMatchList::getInstance(),
                    'season_id=? and (home_team_id=? or away_team_id=?)', [$selectSeasonId, $teamId, $teamId],
                    '*', false);
                if (!empty($tmp)) {
                    $competitionIds = $teamIds = [];
                    array_walk($tmp, function ($v, $k) use (&$competitionIds, &$teamIds) {
                        $id = intval($v['competition_id']);
                        if ($id > 0 && !in_array($id, $competitionIds)) $competitionIds[] = $id;
                        $id = intval($v['home_team_id']);
                        if ($id > 0 && !in_array($id, $teamIds)) $teamIds[] = $id;
                        $id = intval($v['away_team_id']);
                        if ($id > 0 && !in_array($id, $teamIds)) $teamIds[] = $id;
                    });
                    $competitionMapper = empty($competitionIds) ? [] : Utils::queryHandler(AdminCompetition::getInstance(),
                        'competition_id in (' . join(',', $competitionIds) . ')', null,
                        'competition_id,short_name_zh', false, null, 'competition_id,short_name_zh,1');
                    $teamMapper = empty($teamIds) ? [] : Utils::queryHandler(AdminTeam::getInstance(),
                        'team_id in (' . join(',', $teamIds) . ')', null,
                        'team_id,name_zh', false, null, 'team_id,name_zh,1');
                    foreach ($tmp as $v) {
                        $cid = intval($v['competition_id']);
                        $htId = intval($v['home_team_id']);
                        $atId = intval($v['away_team_id']);
                        $decodeHomeScore = json_decode($v['home_scores'], true);
                        $decodeAwayScore = json_decode($v['away_scores'], true);
                        $tmp = [];
                        [$tmp['home_scores'], $tmp['away_scores']] = AppFunc::getFinalScore($decodeHomeScore, $decodeAwayScore);
                        [$tmp['half_home_scores'], $tmp['half_away_scores']] = AppFunc::getHalfScore($decodeHomeScore, $decodeAwayScore);
                        [$tmp['home_corner'], $tmp['away_corner']] = AppFunc::getCorner($decodeHomeScore, $decodeAwayScore);//角球
                        $result[] = array_merge($tmp, [
                            'match_id' => intval($v['id']),
                            'match_time' => date('Y-m-d', $v['match_time']),
                            'home_team_name_zh' => empty($teamMapper[$htId]) ? '' : $teamMapper[$htId],
                            'away_team_name_zh' => empty($teamMapper[$atId]) ? '' : $teamMapper[$atId],
                            'competition_short_name_zh' => empty($competitionMapper[$cid]) ? '' : $competitionMapper[$cid],
                        ]);
                    }
                }
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
            case 4:
                $teamStr = $playerStr = '';
                //
                $seasonTeamPlayerKey = 'season_team_player_' . $selectSeasonId;
                $tmp = Cache::get($seasonTeamPlayerKey);
                if (empty($tmp)) {
                    $tmp = Utils::queryHandler(SeasonTeamPlayer::getInstance(), 'season_id=?', $selectSeasonId, 'teams_stats,players_stats');
                    if (!empty($tmp)) {
                        $teamStr = preg_replace('/\[?(,\s)?\{\"team\":\s\{\"id\":\s(?!' . $teamId . ')\d+,((?!,\s\{\"team\":).)+/', '', $tmp['teams_stats']);
                        $teamStr = trim($teamStr, '[,]');
                        $playerStr = preg_replace('/\[?(,\s)?\{\"team\":\s\{\"id\":\s(?!' . $teamId . ')\d+,((?!,\s\{\"team\":).)+/', '', $tmp['players_stats']);
                        $playerStr = '[' . trim($playerStr, '[,]') . ']';
                        Cache::set($seasonTeamPlayerKey, ['teams' => $teamStr, 'players' => $playerStr], 300);
                    }
                } else {
                    $teamStr = $tmp['teams'];
                    $playerStr = $tmp['players'];
                }
                // 获取球队信息
                if (empty($teamStr)) return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);
                $teamInfo = json_decode($teamStr, true);
                if (empty($teamInfo['matches'])) return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);
                $teamMatchNum = $teamInfo['matches'];
                //球队数据
                $teamData = [
                    'goals' => !empty($teamInfo['goals']) ? $teamInfo['goals'] : '0.0', //进球
                    'penalty' => !empty($teamInfo['penalty']) ? $teamInfo['penalty'] : '0.0',//点球
                    'shots_per_match' => !empty($teamInfo['shots']) ? AppFunc::getAverageData($teamInfo['shots'], $teamMatchNum) : '0',//场均射门
                    'shots_on_target_per_match' => !empty($teamInfo['shots_on_target']) ? AppFunc::getAverageData($teamInfo['shots_on_target'], $teamMatchNum) : '0.0',//场均射正
                    'penalty_per_match' => !empty($teamInfo['penalty']) ? AppFunc::getAverageData($teamInfo['penalty'], $teamMatchNum) : '0.0',//场均角球
                    'passes_per_match' => !empty($teamInfo['passes']) ? AppFunc::getAverageData($teamInfo['passes'], $teamMatchNum) : '0.0',//场均传球
                    'key_passes_per_match' => !empty($teamInfo['key_passes']) ? AppFunc::getAverageData($teamInfo['key_passes'], $teamMatchNum) : '0.0',//场均关键传球
                    'passes_accuracy_per_match' => !empty($teamInfo['passes_accuracy']) ? AppFunc::getAverageData($teamInfo['passes_accuracy'], $teamMatchNum) : '0.0',//场均成功传球
                    'crosses_per_match' => !empty($teamInfo['crosses']) ? AppFunc::getAverageData($teamInfo['crosses'], $teamMatchNum) : '0.0',//场均过人
                    'crosses_accuracy_per_match' => !empty($teamInfo['crosses_accuracy']) ? AppFunc::getAverageData($teamInfo['crosses_accuracy'], $teamMatchNum) : '0.0',//场均成功过人
                    'goals_against' => !empty($teamInfo['goals_against']) ? $teamInfo['goals_against'] : '0.0',//失球
                    'fouls' => !empty($teamInfo['fouls']) ? $teamInfo['fouls'] : '0.0',//犯规
                    'was_fouled' => !empty($teamInfo['was_fouled']) ? $teamInfo['was_fouled'] : '0.0',//被犯规
                    'assists' => !empty($teamInfo['assists']) ? $teamInfo['assists'] : 0,//助攻
                    'red_cards' => !empty($teamInfo['red_cards']) ? $teamInfo['red_cards'] : '0.0',//红牌
                    'yellow_cards' => !empty($teamInfo['yellow_cards']) ? $teamInfo['yellow_cards'] : '0.0',//黄牌
                ];
                // 队员数据
                $keyPlayers = $players = [];
                $items = json_decode($playerStr, true);

                if (!empty($items)) {
                    $mostGoals = $mostAssists = $mostShots = $mostShotsOnTarget = [];
                    $mostPasses = $mostPassesAccuracy = $mostKeyPasses = [];
                    $mostInterceptions = $mostClearances = $mostSaves = [];
                    $mostYellowCards = $mostRedCards = $mostMinutesPlayed = [];
                    $handler = function ($item, $field, $target) {
                        if (!empty($item[$field]) && (empty($target) || intval($item[$field]) >= intval($target[$field]))) $target = $item;
                        return $target;
                    };
                    foreach ($items as $v) {
                        $playInfo = $v['player'];
                        $players[$playInfo['id']] = $playInfo;
                        $mostGoals = $handler($v, 'goals', $mostGoals);
                        $mostAssists = $handler($v, 'assists', $mostAssists);
                        $mostShots = $handler($v, 'shots', $mostShots);
                        $mostShotsOnTarget = $handler($v, 'shots_on_target', $mostShotsOnTarget);
                        $mostPasses = $handler($v, 'passes', $mostPasses);
                        $mostPassesAccuracy = $handler($v, 'passes_accuracy', $mostPassesAccuracy);
                        $mostKeyPasses = $handler($v, 'key_passes', $mostKeyPasses);
                        $mostInterceptions = $handler($v, 'interceptions', $mostInterceptions);
                        $mostClearances = $handler($v, 'clearances', $mostClearances);
                        $mostSaves = $handler($v, 'saves', $mostSaves);
                        $mostYellowCards = $handler($v, 'yellow_cards', $mostYellowCards);
                        $mostRedCards = $handler($v, 'red_cards', $mostRedCards);
                        $mostMinutesPlayed = $handler($v, 'minutes_played', $mostMinutesPlayed);
                    }
                    $keyPlayers = [
                        'most_goals' => FrontService::formatKeyPlayer($mostGoals, 'goals'),
                        'most_assists' => FrontService::formatKeyPlayer($mostAssists, 'assists'),
                        'most_shots' => FrontService::formatKeyPlayer($mostShots, 'shots'),
                        'most_shots_on_target' => FrontService::formatKeyPlayer($mostShotsOnTarget, 'shots_on_target'),
                        'most_passes' => FrontService::formatKeyPlayer($mostPasses, 'passes'),
                        'most_passes_accuracy' => FrontService::formatKeyPlayer($mostPassesAccuracy, 'passes_accuracy'),
                        'most_key_passes' => FrontService::formatKeyPlayer($mostKeyPasses, 'key_passes'),
                        'most_interceptions' => FrontService::formatKeyPlayer($mostInterceptions, 'interceptions'),
                        'most_clearances' => FrontService::formatKeyPlayer($mostClearances, 'clearances'),
                        'most_saves' => FrontService::formatKeyPlayer($mostSaves, 'saves'),
                        'most_yellow_cards' => FrontService::formatKeyPlayer($mostYellowCards, 'yellow_cards'),
                        'most_red_cards' => FrontService::formatKeyPlayer($mostRedCards, 'red_cards'),
                        'most_minutes_played' => FrontService::formatKeyPlayer($mostMinutesPlayed, 'minutes_played'),
                    ];
                }
                // 输出数据
                $result = ['team_data' => $teamData, 'key_player' => $keyPlayers];
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
            case 5: //阵容
                // 战队信息映射
                $players = $playerIds = [];
                $tmp = Utils::queryHandler(AdminTeamLineUp::getInstance(), 'team_id=?', $teamId, 'squad');
                $tmp = json_decode($tmp['squad'], true);
                foreach ($tmp as $v) {
                    $id = intval($v['player']['id']);
                    if (!in_array($id, $playerIds)) $playerIds[] = $id;
                    $players[$id] = [
                        'player_id' => $id,
                        'logo' => '',
                        'position' => $v['position'],
                        'shirt_number' => $v['shirt_number'],
                        'age' => 0,
                        'weight' => 0,
                        'height' => 0,
                        'nationality' => '',
                        'format_market_value' => '',
                        'name_zh' => $v['player']['name_zh'],
                    ];
                }
                $playerMapper = [];
                if (!empty($playerIds)) $playerMapper = Utils::queryHandler(AdminPlayer::getInstance(),
                    'player_id in (' . join(',', $playerIds) . ')', null,
                    'player_id,market_value,logo,age,weight,height,nationality', false, null, 'player_id,*,1');
                // 输出数据
                $result = [];
                foreach ($players as $k => $v) {
                    $id = $v['player_id'];
                    $position = $v['position'];
                    unset($v['position']);
                    if (!empty($playerMapper[$id])) {
                        $playerInfo = $playerMapper[$id];
                        $v['age'] = $playerInfo['age'];
                        $v['logo'] = $playerInfo['logo'];
                        $v['weight'] = $playerInfo['weight'];
                        $v['height'] = $playerInfo['height'];
                        $v['nationality'] = $playerInfo['nationality'];
                        $v['format_market_value'] = AppFunc::changeToWan($playerInfo['market_value']);
                    }
                    $result[$position][] = $v;
                }
                $managerInfo = Utils::queryHandler(AdminManagerList::getInstance(), 'manager_id=?', $team['manager_id'], 'name_zh,logo');
                $result['C'] = [
                    'name_zh' => empty($managerInfo['name_zh']) ? '' : $managerInfo['name_zh'],
                    'logo' => $this->player_logo . (empty($managerInfo['logo']) ? '' : $managerInfo['logo']),
                    'manager_id' => $team['manager_id'],
                ];
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
        }
    }

    /**
     * 球队转入转出记录
     * @return bool
     * @throws Throwable
     */
    public function teamChangeClubHistory(): bool
    {
        // 参数处理
        $teamId = empty($this->params['team_id']) || intval($this->params['team_id']) < 1 ? 0 : intval($this->params['team_id']);
        $type = empty($this->params['type']) || intval($this->params['type']) < 1 ? 0 : intval($this->params['type']);
        $page = empty($this->params['page']) || intval($this->params['page']) < 1 ? 1 : intval($this->params['page']);
        $size = empty($this->params['size']) || intval($this->params['size']) < 1 ? 10 : intval($this->params['size']);

        // 球队信息
        $team = $teamId > 0 ? Utils::queryHandler(AdminTeam::getInstance(), 'team_id=?', $teamId) : false;
        if (empty($team)) return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        // 输出数据
        $result = [];
        if ($type == 1) {
            $data = Utils::queryHandler(AdminPlayerChangeClub::getInstance(), 'to_team_id=?', $teamId,
                '*', false, 'transfer_time desc', null, $page, $size);
            $list = FrontService::handChangePlayer($data['list']);
            $result = ['list' => $list, 'total' => $data['total']];
        } elseif ($type == 2) {
            $data = Utils::queryHandler(AdminPlayerChangeClub::getInstance(), 'from_team_id=?', $teamId,
                '*', false, 'transfer_time desc', null, $page, $size);
            $list = FrontService::handChangePlayer($data['list']);
            $result = ['list' => $list, 'total' => $data['total']];
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
    }

    /**
     * 热搜赛事
     * @return bool
     * @throws Throwable
     */
    public function hotSearchCompetition(): bool
    {
        $list = Utils::queryHandler(AdminSysSettings::getInstance(),
            'sys_key=?', [AdminSysSettings::SETTING_HOT_SEARCH_COMPETITION], 'sys_value');
        $list = empty($list['sys_value']) ? [] : json_decode($list['sys_value'], true);
        if (!empty($list)) {
            $list = Utils::queryHandler(AdminCompetition::getInstance(),
                'competition_id in(' . join(',', $list) . ')', null,
                'competition_id,short_name_zh,logo', false);
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $list);

    }

    /**
     * 赛事信息
     * @return bool
     * @throws Throwable
     */
    public function competitionInfo(): bool
    {
        // 配置数据
        $data = Utils::queryHandler(AdminSysSettings::getInstance(),
            'sys_key=?', [AdminSysSettings::SETTING_DATA_COMPETITION], 'sys_value');
        $data = empty($data['sys_value']) ? [] : json_decode($data['sys_value'], true);
        // 赛事数据
        $competitionId = empty($data[0]) || intval($data[0]) < 1 ? 0 : intval($data[0]);
        if (!empty($this->params['competition_id']) && intval($this->params['competition_id']) > 0) {
            $competitionId = intval($this->params['competition_id']);
        }
        if ($competitionId < 1) return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        $competition = Utils::queryHandler(AdminCompetition::getInstance(), 'competition_id=?', $competitionId);
        if (empty($competition)) return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
        //
        $selectSeasonId = intval($competition['cur_season_id']);
        if (!empty($this->params['season_id']) && intval($this->params['season_id']) > 0) {
            $selectSeasonId = intval($this->params['season_id']);
        }
        // 类型
        $type = intval($this->params['type']); //0基本信息  1积分榜 2比赛 3最佳球员 4最佳球队
        if ($type == 1) { // 积分榜
            // 输出数据
            $result = [
                'data' => [],
                'promotion' => 0,
                'competition_describe' => '',
            ];
            // 赛季数据
            $result['season_list'] = Utils::queryHandler(AdminSeason::getInstance(), 'competition_id=?', $competitionId, '*', false);
            // 赛事描述
            $competitionDescribe = Cache::get('competition_describe_' . $selectSeasonId);
            if (empty($competitionDescribe)) {
                $competitionDescribe = Utils::queryHandler(AdminCompetitionRuleList::getInstance(),
                    "json_contains(season_ids,'{$selectSeasonId}') and competition_id=?", $competitionId, 'text');
                $competitionDescribe = empty($competitionDescribe['text']) ? '' : $competitionDescribe['text'];
                Cache::set('competition_describe_' . $selectSeasonId, $competitionDescribe, 60 * 60 * 24);
            }
            $result['competition_describe'] = $competitionDescribe;
            //积分榜
            $tmp = Utils::queryHandler(SeasonAllTableDetail::getInstance(), 'season_id=?', $selectSeasonId);
            if (!empty($tmp)) {
                $promotions = json_decode($tmp['promotions'], true);
                $tables = json_decode($tmp['tables'], true);
                if (!empty($promotions)) {
                    $result['promotion'] = 1;
                    // 晋升数据映射 & 球队数据映射
                    $promotionMapper = $teamIds = $teamMapper = [];
                    $rows = empty($tables[0]['rows']) ? [] : $tables[0]['rows'];
                    array_walk($promotions, function ($v, $k) use (&$promotionMapper) {
                        $id = intval($v['id']);
                        $promotionMapper[$id] = $v['name_zh'];
                    });
                    array_walk($rows, function ($v, $k) use (&$teamIds) {
                        $id = intval($v['team_id']);
                        if ($id > 0 && !in_array($id, $teamIds)) $teamIds[] = $id;
                    });
                    if (!empty($teamIds)) $teamMapper = Utils::queryHandler(AdminTeam::getInstance(),
                        'team_id in (' . join(',', $teamIds) . ')', null,
                        'team_id,name_zh,logo', false, null, 'team_id,*,1');
                    // 数据填充
                    foreach ($rows as $v) {
                        $tid = intval($v['team_id']);
                        $pid = intval($v['promotion_id']);
                        $team = empty($teamMapper[$tid]) ? [] : $teamMapper[$tid];
                        $result['data'][] = [
                            'won' => $v['won'],
                            'draw' => $v['draw'],
                            'loss' => $v['loss'],
                            'goals' => $v['goals'],
                            'total' => $v['total'],
                            'points' => $v['points'],
                            'goals_against' => $v['goals_against'],
                            'promotion_id' => $pid,
                            'promotion_name_zh' => empty($promotionMapper[$pid]) ? '' : $promotionMapper[$pid],
                            'team_id' => $tid,
                            'logo' => empty($team['logo']) ? '' : $team['logo'],
                            'name_zh' => empty($team['name_zh']) ? '' : $team['name_zh'],
                        ];
                    }
                } else {
                    $list = $teamIds = [];
                    foreach ($tables as $k => $v) {
                        $rows = empty($v['rows']) ? [] : $v['rows'];
                        $items = [];
                        array_walk($rows, function ($vv, $kk) use (&$items, &$teamIds) {
                            $id = intval($vv['team_id']);
                            if ($id > 0 && !in_array($id, $teamIds)) $teamIds[] = $id;
                            if ($id > 0) $items[] = [
                                'team_id' => $id,
                                'name_zh' => '',
                                'logo' => '',
                                'total' => $vv['total'],
                                'won' => $vv['won'],
                                'draw' => $vv['draw'],
                                'loss' => $vv['loss'],
                                'goals' => $vv['goals'],
                                'points' => $vv['points'],
                                'goals_against' => $vv['goals_against'],
                            ];
                        });
                        $list[] = ['list' => $items, 'group' => $v['group']];
                    }
                    // 填充数据
                    if (!empty($teamIds)) $teamMapper = Utils::queryHandler(AdminTeam::getInstance(),
                        'team_id in (' . join(',', $teamIds) . ')', null,
                        'team_id,name_zh,logo', false, null, 'team_id,*,1');
                    foreach ($list as $k => $v) {
                        foreach ($v['list'] as $kk => $vv) {
                            $id = intval($vv['team_id']);
                            if (empty($teamMapper[$id])) {
                                unset($v['list'][$kk]);
                                continue;
                            }
                            $team = $teamMapper[$id];
                            $v['list'][$kk]['logo'] = $team['logo'];
                            $v['list'][$kk]['name_zh'] = $team['name_zh'];
                        }
                        $list[$k]['list'] = array_values($v['list']);
                    }
                    $result['data'] = $list;
                }
            }
        } elseif ($type == 2) { //比赛
            // 输出数据
            $result = [
                'stage' => [],
                'match_list' => [],
                'cur_round' => $competition['cur_round'],
                'cur_stage_id' => $competition['cur_stage_id'],
            ];
            $result['stage'] = Utils::queryHandler(AdminStageList::getInstance(),
                'season_id=?', $selectSeasonId,
                'name_zh,stage_id,round_count,group_count', false);
            $firstStage = empty($result['stage']) ? [] : $result['stage'][0];
            $stageId = $selectSeasonId == $competition['cur_season_id'] ?
                intval($competition['cur_stage_id']) : (empty($firstStage['stage_id']) ? 0 : intval($firstStage['stage_id']));
            if (!empty($this->params['stage_id']) && intval($this->params['stage_id']) > 0) $stageId = intval($this->params['stage_id']);
            $roundId = $selectSeasonId == $competition['cur_season_id'] ?
                intval($competition['cur_round']) : (empty($firstStage['round_count']) ? 0 : intval($firstStage['round_count']));
            if (!empty($this->params['round_id']) && intval($this->params['round_id']) > 0) $roundId = intval($this->params['round_id']);
            $groupId = 1;
            if (!empty($this->params['group_id']) && intval($this->params['group_id']) > 0) $groupId = intval($this->params['group_id']);
            // 比赛信息
            $tmp = Utils::queryHandler(SeasonMatchList::getInstance(), 'season_id=?', $selectSeasonId, '*', false);
            foreach ($tmp as $v) {
                $round = json_decode($v['round'], true);
                $isOk = intval($round['stage_id']) == $stageId && (intval($round['round_num']) == $roundId || intval($round['group_num']) == $groupId);
                if (!$isOk) continue;
                $decodeHomeScore = json_decode($v['home_scores'], true);
                $decodeAwayScore = json_decode($v['away_scores'], true);
                $data = [];
                $data['match_id'] = intval($v['id']);
                $data['match_time'] = date('Y-m-d H:i:s', $v['match_time']);
                $data['home_team_name_zh'] = $v['home_team_name'];
                $data['away_team_name_zh'] = $v['away_team_name'];
                $data['status_id'] = $v['status_id'];
                [$data['home_scores'], $data['away_scores']] = AppFunc::getFinalScore($decodeHomeScore, $decodeAwayScore);
                [$data['half_home_scores'], $data['half_away_scores']] = AppFunc::getHalfScore($decodeHomeScore, $decodeAwayScore);
                [$data['home_corner'], $data['away_corner']] = AppFunc::getCorner($decodeHomeScore, $decodeAwayScore);
                //list($data['home_scores'], $data['away_scores'], $data['half_home_scores'], $data['half_away_scores'], $data['home_corner'], $data['away_corner']) = AppFunc::getAllScoreType($decode_home_score, $decode_away_score);
                $result['match_list'][] = $data;
            }
        } else { // 最佳球员
            // 输出数据
            $result = [];
            $tmp = Utils::queryHandler(SeasonTeamPlayer::getInstance(), 'season_id=?', $selectSeasonId, 'players_stats,teams_stats');
            if ($type == 3) {
                $tmp = json_decode($tmp['players_stats'], true);
                if (!empty($tmp)) array_walk($tmp, function ($v, $k) use (&$result) {
                    $result[] = [
                        'player_id' => $v['player']['id'],
                        'name_zh' => $v['player']['name_zh'],
                        'team_logo' => FrontService::TEAM_LOGO . $v['team']['logo'],
                        'player_logo' => FrontService::PLAYER_LOGO . $v['player']['logo'],
                        'assists' => $v['assists'],//助攻
                        'shots' => $v['shots'],//射门
                        'shots_on_target' => $v['shots_on_target'],//射正
                        'passes' => $v['passes'],//传球
                        'passes_accuracy' => $v['passes_accuracy'],//成功传球
                        'key_passes' => $v['key_passes'],//关键传球
                        'interceptions' => $v['interceptions'],//拦截
                        'clearances' => $v['clearances'],//解围
                        'yellow_cards' => $v['yellow_cards'],//黄牌
                        'red_cards' => $v['red_cards'],//红牌
                        'minutes_played' => $v['minutes_played'],//出场时间
                        'goals' => $v['goals'],//出场进球
                    ];
                });
            } elseif ($type == 4) { // 最佳球队
                $tmp = json_decode($tmp['teams_stats'], true);
                if (!empty($tmp)) array_walk($tmp, function ($v, $k) use (&$result) {
                    $result[] = [
                        'team_id' => $v['team']['id'],
                        'name_zh' => $v['team']['name_zh'],
                        'team_logo' => FrontService::TEAM_LOGO . $v['team']['logo'],
                        'goals' => $v['goals'],
                        'goals_against' => $v['goals_against'],
                        'penalty' => $v['penalty'],
                        'shots' => $v['shots'],
                        'shots_on_target' => $v['shots_on_target'],
                        'key_passes' => $v['key_passes'],
                        'interceptions' => $v['interceptions'],
                        'clearances' => $v['clearances'],
                        'yellow_cards' => $v['yellow_cards'],
                        'red_cards' => $v['red_cards'],
                    ];
                });
            } else {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
            }
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => $result]);
    }

    /**
     * 关键词搜索
     * @return bool
     * @throws Throwable
     */
    public function contentByKeyWord(): bool
    {
        // 关键字
        $keywords = isset($this->params['key_word']) ? trim($this->params['key_word']) : '';
        if (empty($keywords)) return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        $keywords = '%' . $keywords . '%';
        // 参数整理
        $type = empty($this->params['type']) || intval($this->params['type']) < 1 ? 0 : intval($this->params['type']);
        $page = empty($this->params['page']) || intval($this->params['page']) < 1 ? 1 : intval($this->params['page']);
        $size = empty($this->params['size']) || intval($this->params['size']) < 1 ? 10 : intval($this->params['size']);
        // 输出数据
        if ($type == 1) { //赛事
            $result = Utils::queryHandler(AdminCompetition::getInstance(),
                'name_zh like ? or short_name_zh like ?', [$keywords, $keywords],
                'competition_id,name_zh,short_name_zh,logo', false, 'competition_id desc', null, $page, $size);
        } elseif ($type == 2) { //球队
            $result = Utils::queryHandler(AdminTeam::getInstance(),
                'name_zh like ?', [$keywords],
                'team_id,name_zh,logo', false, 'team_id desc', null, $page, $size);
        } elseif ($type == 3) { //球员
            $result = Utils::queryHandler(AdminPlayer::getInstance(),
                'name_zh like ?', [$keywords],
                'player_id,name_zh,logo', false, 'market_value desc', null, $page, $size);
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
    }

    /**
     * @return bool
     * @throws Throwable
     */
    public function getContinentCompetition(): bool
    {
        $categoryId = empty($this->params['category_id']) || intval($this->params['category_id']) < 1 ? 0 : intval($this->params['category_id']);
        if ($categoryId < 1) return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        $list = Utils::queryHandler(AdminCompetition::getInstance(),
            'category_id=? and country_id=0', $categoryId,
            'competition_id,short_name_zh,logo', false);
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $list);
    }

}
