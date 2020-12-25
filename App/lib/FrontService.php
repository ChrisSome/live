<?php

namespace App\lib;

use App\Common\AppFunc;
use App\Model\AdminCompetition;
use App\HttpController\Match\FootballApi;
use App\Model\AdminInformation;
use App\Model\AdminInterestMatches;
use App\Model\AdminInterestMatchesBak;
use App\Model\AdminPlayer;
use App\Model\AdminPostComment;
use App\Model\AdminPostOperate;
use App\Model\AdminSysSettings;
use App\Model\AdminTeam;
use App\Model\AdminUser;
use App\Model\AdminUserInterestCompetition;
use App\Model\AdminUserOperate;
use App\Model\AdminUserPost;
use App\Model\AdminUserPostsCategory;
use App\Model\AdminUserSetting;
use App\Utility\Log\Log;
use easySwoole\Cache\Cache;

class  FrontService
{
	const TEAM_LOGO = 'https://cdn.sportnanoapi.com/football/team/';
	const PLAYER_LOGO = 'https://cdn.sportnanoapi.com/football/player/';
	const ALPHA_LIVE_LIVING_URL = 'https://cdn.sportnanoapi.com/football/player/';
	
	/** 登录人id
	 * @param $posts
	 * @param $authId
	 * @return array
	 * @throws
	 */
	public static function handPosts($posts, $authId): array
	{
		if (!$posts) return [];
		$authId = intval($authId);
		
		$postIds = $userIds = $categoryIds = [];
		array_walk($posts, function ($v, $k) use (&$postIds, &$userIds, &$categoryIds) {
			$id = intval($v['id']);
			if (!in_array($id, $postIds)) $postIds[] = $id;
			$id = intval($v['user_id']);
			if ($id > 0 && !in_array($id, $userIds)) $userIds[] = $id;
			$id = intval($v['cat_id']);
			if ($id > 0 && !in_array($id, $categoryIds)) $categoryIds[] = $id;
		});
		
		// 点赞/收藏数据映射
		$where = ['type' => [[1, 2], 'in'], 'item_type' => 1, 'is_cancel' => 0, 'item_id' => [$postIds, 'in'], 'user_id' => $authId];
		$tmp = empty($postIds) || $authId < 1 ? [] : AdminUserOperate::getInstance()->findAll($where, 'item_id,type');
		foreach ($tmp as $v) {
			$key = $v['item_id'] . '_' . $v['type'];
			$operateMapper[$key] = 1;
		}
		// 最新发帖时间映射
		$commentMapper = empty($postIds) ? [] : AdminPostComment::getInstance()->findAll(['post_id' => [$postIds, 'in']],
			'post_id,max(created_at) time', ['group' => 'post_id'],
			false, 0, 0, 'post_id,time,true');
		// 类型数据映射
		$categoryMapper = empty($categoryIds) ? [] : AdminUserPostsCategory::getInstance()->findAll(['id' => [$categoryIds, 'in']], null, null,
			false, 0, 0, 'id,*,true');
		// 设置数据映射 & 用户数据映射
		$settingMapper = $userMapper = [];
		if (!empty($userIds)) {
			$settingMapper = AdminUserSetting::getInstance()->findAll(['user_id' => [$userIds, 'in']],
				'user_id,private', null, false, 0, 0, 'user_id,private,true');
			$userMapper = AdminUser::getInstance()->findAll(['id' => [$userIds, 'in']], 'id,photo,nickname,level,is_offical',
				null, false, 0, 0, 'id,*,true');
		}
		$list = [];
		foreach ($posts as $v) {
			$postId = intval($v['id']);
			$userId = intval($v['user_id']);
			$setting = empty($settingMapper[$userId]) ? false : $settingMapper[$userId];
			$categoryId = intval($v['cat_id']);
			$category = in_array($categoryId, [1, 2]) && !empty($categoryMapper[$categoryId]) ?
				false : (empty($categoryMapper[$categoryId]) ? false : $categoryMapper[$categoryId]);
			if (!empty($setting)) {
				$setting = json_decode($setting, true);
				$setting = empty($setting['see_my_post']) ? 0 : $setting['see_my_post'];
				if ($userId != $authId) {
					if ($setting == 2) { //发帖人关注的人
						if (!$authId || !AppFunc::isFollow($userId, $authId)) continue;
					} elseif ($setting == 3) { //发帖人的粉丝
						if (!$authId || !AppFunc::isFollow($authId, $userId)) continue;
					} elseif ($setting == 4) {
						continue;
					}
				}
			}
			$list[] = [
				'id' => $postId,
				'hit' => $v['hit'],
				'user_id' => $userId,
				'title' => $v['title'],
				'cat_id' => $categoryId,
				'status' => $v['status'],
				'created_at' => $v['created_at'],
				'is_refine' => intval($v['is_refine']),
				'respon_number' => $v['respon_number'],
				'fabolus_number' => $v['fabolus_number'],
				'collect_number' => $v['collect_number'],
				'content' => base64_decode($v['content']),
				'is_me' => $authId ? $userId == $authId : false,
				'cat_name' => empty($category['name']) ? '' : $category['name'],
				'is_fabolus' => !empty($operateMapper[$postId . '_1']), //是否赞过
				'cat_color' => empty($category['color']) ? [] : $category['color'],
				'is_collect' => !empty($operateMapper[$postId . '_2']), //是否收藏该帖子
				'imgs' => empty($v['imgs']) ? [] : json_decode($v['imgs'], true),
				'user_info' => empty($userMapper[$userId]) ? [] : $userMapper[$userId], //发帖人信息
				'is_follow' => $authId > 0 ? AppFunc::isFollow($authId, $userId) : false, //是否关注发帖人
				'lasted_resp' => empty($commentMapper[$postId]) ? $v['created_at'] : $commentMapper[$postId], //帖子最新回复
			];
		}
		return $list;
	}
	
