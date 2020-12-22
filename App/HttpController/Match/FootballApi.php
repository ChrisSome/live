<?php

namespace App\HttpController\Match;

use App\lib\Tool;
use App\Model\AdminUser;
use App\lib\FrontService;
use App\Model\AdminMatch;
use App\Model\AdminPlayer;
use easySwoole\Cache\Cache;
use EasySwoole\ORM\DbManager;
use App\Model\AdminMatchTlive;
use App\Model\AdminCompetition;
use App\Model\AdminSysSettings;
use App\Utility\Message\Status;
use App\Model\AdminClashHistory;
use App\Base\FrontUserController;
use App\Model\SeasonAllTableDetail;
use App\Model\AdminInterestMatches;
use App\Model\AdminUserInterestCompetition;

class FootballApi extends FrontUserController
{
	protected $needCheckToken = false;
	protected $lineUpDetail = 'https://open.sportnanoapi.com/api/v4/football/match/lineup/detail?user=%s&secret=%s&id=%s';
	protected $matchHistory = 'https://open.sportnanoapi.com/api/v4/football/match/live/history?user=%s&secret=%s&id=%s';
	protected $urlIntRank = 'https://open.sportnanoapi.com/api/v4/football/season/table/detail?user=%s&secret=%s&id=%s';
	protected $playerLogo = 'http://cdn.sportnanoapi.com/football/player/';
	const STATUS_NO_START = 1;
	const STATUS_SCHEDULE = [0, 1, 9];
	const STATUS_PLAYING = [2, 3, 4, 5, 7];
	const STATUS_RESULT = [8, 9, 10, 11, 12, 13];
	const hotCompetition = [
		'hot' => [['competition_id' => 45, 'short_name_zh' => '欧洲杯'],
			['competition_id' => 47, 'short_name_zh' => '欧联杯'],
			['competition_id' => 542, 'short_name_zh' => '中超']],
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
	
	/**
	 * 赛事列表
	 * @throws
	 */
	public function getCompetition()
	{
		// 当前登录用户ID
		$authId = empty($this->auth['id']) ? 0 : $this->auth['id'];
		// 配置数据
		$config = AdminSysSettings::getInstance()->findOne(['sys_key' => AdminSysSettings::RECOMMEND_COM], 'sys_value');
		// 输出数据
		$result = empty($config['sys_value']) ? [] : json_decode($config['sys_value'], true);
		if (empty($result)) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);
		// 填充数据
		$interest = [];
		$tmp = $authId < 1 ? null : AdminUserInterestCompetition::getInstance()->findOne(['user_id' => $authId], 'competition_ids');
		if (!empty($tmp['competition_ids'])) $interest = json_decode($tmp['competition_ids'], true);
		foreach ($result as $k => $v) {
			foreach ($v as $kk => $vv) {
				$recommend[$k][$kk]['is_notice'] = in_array($vv['competition_id'], $interest);
			}
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 比赛列表
	 * @throws
	 */
	public function frontMatchList()
	{
		// 当前登录用户ID
		$authId = empty($this->auth['id']) ? 0 : intval($this->auth['id']);
		$todayTime = strtotime(date('Y-m-d', time()));
		$tomorrowTime = strtotime(date('Y-m-d', strtotime('+1 day')));
		$afterTomorrowTime = strtotime(date('Y-m-d', strtotime('+2 day')));
		$hotCompetition = FrontService::getHotCompetitionIds();
		$where = [
			'status_id' => [self::STATUS_PLAYING, 'in'],
			'competition_id' => [$hotCompetition, 'in'],
			'match_time' => [time(), '<'], 'is_delete' => 0,
		];
		$playingMatch = AdminMatch::getInstance()->findAll($where, null, 'match_time,asc');
		$where = [
			'match_time' => [[$todayTime, $tomorrowTime], 'between'], 'status_id' => [self::STATUS_PLAYING, 'not in'],
			'competition_id' => [$hotCompetition, 'in'], 'is_delete' => 0,
		];
		$todayMatch = AdminMatch::getInstance()->findAll($where, null, 'match_time,asc');
		$where = [
			'match_time' => [[$tomorrowTime, $afterTomorrowTime], 'between'],
			'competition_id' => [$hotCompetition, 'in'], 'is_delete' => 0,
		];
		$tomorrowMatch = AdminMatch::getInstance()->findAll($where, 'match_time,asc');
		$playing = FrontService::handMatch($playingMatch, $authId);
		$today = FrontService::handMatch($todayMatch, $authId);
		$tomorrow = FrontService::handMatch($tomorrowMatch, $authId);
		// 输出数据
		$result = ['playing' => $playing, 'today' => $today, 'tomorrow' => $tomorrow];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 正在进行中比赛列表
	 * @throws
	 */
	public function matchListPlaying()
	{
		// 当前登录用户ID
		$authId = empty($this->auth['id']) ? 0 : intval($this->auth['id']);
		$selectCompetitionIdArr = DbManager::getInstance()->invoke(function ($client) use ($authId) {
			$userInterestCompetitiones = $recommand_competition_id_arr = [];
			if ($authId > 0) {
				$competitiones = AdminUserInterestCompetition::invoke($client)->get(['user_id' => $authId]);
				$userInterestCompetitiones = json_decode($competitiones['competition_ids'], true);
			}
			$recommand_competition_id_arr = AdminSysSettings::invoke($client)
				->where('sys_key', AdminSysSettings::COMPETITION_ARR)->get();
			$in_competition_arr = json_decode($recommand_competition_id_arr['sys_value'], true);
			return array_intersect($userInterestCompetitiones, $in_competition_arr);
		});
		if (empty($selectCompetitionIdArr)) $this->output(Status::CODE_WRONG_INTERNET, Status::$msg[Status::CODE_WRONG_INTERNET]);
		$match = DbManager::getInstance()->invoke(function ($client) use ($selectCompetitionIdArr) {
			return AdminMatch::invoke($client)->where('is_delete', 0)
				->where('competition_id', $selectCompetitionIdArr, 'in')
				->where('status_id', self::STATUS_PLAYING, 'in')->all();
		});
		$formatMatch = FrontService::formatMatchTwo($match, $authId);
		//用户关注比赛数量
		$userInterestMatchCount = DbManager::getInstance()->invoke(function ($client) use ($authId) {
			return AdminInterestMatches::invoke($client)->get(['uid' => $authId]);
		});
		$count = 0;
		if ($userInterestMatchCount > 0) $count = count(json_decode($userInterestMatchCount['match_ids']));
		// 输出数据
		$result = ['list' => $formatMatch, 'user_interest_count' => $count, 'count' => count($formatMatch)];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 用户关注的比赛列表
	 * @throws
	 */
	public function userInterestMatchList()
	{
		// 当前登录用户ID
		$authId = empty($this->auth['id']) ? 0 : intval($this->auth['id']);
		if ($authId < 1) $this->output(Status::CODE_VERIFY_ERR, '登陆令牌缺失或者已过期');
		$params = $this->params;
		// 分页参数
		$page = empty($params['page']) || intval($params['page']) < 1 ? 1 : intval($params['page']);
		$size = empty($params['size']) || intval($params['page']) < 1 ? 20 : intval($params['size']);
		//
		$tmp = AdminInterestMatches::getInstance()->findOne(['uid' => $authId]);
		if (empty($tmp['match_ids'])) {
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => [], 'count' => 0]);
		}
		$matchIds = json_decode($tmp['match_ids'], true);
		if (empty($matchIds)) $this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => [], 'count' => 0]);
		
		$formatMatchId = array_slice($matchIds, ($page - 1) * $size, $size);
		$count = count($matchIds);
		if (!empty($formatMatchId) && is_array($formatMatchId)) {
			$where = ['match_id' => [$formatMatchId, 'in'], 'is_delete' => 0];
			$matches = AdminMatch::getInstance()->findAll($where, null, 'match_time,asc');
			$data = FrontService::formatMatchTwo($matches, $authId);
		} else {
			$data = [];
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => $data, 'count' => $count]);
	}
	
	/**
	 * 赛程列表
	 * @throws
	 */
	public function matchSchedule()
	{
		// 参数校验
		$params = $this->params;
		$time = empty($params['time']) || intval($params['time']) < 1 ? 0 : intval($params['time']);
		if ($time < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 当前登录用户ID
		$authId = empty($this->auth['id']) ? 0 : intval($this->auth['id']);
		// 分页参数
		$page = empty($params['page']) || intval($params['page']) < 1 ? 1 : intval($params['page']);
		$size = empty($params['size']) || intval($params['page']) < 1 ? 20 : intval($params['size']);
		
		$isToday = $time == date('Y-m-d');
		$start = strtotime($time);
		$end = $start + 60 * 60 * 24;
		
		$userInterestCompetitiones = [];
		
		if ($authId && $competitiones = AdminUserInterestCompetition::getInstance()->findOne(['user_id' => $authId])) {
			$userInterestCompetitiones = json_decode($competitiones['competition_ids'], true);
		}
		
		//后台推荐赛事
		$in_competition_arr = [];
		
		if ($recommand_competition_id_arr = AdminSysSettings::getInstance()->findOne(['sys_key' => AdminSysSettings::COMPETITION_ARR])) {
			$in_competition_arr = json_decode($recommand_competition_id_arr->sys_value, true);
		}
		
		if ($userInterestCompetitiones) {
			$selectCompetition = array_intersect($in_competition_arr, $userInterestCompetitiones);
		} else {
			$selectCompetition = $in_competition_arr;
		}
		$selectCompetition = array_values($selectCompetition);
		if (!$selectCompetition) $this->output(Status::CODE_WRONG_INTERNET, Status::$msg[Status::CODE_WRONG_INTERNET]);
		// 分页数据
		$where = [
			'status_id' => [self::STATUS_SCHEDULE, 'in'],
			'match_time' => [[$isToday ? time() : $start, $end], 'between'],
			'is_delete' => 0, 'competition_id' => [$selectCompetition, 'in'],
		];
		[$list, $count] = AdminMatch::getInstance()->findAll($where, null, 'match_time,asc', true, $page, $size);
		$list = empty($list) ? [] : FrontService::formatMatchTwo($list, $authId);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => $list, 'count' => $count]);
	}
	
	/**
	 * 赛果列表
	 * @throws
	 */
	public function matchResult()
	{
		// 参数校验
		$params = $this->params;
		$time = empty($params['time']) || intval($params['time']) < 1 ? 0 : intval($params['time']);
		if ($time < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		// 当前登录用户ID
		$authId = empty($this->auth['id']) ? 0 : intval($this->auth['id']);
		//需要展示的赛事id 以及用户关注的比赛
		[$selectCompetitionIdArr, $interestMatchArr] = AdminUser::getUserShowCompetitionId($authId);
		// 分页参数
		$page = empty($params['page']) || intval($params['page']) < 1 ? 1 : intval($params['page']);
		$size = empty($params['size']) || intval($params['page']) < 1 ? 20 : intval($params['size']);
		// 分页数据
		$start = strtotime($time);
		$end = $start + 60 * 60 * 24;
		$where = [
			'match_time' => [[$start, $end], 'between'],
			'status_id' => [self::STATUS_RESULT, 'in'],
			'competition_id' => [$selectCompetitionIdArr, 'in'],
			'is_delete' => 0,
		];
		[$list, $count] = AdminMatch::getInstance()->findAll($where, null, 'match_time,desc', true, $page, $size);
		$list = empty($list) ? [] : FrontService::formatMatchThree($list, $authId, $interestMatchArr);
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => $list, 'count' => $count]);
	}
	
	/**
	 * 首发阵容
	 * @throws
	 */
	public function lineUpDetail()
	{
		// 参数校验
		$params = $this->params;
		$matchId = empty($params['match_id']) ? 0 : intval($params['match_id']);
		if ($matchId < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		//
		$res = Tool::getInstance()->postApi(sprintf($this->lineUpDetail, 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', $matchId));
		$decode = json_decode($res, true);
		$homeFirstPlayers = [];
		$homeAlternatePlayers = [];
		$awayFirstPlayers = [];
		$awayAlternatePlayers = [];
		if ($decode['code'] == 0) {
			$homeFormation = $decode['results']['home_formation'];
			$awayFormation = $decode['results']['away_formation'];
			$home = $decode['results']['home'];
			$away = $decode['results']['away'];
			if ($home) {
				foreach ($home as $homeItem) {
					if (empty($home['logo'])) {
						$homeplayerinfo = AdminPlayer::getInstance()->findOne(['player_id' => $homeItem['id']]);
					}
					$homePlayer['player_id'] = $homeItem['id'];
					$homePlayer['name'] = $homeItem['name'];
					$homePlayer['logo'] = isset($home['logo']) ? $this->playerLogo . $homeItem['logo'] : ($homeplayerinfo ? $homeplayerinfo->logo : '');
					$homePlayer['position'] = $homeItem['position'];
					$homePlayer['shirt_number'] = $homeItem['shirt_number'];
					if ($homeItem['first']) {
						$homeFirstPlayers[] = $homePlayer;
					} else {
						$homeAlternatePlayers[] = $homePlayer;
					}
					unset($homePlayer);
				}
			}
			
			if ($away) {
				foreach ($away as $awayItem) {
					if (!$awayItem['logo']) $awayplayerinfo = AdminPlayer::getInstance()->findOne(['player_id' => $awayItem['id']]);
					$awayPlayer['player_id'] = $awayItem['id'];
					$awayPlayer['name'] = $awayItem['name'];
					$awayPlayer['logo'] = $awayItem['logo'] ? $this->playerLogo . $awayItem['logo'] : ($awayplayerinfo ? $awayplayerinfo->logo : '');
					$awayPlayer['position'] = $awayItem['position'];
					$awayPlayer['shirt_number'] = $awayItem['shirt_number'];
					if ($awayItem['first']) {
						$awayFirstPlayers[] = $awayPlayer;
					} else {
						$awayAlternatePlayers[] = $awayPlayer;
					}
					unset($awayPlayer);
				}
			}
			
			$matchInfo = AdminMatch::getInstance()->findOne(['match_id' => $matchId, 'is_delete' => 0]);
			if (empty($matchInfo)) {
				$homeTeamInfo = [];
				$awayTeamInfo = [];
			} else {
				$homeTeamInfo['firstPlayers'] = $homeFirstPlayers;
				$homeTeamInfo['alternatePlayers'] = $homeAlternatePlayers;
				$homeTeamInfo['teamName'] = $matchInfo->homeTeamName()['name_zh'];
				$homeTeamInfo['teamLogo'] = $matchInfo->homeTeamName()['logo'];
				$homeTeamInfo['homeManagerName'] = $matchInfo->homeTeamName()->getManager()->name_zh;
				$homeTeamInfo['homeFormation'] = $homeFormation;
				$awayTeamInfo['firstPlayers'] = $awayFirstPlayers;
				$awayTeamInfo['alternatePlayers'] = $awayAlternatePlayers;
				$awayTeamInfo['teamName'] = $matchInfo->awayTeamName()['name_zh'];
				$awayTeamInfo['teamLogo'] = $matchInfo->awayTeamName()['logo'];
				$awayTeamInfo['awayManagerName'] = $matchInfo->awayTeamName()->getManager()->name_zh;
				$awayTeamInfo['awayFormation'] = $awayFormation;
			}
			// 输出数据
			$result = ['home' => $homeTeamInfo,'away' => $awayTeamInfo];
			$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
		}
		$this->output(Status::CODE_MATCH_LINE_UP_ERR, Status::$msg[Status::CODE_MATCH_LINE_UP_ERR]);
	}
	
	/**
	 * 历史交锋
	 * @throws
	 */
	public function getClashHistory()
	{
		$params = $this->params;
		// 参数校验
		$matchId = empty($params['match_id']) || intval($params['match_id']) < 1 ? 0 : intval($params['match_id']);
		if ($matchId < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		$senses = AdminClashHistory::getInstance()->findOne(['match_id' => $matchId]);
		$match = AdminMatch::getInstance()->findOne(['match_id' => $matchId, 'is_delete' => 0]);
		//积分排名
		$currentSeasonId = $match->competitionName()->cur_season_id;
		if (empty($currentSeasonId)) {
			$intRank = [];
		} else {
			$res = SeasonAllTableDetail::getInstance()->findOne(['season_id' => $currentSeasonId]);
			$decode = json_decode($res->tables, true);
			$promotions = json_decode($res->promotions, true);
			if ($promotions) {
				$rows = isset($decode[0]['rows']) ? $decode[0]['rows'] : [];
			} else {
				$rows = isset($decode['rows']) ? $decode['rows'] : [];
			}
			if ($rows) {
				$intRank = [];
				foreach ($rows as $row) {
					if ($row['team_id'] == $match->home_team_id) {
						$intRank['homeIntvalRank'] = $row;
					}
					
					if ($row['team_id'] == $match->away_team_id) {
						$intRank['awayIntvalRank'] = $row;
					}
				}
			} else {
				$intRank = [];
			}
		}
		$homeTid = $match->home_team_id;
		$awayTid = $match->away_team_id;
		//历史交锋
		$matches = AdminMatch::getInstance()->where('status_id', 8)
			->where('((home_team_id=' . $homeTid . ' and away_team_id=' . $awayTid . ') or (home_team_id=' . $awayTid . ' and away_team_id=' . $homeTid . '))')
			->where('is_delete', 0)->order('match_time', 'DESC')->all();
		//是否显示不感兴趣的赛事
		$formatHistoryMatches = FrontService::handMatch($matches, 0, true);
		
		//近期战绩
		$homeRecentMatches = AdminMatch::getInstance()->where('status_id', 8)
			->where('home_team_id=' . $homeTid . ' or away_team_id=' . $homeTid)->where('is_delete', 0)
			->order('match_time', 'DESC')->all();
		$awayRecentMatches = AdminMatch::getInstance()->where('status_id', 8)
			->where('home_team_id=' . $awayTid . ' or away_team_id=' . $awayTid)->where('is_delete', 0)
			->order('match_time', 'DESC')->all();
		
		//近期赛程
		$homeRecentSchedule = AdminMatch::getInstance()->where('status_id', [1, 2, 3, 4, 5, 7, 8], 'in')
			->where('(home_team_id = ' . $homeTid . ' or away_team_id = ' . $homeTid . ')')
			->where('is_delete', 0)->order('match_time', 'ASC')->all();
		$awayRecentSchedule = AdminMatch::getInstance()->where('status_id', [1, 2, 3, 4, 5, 7, 8], 'in')
			->where('(home_team_id = ' . $awayTid . ' or away_team_id = ' . $awayTid . ')')
			->where('is_delete', 0)->order('match_time', 'ASC')->all();
		// 输出数据
		$result = [
			'intvalRank' => $intRank, //积分排名
			'historyResult' => !empty($senses['history']) ? json_decode($senses['history'], true) : [],
			'recentResult' => !empty($senses['recent']) ? json_decode($senses['recent'], true) : [],
			'history' => $formatHistoryMatches, //历史交锋
			'homeRecent' => FrontService::handMatch($homeRecentMatches, 0, true),//主队近期战绩
			'awayRecent' => FrontService::handMatch($awayRecentMatches, 0, true),//客队近期战绩
			'homeRecentSchedule' => FrontService::handMatch($homeRecentSchedule, $this->auth['id'] ? $this->auth['id'] : 0, true),//主队近期赛程
			'awayRecentSchedule' => FrontService::handMatch($awayRecentSchedule, $this->auth['id'] ? $this->auth['id'] : 0, true),//客队近期赛程
		];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 直播间公告
	 * @throws
	 */
	public function noticeInMatch()
	{
		$setting = AdminSysSettings::getInstance()->findOne(['sys_key' => AdminSysSettings::SETTING_MATCH_NOTICEMENT]);
		// 输出数据
		$result = empty($setting['sys_value']) ? '' : $setting['sys_value'];
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
	}
	
	/**
	 * 比赛信息
	 * @throws
	 */
	public function getMatchInfo()
	{
		// 当前登录用户ID
		$authId = empty($this->auth['id']) ? 0 : intval($this->auth['id']);
		// 参数校验
		$params = $this->params;
		$matchId = empty($params['match_id']) || intval($params['match_id']) < 1 ? 0 : intval($params['match_id']);
		if ($matchId < 1) $this->output(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
		$match = $matchId < 1 ? null : AdminMatch::getInstance()->findOne(['match_id' => $matchId]);
		
		$match = FrontService::formatMatchThree([$match], $authId, []);
		$match = empty($match[0]) ? null : $match[0];
		if (empty($match)) $this->output(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
		
		$competitionId = $match['competition_id'];
		$match['competition_type'] = 0;
		if ($competition = AdminCompetition::create()->findOne(['competition_id' => $competitionId])) {
			$match['competition_type'] = $competition['type'];
		}
		$this->output(Status::CODE_OK, Status::$msg[Status::CODE_OK], $match);
	}
}
