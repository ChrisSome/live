<?php

namespace App\HttpController\Match;

use App\lib\Tool;
use App\Common\AppFunc;
use App\Model\AdminTeam;
use App\Model\AdminUser;
use App\Utility\Log\Log;
use App\lib\FrontService;
use App\Model\AdminMatch;
use App\Model\AdminSteam;
use App\Task\MatchNotice;
use App\Model\AdminPlayer;
use App\Model\AdminSeason;
use easySwoole\Cache\Cache;
use App\Model\AdminHonorList;
use App\Model\AdminStageList;
use App\Model\AdminTeamHonor;
use App\Model\AdminAlphaMatch;
use App\Model\AdminMatchTlive;
use App\Model\AdminPlayerStat;
use App\Model\AdminTeamLineUp;
use App\Model\SeasonMatchList;
use App\GeTui\BatchSignalPush;
use App\Model\AdminCompetition;
use App\Model\AdminManagerList;
use App\Model\AdminNoticeMatch;
use App\Model\AdminUserSetting;
use App\Model\SeasonTeamPlayer;
use App\Utility\Message\Status;
use App\Model\AdminClashHistory;
use App\Base\FrontUserController;
use App\WebSocket\WebSocketStatus;
use App\Model\SeasonAllTableDetail;
use App\Model\AdminPlayerHonorList;
use App\Model\AdminPlayerChangeClub;
use App\HttpController\User\WebSocket;
use App\Model\AdminCompetitionRuleList;
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
class FootballMatch extends FrontUserController
{
	protected $isCheckSign = false;
	protected $needCheckToken = false;
	private $startId = 0;
	private $user = 'mark9527';
	private $secret = 'dbfe8d40baa7374d54596ea513d8da96';
	protected $url = 'https://open.sportnanoapi.com';
	protected $uriTeamList = '/api/v4/football/team/list?user=%s&secret=%s&time=%s'; //球队列表
	protected $uriTeamList1 = '/api/v4/football/team/list?user=%s&secret=%s&id=%s'; //球队列表
	protected $uriM = 'https://open.sportnanoapi.com/api/v4/football/match/diary?user=%s&secret=%s&date=%s';
	protected $uriCompetition = '/api/v4/football/competition/list?user=%s&secret=%s&time=%s';
	protected $uriStage = '/api/v4/football/stage/list?user=%s&secret=%s&date=%s';
	protected $uriSteam = '/api/sports/stream/urls_free?user=%s&secret=%s'; //直播地址
	protected $uriLineUp = '/api/v4/football/team/squad/list?user=%s&secret=%s&time=%s'; //阵容
	protected $uriPlayer = '/api/v4/football/player/list?user=%s&secret=%s&time=%s'; //球员
	protected $uriCompensation = '/api/v4/football/compensation/list?user=%s&secret=%s&time=%s'; //获取比赛历史同赔统计数据列表
	protected $liveUrl = 'https://open.sportnanoapi.com/api/sports/football/match/detail_live?user=%s&secret=%s';//比赛列表
	protected $seasonUrl = 'https://open.sportnanoapi.com/api/v4/football/season/list?user=%s&secret=%s&time=%s'; //更新赛季
	protected $playerStat = 'https://open.sportnanoapi.com/api/v4/football/player/list/with_stat?user=%s&secret=%s&time=%s'; //获取球员能力技术列表
	protected $playerChangeClubHistory = 'https://open.sportnanoapi.com/api/v4/football/transfer/list?user=%s&secret=%s&time=%s'; //球员转会历史
	protected $teamHonor = 'https://open.sportnanoapi.com/api/v4/football/team/honor/list?user=%s&secret=%s&id=%s'; //球队荣誉
	protected $honorList = 'https://open.sportnanoapi.com/api/v4/football/honor/list?user=%s&secret=%s&time=%s'; //荣誉详情
	protected $allStat = 'https://open.sportnanoapi.com/api/v4/football/season/all/stats/detail?user=%s&secret=%s&id=%s'; //获取赛季球队球员统计详情-全量
	protected $stageList = 'https://open.sportnanoapi.com/api/v4/football/stage/list?user=%s&secret=%s&time=%s'; //获取阶段列表
	protected $managerList = 'https://open.sportnanoapi.com/api/v4/football/manager/list?user=%s&secret=%s&time=%s'; //教练
	protected $uriDeleteMatch = '/api/v4/football/deleted?user=%s&secret=%s'; //删除或取消的比赛
	protected $playerHonorList = 'https://open.sportnanoapi.com/api/v4/football/player/honor/list?user=%s&secret=%s&time=%s'; //获取球员荣誉列表
	protected $trendDetail = 'https://open.sportnanoapi.com/api/v4/football/match/trend/detail?user=%s&secret=%s&id=%s'; //获取比赛趋势详情
	protected $competitionRule = 'https://open.sportnanoapi.com/api/v4/football/competition/rule/list?user=%s&secret=%s&time=%s'; //获取赛事赛制列表
	protected $history = 'https://open.sportnanoapi.com/api/v4/football/match/live/history?user=%s&secret=%s&id=%s'; //历史比赛数据
	protected $seasonAllTableDetail = 'https://open.sportnanoapi.com/api/v4/football/season/all/table/detail?user=%s&secret=%s&id=%s'; //获取赛季积分榜数据-全量
	protected $uriPlayerOne = '/api/v4/football/player/list?user=%s&secret=%s&id=%s'; //球员
	
	/**
	 * 更新球队列表 1day/次
	 * @throws
	 */
	function getTeamList()
	{
		while (true) {
			$timestamp = AdminTeam::getInstance()->max('updated_at');
			$url = sprintf($this->url . $this->uriTeamList, $this->user, $this->secret, $timestamp + 1);
			$tmp = Tool::getInstance()->postApi($url);
			$tmp = empty($tmp) ? null : json_decode($tmp, true);
			$list = empty($tmp['results']) ? null : $tmp['results'];
			if (empty($list)) break;
			foreach ($list as $v) {
				$id = intval($v['id']);
				$data = [
					'team_id' => $id,
					'name_zh' => $v['name_zh'],
					'name_en' => $v['name_en'],
					'national' => $v['national'],
					'updated_at' => $v['updated_at'],
					'manager_id' => $v['manager_id'],
					'short_name_zh' => $v['short_name_zh'],
					'short_name_en' => $v['short_name_en'],
					'competition_id' => $v['competition_id'],
					'foundation_time' => $v['foundation_time'],
					'logo' => empty($v['logo']) ? '' : $v['logo'],
					'website' => empty($v['website']) ? '' : $v['website'],
					'country_id' => empty($v['country_id']) ? 0 : $v['country_id'],
					'venue_id' => empty($v['venue_id']) ? 0 : intval($v['venue_id']),
					'market_value' => empty($v['market_value']) ? '' : $v['market_value'],
					'country_logo' => empty($v['country_logo']) ? '' : $v['country_logo'],
					'total_players' => empty($v['total_players']) ? 0 : intval($v['total_players']),
					'foreign_players' => empty($v['foreign_players']) ? 0 : intval($v['foreign_players']),
					'national_players' => empty($v['national_players']) ? 0 : intval($v['national_players']),
					'market_value_currency' => empty($v['market_value_currency']) ? '' : $v['market_value_currency'],
				];
				$team = AdminTeam::getInstance()->findOne(['team_id' => $v['id']]);
				if (!empty($team)) {
					unset($data['team_id']);
					AdminTeam::getInstance()->update($data, ['team_id' => $id]);
				} else {
					AdminTeam::getInstance()->insert($data);
				}
			}
		}
	}
	