	/**
	 * 处理评论
	 * @param $comments
	 * @param $authId
	 * @return array
	 * @throws
	 */
	public static function handComments($comments, $authId): array
	{
		if (empty($comments)) return [];
		$authId = intval($authId);
		
		// 映射数据
		$commentIds = $postIds = $userIds = $operateIds = [];
		array_walk($comments, function ($v) use (&$commentIds, &$userIds, &$postIds, &$operateIds) {
			$userId = intval($v['user_id']);
			if ($userId > 0 && !in_array($userId, $userIds)) $userIds[] = $userId;
			$userId = intval($v['t_u_id']);
			if ($userId > 0 && !in_array($userId, $userIds)) $userIds[] = $userId;
			$commentId = intval($v['parent_id']);
			if ($commentId > 0 && !in_array($commentId, $commentIds)) $commentIds[] = $commentId;
			$postId = intval($v['post_id']);
			if ($postId > 0 && !in_array($postId, $postIds)) $postIds[] = $postId;
			$operateId = '(user_id=' . $userId . ' and item_id=' . $v['id'] . ')';
			if ($userId > 0 && !in_array($operateId, $operateIds)) $operateIds[] = $operateId;
		});
		// 用户映射
		$userMapper = empty($userIds) ? [] : AdminUser::getInstance()->findAll(['id' => [$userIds, 'in']],
			'id,mobile,photo,nickname,level,is_offical', null, false, 0, 0, 'id,*,true');
		// 回复映射
		$commentMapper = empty($commentIds) ? [] : AdminPostComment::getInstance()->findAll(['id' => [$commentIds, 'in']],
			'id,content', null, false, 0, 0, 'id,content,true');
		// 帖子映射
		$postMapper = empty($postIds) ? [] : AdminUserPost::getInstance()->findAll(['id' => [$postIds, 'in']],
			'id,title', null, false, 0, 0, 'id,title,true');
		// 点赞映射
		$operateMapper = empty($operateIds) || $authId < 1 ? [] : AdminUserOperate::getInstance()
			->findAll(['or' => $operateIds, 'type' => 1, 'is_cancel' => 0, 'item_type' => 2, 'user_id' => $authId], 'item_id', null,
				false, 0, 0, 'item_id,item_id,true');
		// 返回数据
		$list = [];
		foreach ($comments as $v) {
			$id = intval($v['id']);
			$userId = intval($v['user_id']);
			$user = empty($userMapper[$userId]) ? [] : $userMapper[$userId];
			$userId = intval($v['t_u_id']);
			$topUser = empty($userMapper[$userId]) ? [] : $userMapper[$userId];
			$parentId = intval($v['parent_id']);
			$parentContent = empty($commentMapper[$parentId]) ? [] : $commentMapper[$parentId];
			$postId = intval($v['post_id']);
			$postTitle = empty($postMapper[$postId]) ? [] : $postMapper[$postId];
			$list[] = [
				'id' => $v['id'],
				'post_id' => $postId,
				'user_info' => $user,
				't_u_info' => $topUser,
				'parent_id' => $parentId,
				'post_title' => $postTitle,
				'created_at' => $v['created_at'],
				'parent_content' => $parentContent,
				'content' => base64_decode($v['content']),
				'is_fabolus' => !empty($operateMapper[$id]),
				'respon_number' => intval($v['respon_number']),
				'top_comment_id' => intval($v['top_comment_id']),
				'fabolus_number' => intval($v['fabolus_number']),
				'is_follow' => AppFunc::isFollow($authId, $userId),
			];
		}
		return $list;
	}
	
