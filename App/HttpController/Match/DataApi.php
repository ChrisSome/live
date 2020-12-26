<?php

namespace App\HttpController\Match;

use App\lib\Tool;
use App\Common\AppFunc;
use App\Model\AdminTeam;
use App\lib\FrontService;
use App\Model\AdminPlayer;
use App\Model\AdminSeason;
use easySwoole\Cache\Cache;
use App\Model\AdminHonorList;
use App\Model\AdminStageList;
use App\Model\AdminTeamHonor;
use App\Model\AdminTeamLineUp;
use App\Model\SeasonMatchList;
use App\Model\AdminCompetition;
use App\Model\AdminCountryList;
use App\Model\AdminSysSettings;
use App\Model\SeasonTeamPlayer;
use App\Utility\Message\Status;
use App\Base\FrontUserController;
use App\Model\AdminCountryCategory;
use App\Model\AdminPlayerHonorList;
use App\Model\SeasonAllTableDetail;
use App\Model\AdminPlayerChangeClub;
use App\Model\AdminCompetitionRuleList;

class DataApi extends FrontUserController
{
	private $user = 'mark9527';
	private $secret = 'dbfe8d40baa7374d54596ea513d8da96';
	private $teamLogoUrlPrefix = 'https://cdn.sportnanoapi.com/football/team/';
	private $playerLogoUrlPrefix = 'https://cdn.sportnanoapi.com/football/player/';
	private $fifaMaleRankURL = 'https://open.sportnanoapi.com/api/v4/football/ranking/fifa/men?user=%s&secret=%s'; //FIFA男子排名
	private $fifaFemaleRankURL = 'https://open.sportnanoapi.com/api/v4/football/ranking/fifa/women?user=%s&secret=%s'; //FIFA女子子排名
	