	/**
	 * 当天比赛 十分钟/次
	 * @param int $updateYesterday
	 * @throws
	 */
	function getTodayMatches(int $updateYesterday = 0)
	{
		$time = date('Ymd');
		if ($updateYesterday > 0) $time = date("Ymd", strtotime("-1 day"));
		$url = sprintf($this->uriM, $this->user, $this->secret, $time);
		$tmp = Tool::getInstance()->postApi($url);
		$tmp = empty($tmp) ? [] : json_decode($tmp, true);
		$list = empty($tmp['results']) ? [] : $tmp['results'];
		if (empty($list)) {
			Log::getInstance()->info(date('Y-d-d H:i:s') . ' 更新无数据');
			return;
		}
		foreach ($list as $v) {
			$id = intval($v['id']);
			$competitionId = intval($v['competition_id']);
			$match = $id > 0 ? AdminMatch::getInstance()->findOne(['match_id' => $id]) : null;
			if (!empty($match)) {
				AdminMatch::getInstance()->saveDataById($id, [
					'status_id' => $v['status_id'],
					'updated_at' => $v['updated_at'],
					'match_time' => $v['match_time'],
					'home_position' => $v['home_position'],
					'away_position' => $v['away_position'],
					'home_scores' => json_encode($v['home_scores']),
					'away_scores' => json_encode($v['away_scores']),
					'round' => empty($v['round']) ? '' : json_encode($v['round']),
					'referee_id' => empty($v['referee_id']) ? 0 : intval($v['referee_id']),
					'coverage' => empty($v['coverage']) ? '' : json_encode($v['coverage']),
					'environment' => empty($v['environment']) ? '' : json_encode($v['environment']),
				]);
			} else {
				$homeTeam = empty($v['home_team_id']) ? null : AdminTeam::getInstance()->findOne(['team_id' => $v['home_team_id']]);
				$awayTeam = empty($v['away_team_id']) ? null : AdminTeam::getInstance()->findOne(['team_id' => $v['away_team_id']]);
				if (empty($homeTeam) || empty($awayTeam)) continue;
				$competition = $competitionId < 1 ? null : AdminCompetition::getInstance()->findOne(['competition_id' => $v['competition_id']]);
				AdminMatch::getInstance()->insert([
					'match_id' => $id,
					'note' => $v['note'],
					'neutral' => $v['neutral'],
					'season_id' => $v['season_id'],
					'status_id' => $v['status_id'],
					'updated_at' => $v['updated_at'],
					'match_time' => $v['match_time'],
					'home_team_id' => $v['home_team_id'],
					'away_team_id' => $v['away_team_id'],
					'home_position' => $v['home_position'],
					'away_position' => $v['away_position'],
					'competition_id' => $v['competition_id'],
					'home_scores' => json_encode($v['home_scores']),
					'away_scores' => json_encode($v['away_scores']),
					'venue_id' => isset($v['venue_id']) ? $v['venue_id'] : 0,
					'round' => isset($v['round']) ? json_encode($v['round']) : '',
					'referee_id' => isset($v['referee_id']) ? intval($v['referee_id']) : 0,
					'home_team_logo' => empty($homeTeam['logo']) ? '' : $homeTeam['logo'],
					'away_team_logo' => empty($awayTeam['logo']) ? '' : $awayTeam['logo'],
					'coverage' => isset($v['coverage']) ? json_encode($v['coverage']) : '',
					'environment' => isset($v['environment']) ? json_encode($v['environment']) : '',
					'competition_color' => empty($competition['primary_color']) ? '' : $competition['primary_color'],
					'home_team_name' => empty($homeTeam['short_name_zh']) ? $homeTeam['name_zh'] : $homeTeam['short_name_zh'],
					'away_team_name' => empty($awayTeam['short_name_zh']) ? $awayTeam['name_zh'] : $awayTeam['short_name_zh'],
					'competition_name' => empty($competition['short_name_zh']) ? (empty($competition['name_zh']) ? '' : $competition['name_zh']) : $competition['short_name_zh'],
				]);
				Log::getInstance()->info('insert_match_id-1-' . $id);
			}
		}
		$updateYesterday = $updateYesterday > 1 ? '昨日' : '当天';
		Log::getInstance()->info(date('Y-d-d H:i:s') . ' ' . $updateYesterday . '比赛更新完成');
	}
	
	/**
	 * 未来一周比赛列表 30 min / time
	 */
	function getWeekMatches()
	{
		$weeks = FrontService::getWeek();
		foreach ($weeks as $week) {
			$url = sprintf($this->uriM, $this->user, $this->secret, $week);
			$tmp = Tool::getInstance()->postApi($url);
			$tmp = empty($tmp) ? [] : json_decode($tmp, true);
			$list = empty($tmp['results']) ? [] : $tmp['results'];
			if (empty($list)) continue;
			foreach ($list as $v) {
				$id = intval($v['id']);
				$match = $id > 0 ? AdminMatch::getInstance()->findOne(['match_id' => $id]) : null;
				if (!empty($match)) {
					AdminMatch::getInstance()->saveDataById($id, [
						'status_id' => $v['status_id'],
						'updated_at' => $v['updated_at'],
						'match_time' => $v['match_time'],
						'home_position' => $v['home_position'],
						'away_position' => $v['away_position'],
						'home_scores' => json_encode($v['home_scores']),
						'away_scores' => json_encode($v['away_scores']),
						'round' => empty($v['round']) ? '' : json_encode($v['round']),
						'referee_id' => empty($v['referee_id']) ? 0 : intval($v['referee_id']),
						'coverage' => empty($v['coverage']) ? '' : json_encode($v['coverage']),
						'environment' => empty($v['environment']) ? '' : json_encode($v['environment']),
					]);
				} else {
					$homeTeam = empty($v['home_team_id']) ? null : AdminTeam::getInstance()->findOne(['team_id' => $v['home_team_id']]);
					$awayTeam = empty($v['away_team_id']) ? null : AdminTeam::getInstance()->findOne(['team_id' => $v['away_team_id']]);
					if (empty($homeTeam) || empty($awayTeam)) continue;
					$competition = AdminCompetition::getInstance()->findOne(['competition_id' => $v['competition_id']]);
					AdminMatch::getInstance()->insert([
						'match_id' => $id,
						'note' => $v['note'],
						'neutral' => $v['neutral'],
						'season_id' => $v['season_id'],
						'status_id' => $v['status_id'],
						'updated_at' => $v['updated_at'],
						'match_time' => $v['match_time'],
						'home_team_id' => $v['home_team_id'],
						'away_team_id' => $v['away_team_id'],
						'competition_id' => $v['competition_id'],
						'home_position' => $v['home_position'],
						'away_position' => $v['away_position'],
						'home_scores' => json_encode($v['home_scores']),
						'away_scores' => json_encode($v['away_scores']),
						'venue_id' => isset($v['venue_id']) ? $v['venue_id'] : 0,
						'round' => isset($v['round']) ? json_encode($v['round']) : '',
						'referee_id' => isset($v['referee_id']) ? $v['referee_id'] : 0,
						'home_team_logo' => empty($homeTeam['logo']) ? '' : $homeTeam['logo'],
						'away_team_logo' => empty($awayTeam['logo']) ? '' : $awayTeam['logo'],
						'coverage' => isset($v['coverage']) ? json_encode($v['coverage']) : '',
						'environment' => isset($v['environment']) ? json_encode($v['environment']) : '',
						'competition_color' => empty($competition['primary_color']) ? '' : $competition['primary_color'],
						'home_team_name' => empty($homeTeam['short_name_zh']) ? $homeTeam['name_zh'] : $homeTeam['short_name_zh'],
						'away_team_name' => empty($awayTeam['short_name_zh']) ? $awayTeam['name_zh'] : $awayTeam['short_name_zh'],
						'competition_name' => empty($competition['short_name_zh']) ? (empty($competition['name_zh']) ? '' : $competition['name_zh']) : $competition['short_name_zh'],
					]);
					Log::getInstance()->info('insert_match_id-1-' . $id);
				}
			}
		}
		Log::getInstance()->info(date('Y-d-d H:i:s') . ' 未来一周比赛更新完成');
	}
	