	/**
	 * @param $informationComments
	 * @param $authId
	 * @return array
	 * @throws
	 */
	public static function handInformationComment($informationComments, $authId): array
	{
		if (empty($informationComments)) return [];
		$authId = intval($authId);
		
		// 映射数据
		$list = $operateMapper = $informationMapper = $userMapper = [];
		$commentIds = $informationIds = $userIds = [];
		array_walk($informationComments, function ($v, $k) use (&$commentIds, &$informationIds, &$userIds) {
			$id = intval($v['id']);
			if (!in_array($id, $commentIds)) $commentIds[] = $id;
			$id = intval($v['information_id']);
			if ($id > 0 && !in_array($id, $informationIds)) $informationIds[] = $id;
			$id = intval($v['user_id']);
			if ($id > 0 && !in_array($id, $userIds)) $userIds[] = $id;
			$id = intval($v['t_u_id']);
			if ($id > 0 && !in_array($id, $userIds)) $userIds[] = $id;
		});
		$where = ['item_type' => 4, 'type' => 1, 'is_cancel' => 0, 'user_id' => $authId, 'item_id' => [$commentIds, 'in']];
		$operateMapper = empty($commentIds) ? [] : AdminUserOperate::getInstance()->findAll($where, null, null,
			false, 0, 0, 'item_id,*,true');
		$informationMapper = empty($informationIds) ? [] : AdminInformation::getInstance()
			->findAll(['id' => [$informationIds, 'in']], null, null,
				false, 0, 0, 'id,*,true');
		$userMapper = empty($userIds) ? [] : AdminUser::getInstance()
			->findAll(['id' => [$userIds, 'in']], 'id,photo,nickname,level,is_offical', null,
				false, 0, 0, 'id,*,true');
		foreach ($informationComments as $v) {
			$id = intval($v['id']);
			$isFabolus = !empty($operateMapper[$id]);
			$informationId = intval($v['information_id']);
			$information = empty($informationMapper[$informationId]) ? false : $informationMapper[$informationId];
			$userId = intval($v['user_id']);
			$userInfo = empty($userMapper[$userId]) ? [] : $userMapper[$userId];
			$toUserId = intval($v['t_u_id']);
			$toUserInfo = empty($userMapper[$toUserId]) ? [] : $userMapper[$toUserId];
			//是否点赞
			$list[] = [
				'id' => $id,
				'information_id' => $informationId,
				'information_title' => empty($information['title']) ? '' : $information['title'],
				'content' => base64_decode($v['content']),
				'parent_id' => $v['parent_id'],
				'created_at' => $v['created_at'],
				'respon_number' => $v['respon_number'],
				'fabolus_number' => $v['fabolus_number'],
				'is_fabolus' => $isFabolus,
				'user_info' => $userInfo,
				't_u_info' => $toUserInfo,
				'is_follow' => AppFunc::isFollow($authId, $userId),
			];
		}
		return $list;
	}
	
	/**
	 * @param $uid
	 * @return mixed
	 */
	public static function myPostsCount($uid)
	{
		return AdminUserPost::getInstance()->where('user_id', $uid)->where('status', AdminUserPost::STATUS_EXAMINE_SUCCESS)->count();
	}
	
	public static function ifFabolus($uid, $cid)
	{
		return AdminPostOperate::getInstance()->get(['comment_id' => $cid, 'user_id' => $uid, 'action_type' => 1]);
	}
	
	/**
	 * 今天及未来七天的日期
	 * @param string $time
	 * @param string $format
	 * @return array
	 */
	static function getWeek($time = '', $format = 'Ymd'): array
	{
		$time = $time != '' ? $time : time();
		//组合数据
		$date = [];
		for ($i = 1; $i <= 7; $i++) {
			$date[$i] = date($format, strtotime('+' . $i . ' days', $time));
		}
		return $date;
	}
	
