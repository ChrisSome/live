<?php
namespace App\lib;

use App\Common\AppFunc;
use App\HttpController\Match\BasketballApi;
use App\HttpController\Match\FootballApi;
use App\Model\AdminCompetition;
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
use App\Model\BasketBallCompetition;
use App\Utility\Log\Log;
use easySwoole\Cache\Cache;

class  FrontService {
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
            '(type=1 or type=2) and item_type=1 and user_id=' . $authId . ' and is_cancel=0 and item_id in(' . join(',', $postIds) . ')', null,
            '*', false);
        foreach ($tmp as $v) {
            $key = $v['item_id'] . '_' . $v['type'];
            $operateMapper[$key] = 1;
        }
        // 最新发帖时间映射
        $commentMapper = Utils::queryHandler(AdminPostComment::getInstance(), 'post_id in(' . join(',', $postIds) . ')', null,
            'post_id,max(created_at) time', false, ['group' => 'post_id'], 'post_id,time,1');
        // 类型数据映射
        $categoryMapper = empty($categoryIds) ? [] : Utils::queryHandler(AdminUserPostsCategory::getInstance(), 'id in(' . join(',', $categoryIds) . ')', null,
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
            $category = in_array($categoryId, [1, 2]) && !empty($categoryMapper[$categoryId]) ? false : (isset($categoryMapper[$categoryId]) ? $categoryMapper[$categoryId] : []);
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
                'updated_at' => $v['updated_at'],
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
    public static function  handComments($comments, $uid)
    {
        if (!$comments) {
            return [];
        } else {
            $datas = [];
            foreach ($comments as $item) {
                $user = $item->uInfo();
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
                $data['user_info'] = $user ? ['id' => $user->id, 'nickname' => $user->nickname, 'photo' => $user->photo, 'level' => $user->level, 'is_offical' => $user->is_offical] : [];
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
                'top_comment_id' => $v['top_comment_id']
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


    public static function ifFabolus($uid, $cid) {
        return AdminPostOperate::getInstance()->get(['comment_id' => $cid, 'user_id' => $uid, 'action_type' => 1]);

    }


    /**
     * 今天及未来七天的日期
     * @param string $time
     * @param string $format
     * @return array
     */
    static function getWeek($time = '', $format='Ymd')
    {

        $time = $time != '' ? $time : time();
        //组合数据
        $date = [];
        for ($i=1; $i<=30; $i++){
            $date[$i] = date($format ,strtotime( '+' . $i .' days', $time));
        }
        return $date;

    }




    /**
     * @return array
     */
    public static function getHotCompetitionIds(){
        $competition_ids = [];
        if ($setting = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::COMPETITION_ARR)->get()) {
            $competition_ids = json_decode($setting->sys_value, true);
        }
        return $competition_ids;
    }

    static function formatMatchThree($matches, $uid, $interestMatchArr)
    {
        if (!$matches) return [];
        $data = [];

        //主队映射表
        $homeTeamIds = array_column($matches, 'home_team_id');
        $awayTeamIds = array_column($matches, 'away_team_id');
        $teamIds = array_merge($homeTeamIds, $awayTeamIds);
        $teamIds = array_keys(array_flip($teamIds));
        $teams = AdminTeam::getInstance()->field(['name_zh', 'short_name_zh', 'team_id', 'logo'])->where('team_id', $teamIds, 'in')->all();
        $formatTeams = [];
        array_walk($teams, function ($v, $k) use(&$formatTeams) {
            $formatTeams[$v['team_id']] = $v;
        });
        //用户关注比赛
        $userInterestMatchIds = $interestMatchArr;
        foreach ($matches as $match) {
            //用户关注比赛
            $is_interest = false;
            if ($userInterestMatchIds && $uid && in_array($match->match_id, $userInterestMatchIds)) {
                $is_interest = true;
            }

            $is_start = false;
            if (in_array($match->status_id, FootballApi::STATUS_SCHEDULE)) {
                $is_start = false;
            } else if (in_array($match->status_id, FootballApi::STATUS_PLAYING)) {
                $is_start = true;
            } else if (in_array($match->status_id, FootballApi::STATUS_RESULT)) {
                $is_start = false;
            }
            $has_living = 0;
            $home_win = 0;
            list($home_total_score, $away_total_score) = AppFunc::getFinalScore(json_decode($match->home_scores, true), json_decode($match->away_scores, true));
            if ($match->status_id == 8) {
                if ($home_total_score > $away_total_score) {
                    $home_win = 1;
                } else if ($home_total_score == $away_total_score) {
                    $home_win = 2;
                } else if ($home_total_score < $away_total_score) {
                    $home_win = 3;
                }
            }
            $living_url = ['liveUrl' => '', 'liveUrl2' => '', 'liveUrl3' => ''];
            $steamLike = $match->steamLink();
            $item['home_team_name'] = $formatTeams[$match->home_team_id]['name_zh'];
            $item['home_team_logo'] = $formatTeams[$match->home_team_id]['logo'];
            $item['away_team_name'] = $formatTeams[$match->away_team_id]['name_zh'];
            $item['away_team_logo'] = $formatTeams[$match->away_team_id]['logo'];
            $item['round'] = $match->round;
            $item['competition_id'] = $match->competition_id;
            $item['competition_name'] = $match->competition_name;
            $item['competition_color'] = $match->competition_color;
            $item['match_time'] = date('H:i', $match['match_time']);
            $item['format_match_time'] = date('Y-m-d H:i', $match['match_time']); //开赛时间
            $item['user_num'] = count(AppFunc::getUsersInRoom($match->match_id));
            $item['match_id'] = $match->match_id;
            $item['is_start'] = $is_start;
            $item['status_id'] = $match->status_id;
            $item['is_interest'] = $is_interest;
            $item['neutral'] = $match->neutral;  //1中立 0否
            $item['matching_time'] = AppFunc::getPlayingTime($match->match_id);  //比赛进行时间
            $item['matching_info'] = AppFunc::getMatchingInfo($match->match_id);
            $item['has_living'] = $has_living;
            $item['living_url'] = $living_url;
            $item['note'] = $match->note;  //备注   欧青连八分之一决赛
            $item['home_scores'] = $match->home_scores;  //主队比分
            $item['away_scores'] = $match->away_scores;  //主队比分
            list($item['home_total_scores'], $item['away_total_scores']) = [$home_total_score, $away_total_score];
            $item['coverage'] = $match->coverage;  //阵容 动画
            $item['steamLink'] = !empty($steamLike['mobile_link']) ? $steamLike['mobile_link'] : '' ;  //直播地址
            $item['home_win'] = $home_win;  //比赛胜负
            $data[] = $item;
            unset($item);
        }
        return $data;
    }

    /**
     * @param $matches
     * @param $uid
     * @param $interestMatchArr
     * @return array
     */
    static function formatBasketballMatch($matches, $uid, $interestMatchArr)
    {
        if (!$matches) return [];
        $data = [];

        //用户关注比赛
        $userInterestMatchIds = $interestMatchArr;
        $competitionIds = array_column($matches, 'competition_id');
        $competition = BasketBallCompetition::getInstance()->field(['competition_id', 'name_zh', 'short_name_zh'])->where('competition_id', $competitionIds, 'in')->all();
        array_walk($competition, function ($v, $k) use(&$formatCompetition) {
            $item = ['competition_id' => $v->competition_id, 'name_zh' => $v->name_zh, 'short_name_zh' => $v->short_name_zh];
            $formatCompetition[$v->competition_id] = $item;
        });
        foreach ($matches as $match) {
            //用户关注比赛
            $is_interest = false;
            if ($userInterestMatchIds && $uid && in_array($match->match_id, $userInterestMatchIds)) {
                $is_interest = true;
            }

            $is_start = false;
            if (in_array($match->status_id, BasketballApi::STATUS_SCHEDULE)) {
                $is_start = false;
            } else if (in_array($match->status_id, BasketballApi::STATUS_PLAYING)) {
                $is_start = true;
            } else if (in_array($match->status_id, BasketballApi::STATUS_RESULT)) {
                $is_start = false;
            }
            $home_win = 0;
            $home_scores = json_decode($match->home_scores, true);
            $away_scores = json_decode($match->away_scores, true);
            $home_total = $away_total = 0;
            for ($i = 0; $i <= 4; $i++) {
                if (isset($home_scores[$i]) && isset($away_scores[$i])) {
                    $home_total += $home_scores[$i];
                    $away_total += $away_scores[$i];
                }
            }
            if ($match->status_id == 10) {
                if ($home_total > $away_total) {
                    $home_win = 1;
                } else {
                    $home_win = 2;
                }
            }
            $item['home_team_name'] = $match->home_team_name;
            $item['home_team_logo'] = $match->home_team_logo;
            $item['home_team_id'] = $match->home_team_id;
            $item['away_team_name'] = $match->away_team_name;
            $item['away_team_logo'] = $match->away_team_logo;
            $item['away_team_id'] = $match->away_team_id;
            $item['round'] = $match->round;
            $item['competition_id'] = $match->competition_id;
            $item['competition_name'] = !empty($formatCompetition[$match->competition_id]['short_name_zh']) ? $formatCompetition[$match->competition_id]['short_name_zh'] : '';
            $item['match_time'] = date('H:i', $match['match_time']);
            $item['format_match_time'] = date('Y-m-d H:i', $match['match_time']); //开赛时间
            $item['user_num'] = count(AppFunc::getUsersInRoom($match->match_id), 2);
            $item['match_id'] = $match->match_id;
            $item['is_start'] = $is_start;
            $item['status_id'] = $match->status_id;
            $item['is_interest'] = $is_interest;
            $item['neutral'] = $match->neutral;  //1中立 0否
            $item['kind'] = $match->kind;  //类型id，1-常规赛、2-季后赛、3-季前赛、4-全明星、5-杯赛、0-无
            $item['left-seconds-in-current-matter'] = (int)Cache::get('left-seconds-in-current-matter-' . $match->match_id);  //比赛进行时间
            $item['matching_info'] = Cache::get('basketball-matching-info-' . $match->match_id);
            $item['note'] = $match->note;  //备注
            $item['home_scores'] = $match->home_scores;  //主队比分
            $item['away_scores'] = $match->away_scores;  //主队比分
            $item['coverage'] = $match->coverage;  //阵容 动画
            $item['home_win'] = $home_win;  //比赛胜负
            $item['home_total'] = $home_total;  //主队总得分
            $item['away_total'] = $away_total;  //客队总得分
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
        if(!empty($res)) array_walk($res, function($v, $k) use(&$playerIds,&$teamIds) {
            $id = $res[$k]['player_id'] = intval($v['player_id']);
            if($id > 0 && !in_array($id, $playerIds)) $playerIds[] = $id;
            $id = $res[$k]['to_team_id'] = intval($v['to_team_id']);
            if($id > 0 && !in_array($id, $teamIds)) $teamIds[] = $id;
            $id = $res[$k]['from_team_id'] = intval($v['from_team_id']);
            if($id > 0 && !in_array($id, $teamIds)) $teamIds[] = $id;
        });
        if (empty($playerIds)) return [];
        // 获取映射数据
        $tmp = AdminPlayer::getInstance()->func(function ($builder) use ($playerIds) {
            $builder->raw('select * from admin_player_list where player_id in (' . join(',', $playerIds) . ')');
            return true;
        });
        foreach ($tmp as $v){
            $id = $v['player_id'];
            $playerMapper[$id] = $v;
        }
        $tmp = empty($teamIds) ? [] : AdminTeam::getInstance()->func(function ($builder) use ($teamIds) {
            $builder->raw('select * from admin_team_list where team_id in (' . join(',', $teamIds) . ')');
            return true;
        });
        foreach ($tmp as $v){
            $id = $v['team_id'];
            $teamMapper[$id] = $v;
        }
        // 填充数据
        foreach ($res as $item) {
            $pid = $item['player_id'];
            $ttId = $item['to_team_id'];
            $ftId = $item['from_team_id'];
            if(empty($playerMapper[$pid])) continue;
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
    public static  function handInformation($informations, $uid, $sportType = 1)
    {
        if (!$informations) {
            return [];
        }
        $format = [];
        //用户关系映射表
        $userIds = array_column($informations, 'user_id');
        if (!$userIds) return [];
        $informationIds = array_column($informations, 'id');
        $users = AdminUser::getInstance()->where('id', $userIds, 'in')->field(['id', 'nickname', 'photo', 'level', 'is_offical'])->all();
        $operates = AdminUserOperate::getInstance()->where('user_id', $uid)->where('item_id', $informationIds, 'in')->where('item_type', 3)->where('type', 1)->where('is_cancel', 0)->all();
        $formatUsers = $formatOperate = [];
        array_walk($users, function($v, $k) use(&$formatUsers){
            $formatUsers[$v->id] = $v;
        });
        array_walk($operates, function($vi, $ki) use(&$formatOperate){
            $formatOperate[$vi->item_id] = 1;
        });
        foreach ($informations as $item)
        {
            if ($item->created_at > date('Y-m-d H:i:s')) continue;
            $competition = $item->getCompetition($sportType);
            $data['id'] = $item['id'];
            $data['title'] = $item['title'];
            $data['img'] = $item['img'];
            $data['status'] = $item['status'];
            $data['is_fabolus'] = $uid ? (isset($formatOperate[$item->id]) ? true : false) : false;
            $data['fabolus_number'] = $item['fabolus_number'];
            $data['respon_number'] = $item['respon_number'];
            $data['competition_id'] = $item['competition_id'];
            $data['created_at'] = $item['created_at'];
            $data['is_title'] = ($item['type'] == 1) ? true : false;
            $data['competition_short_name_zh'] = isset($competition->short_name_zh) ? $competition->short_name_zh : '';
            $data['user_info'] = $formatUsers[$item->user_id];
            $format[] = $data;
            unset($data);
        }

        return $format;
    }


    /**
     * @param $informationList
     * @param $authId
     * @return array
     * @throws
     */
    public static function formatInformation($informationList, $authId): array
    {
        if (empty($informationList)) return [];
        // 映射数据
        $ids = $userIds = $competitionIds = [];

        array_walk($informationList, function ($v) use (&$ids, &$userIds, &$competitionIds) {
            $id = intval($v->id);
            if ($id > 0 && !in_array($id, $ids)) $ids[] = $id;
            $id = intval($v->user_id);
            if ($id > 0 && !in_array($id, $userIds)) $userIds[] = $id;
            $id = intval($v->competition_id);
            if ($id > 0 && !in_array($id, $competitionIds)) $competitionIds[] = $id;
        });
        // 用户映射
        $userMapper = empty($userIds) ? [] : AdminUser::getInstance()
            ->findAll(['id' => [$userIds, 'in']], 'id,nickname,photo,is_offical,level', null,
                false, 0, 0, 'id,*,true');

        // 赛事映射
        $competitionMapper = empty($competitionIds) ? [] : AdminCompetition::getInstance()
            ->findAll(['id' => [$competitionIds, 'in']], null, null,
                false, 0, 0, 'id,*,true');
        $where = ['item_id' => [$ids, 'in'], 'item_type' => 3, 'type' => 1, 'is_cancel' => 0];
        $tmp = empty($ids) ? [] : AdminUserOperate::getInstance()->findAll($where, 'item_id,user_id');
        $operateMapper = [];
        array_walk($tmp, function ($v) use (&$operateMapper, $authId) {
            $itemId = intval($v['item_id']);
            $operateMapper[$itemId . '_' . $authId] = 1;
        });

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
                'respon_number' => intval($v['respon_number']),
                'fabolus_number' => intval($v['fabolus_number']),
                'is_fabolus' => $authId > 0 ? !empty($operateMapper[$id . '_' . $authId]) : false,
                'competition_short_name_zh' => empty($competition['short_name_zh']) ? '' : $competition['short_name_zh'],
            ];
        }
        return $list;
    }


    /**
     * @param $users
     * @param $uid
     */
    public static function handUser($users, $uid)
    {
        if (!$users) {
            return [];
        }
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