	/**
	 * one day / time 赛事列表
	 * @throws
	 */
	function getCompetitiones()
	{
		$timestamp = AdminCompetition::getInstance()->max('updated_at');
		$url = sprintf($this->url . $this->uriCompetition, $this->user, $this->secret, $timestamp + 1);
		$tmp = Tool::getInstance()->postApi($url);
		$tmp = empty($tmp) ? null : json_decode($tmp, true);
		$list = empty($tmp['results']) ? [] : $tmp['results'];
		if (empty($list)) {
			Log::getInstance()->info(date('Y-m-d H:i:s') . ' 更新赛季');
			return;
		}
		foreach ($list as $v) {
			$id = intval($v['id']);
			$data = [
				'logo' => $v['logo'],
				'type' => $v['type'],
				'competition_id' => $id,
				'name_zh' => $v['name_zh'],
				'cur_round' => $v['cur_round'],
				'country_id' => $v['country_id'],
				'updated_at' => $v['updated_at'],
				'round_count' => $v['round_count'],
				'category_id' => $v['category_id'],
				'cur_stage_id' => $v['cur_stage_id'],
				'short_name_zh' => $v['short_name_zh'],
				'cur_season_id' => $v['cur_season_id'],
				'host' => empty($v['host']) ? '' : json_encode($v['host']),
				'primary_color' => empty($v['primary_color']) ? '' : $v['primary_color'],
				'newcomers' => empty($v['newcomers']) ? '' : json_encode($v['newcomers']),
				'divisions' => empty($v['divisions']) ? '' : json_encode($v['divisions']),
				'secondary_color' => empty($v['secondary_color']) ? '' : $v['secondary_color'],
				'most_titles' => empty($v['most_titles']) ? '' : json_encode($v['most_titles']),
				'title_holder' => empty($v['title_holder']) ? '' : json_encode($v['title_holder']),
			];
			$competition = AdminCompetition::getInstance()->findOne(['competition_id' => $id]);
			if (!empty($competition)) {
				unset($data['competition_id']);
				AdminCompetition::getInstance()->update($data, ['competition_id' => $id]);
			} else {
				AdminCompetition::getInstance()->insert($data);
			}
		}
	}
	
	/**
	 * 直播地址 10min/次
	 * @throws
	 */
	public function getSteam()
	{
		$url = sprintf($this->url . $this->uriSteam, $this->user, $this->secret);
		$tmp = Tool::getInstance()->postApi($url);
		$tmp = empty($tmp) ? null : json_decode($tmp, true);
		if (empty($tmp['data'])) return;
		foreach ($tmp['data'] as $v) {
			$id = intval($v['match_id']);
			$data = [
				'match_id' => $id,
				'comp' => $v['comp'],
				'home' => $v['home'],
				'away' => $v['away'],
				'pc_link' => $v['pc_link'],
				'sport_id' => $v['sport_id'],
				'match_time' => $v['match_time'],
				'mobile_link' => $v['mobile_link'],
			];
			$team = AdminSteam::getInstance()->findOne(['match_id' => $id]);
			if (!empty($team)) {
				AdminSteam::getInstance()->update($data, ['match_id' => $id]);
			} else {
				AdminSteam::getInstance()->insert($data);
			}
		}
		Log::getInstance()->info('视频直播源更新完毕');
	}
	
	/**
	 * 更新球员列表 one day/time
	 * @throws
	 */
	public function players()
	{
		while (true) {
			$timestamp = AdminPlayer::getInstance()->max('updated_at');
			$url = sprintf($this->url . $this->uriPlayer, $this->user, $this->secret, $timestamp + 1);
			$tmp = Tool::getInstance()->postApi($url);
			$tmp = empty($tmp) ? null : json_decode($tmp, true);
			if (empty($tmp) || $tmp['code'] != 0 || $tmp['query']['total'] == 0 || empty($tmp['results'])) break;
			foreach ($tmp['results'] as $v) {
				$id = intval($v['id']);
				$data = [
					'player_id' => $id,
					'age' => $v['age'],
					'logo' => $v['logo'],
					'weight' => $v['weight'],
					'height' => $v['height'],
					'name_zh' => $v['name_zh'],
					'name_en' => $v['name_en'],
					'team_id' => $v['team_id'],
					'birthday' => $v['birthday'],
					'position' => $v['position'],
					'country_id' => $v['country_id'],
					'updated_at' => $v['updated_at'],
					'nationality' => $v['nationality'],
					'market_value' => $v['market_value'],
					'contract_until' => $v['contract_until'],
					'preferred_foot' => $v['preferred_foot'],
					'market_value_currency' => $v['market_value_currency'],
				];
				$player = AdminPlayer::getInstance()->findOne(['player_id' => $id]);
				if (!empty($player)) {
					AdminPlayer::getInstance()->update($data, ['player_id' => $id]);
				} else {
					AdminPlayer::getInstance()->insert($data);
				}
			}
		}
	}
	
	/**
	 * 每天凌晨十二点半一次
	 * @throws
	 */
	public function clashHistory()
	{
		while (true) {
			$timestamp = AdminClashHistory::getInstance()->max('updated_at');
			$url = sprintf($this->url . $this->uriCompensation, $this->user, $this->secret, $timestamp + 1);
			$tmp = Tool::getInstance()->postApi($url);
			$tmp = empty($tmp) ? null : json_decode($tmp, true);
			if (empty($tmp) || $tmp['code'] != 0 || $tmp['query']['total'] == 0 || empty($tmp['results'])) {
				if ($tmp['query']['total'] == 0) $this->output(Status::CODE_OK, '更新完成');
				break;
			}
			foreach ($tmp['results'] as $v) {
				$id = intval($v['id']);
				$insert = [
					'match_id' => $id,
					'updated_at' => $v['updated_at'],
					'recent' => json_encode($v['recent']),
					'history' => json_encode($v['history']),
					'similar' => json_encode($v['similar']),
				];
				$history = AdminClashHistory::getInstance()->findOne(['match_id' => $id]);
				if (!empty($history)) {
					AdminClashHistory::getInstance()->update($insert, ['match_id' => $id]);
				} else {
					AdminClashHistory::getInstance()->insert($insert);
				}
			}
		}
	}
	