	/**
	 * 比赛格式化
	 * @param      $matches
	 * @param      $authId
	 * @param bool $showNotInterest
	 * @param bool $isLiving
	 * @param bool $isShow 强制显示
	 * @return array
	 */
	static function handMatch($matches, $authId, $showNotInterest = false, $isLiving = false, $isShow = false): array
	{
		if (!$matches) return [];
		
		//用户关注赛事
		$userInterestCompetitionIds = [];
		$tmp = AdminUserInterestCompetition::getInstance()->findOne(['user_id' => $authId]);
		if (!empty($tmp['competition_ids'])) $userInterestCompetitionIds = json_decode($tmp['competition_ids'], true);
		
		//用户关注比赛
		$userInterestMatchIds = [];
		$tmp = AdminInterestMatches::getInstance()->findOne(['uid' => $authId]);
		if (!empty($tmp['match_ids'])) $userInterestMatchIds = json_decode($tmp['match_ids'], true);
		
		$configCompetitionIds = [];
		$tmp = AdminSysSettings::getInstance()->where(['sys_key' => AdminSysSettings::COMPETITION_ARR], 'sys_value');
		if (!empty($tmp['sys_value'])) $configCompetitionIds = json_decode($tmp['sys_value'], true);
		
		// 映射数据
		$teamIds = $competitionIds = [];
		array_walk($matches, function ($v) use (&$teamIds, &$competitionIds) {
			$id = intval($v['home_team_id']);
			if ($id > 0 && !in_array($id, $teamIds)) $teamIds[] = $id;
			$id = intval($v['away_team_id']);
			if ($id > 0 && !in_array($id, $teamIds)) $teamIds[] = $id;
			$id = intval($v['competition_id']);
			if ($id > 0 && !in_array($id, $competitionIds)) $competitionIds[] = $id;
		});
		// 球队映射
		$teamMapper = empty($teamIds) ? [] : AdminTeam::getInstance()->findAll(['id' => [$teamIds, 'in']], null, null,
			false, 0, 0, 'id,*,true');
		// 赛事映射
		$competitionMapper = empty($competitionIds) ? [] : AdminCompetition::getInstance()
			->findAll(['id' => [$competitionIds, 'in']], null, null,
				false, 0, 0, 'id,*,true');
		
		$list = [];
		foreach ($matches as $v) {
			$matchId = intval($v['match_id']);
			$match = Cache::get('match_data_info' . $matchId);
			$homeTeamId = intval($v['home_team_id']);
			$homeTeam = empty($teamMapper[$homeTeamId]) ? null : $teamMapper[$homeTeamId];
			$awayTeamId = intval($v['away_team_id']);
			$awayTeam = empty($teamMapper[$awayTeamId]) ? null : $teamMapper[$awayTeamId];
			$competitionId = intval($v['competition_id']);
			$competition = empty($competitionMapper[$competitionId]) ? null : $competitionMapper[$competitionId];
			if (empty($homeTeam) || empty($awayTeam) || empty($competition)) continue;
			$hasLiving = 0;
			$livingUrl = ['liveUrl' => '', 'liveUrl2' => '', 'liveUrl3' => ''];
			$livingMatch = !$isLiving ? null : AppFunc::getAlphaLiving(empty($homeTeam['name_en']) ? '' : $homeTeam['name_en'], empty($awayTeam['name_en']) ? '' : $awayTeam['name_en']);
			if (!empty($livingMatch)) {
				$hasLiving = intval($livingMatch['liveStatus']);
				if (!empty($livingMatch['liveUrl']) || !empty($livingMatch['liveUrl2']) || !empty($livingMatch['liveUrl3'])) {
					$livingUrl = [
						'liveUrl' => $livingMatch['liveUrl'],
						'liveUrl2' => $livingMatch['liveUrl2'],
						'liveUrl3' => $livingMatch['liveUrl3'],
					];
				}
			}
			if (!$isShow) {
				if ($authId && !$showNotInterest && !in_array($competitionId, $userInterestCompetitionIds)) continue;
				if (!in_array($competitionId, $configCompetitionIds)) continue;
			}
			$isStart = false;
			$statusId = intval($v['status_id']);
			if (in_array($statusId, FootballApi::STATUS_PLAYING)) $isStart = true;
			$list[] = [
				'match_id' => $matchId,
				'is_start' => $isStart,
				'status_id' => $statusId,
				'has_living' => $hasLiving,
				'living_url' => $livingUrl,
				'user_num' => mt_rand(20, 50),
				'home_team_logo' => $homeTeam['logo'],
				'away_team_logo' => $awayTeam['logo'],
				'home_team_name' => $homeTeam['name_zh'],
				'away_team_name' => $awayTeam['name_zh'],
				'neutral' => $v['neutral'],  //1中立 0否
				'home_scores' => $v['home_scores'],  //主队比分
				'away_scores' => $v['away_scores'],  //主队比分
				'note' => $v['note'],  //备注   欧青连八分之一决赛
				'competition_name' => $competition['short_name_zh'],
				'competition_color' => $competition['primary_color'],
				'match_time' => date('H:i', $v['match_time']),
				'matching_info' => json_decode($match, true),
				'is_interest' => in_array($matchId, $userInterestMatchIds),
				'matching_time' => AppFunc::getPlayingTime($matchId),  //比赛进行时间
				'format_match_time' => date('Y-m-d H:i', $v['match_time']), //开赛时间
				'group_num' => json_decode($v['round'], true)['group_num'], //第几组
				'round_num' => json_decode($v['round'], true)['round_num'], //第几轮
				'mlive' => json_decode($v['coverage'], true)['mlive'] ? true : false,  //动画
				'line_up' => json_decode($v['coverage'], true)['lineup'] ? true : false,  //阵容
				'steamLink' => !empty($v->steamLink()['mobile_link']) ? $v->steamLink()['mobile_link'] : '',  //直播地址
			];
		}
		return $list;
	}
	
