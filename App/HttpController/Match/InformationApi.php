<?php

namespace App\HttpController\Match;

use App\Base\FrontUserController;
use App\Common\AppFunc;
use App\Common\Time;
use App\lib\FrontService;
use App\lib\Tool;
use App\lib\Utils;
use App\Model\AdminCompetition;
use App\Model\AdminInformation;
use App\Model\AdminInformationComment;
use App\Model\AdminMatch;
use App\Model\AdminMessage;
use App\Model\AdminSensitive;
use App\Model\AdminSysSettings;
use App\Model\AdminUser;
use App\Model\AdminUserOperate;
use App\Model\BasketBallCompetition;
use App\Storage\OnlineUser;
use App\Task\SerialPointTask;
use App\Utility\Message\Status;
use easySwoole\Cache\Cache;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\Validate\Validate;

/**
 * 资讯中心
 * Class InformationApi
 * @package App\HttpController\Match
 */
class InformationApi extends FrontUserController
{


    /**
     * 标题栏
     */
    public function titleBar()
    {
        $return = [];

        $data_competitions = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::SETTING_DATA_COMPETITION)->get();

        if (!$data_competitions || !$data_competitions['sys_value']) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

        } else {
            $data_competitions_info = json_decode($data_competitions['sys_value'], true);

        }



        $head = [
            'competition_id' => 0,
            'short_name_zh' => '头条',
            'type' => 1
        ];
        $return[] = $head;
        $changeClub = [
            'competition_id' => 0,
            'short_name_zh' => '转会',
            'type' => 2
        ];
        $return[] = $changeClub;

        $normal_competition = $data_competitions_info;
        foreach ($normal_competition as $item) {

            if ($competition = AdminCompetition::getInstance()->where('competition_id', $item)->get()) {
                $data['competition_id'] = $competition->competition_id;
                $data['short_name_zh'] = $competition->short_name_zh;
                $data['type'] = 3;
                $return[] = $data;
                unset($data);
            } else {
                continue;
            }

        }


        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

    }



    /**
     * 获取个分类的内容
     * @return bool
     */
    public function getCategoryInformation()
    {


        //资讯文章
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $type = !empty($this->params['type']) ? $this->params['type'] : 1;
        if ($type == 1) {
            //头条banner
            $setting = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::SETTING_TITLE_BANNER)->get();
            $decode = json_decode($setting->sys_value, true);
            $banner_list = [];

            if ($banner = $decode['banner']) {
                $sort = array_column($banner, 'sort');
                array_multisort($banner,SORT_DESC,$sort);
                foreach ($banner as $item_banner) {
                    if (Time::isBetween($item_banner['start_time'], $item_banner['end_time'])) {
                        $banner_list[] = $item_banner;
                    } else {
                        continue;
                    }
                }
            }
            if ($decode['match']) {
                $matches = AdminMatch::getInstance()->where('match_id', $decode['match'], 'in')->all();
                $formatMatches = FrontService::formatMatch($matches, 0);
            } else {
                $formatMatches = [];
            }


            $model = AdminInformation::getInstance()->where('type', 1)->where('status', AdminInformation::STATUS_NORMAL)->getLimit($page, $size);

            $list = $model->all(null);
            $format_information = FrontService::handInformation($list, $this->auth['id']);

            $count = $model->lastQueryResult()->getTotalCount();
            $return_data = [
                'banner' => $banner_list,
                'matches' => $formatMatches,
                'information' => ['list' => $format_information, 'count' => $count]
            ];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return_data);
        } else if ($type == 2) {
            //转会
            $model = AdminInformation::getInstance()->where('type', 2)->where('status', AdminInformation::STATUS_NORMAL)->getLimit($page, $size);
            $list = $model->all(null);
            $count = $model->lastQueryResult()->getTotalCount();
            $format_information = FrontService::handInformation($list, $this->auth['id']);

            $return_data = [
                'banner' => [],
                'matches' => [],
                'information' => ['list' => $format_information, 'count' => $count]
            ];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return_data);


        } else {
            //普通赛事
            if ($competition_id = $this->params['competition_id']) {

                $matches = AdminMatch::getInstance()->where('competition_id', $competition_id)->where('status_id', FootballApi::STATUS_NO_START)->order('match_time', 'ASC')->limit(2)->all();
                $format_matches = FrontService::handMatch($matches, 0, true);

                $page = $this->params['page'] ?: 1;
                $size = $this->params['size'] ?: 10;
                $model = AdminInformation::getInstance()->where('competition_id', $competition_id)->where('status', AdminInformation::STATUS_NORMAL)->getLimit($page, $size);
                $list = $model->all(null);
                $format_information = FrontService::handInformation($list, $this->auth['id']);

                $count = $model->lastQueryResult()->getTotalCount();

                $title_content = [
                    'banner' => [],
                    'matches' => $format_matches,
                    'information' => ['list' => $format_information, 'count' => $count]
                ];
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $title_content);

            } else {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

            }
        }


    }
    /**
     * 赛事比赛及相关资讯文章
     * @return bool
     */
    public function competitionContent()
    {
        if ($competition_id = $this->params['competition_id']) {

            $matches = AdminMatch::getInstance()->where('competition_id', $competition_id)->where('status_id', FootballApi::STATUS_NO_START)->order('match_time', 'ASC')->limit(2)->all();
            $format_matches = FrontService::handMatch($matches, 0, true);

            $page = $this->params['page'] ?: 1;
            $size = $this->params['size'] ?: 10;
            $model = AdminInformation::getInstance()->where('competition_id', $competition_id)->field(['id', 'title', 'fabolus_number', 'respon_number', 'img'])->where('status', AdminInformation::STATUS_NORMAL)->where('created_at', date('Y-m-d H:i:s', '>='))->getLimit($page, $size);
            $list = $model->all(null);
            $informations = [];
            if ($list) {
                foreach ($list as $k => $item) {
                    $data['id'] = $item['id'];
                    $data['title'] = $item['title'];
                    $data['img'] = $item['img'];
                    $data['respon_number'] = $item['respon_number'];
                    $data['fabolus_number'] = $item['fabolus_number'];
                    $data['competition_id'] = $item['competition_id'];
                    $data['competition_short_name_zh'] = $item->getCompetition()['short_name_zh'];
                    $informations[] = $data;
                    unset($data);
                }
            }
            $count = $model->lastQueryResult()->getTotalCount();
            if ($list) {
                foreach ($list as $item) {
                    $data['id'] = $item['id'];
                    $data['title'] = $item['title'];
                }
            }
            $title_content = [
                'matches' => $format_matches,
                'information' => ['list' => $informations ?: [], 'count' => $count]
            ];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $title_content);

        } else {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

    }

    /**
     * 资讯详情
     */
    public function informationInfo()
    {

        $params = $this->params;
        $authId = empty($this->auth['id']) || intval($this->auth['id']) < 1 ? 0 : intval($this->auth['id']); // 当前登录用户ID
        $informationId = empty($params['information_id']) || intval($params['information_id']) < 1 ? 0 : intval($params['information_id']);
        $information = $informationId > 0 ? Utils::queryHandler(AdminInformation::getInstance(), 'id=?', $informationId) : false;
        if (empty($information)) return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        if (intval($information['status']) == AdminInformation::STATUS_DELETE) return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
        // 填充用户信息
        $information['user_info'] = Utils::queryHandler(AdminUser::getInstance(),
            'id=?', $information['user_id'], 'id,nickname,photo,is_offical,level');
        // 是否未被举报
        $tmp = Utils::queryHandler(AdminUserOperate::getInstance(),
            'item_type=3 and type=1 and is_cancel=0 and item_id=? and user_id=?', [$informationId, $authId]);
        $information['is_fabolus'] = !empty($tmp);
        // 是否已被收藏
        $tmp = Utils::queryHandler(AdminUserOperate::getInstance(),
            'item_type=3 and type=2 and is_cancel=0 and item_id=? and user_id=?', [$informationId, $authId]);
        $information['is_collect'] = !empty($tmp);
        // 类型
        $orderType = empty($params['order_type']) || intval($params['order_type']) < 1 ? 0 : intval($params['order_type']); // 0:最热 1:最早 2:最新
        $page = empty($params['page']) || intval($params['page']) < 1 ? 1 : intval($params['page']);
        $size = empty($params['size']) || intval($params['size']) < 1 ? 10 : intval($params['size']);
        // 评论/回复数据
        $order = $orderType == 0 ? 'fabolus_number desc, id ASC' : ($orderType == 1 ? 'created_at asc' : ($orderType == 2 ? 'created_at desc, id ASC' : null));
        $data = Utils::queryHandler(AdminInformationComment::getInstance(),
            'information_id=? and top_comment_id=0 and parent_id=0', $informationId,
            '*', false, $order, null, $page, $size);
        if (!empty($data['list'])) {
            $commentIdsStr = join(',', array_column($data['list'], 'id'));
            $userIdsStr = join(',', array_unique(array_filter(array_column($data['list'], 'user_id'))));
            // 用户数据映射
            $userMapper = empty($userIdsStr) ? [] : Utils::queryHandler(AdminUser::getInstance(),
                'id in(' . $userIdsStr . ')', null,
                'id,nickname,photo,is_offical,level', false, null, 'id,*,1');
            // 回复统计映射
            $childCountMapper = Utils::queryHandler(AdminInformationComment::getInstance(),
                'information_id=? and status=? and top_comment_id in (' . $commentIdsStr . ')', [$informationId, AdminInformationComment::STATUS_NORMAL],
                'top_comment_id,count(*) total', false,
                ['group' => 'top_comment_id'], 'top_comment_id,total,1');
            // 点赞数据映射
            $operateMapper = Utils::queryHandler(AdminUserOperate::getInstance(),
                'item_type=4 and item_id in(' . $commentIdsStr . ') and type=1 and user_id=? and is_cancel=0', $authId,
                'item_id', false, null, 'item_id,item_id,1');
            // 回复数据映射
            $childGroupMapper = [];
            $subSql = 'select count(*)+1 from admin_information_comments x where x.id=a.top_comment_id and x.information_id=? and x.status=? having (count(*)+1)<=3';
            $tmp = Utils::queryHandler(AdminInformationComment::getInstance(),
                'information_id=? and status=? and top_comment_id in(' . $commentIdsStr . ') and exists(' . $subSql . ')',
                [$informationId, AdminInformationComment::STATUS_NORMAL, $informationId, AdminInformationComment::STATUS_NORMAL],
                '*', false, 'a.created_at desc');
            foreach ($tmp as $v) {
                $id = intval($v['top_comment_id']);
                $childGroupMapper[$id][] = $v;
            }
            $comments = [];
            foreach ($data['list'] as $v) {
                $id = intval($v['id']);
                $userId = intval($v['user_id']);
                $children = empty($childGroupMapper[$id]) ? [] : $childGroupMapper[$id];
                $childrenCount = empty($childCountMapper[$id]) ? 0 : $childCountMapper[$id];
                $userInfo = empty($userMapper[$userId]) ? [] : $userMapper[$userId];
                $comments[] = [
                    'id' => $id,
                    'content' => base64_decode($v['content']),
                    'created_at' => $v['created_at'],
                    'respon_number' => $v['respon_number'],
                    'fabolus_number' => $v['fabolus_number'],
                    'child_comment_list' => FrontService::handInformationComment($children, $authId),
                    'child_comment_count' => $childrenCount,
                    'is_fabolus' => !empty($operateMapper[$id]),
                    'is_follow' => AppFunc::isFollow($authId, $userId),
                    'user_info' => $userInfo,
                ];
            }
            $data['list'] = $comments;
        }
        // 比赛信息
        $match = [];
        $tmp = Utils::queryHandler(AdminMatch::getInstance(), 'match_id=?', $information['match_id']);
        if (!empty($tmp)) {
            $tmp = FrontService::handMatch([$tmp], 0, true);
            if (isset($tmp[0])) {
                $match = [
                    'match_id' => $tmp[0]['match_id'],
                    'competition_id' => $tmp[0]['competition_id'],
                    'competition_name' => $tmp[0]['competition_name'],
                    'home_team_name' => $tmp[0]['home_team_name'],
                    'away_team_name' => $tmp[0]['away_team_name'],
                    'format_match_time' => $tmp[0]['format_match_time'],
                ];
            }
        }
        // 输出数据
        $result = [
            'relate_match' => $match,
            'information_info' => $information,
            'comments' => $data['list'], 'count' => $data['total'],
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
    }






    /**
     * 发表评论
     * @return bool
     */
    public function informationComment()
    {

        if (!$this->auth['id']) {
            return $this->writeJson(Status::CODE_LOGIN_ERR, Status::$msg[Status::CODE_LOGIN_ERR]);
        } else if ($this->auth['status'] == AdminUser::STATUS_FORBIDDEN) {
            return $this->writeJson(Status::CODE_STATUS_FORBIDDEN, Status::$msg[Status::CODE_STATUS_FORBIDDEN]);
        } else if (!$this->params['content']) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        }

        if (Cache::get('user_comment_information_' . $this->auth['id'])) {
            return $this->writeJson(Status::CODE_WRONG_LIMIT, Status::$msg[Status::CODE_WRONG_LIMIT]);
        }

        $validator = new Validate();
        $validator->addColumn('information_id')->required();
        $validator->addColumn('top_comment_id')->required();
        $validator->addColumn('parent_id')->required();
        $validator->addColumn('t_u_id')->required();
        $validator->addColumn('content')->required();
        if (!$validator->validate($this->params)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $information_id = $this->params['information_id'];

        //发布
        if ($sensitiveWords = AdminSensitive::getInstance()->where('status', AdminSensitive::STATUS_NORMAL)->field(['word'])->all()) {
            foreach ($sensitiveWords as $sword) {
                if (!$sword['word']) continue;
                if (strstr($this->params['content'], $sword['word'])) {
                    return $this->writeJson(Status::CODE_ADD_POST_SENSITIVE, sprintf(Status::$msg[Status::CODE_ADD_POST_SENSITIVE], $sword['word']));
                }
            }
        }
        if (!$information = AdminInformation::getInstance()->find($information_id)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        } else {
            $data = [
                'information_id' => $this->params['information_id'],
                'content' => base64_encode(addslashes(htmlspecialchars($this->params['content']))),
                'top_comment_id' => $this->params['top_comment_id'],
                'user_id' => $this->auth['id'],
                'parent_id' => $this->params['parent_id'],
                't_u_id' => $this->params['t_u_id'],
            ];

            $rs = AdminInformationComment::getInstance()->insert($data);
            $parent_id = $this->params['parent_id'];
            TaskManager::getInstance()->async(function () use($parent_id, $information_id) {
                if ($parent_id) {
                    AdminInformationComment::getInstance()->update(
                        ['respon_number' => QueryBuilder::inc(1)],
                        ['id' => $parent_id]
                    );
                }
                AdminInformation::getInstance()->update(
                    ['respon_number' => QueryBuilder::inc(1)],
                    ['id' => $information_id]
                );

            });

        }
        $data_task['task_id'] = 4;
        $data_task['user_id'] = $this->auth['id'];
        TaskManager::getInstance()->async(new SerialPointTask($data_task));
        Cache::set('user_comment_information_' . $this->auth['id'], 1, 5);
        $data['id'] = $rs;

        if ($parent_id) {
            $message_data = [
                'status' => AdminMessage::STATUS_UNREAD,
                'type' => 3,
                'item_type' => 4,
                'item_id' => $rs,
                'title' => '资讯回复通知',
                'did_user_id' => $this->auth['id']
            ];
            $message_data['user_id'] = AdminInformationComment::getInstance()->where('id', $this->params['parent_id'])->get()->user_id;
            AdminMessage::getInstance()->insert($message_data);

        }
        if ($comment_info = AdminInformationComment::getInstance()->where('id', $rs)->get()) {
            $format = FrontService::handInformationComment([$comment_info], $this->auth['id']);

            $comment_info_format = !empty($format[0]) ? $format[0] : [];
        } else {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $comment_info_format);

    }

    /**
     * 二级评论列表
     */
    public function informationChildComment()
    {
        $top_comment_id = $this->params['top_comment_id'];
        $page = isset($this->params['page']) ? $this->params['page'] : 1;
        $size = isset($this->params['size']) ? $this->params['size'] : 1;
        if (!$father_comment = AdminInformationComment::getInstance()->find($top_comment_id)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

        $format_father = FrontService::handInformationComment([$father_comment], $this->auth['id']);

        $model = AdminInformationComment::getInstance()->where('top_comment_id', $top_comment_id)->getLimit($page, $size);
        $list = $model->all(null);
        $total = $model->lastQueryResult()->getTotalCount();

        $format_information_child_comments = FrontService::handInformationComment($list, $this->auth['id']);

        $return = [
            'fatherComment' => isset($format_father[0]) ? $format_father[0] : [],
            'childComment' => $format_information_child_comments,
            'count' => $total
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);


    }


    public function basketballInformationList()
    {
        $information = AdminCompetition::getInstance()->limit(10)->all();
        foreach ($information as $item) {
            $data = [
                'competition_id' => $item['competition_id'],
                'category_id' => $item['category_id'],
                'country_id' => $item['country_id'],
                'name_zh' => $item['name_zh'],
                'short_name_zh' => $item['short_name_zh'],
                'type' => $item['type'],
                'cur_season_id' => $item['cur_season_id'],
                'cur_stage_id' => $item['cur_stage_id'],
                'cur_round' => $item['cur_round'],
                'round_count' => $item['round_count'],
                'logo' => $item['logo'],
                'title_holder' => $item['title_holder'],
                'most_titles' => $item['most_titles'],
                'newcomers' => $item['newcomers'],
                'divisions' => $item['divisions'],
                'host' => $item['host'],
                'primary_color' => $item['primary_color'],
                'secondary_color' => $item['secondary_color'],
                'updated_time' => $item['updated_time'],
                'updated_at' => $item['updated_at'],
            ];

            unset($item['id']);
            BasketBallCompetition::getInstance()->insert($data);
        }

    }


}