	/**
	 * 每分钟一次
	 * 通知用户关注比赛即将开始 提前十五分钟通知
	 */
	public function noticeUserMatch()
	{
		$time = time();
		$where = ['match_time' => [[$time + 60 * 15 + 1, $time + 60 * 15 + 1], 'between'], 'status_id' => 1];
		$matches = AdminMatch::getInstance()->findAll($where);
		if (empty($matches)) return;
		foreach ($matches as $v) {
			$id = intval($v['id']);
			$match = AdminNoticeMatch::getInstance()->findOne(['match_id' => $id, 'is_notice' => 0]);
			if (!empty($match)) continue;
			$matchId = intval($v['match_id']);
			$userIds = $matchId < 1 ? null : AppFunc::getUsersInterestMatch($v['match_id']);
			if (empty($userIds)) continue;
			$users = AdminUser::getInstance()->findAll(['id' => [$userIds, 'in']], 'id,cid');
			foreach ($users as $k => $vv) {
				$userId = intval($vv['id']);
				$setting = AdminUserSetting::getInstance()->findOne(['user_id' => $userId]);
				$setting = empty($setting['push']) ? null : json_decode($setting['push'], true);
				$setting = empty($setting['start']) ? null : $setting['start'];
				if (empty($setting)) unset($users[$k]);
			}
			$userIds = array_unique(array_filter(array_column($users, 'id')));
			if (empty($userIds)) continue;
			//
			$competitionName = $v->competitionName();
			if (empty($competitionName['short_name_zh'])) $competitionName = '';
			$homeTeamName = $v->homeTeamName();
			if (empty($homeTeamName['name_zh'])) $homeTeamName = '';
			$awayTeamName = $v->awayTeamName();
			if (empty($awayTeamName['name_zh'])) $awayTeamName = '';
			// 接收方IDs
			$clientIds = array_unique(array_filter(array_column($users, 'cid')));
			$title = '开赛通知';
			$content = sprintf('您关注的【%s联赛】%s-%s将于5分钟后开始比赛，不要忘了哦', $competitionName, $homeTeamName, $awayTeamName);
			$batchPush = new BatchSignalPush();
			$data = [
				'type' => 10,
				'title' => $title,
				'content' => $content,
				'match_id' => $matchId,
				'uids' => json_encode($userIds),
			];
			$noticeMatch = AdminNoticeMatch::getInstance()->findOne(['match_id' => $matchId, 'type' => 10]);
			if (!empty($noticeMatch)) continue; // 推送失败 直接就不推了
			$info['rs'] = $rs = AdminNoticeMatch::getInstance()->insert($data);
			// 开赛通知
			$batchPush->pushMessageToList($clientIds, [
				'title' => $title,
				'notice_id' => $rs,
				'content' => $content,
				'payload' => ['item_id' => $matchId, 'type' => 1],
			]);
		}
	}
	
	/**
	 * 取消或者删除的比赛 5min/次
	 */
	public function deleteMatch()
	{
		$url = sprintf($this->url . $this->uriDeleteMatch, $this->user, $this->secret);
		$tmp = Tool::getInstance()->postApi($url);
		$tmp = empty($tmp) ? null : json_decode($tmp, true);
		$list = empty($tmp['results']['match']) ? null : $tmp['results']['match'];
		if (empty($tmp) || $tmp['code'] == 0 || empty($list)) {
			Log::getInstance()->info(date('Y-m-d H:i:s') . ' 删除或取消比赛完成');
		}
		foreach ($list as $v) {
			$v = intval($v);
			$match = $v > 0 ? AdminMatch::getInstance()->findOne(['match_id' => $v]) : null;
			if (!empty($match)) AdminMatch::getInstance()->setField('is_delete', 1, ['match_id' => $v]);
		}
		Log::getInstance()->info(date('Y-m-d H:i:s') . ' 删除或取消比赛完成');
	}
	
	/**
	 * 昨天的比赛 十分钟一次  凌晨0-3
	 */
	public function updateYesMatch()
	{
		$this->getTodayMatches(1);
	}
	