	static function formatMatch($matches, $authId): array
	{
		if (!$matches) return [];
		
		$list = [];
		foreach ($matches as $v) {
			if (!AppFunc::isInHotCompetition($v['competition_id'])) continue;
			
			//用户关注赛事
			$interestCompetitionIds = [];
			$tmp = AdminUserInterestCompetition::getInstance()->findOne(['user_id' => $authId]);
			if (!empty($tmp['competition_ids'])) $interestCompetitionIds = json_decode($tmp['competition_ids'], true);
			if ($authId > 0 && !in_array($v['competition_id'], $interestCompetitionIds)) continue;
			
			//用户关注比赛
			$interestMatchIds = [];
			$tmp = AdminInterestMatches::getInstance()->findOne(['uid' => $authId]);
			if (!empty($tmp['match_ids'])) $interestMatchIds = json_decode($tmp['match_ids'], true);
			
			$homeTeam = $v->homeTeamName();
			$awayTeam = $v->awayTeamName();
			$competition = $v->competitionName();
			if (empty($homeTeam) || empty($awayTeam) || empty($competition)) continue;
			
			$isStart = false;
			$statusId = intval($v['status_id']);
			if (in_array($statusId, FootballApi::STATUS_SCHEDULE)) {
				$isStart = false;
			} elseif (in_array($statusId, FootballApi::STATUS_PLAYING)) {
				$isStart = true;
			} elseif (in_array($statusId, FootballApi::STATUS_RESULT)) {
				$isStart = false;
			}
			
			$steamLike = $v->steamLink();
			$round = json_decode($v['round'], true);
			$livingUrl = ['liveUrl' => '', 'liveUrl2' => '', 'liveUrl3' => ''];
			$matchData = Cache::get('match_data_info' . $v['match_id']);
			
			$list[] = [
				'has_living' => 0,
				'is_start' => $isStart,
				'status_id' => $statusId,
				'living_url' => $livingUrl,
				'user_num' => mt_rand(20, 50),
				'match_id' => $v['match_id'],
				'home_team_logo' => $homeTeam['logo'],
				'away_team_logo' => $awayTeam['logo'],
				'home_team_name' => $homeTeam['name_zh'],
				'away_team_name' => $awayTeam['name_zh'],
				'competition_type' => $competition['type'],
				'neutral' => $v['neutral'],  //1中立 0否
				'note' => $v['note'],  //备注 欧青连八分之一决赛
				'home_scores' => $v['home_scores'],  //主队比分
				'away_scores' => $v['away_scores'],  //主队比分
				'competition_name' => $competition['short_name_zh'],
				'competition_color' => $competition['primary_color'],
				'match_time' => date('H:i', $v['match_time']),
				'matching_info' => json_decode($matchData, true),
				'is_interest' => in_array($v['match_id'], $interestMatchIds),
				'matching_time' => AppFunc::getPlayingTime($v['match_id']),  //比赛进行时间
				'group_num' => !empty($round['group_num']) ? $round['group_num'] : 0, //第几组
				'round_num' => !empty($round['round_num']) ? $round['round_num'] : 0, //第几轮
				'format_match_time' => date('Y-m-d H:i', $v['match_time']), //开赛时间
				'mlive' => json_decode($v->coverage, true)['mlive'] ? true : false,  //动画
				'line_up' => json_decode($v->coverage, true)['lineup'] ? true : false, //阵容
				'steamLink' => !empty($steamLike['mobile_link']) ? $steamLike['mobile_link'] : '',  //直播地址
			];
		}
		return $list;
	}
	