	/**
	 * 全部赛事 国家分类
	 * @throws
	 */
	public function CategoryCountry()
	{
		// 输出数据
		$result = [];
		$list = AdminCountryCategory::getInstance()->all();
		foreach ($list as $v) {
			$id = intval($v['category_id']);
			if ($id > 0) $result[] = [
				'category_id' => $id,
				'category_name_zh' => $v['name_zh'],
				'country' => AdminCountryList::getInstance()->findAll(['category_id' => $id], 'country_id,name_zh,logo'),
			];
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 获取赛事
	 * @throws
	 */
	public function competitionByCid()
	{
		// 获取赛事信息
		$countryId = $this->param('country_id', true);
		$where = ['country_id' => $countryId, 'type' => [[1, 2], 'in']];
		$result = AdminCompetition::getInstance()->findAll($where, 'competition_id,short_name_zh,logo');
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 最新FIFA女子男子排名
	 * @throws
	 */
	public function FIFAMaleRank()
	{
		//区域id，1-欧洲足联、2-南美洲足联、3-中北美洲及加勒比海足协、4-非洲足联、5-亚洲足联、6-大洋洲足联
		$regionId = $this->param('region_id', true);
		$isMale = $this->param('is_male', true);
		$url = sprintf($isMale ? $this->fifaMaleRankURL : $this->fifaFemaleRankURL, $this->user, $this->secret);
		$tmp = Tool::getInstance()->postApi($url);
		$tmp = empty($tmp) ? null : json_decode($tmp, true);
		$list = empty($tmp['results']) ? [] : $tmp['results'];
		// 输出数据
		$result = [];
		foreach ($list as $v) {
			if ($regionId > 0 && $regionId != $v['region_id']) continue;
			if ($regionId > 0) $v['team']['country_logo'] = $this->teamLogoUrlPrefix . $v['team']['country_logo'];
			$result[] = $v;
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 赛事信息
	 * @throws
	 */
	public function competitionInfo()
	{
		// 配置数据
		$config = AdminSysSettings::getInstance()->findOne(['sys_key' => AdminSysSettings::SETTING_DATA_COMPETITION], 'sys_value');
		$config = empty($config['sys_value']) ? [] : json_decode($config['sys_value'], true);
		// 赛事数据
		$competitionId = empty($config[0]) || intval($config[0]) < 1 ? 0 : intval($config[0]);
		if (!empty($params['competition_id']) && intval($params['competition_id']) > 0) $competitionId = intval($params['competition_id']);
		if ($competitionId < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		$competition = AdminCompetition::getInstance()->findOne(['competition_id' => $competitionId]);
		if (empty($competition)) $this->output(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
		//
		$selectSeasonId = intval($competition['cur_season_id']);
		$params = $this->param();
		if (!empty($params['season_id']) && intval($params['season_id']) > 0) $selectSeasonId = intval($params['season_id']);
		// 类型
		$type = empty($params['type']) || intval($params['type']) < 0 ? 0 : intval($params['type']); //0基本信息  1积分榜 2比赛 3最佳球员 4最佳球队
		if ($type == 1) { // 积分榜
			// 输出数据
			$result = ['data' => [], 'promotion' => 0, 'competition_describe' => ''];
			// 赛季数据
			$result['season_list'] = $competitionId < 1 ? [] : AdminSeason::getInstance()->findAll(['competition_id' => $competitionId]);
			// 赛事描述
			$competitionDescribe = Cache::get('competition_describe_' . $selectSeasonId);
			if (empty($competitionDescribe)) {
				$where = ["json_contains(season_ids,'{$selectSeasonId}')", 'competition_id' => $competitionId];
				$competitionDescribe = AdminCompetitionRuleList::getInstance()->findOne($where, 'text');
				$competitionDescribe = empty($competitionDescribe['text']) ? '' : $competitionDescribe['text'];
				Cache::set('competition_describe_' . $selectSeasonId, $competitionDescribe, 60 * 60 * 24);
			}
			$result['competition_describe'] = $competitionDescribe;
			//积分榜
			$tmp = SeasonAllTableDetail::getInstance()->findOne(['season_id' => $selectSeasonId]);
			if (!empty($tmp)) {
				$promotions = json_decode($tmp['promotions'], true);
				$tables = json_decode($tmp['tables'], true);
				if (!empty($promotions)) {
					$result['promotion'] = 1;
					// 晋升数据映射 & 球队数据映射
					$promotionMapper = $teamIds = $teamMapper = [];
					$rows = empty($tables[0]['rows']) ? [] : $tables[0]['rows'];
					array_walk($promotions, function ($v) use (&$promotionMapper) {
						$id = intval($v['id']);
						$promotionMapper[$id] = $v['name_zh'];
					});
					array_walk($rows, function ($v) use (&$teamIds) {
						$id = intval($v['team_id']);
						if ($id > 0 && !in_array($id, $teamIds)) $teamIds[] = $id;
					});
					if (!empty($teamIds)) $teamMapper = AdminTeam::getInstance()
						->findAll(['team_id' => [$teamIds, 'in']], 'team_id,name_zh,logo', null,
							false, 0, 0, 'team_id,*,true');
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
							'promotion_id' => $pid,
							'points' => $v['points'],
							'goals_against' => $v['goals_against'],
							'promotion_name_zh' => empty($promotionMapper[$pid]) ? '' : $promotionMapper[$pid],
							'team_id' => $tid,
							'logo' => empty($team['logo']) ? '' : $team['logo'],
							'name_zh' => empty($team['name_zh']) ? '' : $team['name_zh'],
						];
					}
				} else {
					$list = $teamIds = $stageIds = [];
					array_walk($tables, function ($v) use (&$stageIds) {
						$id = intval($v['stage_id']);
						if ($id > 0 && !in_array($id, $stageIds)) $stageIds[] = $id;
					});
					$stageMapper = empty($stageIds) ? [] : AdminStageList::getInstance()
						->findAll(['id' => [$stageIds, 'in']], 'stage_id,season_id,name_zh', null,
							false, 0, 0, 'stage_id,*,true');
					foreach ($tables as $k => $v) {
						$rows = empty($v['rows']) ? [] : $v['rows'];
						$items = [];
						array_walk($rows, function ($vv) use (&$items, &$teamIds) {
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
						$id = intval($v['stage_id']);
						$stageInfo = empty($stageMapper[$id]) ? [] : $stageMapper[$id];
						$list[] = ['list' => $items, 'group' => $v['group'], 'stage' => $stageInfo];
						$list[] = ['list' => $items, 'group' => $v['group']];
					}
					// 填充数据
					if (!empty($teamIds)) $teamMapper = AdminTeam::getInstance()
						->findAll(['team_id' => [$teamIds, 'in']], 'team_id,name_zh,logo', null,
							false, 0, 0, 'team_id,*,true');
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
			$result['stage'] = AdminStageList::getInstance()->findAll(['season_id' => $selectSeasonId], 'name_zh,stage_id,round_count,group_count');
			$firstStage = empty($result['stage']) ? [] : $result['stage'][0];
			$stageId = $selectSeasonId == $competition['cur_season_id'] ?
				intval($competition['cur_stage_id']) : (empty($firstStage['stage_id']) ? 0 : intval($firstStage['stage_id']));
			if (!empty($params['stage_id']) && intval($params['stage_id']) > 0) $stageId = intval($params['stage_id']);
			$roundId = $selectSeasonId == $competition['cur_season_id'] ?
				intval($competition['cur_round']) : (empty($firstStage['round_count']) ? 0 : intval($firstStage['round_count']));
			if (!empty($params['round_id']) && intval($params['round_id']) > 0) $roundId = intval($params['round_id']);
			$groupId = 1;
			if (!empty($params['group_id']) && intval($params['group_id']) > 0) $groupId = intval($params['group_id']);
			// 比赛信息
			$tmp = SeasonMatchList::getInstance()->findAll(['season_id' => $selectSeasonId]);
			foreach ($tmp as $v) {
				$round = json_decode($v['round'], true);
				$isOk = intval($round['stage_id']) == $stageId && (intval($round['round_num']) == $roundId || intval($round['group_num']) == $groupId);
				if (!$isOk) continue;
				$decodeHomeScore = json_decode($v['home_scores'], true);
				$decodeAwayScore = json_decode($v['away_scores'], true);
				$config = [];
				$config['match_id'] = intval($v['id']);
				$config['status_id'] = $v['status_id'];
				$config['home_team_name_zh'] = $v['home_team_name'];
				$config['away_team_name_zh'] = $v['away_team_name'];
				$config['match_time'] = date('Y-m-d H:i:s', $v['match_time']);
				[$config['home_corner'], $config['away_corner']] = AppFunc::getCorner($decodeHomeScore, $decodeAwayScore);
				[$config['home_scores'], $config['away_scores']] = AppFunc::getFinalScore($decodeHomeScore, $decodeAwayScore);
				[$config['half_home_scores'], $config['half_away_scores']] = AppFunc::getHalfScore($decodeHomeScore, $decodeAwayScore);
				//list($data['home_scores'], $data['away_scores'], $data['half_home_scores'], $data['half_away_scores'], $data['home_corner'], $data['away_corner']) = AppFunc::getAllScoreType($decode_home_score, $decode_away_score);
				$result['match_list'][] = $config;
			}
		} else { // 最佳球员
			// 输出数据
			$result = [];
			$tmp = SeasonTeamPlayer::getInstance()->findOne(['season_id' => $selectSeasonId], 'players_stats,teams_stats');
			if ($type == 3) {
				$tmp = json_decode($tmp['players_stats'], true);
				if (!empty($tmp)) array_walk($tmp, function ($v) use (&$result) {
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
				if (!empty($tmp)) array_walk($tmp, function ($v) use (&$result) {
					$result[] = [
						'team_id' => $v['team']['id'],
						'name_zh' => $v['team']['name_zh'],
						'team_logo' => FrontService::TEAM_LOGO . $v['team']['logo'],
						'goals' => $v['goals'],
						'goals_against' => empty($v['goals_against']) ? 0 : intval($v['goals_against']),
						'penalty' => $v['penalty'],
						'shots' => empty($v['shots']) ? 0 : intval($v['shots']),
						'shots_on_target' => empty($v['shots_on_target']) ? 0 : intval($v['shots_on_target']),
						'key_passes' => empty($v['key_passes']) ? 0 : intval($v['key_passes']),
						'interceptions' => empty($v['interceptions']) ? 0 : intval($v['interceptions']),
						'clearances' => $v['clearances'],
						'yellow_cards' => $v['yellow_cards'],
						'red_cards' => $v['red_cards'],
					];
				});
			} else {
				$this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
			}
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => $result]);
	}
	
	/**
	 * 数据中心推荐热门赛事
	 * @throws
	 */
	public function getHotCompetition()
	{
		$hot_competition = AdminSysSettings::getInstance()->findOne(['sys_key' => AdminSysSettings::SETTING_DATA_COMPETITION]);
		$competitionIds = json_decode($hot_competition['sys_value'], true);
		$return = $res = [];
		if ($season = AdminSeason::getInstance()->findAll(['competition_id' => [$competitionIds, 'in']])) {
			foreach ($season as $itemSeason) {
				$res[$itemSeason->competition_id][] = $itemSeason;
			}
		}
		//做映射
		$competition = AdminCompetition::getInstance()->findAll(['competition_id' => [$competitionIds, 'in']]);
		foreach ($competition as $itemCompetition) {
			$data['competition_id'] = $itemCompetition['competition_id'];
			$data['logo'] = $itemCompetition['logo'];
			$data['short_name_zh'] = $itemCompetition['short_name_zh'];
			$data['seasons'] = isset($res[$itemCompetition->competition_id]) ? $res[$itemCompetition->competition_id] : [];
			$return[] = $data;
			unset($data);
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);
	}
	
	/**
	 * 球员详情
	 * @throws
	 */
	public function getPlayerInfo()
	{
		// 参数校验
		$params = $this->param();
		$playerId = empty($params['player_id']) || intval($params['player_id']) < 1 ? 0 : intval($params['player_id']);
		if ($playerId < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 球员信息
		$player = AdminPlayer::getInstance()->findOne(['player_id' => $playerId]);
		if (empty($player)) $this->output(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
		// 类型校验
		$type = empty($params['type']) || intval($params['type']) < 2 ? 1 : intval($params['type']);
		if ($type != 1 && $type != 2) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 填充数据
		if ($type == 1) {
			// 输出数据
			$result = [
				'team_info' => [],
				'season_list' => [],
				'player_honor' => [],
				'country_info' => [],
				'change_club_history' => [],
				'user_info' => [
					'age' => $player['age'],
					'logo' => $player['logo'],
					'weight' => $player['weight'],
					'height' => $player['height'],
					'name_zh' => $player['name_zh'],
					'position' => $player['position'],
					'preferred_foot' => $player['preferred_foot'],
					'market_value' => AppFunc::changeToWan($player['market_value']),
					'birthday' => empty($player['birthday']) ? '' : date('Y-m-d', $player['birthday']),
				],
				'contract_until' => date('Y-m-d', $player['contract_until']),
			];
			// 球员参加的所有赛季
			$season = AppFunc::getPlayerSeasons(json_decode($player['seasons'], true));
			if (!empty($season)) $result['season_list'] = $season;
			// 球队信息
			$team = $player->getTeam();
			if (!empty($team)) $result['team_info'] = ['name_zh' => $team['name_zh'], 'logo' => $team['logo']];
			// 地区信息
			$country = $player->getCountry();
			if (!empty($country)) $result['country_info'] = ['name_zh' => $country['name_zh'], 'logo' => $country['logo']];
			// 转会历史
			$tmp = AdminPlayerChangeClub::getInstance()
				->findAll(['player_id' => $playerId], 'player_id,from_team_id,transfer_time,to_team_id,transfer_type', 'transfer_time,desc');
			if (!empty($tmp)) {
				foreach ($tmp as $v) {
					$toTeam = $v->ToTeamInfo();
					if (!empty($toTeam)) $toTeam = ['name_zh' => $toTeam['name_zh'], 'logo' => $toTeam['logo'], 'team_id' => $toTeam['team_id']];
					$fromTeam = $v->fromTeamInfo();
					if (!empty($fromTeam)) $fromTeam = ['name_zh' => $fromTeam['name_zh'], 'logo' => $fromTeam['logo'], 'team_id' => $fromTeam['team_id']];
					$result['change_club_history'][] = [
						'to_team_info' => empty($toTeam) ? [] : $toTeam,
						'from_team_info' => empty($fromTeam) ? [] : $fromTeam,
						'transfer_time' => date('Y-m-d', $v['transfer_time']),
						'transfer_type' => $v['transfer_type'], // 转会类型 1租借 2租借结束 3转会 4退役 5选秀 6已解约 7已签约 8未知
					];
				}
			}
			// 荣誉清单
			$tmp = AdminPlayerHonorList::getInstance()->findOne(['player_id' => $playerId], 'honors');
			if (!empty($tmp)) $result['player_honor'] = json_decode($tmp['honors'], true);
		} else { //技术统计
			$seasonId = empty($params['select_season_id']) || intval($params['select_season_id']) < 1 ? 0 : intval($params['select_season_id']);
			if ($seasonId < 1) $this->output(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES], []);
			// 输出数据
			$result = ['stat_data' => []];
			$playerStat = SeasonTeamPlayer::getInstance()->findOne(['season_id' => $seasonId]);
			$playerStat = empty($playerStat['players_stats']) ? [] : json_decode($playerStat['players_stats'], true);
			foreach ($playerStat as $v) {
				// 是否忽略
				$ignore = empty($v['player']['id']) || intval($v['player']['id']) != $playerId;
				if ($ignore) continue;
				// 比赛
				$result['stat_data']['match']['first'] = $v['first']; //首发
				$result['stat_data']['match']['matches'] = $v['matches']; //出场
				$result['stat_data']['match']['minutes_played'] = $v['minutes_played']; //出场时间
				$result['stat_data']['match']['minutes_played_per_match'] = AppFunc::getAverageData($v['minutes_played'], $v['matches']); //场均时间
				// 进攻
				$result['stat_data']['goal']['goals'] = $v['goals']; //进球
				$result['stat_data']['goal']['shots'] = $v['shots']; //射门总数
				$result['stat_data']['goal']['penalty'] = $v['penalty']; //点球
				$result['stat_data']['goal']['was_fouled'] = $v['was_fouled']; //被犯规
				$result['stat_data']['goal']['goals_per_match'] = AppFunc::getAverageData($v['goals'], $v['matches']); //场均进球
				$result['stat_data']['goal']['shots_per_match'] = AppFunc::getAverageData($v['shots'], $v['matches']); //场均射门
				$result['stat_data']['goal']['penalty_per_match'] = AppFunc::getAverageData($v['penalty'], $v['matches']); //场均点球
				$result['stat_data']['goal']['cost_time_per_goal'] = AppFunc::getAverageData($v['minutes_played'], $v['goals']); //每球耗时
				$result['stat_data']['goal']['shots_on_target_per_match'] = AppFunc::getAverageData($v['shots_on_target'], $v['matches']); //场均射正
				//组织
				$result['stat_data']['pass']['passes'] = $v['passes']; //传球
				$result['stat_data']['pass']['assists'] = $v['assists']; //助攻
				$result['stat_data']['pass']['key_passes'] = $v['key_passes']; //关键传球
				$result['stat_data']['pass']['passes_accuracy'] = $v['passes_accuracy']; //成功传球
				$result['stat_data']['pass']['passes_per_match'] = AppFunc::getAverageData($v['passes'], $v['matches']); //传球
				$result['stat_data']['pass']['assists_per_match'] = AppFunc::getAverageData($v['assists'], $v['matches']); //场均助攻
				$result['stat_data']['pass']['key_passes_per_match'] = AppFunc::getAverageData($v['key_passes'], $v['matches']); //场均关键传球
				$result['stat_data']['pass']['passes_accuracy_per_match'] = AppFunc::getAverageData($v['passes_accuracy'], $v['matches']); //场均成功传球
				//防守
				$result['stat_data']['defense']['tackles'] = $v['tackles']; //场均抢断
				$result['stat_data']['defense']['clearances'] = $v['clearances']; //场均解围
				$result['stat_data']['defense']['blocked_shots'] = $v['blocked_shots']; //有效阻挡
				$result['stat_data']['defense']['interceptions'] = $v['interceptions']; //场均拦截
				$result['stat_data']['defense']['tackles_per_match'] = AppFunc::getAverageData($v['tackles'], $v['matches']); //场均抢断
				$result['stat_data']['defense']['clearances_per_match'] = AppFunc::getAverageData($v['clearances'], $v['matches']); //场均解围
				$result['stat_data']['defense']['interceptions_per_match'] = AppFunc::getAverageData($v['interceptions'], $v['matches']); //场均拦截
				$result['stat_data']['defense']['blocked_shots_per_match'] = AppFunc::getAverageData($v['blocked_shots'], $v['matches']); //场均解围
				//其他
				$result['stat_data']['other']['fouls_per_match'] = AppFunc::getAverageData($v['fouls'], $v['matches']); //场均犯规
				$result['stat_data']['other']['red_cards_per_match'] = AppFunc::getAverageData($v['red_cards'], $v['matches']); //红牌场均
				$result['stat_data']['other']['was_fouled_per_match'] = AppFunc::getAverageData($v['was_fouled'], $v['matches']); //场均被犯规
				$result['stat_data']['other']['yellow_cards_per_match'] = AppFunc::getAverageData($v['yellow_cards'], $v['matches']); //黄牌场均
				$result['stat_data']['other']['dribble_succ_per_match'] = AppFunc::getAverageData($v['dribble_succ'], $v['matches']); //场均过人成功
				$result['stat_data']['other']['duels_won_succ_per_match'] = AppFunc::getAverageData($v['duels_won'], $v['matches']); //场均1对1拼抢成功
				break;
			}
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 球队数据
	 * @throws
	 */
	public function teamInfo()
	{
		// 类型校验
		$type = $this->param('type', true, 1);
		if (!in_array($type, [1, 2, 3, 4, 5])) $this->output(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
		// 球队信息
		$teamId = $this->param('team_id', true);
		$team = $teamId < 1 ? null : AdminTeam::getInstance()->findOne(['team_id' => $teamId]);
		if (empty($team)) $this->output(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
		// 赛季信息
		$season = [];
		$currentSeasonId = $selectSeasonId = 0;
		$competitionId = $this->param('competition_id', true, intval($team['competition_id']));
		$competition = $competitionId < 1 ? null : AdminCompetition::getInstance()->findOne(['competition_id' => $competitionId]);
		if (!empty($competition)) {
			$season = AdminSeason::getInstance()->findAll(['competition_id' => $competitionId], 'id,season_id,year');
			$currentSeasonId = $selectSeasonId = intval($competition['cur_season_id']);
		}
		if (!empty($params['select_season_id']) && intval($params['select_season_id']) > 0) $selectSeasonId = intval($params['select_season_id']);
		switch ($type) {
			case 1:
				$country = $team->getCountry();
				$manager = $team->getManager();
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
						'country' => empty($country['name_zh']) ? '' : $country['name_zh'],
						'manager_name_zh' => empty($manager['name_zh']) ? '' : $manager['name_zh'],
					], // 球队基本资料
					'season' => $season,
					'format_honors' => [],
					'format_change_in_players' => [],
					'format_change_out_players' => [],
				];
				// 球队荣誉
				$honorIds = $honorIdGroup = [];
				$tmp = AdminTeamHonor::getInstance()->findOne(['team_id' => $teamId]);
				$tmp = empty($tmp['honors']) ? [] : json_decode($tmp['honors'], true);
				foreach ($tmp as $v) {
					$id = intval($v['honor']['id']);
					if ($id > 0 && !in_array($id, $honorIds)) $honorIds[] = $id;
				}
				// 球队荣誉信息映射
				if (!empty($honorIds)) $honorMapper = AdminHonorList::getInstance()
					->findAll(['id' => [$honorIds, 'in']], 'id,logo', null, false, 0, 0, 'id,logo,true');
				// 球队荣誉信息 分组统计 & 补充数据
				foreach ($tmp as $v) {
					$honor = $v['honor'];
					$honor['logo'] = '';
					$id = intval($honor['id']);
					if (!empty($honorMapper[$id])) $honor['logo'] = $honorMapper[$id];
					// 分组统计
					if (!in_array($id, $honorIdGroup)) {
						$result['format_honors'][$id]['honor'] = $honor;
						$result['format_honors'][$id]['count'] = 1;
						$honorIdGroup[] = $id;
					} else {
						$result['format_honors'][$id]['count'] += 1;
					}
					$result['format_honors'][$id]['season'][] = $v['season'];
				}
				// 转会记录
				$lastYearTimestamp = strtotime(date('Y-m-d 00:00:00', strtotime('-1 year')));
				$where = ['to_team_id' => $teamId, 'transfer_time' => [$lastYearTimestamp, '>']];
				$changeInPlayers = AdminPlayerChangeClub::getInstance()->findAll($where, '*', 'transfer_time,desc');
				$result['format_change_in_players'] = FrontService::handChangePlayer($changeInPlayers);
				$where = ['from_team_id' => $teamId, 'transfer_time' => [$lastYearTimestamp, '>']];
				$changeOutPlayers = AdminPlayerChangeClub::getInstance()->findAll($where, '*', 'transfer_time,desc');
				$result['format_change_out_players'] = FrontService::handChangePlayer($changeOutPlayers);
				$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
				break;
			case 2:
				$tableData = $promotion = [];
				$tmp = SeasonAllTableDetail::getInstance()->findOne(['season_id' => $selectSeasonId]);
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
					$promotion = empty($promotionMapper) ? 0 : 1;
					if ($promotion > 0) {
						// 球队信息映射
						$teamMapper = $teamIds = [];
						$rows = empty($tables[0]['rows']) || !is_array($tables[0]['rows']) ? [] : $tables[0]['rows'];
						foreach ($rows as $v) {
							$id = intval($v['team_id']);
							if ($id > 0 && !in_array($id, $teamIds)) $teamIds[] = $id;
						}
						if (!empty($teamIds)) $teamMapper = AdminTeam::getInstance()
							->findAll(['team_id' => [$teamIds, 'in']], null, null,
								false, 0, 0, 'team_id,*,true');
						foreach ($rows as $v) {
							$id = intval($v['team_id']);
							$promotionId = intval($v['promotion_id']);
							$promotionName = empty($promotionMapper[$promotionId]) ? '' : $promotionMapper[$promotionId];
							$team = empty($teamMapper[$id]) ? false : $teamMapper[$id];
							$tableData[] = [
								'team_id' => $id,
								'won' => $v['won'],
								'draw' => $v['draw'],
								'loss' => $v['loss'],
								'total' => $v['total'],
								'goals' => $v['goals'],
								'points' => $v['points'],
								'promotion_id' => $promotionId,
								'promotion_name_zh' => $promotionName,
								'goals_against' => $v['goals_against'],
								'logo' => empty($team) ? '' : $team['logo'],
								'name_zh' => empty($team) ? '' : $team['name_zh'],
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
						if (!empty($teamIds)) $teamMapper = AdminTeam::getInstance()
							->findAll(['team_id' => [$teamIds, 'in']], null, null,
								false, 0, 0, 'team_id,*,true');
						foreach ($tables as $v) {
							$rows = empty($v['rows']) || !is_array($v['rows']) ? [] : $v['rows'];
							$items = [];
							foreach ($rows as $vv) {
								$id = intval($vv['team_id']);
								$team = empty($teamMapper[$id]) ? false : $teamMapper[$id];
								$items[] = [
									'team_id' => $id,
									'won' => $vv['won'],
									'draw' => $vv['draw'],
									'loss' => $vv['loss'],
									'total' => $vv['total'],
									'goals' => $vv['goals'],
									'points' => $vv['points'],
									'goals_against' => $vv['goals_against'],
									'logo' => empty($team) ? '' : $team['logo'],
									'name_zh' => empty($team) ? '' : $team['name_zh'],
								];
							}
							$tableData[] = ['group' => $v['group'], 'list' => $items];
						}
					}
				}
				//赛制说明
				$competitionDescribe = AdminCompetitionRuleList::getInstance()
					->findOne(["json_contains(season_ids,'{$selectSeasonId}')", 'competition_id' => $competitionId], 'text');
				$competitionDescribe = empty($competitionDescribe['text']) ? '' : $competitionDescribe['text'];
				// 输出数据
				$result = [
					'table' => $tableData,
					'promotion' => $promotion,
					'current_season_id' => $currentSeasonId,
					'competition_describe' => $competitionDescribe,
				];
				$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
				break;
			case 3:
				// 输出数据
				$result = [];
				$tmp = SeasonMatchList::getInstance()->findAll(['season_id' => $selectSeasonId, 'home_team_id|away_team_id' => $teamId]);
				if (!empty($tmp)) {
					$competitionIds = $teamIds = [];
					array_walk($tmp, function ($v) use (&$competitionIds, &$teamIds) {
						$id = intval($v['competition_id']);
						if ($id > 0 && !in_array($id, $competitionIds)) $competitionIds[] = $id;
						$id = intval($v['home_team_id']);
						if ($id > 0 && !in_array($id, $teamIds)) $teamIds[] = $id;
						$id = intval($v['away_team_id']);
						if ($id > 0 && !in_array($id, $teamIds)) $teamIds[] = $id;
					});
					$competitionMapper = empty($competitionIds) ? [] : AdminCompetition::getInstance()
						->findAll(['competition_id' => [$competitionIds, 'in']], 'competition_id,short_name_zh', 'competition_id,asc',
							false, 0, 0, 'competition_id,short_name_zh,true');
					$teamMapper = empty($teamIds) ? [] : AdminTeam::getInstance()->findAll(['team_id' => [$teamIds, 'in']], 'team_id,name_zh', null,
						false, 0, 0, 'team_id,name_zh,true');
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
				$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
				break;
			case 4:
				$teamStr = $playerStr = '';
				$seasonTeamPlayerKey = 'season_team_player_' . $selectSeasonId;
				$tmp = Cache::get($seasonTeamPlayerKey);
				if (empty($tmp)) {
					$tmp = SeasonTeamPlayer::getInstance()->findOne(['season_id' => $selectSeasonId], 'teams_stats,players_stats');
					if (!empty($tmp)) {
						$teamStr = preg_replace('/\[?(,\s)?{\"team\":\s{\"id\":\s(?!' . $teamId . ')\d+,((?!,\s{\"team\":).)+/', '', $tmp['teams_stats']);
						$teamStr = trim($teamStr, '[,]');
						$playerStr = preg_replace('/\[?(,\s)?{\"team\":\s{\"id\":\s(?!' . $teamId . ')\d+,((?!,\s{\"team\":).)+/', '', $tmp['players_stats']);
						$playerStr = '[' . trim($playerStr, '[,]') . ']';
						Cache::set($seasonTeamPlayerKey, ['teams' => $teamStr, 'players' => $playerStr], 300);
					}
				} else {
					$teamStr = $tmp['teams'];
					$playerStr = $tmp['players'];
				}
				// 获取球队信息
				if (empty($teamStr)) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);
				$teamInfo = json_decode($teamStr, true);
				if (empty($teamInfo['matches'])) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);
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
				$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
				break;
			case 5: //阵容
				// 战队信息映射
				$players = $playerIds = [];
				$tmp = AdminTeamLineUp::getInstance()->findOne(['team_id' => $teamId], 'squad');
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
				if (!empty($playerIds)) $playerMapper = AdminPlayer::getInstance()
					->findAll(['player_id' => [$playerIds, 'in']], 'player_id,market_value,logo,age,weight,height,nationality', null,
						false, 0, 0, 'player_id,*,true');
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
				$managerInfo = $team->getManager();
				$result['C'] = [
					'name_zh' => empty($managerInfo['name_zh']) ? '' : $managerInfo['name_zh'],
					'logo' => $this->playerLogoUrlPrefix . (empty($managerInfo['logo']) ? '' : $managerInfo['logo']),
					'manager_id' => $team['manager_id'],
				];
				$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
		}
	}
	
	/**
	 * 关键词搜索
	 * @throws
	 */
	public function contentByKeyWord()
	{
		$params = $this->param();
		// 关键字
		$keywords = isset($params['key_word']) ? trim($params['key_word']) : '';
		if (empty($keywords)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 类型
		$type = empty($params['type']) || intval($params['type']) < 1 ? 0 : intval($params['type']);
		if ($type != 1 && $type != 2 && $type != 3) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 分页参数
		$page = empty($params['page']) ? 1 : $params['page'];
		$size = empty($params['size']) ? 10 : $params['size'];
		// 输出数据
		if ($type == 1) { //赛事
			$where = ['name_zh|short_name_zh' => [$keywords, 'like']];
			$fields = 'competition_id,name_zh,short_name_zh,logo';
			$result = AdminCompetition::getInstance()->findAll($where, $fields, 'competition_id,desc', true, $page, $size);
		} elseif ($type == 2) { //球队
			$where = ['name_zh' => [$keywords, 'like']];
			$result = AdminTeam::getInstance()->findAll($where, 'team_id,name_zh,logo', 'team_id,desc', true, $page, $size);
		} else { //球员
			$where = ['name_zh' => [$keywords, 'like']];
			$result = AdminPlayer::getInstance()->findAll($where, 'player_id,name_zh,logo', 'market_value,desc', true, $page, $size);
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 球队转入转出记录
	 * @throws
	 */
	public function teamChangeClubHistory()
	{
		$params = $this->param();
		// 类型校验
		$type = empty($params['type']) || intval($params['type']) < 1 ? 0 : intval($params['type']);
		if ($type != 1 && $type != 2) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 球队信息
		$teamId = empty($params['team_id']) || intval($params['team_id']) < 1 ? 0 : intval($params['team_id']);
		$team = $teamId < 1 ? null : AdminTeam::getInstance()->findOne(['team_id' => $teamId]);
		if (empty($team)) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 分页参数
		$page = empty($params['page']) ? 1 : $params['page'];
		$size = empty($params['size']) ? 10 : $params['size'];
		if ($type == 1) {
			$result = AdminPlayerChangeClub::getInstance()
				->findAll(['to_team_id' => $teamId], null, 'transfer_time,desc', true, $page, $size);
			if (!empty($result['list'])) $result['list'] = FrontService::handChangePlayer($result['list']);
		} else {
			$result = AdminPlayerChangeClub::getInstance()
				->findAll(['from_team_id' => $teamId], null, 'transfer_time,desc', true, $page, $size);
			if (!empty($result['list'])) $result['list'] = FrontService::handChangePlayer($result['list']);
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 热搜赛事
	 * @throws
	 */
	public function hotSearchCompetition()
	{
		// 配置信息
		$where = ['sys_key' => AdminSysSettings::SETTING_HOT_SEARCH_COMPETITION];
		$config = AdminSysSettings::getInstance()->findOne($where, 'sys_value');
		// 赛事清单
		$list = empty($config['sys_value']) ? [] : json_decode($config['sys_value'], true);
		if (!empty($list)) $list = AdminCompetition::getInstance()
			->findAll(['competition_id' => [$list, 'in']], 'competition_id,short_name_zh,logo');
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $list);
	}
	
	/**
	 * 国家分类赛事
	 * @throws
	 */
	public function getCompetitionByCountry()
	{
		$params = $this->param();
		// 分类ID
		$categoryId = empty($params['category_id']) || intval($params['category_id']) < 1 ? 0 : intval($params['category_id']);
		// 类型
		$type = empty($params['type']) || intval($params['type']) < 1 ? 0 : intval($params['type']);
		if (empty($type)) {
			$where = ['category_id' => $categoryId];
			$list = $categoryId < 1 ? [] : AdminCountryList::getInstance()->findAll($where, 'id,name_zh,logo');
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $list);
		}
		// 国家ID
		$countryId = empty($params['country_id']) || intval($params['country_id']) < 1 ? 0 : intval($params['country_id']);
		if ($categoryId > 0) {
			if ($countryId > 0) {
				$where = ['category_id' => $categoryId, 'country_id' => $countryId];
				$list = AdminCompetition::getInstance()->findAll($where, 'competition_id,short_name_zh,logo');
				$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $list);
			}
			$list = AdminCountryList::getInstance()->findAll(['category_id' => $categoryId], 'country_id,name_zh');
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $list);
		}
		$this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
	}
	
	/**
	 * 获取赛事信息
	 * @throws
	 */
	public function getContinentCompetition()
	{
		$params = $this->param();
		$categoryId = empty($params['category_id']) || intval($params['category_id']) < 1 ? 0 : intval($params['category_id']);
		if ($categoryId < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 获取清单
		$where = ['category_id' => $categoryId, 'country_id' => 0];
		$list = AdminCompetition::getInstance()->findAll($where, 'competition_id,short_name_zh,logo');
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $list);
	}
}