	/**
	 * 定时推送脚本 30秒/次  需要重点维护
	 * @throws
	 */
	public function matchTlive()
	{
		$tmp = Tool::getInstance()->postApi(sprintf($this->liveUrl, $this->user, $this->secret));
		$list = empty($tmp) ? null : json_decode($tmp, true);
		if (empty($list)) {
			Log::getInstance()->info('333333333');
			return;
		}
		$matchInfo = [];
		foreach ($list as $v) {
			// Status 上半场 / 下半场 / 中场 / 加时赛 / 点球决战 / 结束
			$status = empty($v['score'][1]) ? -1 : intval($v['score'][1]);
			if (!in_array($status, [1, 2, 3, 4, 5, 7, 8])) continue;
			// 比赛ID
			$id = intval($v['id']);
			// 获取比赛信息
			$tmp = $id < 1 ? null : AdminMatch::getInstance()->findOne(['match_id' => $id]);
			// 无效比赛 跳过
			if (empty($tmp)) continue;
			// 不在热门赛事中 跳过
			if (!AppFunc::isInHotCompetition(intval($tmp['competition_id']))) continue;
			// 比赛结束 跳过
			$tmp = AdminMatchTlive::getInstance()->findOne(['match_id' => $id]);
			if (empty($tmp)) continue;
			// 比赛结束通知
			$isEnd = empty($v['score'][1]) || intval($v['score'][1]) == 8;
			if ($isEnd) {
				$data = ['match_id' => $id, 'item' => $v, 'score' => $v['score'], 'type' => 12];
				TaskManager::getInstance()->async(new MatchNotice($data));
			}
			// 比赛趋势
			$tmp = Tool::getInstance()->postApi(sprintf($this->trendDetail, $this->user, $this->secret, $id));
			$tmp = empty($tmp) ? null : json_decode($tmp, true);
			$matchTrend = [];
			if ($tmp['code'] == 0 && !empty($tmp['results'])) $matchTrend = $tmp['results'];
			// 设置比赛进行时间
			AppFunc::setPlayingTime($id, $v['score']);
			// 比赛开始的通知
			$isStart = !empty($v['score'][1]) && intval($v['score'][1]) == 2 && !Cache::get('match_notice_start:' . $id);
			if ($isStart) {
				$data = ['match_id' => $id, 'score' => $v['score'], 'item' => $v, 'type' => 10];
				TaskManager::getInstance()->async(new MatchNotice($data));
				Cache::set('match_notice_start:' . $id, 1, 60 * 240);
			}
			$matchStats = [];
			if (!empty($v['stats'])) {
				foreach ($v['stats'] as $vv) {
					// 21：射正 22：射偏  23:进攻  24危险进攻 25：控球率
					$type = empty($vv['type']) || intval($vv['type']) < 1 ? 0 : intval($vv['type']);
					if ($type == 21 || $type == 22 || $type == 23 || $type == 24 || $type == 25) $matchStats[] = $vv;
				}
				Cache::set('match_stats_' . $id, json_encode($matchStats), 60 * 240);
			}
			$corner = [];
			$tliveCount = 0;
			if (!empty($v['tlive'])) {
				$tmp = Cache::get('match_tlive_count' . $id) ?: 0;
				$tmp = intval($tmp) < 1 ? 0 : intval($tmp);
				$tliveCount = empty($v['tlive']) ? 0 : count($v['tlive']);
				if ($tliveCount > $tmp) { //直播文字
					Cache::set('match_tlive_count' . $id, $tliveCount, 60 * 240);
					$diff = array_slice($v['tlive'], $tmp);
					(new WebSocket())->contentPush($diff, $id);
				}
				$tmp = 0;
				foreach ($v['tlive'] as $vv) {
					if (empty($vv['type']) || $vv['type'] != 2) continue; //非角球 忽略
					$tmp += 1;
					$corner[] = [
						'type' => $vv['type'],
						'time' => intval($vv['time']),
						'position' => $vv['position'],
					];
				}
				Cache::set('match_tlive_' . $v['id'], json_encode($v['tlive']), 60 * 240);
			}
			$goalCount = $redCardCount = $yellowCardCount = [];
			$goalIncident = $redCardIncident = $yellowCardIncident = 0;
			$lastGoalIncident = $lastRedCardIncident = $lastYellowCardIncident = [];
			if (!empty($v['incidents'])) {
				$goalIncidentOld = Cache::get('goal_count_' . $id);
				$redCardIncidentOld = Cache::get('red_card_count_' . $id);
				$yellowCardIncidentOld = Cache::get('yellow_card_count_' . $id);
				foreach ($v['incidents'] as $vv) {
					$tliveCount += 1;
					// 1进球 3黄牌 4红牌 8点球
					$type = empty($vv['type']) ? 0 : intval($vv['type']);
					if (!in_array($type, [1, 3, 4, 8])) continue;
					$data = [
						'type' => $type,
						'time' => intval($vv['time']),
						'position' => $vv['position'],
					];
					if ($type == 1 || $type == 8) { //进球 /点球
						$goalCount[] = $lastGoalIncident = $data;
						$goalIncident += 1;
					} elseif ($type == 3) { //黄牌
						$yellowCardCount[] = $lastYellowCardIncident = $data;
						$yellowCardIncident += 1;
					} elseif ($type == 4) { //红牌
						$redCardCount[] = $lastRedCardIncident = $data;
						$redCardIncident += 1;
					}
				}
				if ($goalIncident > $goalIncidentOld) { // 进球
					$data = ['match_id' => $id, 'last_incident' => $lastGoalIncident, 'score' => $v['score'], 'type' => 1];
					TaskManager::getInstance()->async(new MatchNotice($data));
					Cache::set('goal_count_' . $id, $goalIncident, 60 * 240);
				}
				if ($yellowCardIncident > $yellowCardIncidentOld) { // 黄牌
					$data = ['match_id' => $id, 'last_incident' => $lastYellowCardIncident, 'score' => $v['score'], 'type' => 3];
					TaskManager::getInstance()->async(new MatchNotice($data));
					Cache::set('yellow_card_count_' . $id, $yellowCardIncident, 60 * 240);
				}
				if ($redCardIncident > $redCardIncidentOld) { // 红牌
					$data = ['match_id' => $id, 'last_incident' => $lastRedCardIncident, 'score' => $v['score'], 'type' => 4];
					TaskManager::getInstance()->async(new MatchNotice($data));
					Cache::set('red_card_count_' . $id, $redCardIncident, 60 * 240);
				}
			}
			$matchInfo[] = $data = [
				'score' => [
					'home' => $v['score'][2],
					'away' => $v['score'][3],
				],
				'signal_count' => [
					'corner' => $corner,
					'goal' => $goalCount,
					'red' => $redCardCount,
					'yellow' => $yellowCardCount,
				],
				'match_id' => $id,
				'status' => $status,
				'match_trend' => $matchTrend,
				'match_stats' => $matchStats,
				'time' => AppFunc::getPlayingTime($id),
			];
			Cache::set('match_data_info' . $id, json_encode($data), 60 * 240);
		}
		// 异步的话要做进程间通信，本身也有开销，不如做成同步的，push将数据交给底层，本身不等待
		// $tmp = TaskManager::getInstance()->async(new MatchUpdate(['match_info_list' => $matchInfo]));
		// if (empty($tmp) || intval($tmp) <= 0) {
		// 	Log::getInstance()->info('delivery failed, match list info-' . json_encode($matchInfo));
		// }
		if (empty($matchInfo)) {
			Log::getInstance()->info('333333333');
			return;
		}
		$tool = Tool::getInstance();
		$server = ServerManager::getInstance()->getSwooleServer();
		$fd = 0;
		$data = ['event' => 'match_update', 'match_info_list' => $matchInfo];
		while (true) {
			$list = $server->getClientList($fd, 10);
			if (empty($list) || count($list) === 0) break;
			$fd = end($list);
			foreach ($list as $v) {
				$connection = $server->connection_info($v);
				if (is_array($connection) && $connection['websocket_status'] == 3) {  // 用户正常在线时可以进行消息推送
					Log::getInstance()->info('push succ' . $v);
					$msg = WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC];
					$server->push($v, $tool->writeJson(WebSocketStatus::STATUS_SUCC, $msg, $data));
				} else {
					Log::getInstance()->info('lost-connection-' . $v);
				}
			}
		}
	}
	
	/**
	 * 更新赛季 1hour/次
	 * @throws
	 */
	public function updateSeason()
	{
		$timestamp = AdminSeason::getInstance()->max('updated_at');
		$url = sprintf($this->seasonUrl, $this->user, $this->secret, $timestamp + 1);
		$tmp = Tool::getInstance()->postApi($url);
		$tmp = empty($tmp) ? null : json_decode($tmp, true);
		$list = empty($tmp['results']) ? null : $tmp['results'];
		if (empty($list)) return;
		foreach ($list as $v) {
			$id = intval($v['id']);
			$season = $id < 1 ? null : AdminSeason::getInstance()->findOne(['season_id' => $id]);
			if (empty($season)) {
				AdminSeason::getInstance()->insert([
					'season_id' => $id,
					'year' => $v['year'],
					'end_time' => $v['end_time'],
					'has_table' => $v['has_table'],
					'updated_at' => $v['updated_at'],
					'start_time' => $v['start_time'],
					'is_current' => $v['is_current'],
					'has_team_stats' => $v['has_team_stats'],
					'competition_id' => $v['competition_id'],
					'has_player_stats' => $v['has_player_stats'],
					'competition_rule_id' => $v['competition_rule_id'],
				]);
			} else {
				AdminSeason::getInstance()->update([
					'year' => $v['year'],
					'end_time' => $v['end_time'],
					'has_table' => $v['has_table'],
					'updated_at' => $v['updated_at'],
					'start_time' => $v['start_time'],
					'is_current' => $v['is_current'],
					'has_team_stats' => $v['has_team_stats'],
					'competition_id' => $v['competition_id'],
					'has_player_stats' => $v['has_player_stats'],
					'competition_rule_id' => $v['competition_rule_id'],
				], $id);
			}
		}
	}
	
	/**
	 * 更新赛季排行 1hour/次
	 * @throws
	 */
	public function updatePlayerStat()
	{
		while (true) {
			$timestamp = AdminPlayerStat::getInstance()->max('updated_at');
			$url = sprintf($this->playerStat, $this->user, $this->secret, $timestamp + 1);
			$tmp = Tool::getInstance()->postApi($url);
			$tmp = empty($tmp) ? null : json_decode($tmp, true);
			$isEnd = !empty($resp['query']) && isset($resp['query']['total']) && intval($resp['query']['total']) == 0;
			if ($isEnd) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
			$list = empty($tmp['results']) ? null : $tmp['results'];
			if (empty($list)) break;
			foreach ($list as $v) {
				$id = intval($v['id']);
				$data = [
					'player_id' => $id,
					'age' => $v['age'],
					'logo' => $v['logo'],
					'weight' => $v['weight'],
					'height' => $v['height'],
					'team_id' => $v['team_id'],
					'name_zh' => $v['name_zh'],
					'name_en' => $v['name_en'],
					'birthday' => $v['birthday'],
					'position' => $v['position'],
					'updated_at' => $v['updated_at'],
					'country_id' => $v['country_id'],
					'nationality' => $v['nationality'],
					'market_value' => $v['market_value'],
					'short_name_zh' => $v['short_name_zh'],
					'short_name_en' => $v['short_name_en'],
					'contract_until' => $v['contract_until'],
					'preferred_foot' => $v['preferred_foot'],
					'market_value_currency' => $v['market_value_currency'],
					'ability' => empty($v['ability']) ? '' : json_encode($v['ability']),
					'positions' => empty($v['positions']) ? '' : json_encode($v['positions']),
					'characteristics' => empty($v['characteristics']) ? '' : json_encode($v['characteristics']),
				];
				$player = AdminPlayerStat::getInstance()->findOne(['player_id' => $id]);
				if (empty($player)) {
					AdminPlayerStat::getInstance()->insert($data);
				} else {
					AdminPlayerStat::getInstance()->update($data, ['player_id' => $id]);
				}
			}
		}
	}
	
	/**
	 * 球员转会历史  1day/次
	 * @throws
	 */
	public function playerChangeClubHistory()
	{
		while (true) {
			$timestamp = AdminPlayerChangeClub::getInstance()->max('updated_at');
			$url = sprintf($this->playerChangeClubHistory, $this->user, $this->secret, $timestamp + 1);
			$tmp = Tool::getInstance()->postApi($url);
			$tmp = empty($tmp) ? null : json_decode($tmp, true);
			$isEnd = !empty($resp['query']) && isset($resp['query']['total']) && intval($resp['query']['total']) == 0;
			if ($isEnd) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
			$list = empty($tmp['results']) ? null : $tmp['results'];
			if (empty($list)) break;
			foreach ($list as $v) {
				$id = intval($v['id']);
				$data = [
					'id' => $id,
					'player_id' => $v['player_id'],
					'to_team_id' => $v['to_team_id'],
					'updated_at' => $v['updated_at'],
					'transfer_fee' => $v['transfer_fee'],
					'from_team_id' => $v['from_team_id'],
					'to_team_name' => $v['to_team_name'],
					'transfer_type' => $v['transfer_type'],
					'transfer_time' => $v['transfer_time'],
					'transfer_desc' => $v['transfer_desc'],
					'from_team_name' => $v['from_team_name'],
				];
				$tmp = $id < 1 ? null : AdminPlayerChangeClub::getInstance()->findOne(['id' => $id]);
				if (empty($tmp)) AdminPlayerChangeClub::getInstance()->insert($data);
			}
		}
	}
	
	/**
	 * 球队荣誉  1day/次
	 * @throws
	 */
	public function teamHonor()
	{
		while (true) {
			$timestamp = AdminTeamHonor::getInstance()->max('updated_at');
			$url = sprintf($this->teamHonor, $this->user, $this->secret, $timestamp + 1);
			$tmp = Tool::getInstance()->postApi($url);
			$tmp = empty($tmp) ? null : json_decode($tmp, true);
			$isEnd = !empty($resp['query']) && isset($resp['query']['total']) && intval($resp['query']['total']) == 0;
			if ($isEnd) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
			$list = empty($tmp['results']) ? null : $tmp['results'];
			if (empty($list)) break;
			foreach ($list as $v) {
				$id = intval($v['id']);
				$data = [
					'team_id' => $id,
					'update_at' => $v['updated_at'],
					'team' => json_encode($v['team']),
					'honors' => json_encode($v['honors']),
				];
				$tmp = $id < 1 ? null : AdminTeamHonor::getInstance()->findOne(['team_id' => $id]);
				if (empty($tmp)) {
					AdminTeamHonor::getInstance()->insert($data);
				} else {
					unset($data['team_id']);
					AdminTeamHonor::getInstance()->update($data, ['team_id' => $id]);
				}
			}
			$this->startId = intval($tmp['query']['max_id']);
		}
	}
	
	/**
	 * 荣誉详情  1day/次
	 * @throws
	 */
	public function honorList()
	{
		while (true) {
			$timestamp = AdminHonorList::getInstance()->max('updated_at');
			$url = sprintf($this->honorList, $this->user, $this->secret, $timestamp + 1);
			$tmp = Tool::getInstance()->postApi($url);
			$tmp = empty($tmp) ? null : json_decode($tmp, true);
			$isEnd = !empty($resp['query']) && isset($resp['query']['total']) && intval($resp['query']['total']) == 0;
			if ($isEnd) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
			$list = empty($tmp['results']) ? null : $tmp['results'];
			if (empty($list)) break;
			foreach ($list as $v) {
				$id = intval($v['id']);
				$data = [
					'id' => $id,
					'logo' => $v['logo'],
					'title_zh' => $v['title_zh'],
					'updated_at' => $v['updated_at'],
				];
				$tmp = $id < 1 ? null : AdminHonorList::getInstance()->findOne($id);
				if (empty($tmp)) {
					AdminHonorList::getInstance()->insert($data);
				} else {
					unset($data['id']);
					AdminHonorList::getInstance()->saveDataById($id, $data);
				}
			}
		}
	}
	
	/**
	 * 阶段列表 1day/次
	 * 注意：新增赛季跟新增阶段的时候 都需要同步赛季比赛列表
	 * 因为赛季中比赛并非事先完全确定 比如1/8决赛，需要临时更新
	 * @throws
	 */
	public function stageList()
	{
		while (true) {
			$timestamp = AdminStageList::getInstance()->max('updated_at');
			$url = sprintf($this->stageList, $this->user, $this->secret, $timestamp + 1);
			$tmp = Tool::getInstance()->postApi($url);
			$tmp = empty($tmp) ? null : json_decode($tmp, true);
			$list = empty($tmp['results']) ? null : $tmp['results'];
			if (empty($list)) break;
			foreach ($list as $v) {
				$id = intval($v['id']);
				$seasonId = intval($v['season_id']);
				$data = [
					'stage_id' => $id,
					'mode' => $v['mode'],
					'order' => $v['order'],
					'season_id' => $seasonId,
					'name_zh' => $v['name_zh'],
					'name_en' => $v['name_en'],
					'name_zht' => $v['name_zht'],
					'updated_at' => $v['updated_at'],
					'group_count' => $v['group_count'],
					'round_count' => $v['round_count'],
				];
				$tmp = $id < 1 ? null : AdminStageList::getInstance()->findOne(['stage_id' => $id]);
				if (empty($tmp)) {
					AdminStageList::getInstance()->insert($data);
					
					//新增赛季比赛
					$url = sprintf('https://open.sportnanoapi.com/api/v4/football/match/season?user=%s&secret=%s&id=%s', $this->user, $this->secret, $seasonId);
					$tmp = Tool::getInstance()->postApi($url);
					$tmp = empty($tmp) ? null : json_decode($tmp, true);
					$items = empty($tmp['results']) ? null : $tmp['results'];
					if (empty($items)) continue;
					foreach ($items as $vv) {
						$id = intval($vv['id']);
						$tmp = $id < 1 ? null : SeasonMatchList::getInstance()->findOne(['match_id' => $id]);
						if (!empty($tmp)) continue;
						$homeTeamId = intval($vv['home_team_id']);
						$awayTeamId = intval($vv['away_team_id']);
						$competitionId = intval($vv['competition_id']);
						$homeTeam = AdminTeam::getInstance()->findOne(['team_id', $homeTeamId]);
						$homeTeamName = empty($homeTeam) ? '' : (empty($homeTeam['short_name_zh']) ? $homeTeam['name_zh'] : $homeTeam['short_name_zh']);
						$awayTeam = AdminTeam::getInstance()->findOne(['team_id' => $awayTeamId]);
						$awayTeamName = empty($awayTeam) ? '' : (empty($awayTeam['short_name_zh']) ? $awayTeam['name_zh'] : $awayTeam['short_name_zh']);
						$competition = AdminCompetition::getInstance()->findOne(['competition_id' => $competitionId]);
						$competitionName = empty($competition) ? '' : (empty($competition['short_name_zh']) ? $competition['name_zh'] : $competition['short_name_zh']);
						SeasonMatchList::getInstance()->insert([
							'match_id' => $id,
							'note' => $vv['note'],
							'neutral' => $vv['neutral'],
							'home_team_id' => $homeTeamId,
							'away_team_id' => $awayTeamId,
							'season_id' => $vv['season_id'],
							'status_id' => $vv['status_id'],
							'match_time' => $vv['match_time'],
							'updated_at' => $vv['updated_at'],
							'home_team_name' => $homeTeamName,
							'away_team_name' => $awayTeamName,
							'competition_id' => $competitionId,
							'competition_name' => $competitionName,
							'home_position' => $vv['home_position'],
							'away_position' => $vv['away_position'],
							'home_scores' => json_encode($vv['home_scores']),
							'away_scores' => json_encode($vv['away_scores']),
							'venue_id' => isset($vv['venue_id']) ? $vv['venue_id'] : 0,
							'round' => isset($vv['round']) ? json_encode($vv['round']) : '',
							'referee_id' => isset($vv['referee_id']) ? $vv['referee_id'] : 0,
							'home_team_logo' => empty($homeTeam['logo']) ? '' : $homeTeam['logo'],
							'away_team_logo' => empty($awayTeam['logo']) ? '' : $awayTeam['logo'],
							'coverage' => isset($vv['coverage']) ? json_encode($data['coverage']) : '',
							'environment' => isset($vv['environment']) ? json_encode($vv['environment']) : '',
							'competition_color' => empty($competition['primary_color']) ? '' : $competition['primary_color'],
						]);
					}
				} else {
					AdminStageList::getInstance()->update($data, ['stage_id' => $id]);
				}
			}
			if (!empty($tmp['query']['max_id'])) $this->startId = intval($tmp['query']['max_id']);
		}
	}
	
	/**
	 * 更新教练列表  1day/次
	 * @throws
	 */
	public function managerList()
	{
		while (true) {
			$timestamp = AdminManagerList::getInstance()->max('updated_at');
			$url = sprintf($this->managerList, $this->user, $this->secret, $timestamp);
			$tmp = Tool::getInstance()->postApi($url);
			$tmp = empty($tmp) ? null : json_decode($tmp, true);
			$list = empty($tmp['results']) ? null : $tmp['results'];
			if (empty($list)) break;
			foreach ($list as $v) {
				$id = intval($v['id']);
				$data = [
					'age' => $v['age'],
					'manager_id' => $id,
					'logo' => $v['logo'],
					'team_id' => $v['team_id'],
					'name_zh' => $v['name_zh'],
					'name_en' => $v['name_en'],
					'birthday' => $v['birthday'],
					'updated_at' => $v['updated_at'],
					'nationality' => $v['nationality'],
					'preferred_formation' => $v['preferred_formation'],
				];
				$tmp = $id < 1 ? null : AdminManagerList::getInstance()->where(['manager_id' => $id]);
				if (empty($tmp)) {
					AdminManagerList::getInstance()->insert($data);
				} else {
					AdminManagerList::getInstance()->update($data, ['manager_id' => $id]);
				}
			}
		}
	}
	
	/**
	 * 更新阵容列表  1hour/次
	 * @throws
	 */
	public function getLineUp()
	{
		$timestamp = AdminTeamLineUp::getInstance()->max('updated_at');
		$url = sprintf($this->url . $this->uriLineUp, $this->user, $this->secret, $timestamp);
		$tmp = Tool::getInstance()->postApi($url);
		$tmp = empty($tmp) ? null : json_decode($tmp, true);
		$list = empty($tmp['results']) ? null : $tmp['results'];
		if (empty($list)) $this->output(Status::CODE_OK, '更新完成');
		foreach ($list as $v) {
			$id = intval($v['id']);
			$data = [
				'team_id' => $id,
				'updated_at' => $v['updated_at'],
				'team' => json_encode($v['team']),
				'squad' => json_encode($v['squad']),
			];
			$tmp = $id < 1 ? null : AdminTeamLineUp::getInstance()->findOne(['team_id' => $id]);
			if (empty($tmp)) {
				AdminTeamLineUp::getInstance()->insert($data);
			} else {
				AdminTeamLineUp::getInstance()->update($data, ['team_id' => $id]);
			}
		}
	}
	
	/**
	 * 更新球员荣誉列表
	 * @throws
	 */
	public function playerHonorList()
	{
		while (true) {
			$timestamp = AdminPlayerHonorList::getInstance()->max('updated_at');
			$url = sprintf($this->playerHonorList, $this->user, $this->secret, $timestamp + 1);
			$tmp = Tool::getInstance()->postApi($url);
			$tmp = empty($tmp) ? null : json_decode($tmp, true);
			$list = empty($tmp['results']) ? null : $tmp['results'];
			if (empty($list)) break;
			foreach ($list as $v) {
				$id = intval($v['id']);
				$data = [
					'player_id' => $id,
					'updated_at' => $v['updated_at'],
					'player' => json_encode($v['player']),
					'honors' => json_encode($v['honors']),
				];
				$tmp = $id < 1 ? null : AdminPlayerHonorList::getInstance()->findOne(['player_id' => $id]);
				if (empty($tmp)) {
					AdminPlayerHonorList::getInstance()->insert($data);
				} else {
					AdminPlayerHonorList::getInstance()->update($data, ['player_id' => $id]);
				}
			}
		}
	}
	
	/**
	 * 更新赛制列表 1day/次
	 * @throws
	 */
	public function competitionRule()
	{
		while (true) {
			$timestamp = AdminCompetitionRuleList::getInstance()->max('updated_at');
			$url = sprintf($this->competitionRule, $this->user, $this->secret, $timestamp + 1);
			$tmp = Tool::getInstance()->postApi($url);
			$tmp = empty($tmp) ? null : json_decode($tmp, true);
			$list = empty($tmp['results']) ? null : $tmp['results'];
			if (empty($list)) break;
			foreach ($list as $v) {
				$id = intval($v['id']);
				$data = [
					'id' => $id,
					'text' => $v['text'],
					'updated_at' => $v['updated_at'],
					'competition_id' => $v['competition_id'],
					'season_ids' => json_encode($v['season_ids']),
				];
				$tmp = $id < 1 ? null : AdminCompetitionRuleList::getInstance()->findOne($id);
				if (empty($tmp)) {
					AdminCompetitionRuleList::getInstance()->insert($data);
				} else {
					unset($data['id']);
					AdminCompetitionRuleList::getInstance()->saveDataById($id, $data);
				}
			}
		}
	}
	
	/**
	 * AlphaMatch更新直播地址 1minute/次
	 * @throws
	 */
	public function updateAlphaMatch()
	{
		$header = ['xcode: ty019'];
		$params = ['matchType' => 'football', 'matchDate' => date('Ymd')];
		$url = 'http://www.xsports-live.com:8086/live/sport/getLiveInfo';
		$tmp = Tool::getInstance()->postApi($url, 'GET', $params, $header);
		$tmp = empty($tmp) ? null : json_decode($tmp, true);
		$list = empty($tmp['data']) ? null : $tmp['data'];
		if (empty($list)) return;
		foreach ($list as $v) {
			$id = trim($v['matchId'] . '');
			$tmp = empty($id) ? null : AdminAlphaMatch::getInstance()->findOne(['matchId' => $id]);
			if (empty($tmp)) {
				AdminAlphaMatch::getInstance()->insert([
					'matchId' => $id,
					'liga' => $v['liga'],
					'teams' => $v['teams'],
					'ligaEn' => $v['ligaEn'],
					'status' => $v['status'],
					'liveUrl' => $v['liveUrl'],
					'teamsEn' => $v['teamsEn'],
					'liveUrl2' => $v['liveUrl2'],
					'liveUrl3' => $v['liveUrl3'],
					'sportType' => $v['sportType'],
					'matchTime' => $v['matchTime'],
					'liveStatus' => $v['liveStatus'],
					'timeFormart' => $v['timeFormart'],
				]);
			} else {
				AdminAlphaMatch::getInstance()->update([
					'status' => $v['status'],
					'liveUrl' => $v['liveUrl'],
					'liveUrl2' => $v['liveUrl2'],
					'liveUrl3' => $v['liveUrl3'],
					'matchTime' => $v['matchTime'],
					'liveStatus' => $v['liveStatus'],
				], ['matchId' => $id]);
			}
		}
	}
	
	/**
	 * 更新赛季比赛列表 1day/次 [已废]
	 * @throws
	 */
	public function updateMatchSeason()
	{
		$seasonId = SeasonMatchList::getInstance()->max('season_id');
		if (empty($seasonId) || $seasonId < 1) $seasonId = 0;
		$list = $seasonId < 1 ? null : AdminSeason::getInstance()
			->findAll(['season_id' => [$seasonId, '>']], 'season_id', null, false, 1, 2000);
		if (empty($list)) return;
		foreach ($list as $v) {
			$id = intval($v['season_id']);
			$url = 'https://open.sportnanoapi.com/api/v4/football/match/season?user=%s&secret=%s&id=%s';
			$url = sprintf($url, $this->user, $this->secret, $id);
			$tmp = Tool::getInstance()->postApi($url);
			$tmp = empty($tmp) ? null : json_decode($tmp, true);
			$list = empty($tmp['results']) ? null : $tmp['results'];
			if (empty($list)) continue;
			foreach ($list as $vv) {
				$id = intval($vv['id']);
				$tmp = SeasonMatchList::getInstance()->findOne(['match_id' => $id]);
				if (!empty($tmp)) continue;
				$homeTeamId = intval($vv['home_team_id']);
				$awayTeamId = intval($vv['away_team_id']);
				$competitionId = intval($vv['competition_id']);
				$homeTeam = AdminTeam::getInstance()->findOne(['team_id', $homeTeamId]);
				$homeTeamName = empty($homeTeam) ? '' : (empty($homeTeam['short_name_zh']) ? $homeTeam['name_zh'] : $homeTeam['short_name_zh']);
				$awayTeam = AdminTeam::getInstance()->findOne(['team_id' => $awayTeamId]);
				$awayTeamName = empty($awayTeam) ? '' : (empty($awayTeam['short_name_zh']) ? $awayTeam['name_zh'] : $awayTeam['short_name_zh']);
				$competition = AdminCompetition::getInstance()->findOne(['competition_id' => $competitionId]);
				$competitionName = empty($competition) ? '' : (empty($competition['short_name_zh']) ? $competition['name_zh'] : $competition['short_name_zh']);
				SeasonMatchList::getInstance()->insert([
					'match_id' => $id,
					'note' => $vv['note'],
					'neutral' => $vv['neutral'],
					'home_team_id' => $homeTeamId,
					'away_team_id' => $awayTeamId,
					'status_id' => $vv['status_id'],
					'season_id' => $vv['season_id'],
					'match_time' => $vv['match_time'],
					'updated_at' => $vv['updated_at'],
					'home_team_name' => $homeTeamName,
					'away_team_name' => $awayTeamName,
					'competition_id' => $competitionId,
					'competition_name' => $competitionName,
					'home_position' => $vv['home_position'],
					'away_position' => $vv['away_position'],
					'home_scores' => json_encode($vv['home_scores']),
					'away_scores' => json_encode($vv['away_scores']),
					'venue_id' => empty($vv['venue_id']) ? 0 : $vv['venue_id'],
					'round' => empty($vv['round']) ? '' : json_encode($vv['round']),
					'referee_id' => empty($vv['referee_id']) ? 0 : $vv['referee_id'],
					'home_team_logo' => empty($homeTeam['logo']) ? '' : $homeTeam['logo'],
					'away_team_logo' => empty($awayTeam['logo']) ? '' : $awayTeam['logo'],
					'coverage' => empty($vv['coverage']) ? '' : json_encode($vv['coverage']),
					'environment' => empty($vv['environment']) ? '' : json_encode($vv['environment']),
					'competition_color' => empty($competition['primary_color']) ? '' : $competition['primary_color'],
				]);
			}
		}
	}
	
	/**
	 * 凌晨五点跑一次
	 * 更新赛季球队球员统计详情-全量
	 * 更新赛季积分榜
	 * @throws
	 */
	public function updateYesterdayMatch()
	{
		$startTime = strtotime(date('Ymd', strtotime('-1 day')));
		$endTime = $startTime + 3600 * 24;
		$list = AdminMatch::getInstance()->findAll(['match_time' => [[$startTime, $endTime], 'between'], 'status_id' => 6]);
		$seasonIds = [];
		foreach ($list as $v) {
			$id = intval($v['season_id']);
			if (in_array($id, $seasonIds)) continue;
			$seasonIds[] = $id;
			//更新赛季球队球员统计详情-全量
			$url = sprintf($this->allStat, $this->user, $this->secret, $id);
			$tmp = Tool::getInstance()->postApi($url);
			$tmp = empty($tmp) ? null : json_decode($tmp, true);
			$data = empty($tmp['results']) ? null : $tmp['results'];
			if (empty($data)) continue;
			$tmp = $id < 1 ? null : SeasonTeamPlayer::getInstance()->findOne(['season_id' => $id]);
			if (empty($tmp)) {
				SeasonTeamPlayer::getInstance()->insert([
					'season_id' => $id,
					'shooters' => json_encode($data['shooters']),
					'updated_at' => json_encode($data['updated_at']),
					'teams_stats' => json_encode($data['teams_stats']),
					'players_stats' => json_encode($data['players_stats']),
				]);
			} else {
				SeasonTeamPlayer::getInstance()->update([
					'shooters' => json_encode($data['shooters']),
					'updated_at' => json_encode($data['updated_at']),
					'teams_stats' => json_encode($data['teams_stats']),
					'players_stats' => json_encode($data['players_stats']),
				], ['season_id' => $id]);
			}
			//更新赛季积分榜
			$url = sprintf($this->seasonAllTableDetail, $this->user, $this->secret, $id);
			$tmp = Tool::getInstance()->postApi($url);
			$tmp = empty($tmp) ? null : json_decode($tmp, true);
			$data = empty($tmp['results']) ? null : $tmp['results'];
			if (empty($data)) continue;
			$tmp = $id < 1 ? null : SeasonAllTableDetail::getInstance()->findOne(['season_id' => $id]);
			if (empty($tmp)) {
				SeasonAllTableDetail::getInstance()->insert([
					'season_id' => $id,
					'tables' => json_encode($data['tables']),
					'promotions' => json_encode($data['promotions']),
				]);
			} else {
				SeasonAllTableDetail::getInstance()->update([
					'tables' => json_encode($data['tables']),
					'promotions' => json_encode($data['promotions']),
				], ['season_id' => $id]);
			}
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);
	}
	
	/**
	 * todo ... [更新赛季比赛列表 1day/次] [已废]
	 */
	public function updateMatchSeason1()
	{
		// $season_id = Cache::get('update_season_id');
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
							'competition_color' => $competition->primary_color,
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
	 * 比赛数据矫正
	 * @throws
	 */
	public function fixMatch()
	{
		$matchId = $this->param('match_id', true);
		$url = 'https://open.sportnanoapi.com/api/v4/football/match/live/history?user=%s&secret=%s&id=%s';
		$url = sprintf($url, $this->user, $this->secret, $matchId);
		$tmp = Tool::getInstance()->postApi($url);
		$tmp = empty($tmp) ? null : json_decode($tmp, true);
		$data = empty($tmp['results']) ? null : $tmp['results'];
		if (empty($data)) return;
		AdminMatch::getInstance()->update([
			'status_id' => $data['score'][1],
			'home_scores' => json_encode($data['score'][2]),
			'away_scores' => json_encode($data['score'][3]),
		], ['match_id' => $matchId]);
		//比赛趋势
		$tmp = Tool::getInstance()->postApi(sprintf($this->trendDetail, $this->user, $this->secret, $matchId));
		$tmp = empty($tmp) ? null : json_decode($tmp, true);
		$matchTrend = empty($tmp['results']) ? [] : $tmp['results'];
		$tmp = AdminMatchTlive::getInstance()->findOne(['match_id' => $matchId]);
		if (empty($tmp)) AdminMatchTlive::getInstance()->insert([
			'match_id' => $data['id'],
			'match_trend' => json_encode($matchTrend),
			'stats' => isset($data['stats']) ? json_encode($data['stats']) : '',
			'score' => isset($data['score']) ? json_encode($data['score']) : '',
			'tlive' => isset($data['tlive']) ? json_encode($data['tlive']) : '',
			'incidents' => isset($data['incidents']) ? json_encode($data['incidents']) : '',
		]);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);
	}
}