	static function formatMatchThree($matches, $authId, $interestMatchArr): array
	{
		if (empty($matches)) return [];
		
		$list = [];
		foreach ($matches as $v) {
			//用户关注比赛
			$isInterest = false;
			if (!empty($interestMatchArr) && $authId > 0 && in_array($v['match_id'], $interestMatchArr)) $isInterest = true;
			
			$isStart = false;
			$statusId = intval($v['status_id']);
			if (in_array($statusId, FootballApi::STATUS_SCHEDULE)) {
				$isStart = false;
			} elseif (in_array($statusId, FootballApi::STATUS_PLAYING)) {
				$isStart = true;
			} elseif (in_array($statusId, FootballApi::STATUS_RESULT)) {
				$isStart = false;
			}
			$livingUrl = ['liveUrl' => '', 'liveUrl2' => '', 'liveUrl3' => ''];
			
			$steamLike = $v->steamLink();
			$list[] = [
				'has_living' => 0,
				'round' => $v['round'],
				'is_start' => $isStart,
				'status_id' => $statusId,
				'living_url' => $livingUrl,
				'match_id' => $v['match_id'],
				'is_interest' => $isInterest,
				'coverage' => $v['coverage'],
				'user_num' => mt_rand(20, 150),
				'neutral' => $v['neutral'],  //1中立 0否
				'home_team_name' => $v['home_team_name'],
				'home_team_logo' => $v['home_team_logo'],
				'away_team_name' => $v['away_team_name'],
				'away_team_logo' => $v['away_team_logo'],
				'competition_id' => $v['competition_id'],
				'competition_name' => $v['competition_name'],
				'home_scores' => $v['home_scores'],  //主队比分
				'away_scores' => $v['away_scores'],  //主队比分
				'competition_color' => $v['competition_color'],
				'note' => $v['note'],  //备注   欧青连八分之一决赛
				'match_time' => date('H:i', $v['match_time']),
				'matching_info' => Cache::get('match_data_info' . $v['match_id']),
				'matching_time' => AppFunc::getPlayingTime($v['match_id']),  //比赛进行时间
				'format_match_time' => date('Y-m-d H:i', $v['match_time']), //开赛时间
				'steamLink' => !empty($steamLike['mobile_link']) ? $steamLike['mobile_link'] : '',  //直播地址
			];
		}
		return $list;
	}
	
	public static function getHotCompetitionIds(): array
	{
		$tmp = AdminSysSettings::getInstance()->findOne(['sys_key' => AdminSysSettings::COMPETITION_ARR], 'sys_value');
		return empty($tmp['sys_value']) ? [] : json_decode($tmp['sys_value'], true);
	}
	
	static function formatMatchTwo($matches, $uid): array
	{
		if (!$matches) return [];
		
		$userInterestMatchIds = [];
		$tmp = AdminInterestMatches::getInstance()->findOne(['uid' => $uid]);
		if (!empty($tmp['match_ids'])) $userInterestMatchIds = json_decode($tmp['match_ids'], true);
		
		$list = [];
		foreach ($matches as $v) {
			//用户关注比赛
			$isInterest = false;
			if (!empty($userInterestMatchIds) && $uid > 0 && in_array($v['match_id'], $userInterestMatchIds)) $isInterest = true;
			
			$isStart = false;
			$statusId = intval($v['status_id']);
			if (in_array($statusId, FootballApi::STATUS_SCHEDULE)) {
				$isStart = false;
			} elseif (in_array($statusId, FootballApi::STATUS_PLAYING)) {
				$isStart = true;
			} elseif (in_array($statusId, FootballApi::STATUS_RESULT)) {
				$isStart = false;
			}
			
			$steamLike = $v->steamLink();
			$round = json_decode($v['round'], true);
			$livingUrl = ['liveUrl' => '', 'liveUrl2' => '', 'liveUrl3' => ''];
			$matchData = Cache::get('match_data_info' . $v['match_id']);
			
			$list[] = [
				'has_living' => 0,
				'is_start' => $isStart,
				'status_id' => $statusId,
				'living_url' => $livingUrl,
				'match_id' => $v['match_id'],
				'is_interest' => $isInterest,
				'user_num' => mt_rand(20, 50),
				'neutral' => $v['neutral'],  //1中立 0否
				'home_team_name' => $v['home_team_name'],
				'home_team_logo' => $v['home_team_logo'],
				'away_team_name' => $v['away_team_name'],
				'away_team_logo' => $v['away_team_logo'],
				'competition_id' => $v['competition_id'],
				'group_num' => $round['group_num'], //第几组
				'round_num' => $round['round_num'], //第几轮
				'competition_name' => $v['competition_name'],
				'home_scores' => $v['home_scores'],  //主队比分
				'away_scores' => $v['away_scores'],  //主队比分
				'competition_color' => $v['competition_color'],
				'note' => $v['note'],  //备注   欧青连八分之一决赛
				'match_time' => date('H:i', $v['match_time']),
				'matching_info' => json_decode($matchData, true),
				'matching_time' => AppFunc::getPlayingTime($v['match_id']),  //比赛进行时间
				'format_match_time' => date('Y-m-d H:i', $v['match_time']), //开赛时间
				'mlive' => json_decode($v['coverage'], true)['mlive'] ? true : false,  //动画
				'line_up' => json_decode($v['coverage'], true)['lineup'] ? true : false,  //阵容
				'steamLink' => !empty($steamLike['mobile_link']) ? $steamLike['mobile_link'] : '',  //直播地址
			];
		}
		return $list;
	}
	
