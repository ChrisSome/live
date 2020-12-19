<?php

namespace App\lib;

use App\Common\AppFunc;
use App\HttpController\Match\FootballApi;
use App\Model\AdminInformation;
use App\Model\AdminInterestMatches;
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
	 * @throws Throwable
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
		$tmp = Utils::queryHandler(AdminUserOperate::getInstance(),
			'(type=1 or type=2) and item_type=1 and is_cancel=0 and item_id in(' . join(',', $postIds) . ')', null,
			'*', false);
		foreach ($tmp as $v) {
			$key = $v['item_id'] . '_' . $v['type'];
			$operateMapper[$key] = 1;
		}
		// 最新发帖时间映射
		$commentMapper = Utils::queryHandler(AdminPostComment::getInstance(), 'post_id in(' . join(',', $postIds) . ')', null,
			'post_id,max(created_at) time', false, ['group' => 'post_id'], 'post_id,time,1');
		// 类型数据映射
		$categoryMapper = Utils::queryHandler(AdminUserPostsCategory::getInstance(), 'id in(' . join(',', $categoryIds) . ')', null,
			'*', false, null, 'id,*,1');
		// 设置数据映射 & 用户数据映射
		$settingMapper = $userMapper = [];
		if (!empty($userIds)) {
			$settingMapper = Utils::queryHandler(AdminUserSetting::getInstance(),
				'user_id in(' . join(',', $userIds) . ')', null,
				'user_id,private', false, null, 'user_id,private,1');
			$userMapper = Utils::queryHandler(AdminUser::getInstance(),
				'id in(' . join(',', $userIds) . ')', null,
				'id,photo,nickname,level,is_offical', false, null, 'id,*,1');
		}
		
		$list = [];
		foreach ($posts as $v) {
			$postId = intval($v['id']);
			$userId = intval($v['user_id']);
			$setting = empty($settingMapper[$userId]) ? false : $settingMapper[$userId];
			$categoryId = intval($v['cat_id']);
			$category = in_array($categoryId, [1, 2]) && !empty($categoryMapper[$categoryId]) ? false : $categoryMapper[$categoryId];
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
				'cat_color' => empty($category['color']) ? '' : json_decode($category['color'], true),
				'imgs' => empty($v['imgs']) ? [] : json_decode($v['imgs'], true),
				'user_info' => empty($userMapper[$userId]) ? [] : $userMapper[$userId], //发帖人信息
				'is_follow' => $authId ? AppFunc::isFollow($authId, $userId) : false, //是否关注发帖人
				'is_collect' => $authId ? (!empty($operateMapper[$postId . '_2'])) : false, //是否收藏该帖子
				'lasted_resp' => empty($commentMapper[$postId]) ? $v['created_at'] : $commentMapper[$postId], //帖子最新回复
				'is_fabolus' => $authId ? (!empty($operateMapper[$postId . '_1'])) : false, //是否赞过
			];
		}
		return $list;
	}
	
	/**
	 * 处理评论
	 * @param $comments
	 * @param $uid
	 * @return array
	 */
	public static function handComments($comments, $uid)
	{
		if (!$comments) {
			return [];
		} else {
			$datas = [];
			foreach ($comments as $item) {
				$parentComment = $item->getParentContent();
				$data['id'] = $item->id;  //当前评论id
				$data['post_id'] = $item->post_id; //帖子id
				$data['post_title'] = $item->postInfo()->title; //帖子标题
				$data['parent_id'] = $item->parent_id; //父评论ID，可能为0
				$data['parent_content'] = $parentComment ? $parentComment->content : ''; //父评论内容 可能为''
				$data['content'] = $item->content; //当前评论内容
				$data['created_at'] = $item->created_at;
				$data['fabolus_number'] = $item->fabolus_number;
				$data['is_fabolus'] = $uid ? ($item->isFabolus($uid, $item->id) ? true : false) : false;
				$data['user_info'] = $item->uInfo();
				$data['is_follow'] = AppFunc::isFollow($uid, $item->user_id); //是否关注该评论人
				$data['respon_number'] = $item->respon_number;
				$data['top_comment_id'] = $item->top_comment_id;
				$data['t_u_info'] = $item->tuInfo();
				$datas[] = $data;
				unset($data);
			}
		}
		return $datas;
	}
	
	public static function handInformationComment($informationComments, $authId)
	{
		$authId = intval($authId);
		if (empty($informationComments)) return [];
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
		$where = 'item_type=4 and type=1 and is_cancel=0 and user_id=' . $authId . ' and item_id in(' . join(',', $commentIds) . ')';
		$tmp = AdminUserOperate::getInstance()->func(function ($builder) use ($where) {
			$builder->raw('select * from admin_user_operates where ' . $where, []);
			return true;
		});
		foreach ($tmp as $v) {
			$id = intval($v['item_id']);
			$operateMapper[$id] = $v;
		}
		if (!empty($informationIds)) {
			$where = 'id in(' . join(',', $informationIds) . ')';
			$tmp = AdminInformation::getInstance()->func(function ($builder) use ($where) {
				$builder->raw('select * from admin_information where ' . $where, []);
				return true;
			});
			foreach ($tmp as $v) {
				$id = intval($v['id']);
				$informationMapper[$id] = $v;
			}
		}
		if (!empty($userIds)) {
			$where = 'id in(' . join(',', $userIds) . ')';
			$tmp = AdminUser::getInstance()->func(function ($builder) use ($where) {
				$builder->raw('select id,photo,nickname,level,is_offical from admin_user where ' . $where, []);
				return true;
			});
			foreach ($tmp as $v) {
				$id = intval($v['id']);
				$userMapper[$id] = $v;
			}
		}
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
		return AdminUserPost::getInstance()->where('user_id', $uid)->where('status', AdminUserPost::STATUS_EXAMINE_SUCC)->count();
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
	static function getWeek($time = '', $format = 'Ymd')
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
	 * @param      $uid
	 * @param bool $showWhenUserNotInterestCompetition
	 * @param bool $isLiving
	 * @param bool $is_show 强制显示
	 * @return array
	 */
	static function handMatch($matches, $uid, $showWhenUserNotInterestCompetition = false, $isLiving = false, $is_show = false)
	{
		if (!$matches) {
			return [];
		} else {
			//用户关注赛事
			$userInterestCompetitiones = [];
			if ($competitiones = AdminUserInterestCompetition::getInstance()->where('user_id', $uid)->get()) {
				$userInterestCompetitiones = json_decode($competitiones['competition_ids'], true);
			}
			//            $userInterestCompetitiones = $competitiones ? json_decode($competitiones['competition_ids'], true) : [];
			//用户关注比赛
			$userInterestMatchIds = [];
			
			if ($userInterestMatchRes = AdminInterestMatches::getInstance()->where('uid', $uid)->get()) {
				$userInterestMatchIds = json_decode($userInterestMatchRes->match_ids, true);
			}
			$in_competition_arr = [];
			if ($recommand_competition_id_arr = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::COMPETITION_ARR)->get()) {
				$in_competition_arr = json_decode($recommand_competition_id_arr->sys_value, true);
			}
			
			foreach ($matches as $match) {
				$match_data_info = Cache::get('match_data_info' . $match->match_id);
				$home_team = isset($match->home_team_id) ? $match->homeTeamName() : '';
				$away_team = isset($match->away_team_id) ? $match->awayTeamName() : '';
				$competition = $match->competitionName();
				if (!$home_team || !$away_team || !$competition) {
					continue;
				}
				$has_living = 0;
				$living_url = ['liveUrl' => '', 'liveUrl2' => '', 'liveUrl3' => ''];
				if ($isLiving && $living_match = AppFunc::getAlphaLiving(isset($home_team->name_en) ? $home_team->name_en : '', isset($away_team->name_en) ? $away_team->name_en : '')) {
					$has_living = $living_match['liveStatus'];
					if ($living_match['liveUrl'] || $living_match['liveUrl2'] || $living_match['liveUrl3']) {
						$living_url = [
							'liveUrl' => $living_match['liveUrl'],
							'liveUrl2' => $living_match['liveUrl2'],
							'liveUrl3' => $living_match['liveUrl3'],
						];
					}
				}
				
				if (!$is_show) {
					if ($uid && !$showWhenUserNotInterestCompetition && !in_array($match->competition_id, $userInterestCompetitiones)) {
						continue;
					}
					
					if (!in_array($match->competition_id, $in_competition_arr)) {
						continue;
					}
					// if (!AppFunc::isInHotCompetition($match->competition_id)) {
					//     continue;
					// }
				}
				
				$is_start = false;
				if (in_array($match->status_id, FootballApi::STATUS_SCHEDULE)) {
					$is_start = false;
				} elseif (in_array($match->status_id, FootballApi::STATUS_PLAYING)) {
					$is_start = true;
				} elseif (in_array($match->status_id, FootballApi::STATUS_RESULT)) {
					$is_start = false;
				}
				
				$item['home_team_name'] = $home_team['name_zh'];
				$item['home_team_logo'] = $home_team['logo'];
				$item['away_team_name'] = $away_team['name_zh'];
				$item['away_team_logo'] = $away_team['logo'];
				$item['group_num'] = json_decode($match->round, true)['group_num']; //第几组
				$item['round_num'] = json_decode($match->round, true)['round_num']; //第几轮
				//                $item['competition_type'] = $match->competitionName['type'];
				$item['competition_name'] = $competition['short_name_zh'];
				$item['competition_color'] = $competition['primary_color'];
				$item['match_time'] = date('H:i', $match['match_time']);
				$item['format_match_time'] = date('Y-m-d H:i', $match['match_time']); //开赛时间
				$item['user_num'] = mt_rand(20, 50);
				$item['match_id'] = $match->match_id;
				$item['is_start'] = $is_start;
				$item['status_id'] = $match->status_id;
				$item['is_interest'] = in_array($match->match_id, $userInterestMatchIds) ? true : false;
				$item['neutral'] = $match->neutral;  //1中立 0否
				//                $item['competition_id'] = $match->competition_id;  //1中立 0否
				$item['note'] = $match->note;  //备注   欧青连八分之一决赛
				$item['home_scores'] = $match->home_scores;  //主队比分
				$item['away_scores'] = $match->away_scores;  //主队比分
				$item['steamLink'] = !empty($match->steamLink()['mobile_link']) ? $match->steamLink()['mobile_link'] : '';  //直播地址
				$item['line_up'] = json_decode($match->coverage, true)['lineup'] ? true : false;  //阵容
				$item['mlive'] = json_decode($match->coverage, true)['mlive'] ? true : false;  //动画
				//                $item['home_win'] = $homeWin;  //比赛输赢
				$item['matching_time'] = AppFunc::getPlayingTime($match->match_id);  //比赛进行时间
				$item['matching_info'] = json_decode($match_data_info, true);
				$item['has_living'] = $has_living;
				$item['living_url'] = $living_url;
				
				$data[] = $item;
				
				unset($item);
			}
			return isset($data) ? $data : [];
		}
	}
	
	static function formatMatch($matches, $uid)
	{
		if (!$matches) return [];
		$data = [];
		foreach ($matches as $match) {
			if (!AppFunc::isInHotCompetition($match->competition_id)) {
				continue;
			}
			
			//用户关注赛事
			$userInterestCompetitiones = [];
			if ($competitiones = AdminUserInterestCompetition::getInstance()->where('user_id', $uid)->get()) {
				$userInterestCompetitiones = json_decode($competitiones['competition_ids'], true);
			}
			//用户关注比赛
			$userInterestMatchIds = [];
			
			if ($userInterestMatchRes = AdminInterestMatches::getInstance()->where('uid', $uid)->get()) {
				$userInterestMatchIds = json_decode($userInterestMatchRes->match_ids, true);
			}
			
			if ($uid && !in_array($match->competition_id, $userInterestCompetitiones)) {
				continue;
			}
			$home_team = $match->homeTeamName();
			$away_team = $match->awayTeamName();
			$competition = $match->competitionName();
			if (!$home_team || !$away_team || !$competition) {
				continue;
			}
			
			$is_start = false;
			if (in_array($match->status_id, FootballApi::STATUS_SCHEDULE)) {
				$is_start = false;
			} elseif (in_array($match->status_id, FootballApi::STATUS_PLAYING)) {
				$is_start = true;
			} elseif (in_array($match->status_id, FootballApi::STATUS_RESULT)) {
				$is_start = false;
			}
			$round = json_decode($match->round, true);
			$has_living = 0;
			$living_url = ['liveUrl' => '', 'liveUrl2' => '', 'liveUrl3' => ''];
			$match_data_info = Cache::get('match_data_info' . $match->match_id);
			
			$item['home_team_name'] = $home_team['name_zh'];
			$item['home_team_logo'] = $home_team['logo'];
			$item['away_team_name'] = $away_team['name_zh'];
			$item['away_team_logo'] = $away_team['logo'];
			$item['competition_name'] = $competition['short_name_zh'];
			$item['competition_color'] = $competition['primary_color'];
			$item['competition_type'] = $competition['type'];
			$item['group_num'] = !empty($round['group_num']) ? $round['group_num'] : 0; //第几组
			$item['round_num'] = !empty($round['round_num']) ? $round['round_num'] : 0; //第几轮
			$item['match_time'] = date('H:i', $match['match_time']);
			$item['format_match_time'] = date('Y-m-d H:i', $match['match_time']); //开赛时间
			$item['user_num'] = mt_rand(20, 50);
			$item['match_id'] = $match->match_id;
			$item['is_start'] = $is_start;
			$item['status_id'] = $match->status_id;
			$item['is_interest'] = in_array($match->match_id, $userInterestMatchIds) ? true : false;
			$item['neutral'] = $match->neutral;  //1中立 0否
			$item['matching_time'] = AppFunc::getPlayingTime($match->match_id);  //比赛进行时间
			$item['matching_info'] = json_decode($match_data_info, true);
			$item['has_living'] = $has_living;
			$item['living_url'] = $living_url;
			$item['note'] = $match->note;  //备注   欧青连八分之一决赛
			$item['home_scores'] = $match->home_scores;  //主队比分
			$item['away_scores'] = $match->away_scores;  //主队比分
			$item['steamLink'] = !empty($match->steamLink()['mobile_link']) ? $match->steamLink()['mobile_link'] : '';  //直播地址
			$item['line_up'] = json_decode($match->coverage, true)['lineup'] ? true : false;  //阵容
			$item['mlive'] = json_decode($match->coverage, true)['mlive'] ? true : false;  //动画
			
			$data[] = $item;
			
			unset($item);
		}
		return $data;
	}
	
	/**
	 * @return array
	 */
	public static function getHotCompetitionIds()
	{
		$competition_ids = [];
		if ($setting = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::COMPETITION_ARR)->get()) {
			$competition_ids = json_decode($setting->sys_value, true);
		}
		return $competition_ids;
	}
	
	static function formatMatchTwo($matches, $uid)
	{
		if (!$matches) return [];
		$data = [];
		
		//用户关注比赛
		
		$userInterestMatchIds = [];
		
		if ($userInterestMatchRes = AdminInterestMatches::getInstance()->where('uid', $uid)->get()) {
			$userInterestMatchIds = json_decode($userInterestMatchRes->match_ids, true);
		}
		
		foreach ($matches as $match) {
			//用户关注比赛
			$is_interest = false;
			if ($userInterestMatchIds && $uid && in_array($match->match_id, $userInterestMatchIds)) {
				$is_interest = true;
			}
			
			$is_start = false;
			if (in_array($match->status_id, FootballApi::STATUS_SCHEDULE)) {
				$is_start = false;
			} elseif (in_array($match->status_id, FootballApi::STATUS_PLAYING)) {
				$is_start = true;
			} elseif (in_array($match->status_id, FootballApi::STATUS_RESULT)) {
				$is_start = false;
			}
			$has_living = 0;
			$living_url = ['liveUrl' => '', 'liveUrl2' => '', 'liveUrl3' => ''];
			
			$match_data_info = Cache::get('match_data_info' . $match->match_id);
			$round = json_decode($match->round, true);
			$item['home_team_name'] = $match->home_team_name;
			$item['home_team_logo'] = $match->home_team_logo;
			$item['away_team_name'] = $match->away_team_name;
			$item['away_team_logo'] = $match->away_team_logo;
			$item['group_num'] = $round['group_num']; //第几组
			$item['round_num'] = $round['round_num']; //第几轮
			$item['competition_id'] = $match->competition_id;
			$item['competition_name'] = $match->competition_name;
			$item['competition_color'] = $match->competition_color;
			$item['match_time'] = date('H:i', $match['match_time']);
			$item['format_match_time'] = date('Y-m-d H:i', $match['match_time']); //开赛时间
			$item['user_num'] = mt_rand(20, 50);
			$item['match_id'] = $match->match_id;
			$item['is_start'] = $is_start;
			$item['status_id'] = $match->status_id;
			$item['is_interest'] = $is_interest;
			$item['neutral'] = $match->neutral;  //1中立 0否
			$item['matching_time'] = AppFunc::getPlayingTime($match->match_id);  //比赛进行时间
			$item['matching_info'] = json_decode($match_data_info, true);
			$item['has_living'] = $has_living;
			$item['living_url'] = $living_url;
			$item['note'] = $match->note;  //备注   欧青连八分之一决赛
			$item['home_scores'] = $match->home_scores;  //主队比分
			$item['away_scores'] = $match->away_scores;  //主队比分
			$item['steamLink'] = !empty($match->steamLink()['mobile_link']) ? $match->steamLink()['mobile_link'] : '';  //直播地址
			$item['line_up'] = json_decode($match->coverage, true)['lineup'] ? true : false;  //阵容
			$item['mlive'] = json_decode($match->coverage, true)['mlive'] ? true : false;  //动画
			
			$data[] = $item;
			
			unset($item);
		}
		return $data;
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
	
	public static function handChangePlayer($res)
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
	 * @param $informations
	 * @return array
	 */
	public static function handInformation($informations, $uid)
	{
		if (!$informations) {
			return [];
		}
		$format = [];
		foreach ($informations as $item) {
			if ($item->created_at > date('Y-m-d H:i:s')) continue;
			$user = AdminUser::getInstance()->where('id', $item['user_id'])->get();
			if (!$user) continue;
			$operate = AdminUserOperate::getInstance()->where('user_id', $uid)->where('item_id', $item['id'])->where('item_type', 3)->where('type', 1)->where('is_cancel', 0)->get();
			$competition = $item->getCompetition();
			$data['id'] = $item['id'];
			$data['title'] = $item['title'];
			$data['img'] = $item['img'];
			$data['status'] = $item['status'];
			$data['is_fabolus'] = $uid ? ($operate ? true : false) : false;
			$data['fabolus_number'] = $item['fabolus_number'];
			$data['respon_number'] = $item['respon_number'];
			$data['competition_id'] = $item['competition_id'];
			$data['created_at'] = $item['created_at'];
			$data['is_title'] = ($item['type'] == 1) ? true : false;
			$data['competition_short_name_zh'] = isset($competition->short_name_zh) ? $competition->short_name_zh : '';
			$data['user_info'] = ['id' => $user->id, 'nickname' => $user->nickname, 'photo' => $user->photo, 'level' => $user->level, 'is_offical' => $user->is_offical];
			
			$format[] = $data;
			unset($data);
		}
		
		return $format;
	}
	
	/**
	 * @param $users
	 * @param $uid
	 */
	public static function handUser($users, $uid)
	{
		if (!$users) return [];
		$format_users = [];
		foreach ($users as $user) {
			$data['id'] = $user['id'];
			$data['nickname'] = $user['nickname'];
			$data['is_offical'] = $user['is_offical'];
			$data['level'] = $user['level'];
			$data['photo'] = $user['photo'];
			$data['fans_count'] = count(AppFunc::getUserFans($user->id));
			$data['is_follow'] = AppFunc::isFollow($uid, $user['id']);
			$format_users[] = $data;
			unset($data);
		}
		
		return $format_users;
	}
}