	/**
	 * 关键球员
	 * @param $item
	 * @param $column
	 * @return array
	 */
	
	public static function formatKeyPlayer($item, $column)
	{
		if (empty($item)) return [];
		if (!isset($item[$column])) return [];
		$data['player_id'] = $item['player']['id'];
		$data['name_zh'] = $item['player']['name_zh'];
		$data['player_logo'] = self::PLAYER_LOGO . $item['player']['logo'];
		$data['total'] = $item[$column];
		return $data;
	}
	
	/**
	 * 转会球员
	 */
	public static function handChangePlayerSonto($res)
	{
		if (!$res) {
			return [];
		}
		$return = [];
		foreach ($res as $item) {
			if (!$player = AdminPlayer::getInstance()->where('player_id', $item['player_id'])->get()) {
				continue;
			}
			$from_team = $item->fromTeamInfo();
			$to_team = $item->ToTeamInfo();
			//            if(!$to_team = AdminTeam::getInstance()->where('team_id', $item['to_team_id'])->get()) {
			//                continue;
			//            }
			
			$data['player_id'] = $item['player_id'];
			$data['player_position'] = $player['position'];
			$data['transfer_time'] = date('Y-m-d', $item['transfer_time']);
			$data['transfer_type'] = $item['transfer_type'];
			$data['transfer_fee'] = AppFunc::changeToWan($item['transfer_fee']);
			$data['name_zh'] = $player['name_zh'];
			$data['logo'] = $player['logo'];
			$data['from_team_name_zh'] = isset($from_team['name_zh']) ? $from_team['name_zh'] : '';
			$data['from_team_logo'] = isset($from_team['logo']) ? $from_team['logo'] : '';
			$data['from_team_id'] = isset($from_team['team_id']) ? $from_team['team_id'] : 0;
			$data['to_team_name_zh'] = isset($to_team['name_zh']) ? $to_team['name_zh'] : '';
			$data['to_team_logo'] = isset($to_team['logo']) ? $to_team['logo'] : '';
			$data['to_team_id'] = isset($to_team['team_id']) ? $to_team['team_id'] : 0;
			$return[] = $data;
			unset($data);
		}
		return $return;
	}
	
	public static function handChangePlayer($res): array
	{
		$return = $playerMapper = $teamMapper = $playerIds = $teamIds = [];
		if (!empty($res)) array_walk($res, function ($v, $k) use (&$playerIds, &$teamIds) {
			$id = $res[$k]['player_id'] = intval($v['player_id']);
			if ($id > 0 && !in_array($id, $playerIds)) $playerIds[] = $id;
			$id = $res[$k]['to_team_id'] = intval($v['to_team_id']);
			if ($id > 0 && !in_array($id, $teamIds)) $teamIds[] = $id;
			$id = $res[$k]['from_team_id'] = intval($v['from_team_id']);
			if ($id > 0 && !in_array($id, $teamIds)) $teamIds[] = $id;
		});
		if (empty($playerIds)) return [];
		// 获取映射数据
		$tmp = AdminPlayer::getInstance()->func(function ($builder) use ($playerIds) {
			$builder->raw('select * from admin_player_list where player_id in (' . join(',', $playerIds) . ')');
			return true;
		});
		foreach ($tmp as $v) {
			$id = $v['player_id'];
			$playerMapper[$id] = $v;
		}
		$tmp = empty($teamIds) ? [] : AdminTeam::getInstance()->func(function ($builder) use ($teamIds) {
			$builder->raw('select * from admin_team_list where team_id in (' . join(',', $teamIds) . ')');
			return true;
		});
		foreach ($tmp as $v) {
			$id = $v['team_id'];
			$teamMapper[$id] = $v;
		}
		// 填充数据
		foreach ($res as $item) {
			$pid = $item['player_id'];
			$ttId = $item['to_team_id'];
			$ftId = $item['from_team_id'];
			if (empty($playerMapper[$pid])) continue;
			$player = $playerMapper[$pid];
			$toTeam = isset($teamMapper[$ttId]) ? $teamMapper[$ttId] : [];
			$fromTeam = isset($teamMapper[$ftId]) ? $teamMapper[$ftId] : [];
			$data = [];
			$data['player_id'] = $item['player_id'];
			$data['player_position'] = $player['position'];
			$data['transfer_time'] = date('Y-m-d', $item['transfer_time']);
			$data['transfer_type'] = $item['transfer_type'];
			$data['transfer_fee'] = AppFunc::changeToWan($item['transfer_fee']);
			$data['name_zh'] = $player['name_zh'];
			$data['logo'] = $player['logo'];
			$data['from_team_name_zh'] = empty($fromTeam['name_zh']) ? '' : $fromTeam['name_zh'];
			$data['from_team_logo'] = empty($fromTeam['logo']) ? '' : $fromTeam['logo'];
			$data['from_team_id'] = intval($ftId);
			$data['to_team_name_zh'] = empty($toTeam['name_zh']) ? '' : $toTeam['name_zh'];
			$data['to_team_logo'] = empty($toTeam['logo']) ? '' : $toTeam['logo'];
			$data['to_team_id'] = intval($ttId);
			$return[] = $data;
		}
		return $return;
	}
	
	/**
	 * @param $informationList
	 * @param $authId
	 * @return array
	 * @throws
	 */
	public static function handInformation($informationList, $authId): array
	{
		if (empty($informationList)) return [];
		// 映射数据
		$ids = $userIds = $competitionIds = [];
		array_walk($informationList, function ($v) use (&$ids, &$userIds, &$competitionIds) {
			$id = intval($v['id']);
			if ($id > 0 && !in_array($id, $ids)) $ids[] = $id;
			$id = intval($v['user_id']);
			if ($id > 0 && !in_array($id, $userIds)) $userIds[] = $id;
			$id = intval($v['competition_id']);
			if ($id > 0 && !in_array($id, $competitionIds)) $competitionIds[] = $id;
		});
		// 用户映射
		$userMapper = empty($userIds) ? [] : AdminUser::getInstance()
			->findAll(['id' => [$userIds, 'in']], 'id,nickname,photo,is_offical,level', null,
				false, 0, 0, 'id,*,true');
		// 赛事映射
		$competitionMapper = empty($competitionIds) ? [] : AdminCompetition::getInstance()
			->findAll(['competition_id' => [$competitionIds, 'in']], null, null,
				false, 0, 0, 'competition_id,*,true');
		$where = ['item_id' => [$ids, 'in'], 'item_type' => 3, 'type' => 1, 'is_cancel' => 0, 'user_id' => $authId];
		$operateMapper = empty($ids) || $authId < 1 ? [] : AdminUserOperate::getInstance()->findAll($where, 'item_id', null,
			false, 0, 0, 'item_id,*,true');
		$list = [];
		foreach ($informationList as $v) {
			if ($v['created_at'] > date('Y-m-d H:i:s')) continue;
			$id = intval($v['id']);
			$userId = intval($v['user_id']);
			$user = empty($userMapper[$userId]) ? [] : $userMapper[$userId];
			if (empty($user)) continue;
			$competitionId = intval($v['competition_id']);
			$competition = empty($competitionMapper[$competitionId]) ? null : $competitionMapper[$competitionId];
			$list[] = [
				'id' => $id,
				'user' => $user,
				'img' => $v['img'],
				'title' => $v['title'],
				'status' => $v['status'],
				'is_title' => $v['type'] == 1,
				'created_at' => $v['created_at'],
				'competition_id' => $competitionId,
				'is_fabolus' => !empty($operateMapper[$id]),
				'respon_number' => intval($v['respon_number']),
				'fabolus_number' => intval($v['fabolus_number']),
				'competition_short_name_zh' => empty($competition['short_name_zh']) ? '' : $competition['short_name_zh'],
			];
		}
		return $list;
	}
	
	/**
	 * @param $users
	 * @param $authId
	 * @return array
	 * @throws
	 */
	public static function handUser($users, $authId): array
	{
		if (empty($users)) return [];
		$list = [];
		foreach ($users as $v) {
			$list[] = [
				'id' => $v['id'],
				'level' => $v['level'],
				'photo' => $v['photo'],
				'nickname' => $v['nickname'],
				'is_offical' => $v['is_offical'],
				//				'fans_count' => count(AppFunc::getUserFans($v->id)),
				//				'is_follow' => AppFunc::isFollow($authId, $v['id']),
			];
		}
		return $list;
	}
}
