<?php

namespace App\HttpController\Match;

use App\Base\FrontUserController;
use App\Common\AppFunc;
use App\Common\Time;
use App\GeTui\BatchSignalPush;
use App\lib\FrontService;
use App\lib\Tool;
use App\lib\Utils;
use App\Model\AdminCompetition;
use App\Model\AdminInformation;
use App\Model\AdminInformationComment;
use App\Model\AdminMatch;
use App\Model\AdminMessage;
use App\Model\AdminNoticeMatch;
use App\Model\AdminSensitive;
use App\Model\AdminSysSettings;
use App\Model\AdminUser;
use App\Model\AdminUserOperate;
use App\Model\AdminUserSetting;
use App\Model\BasketBallCompetition;
use App\Storage\OnlineUser;
use App\Task\SerialPointTask;
use App\Utility\Message\Status;
use easySwoole\Cache\Cache;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\Validate\Validate;
use EasySwoole\HttpAnnotation\AnnotationController;
use EasySwoole\HttpAnnotation\AnnotationTag\Api;
use EasySwoole\HttpAnnotation\AnnotationTag\Param;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiDescription;
use EasySwoole\HttpAnnotation\AnnotationTag\Method;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiSuccess;
/**
 * 资讯中心
 * Class InformationApi
 * @package App\HttpController\Match
 */
class InformationApi extends FrontUserController
{


    /**
     * 顶部标题栏
     * @Api(name="顶部标题栏",path="/api/information/titleBar",version="3.0")
     * @ApiDescription(value="serverClient for titleBar")
     * @Method(allow="{GET}")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": [
        {
        "competition_id": 0,
        "short_name_zh": "头条",
        "type": 1
        }
        ]
        })
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
     * 分类资讯
     * @Api(name="分类资讯",path="/api/information/getCategoryInformation",version="3.0")
     * @ApiDescription(value="serverClient for getCategoryInformation")
     * @Method(allow="{GET}")
     * @Param(name="type",type="string",required="",description="类型 1头条 2转会 3普通赛事")
     * @Param(name="page",type="int",required="",description="页码")
     * @Param(name="size",type="int",required="",description="每页数")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
        "banner": [
        {
        "id": 2,
        "title": "法国粉红色的",
        "img": "http://n.sinaimg.cn/sports/transform/220/w660h360/20200921/6b82-izmihnt6941060.jpg",
        "sort": 9,
        "start_time": "2020-10-27 00:00:00",
        "end_time": "2022-11-27 00:00:00",
        "information_id": 3
        },
        {
        "id": 1,
        "title": "更加放松的方式",
        "img": "http://n.sinaimg.cn/sports/transform/220/w660h360/20200921/6b82-izmihnt6941060.jpg",
        "sort": 1,
        "start_time": "2020-10-27 00:00:00",
        "end_time": "2022-11-27 00:00:00",
        "information_id": 3
        }
        ],
        "matches": [],
        "information": {
        "list": [
        {
        "id": 60,
        "title": "大逆转！墨西哥美洲狮首回合0-4，次回合连扳四球&绝杀晋级",
        "img": "http://backgroundtest.ymtyadmin.com/upload/article/a4dd012879f572cea96c1d8f66259b9f.jpeg",
        "status": 1,
        "is_fabolus": false,
        "fabolus_number": 2,
        "respon_number": 1,
        "competition_id": 142,
        "created_at": "2020-12-07 13:20:00",
        "is_title": true,
        "competition_short_name_zh": "法甲",
        "user_info": {
        "id": 1,
        "nickname": "夜猫官方",
        "photo": "http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/c9f0f56cdcae3695.jpg",
        "level": 1,
        "is_offical": 1
        }
        }
        ],
        "count": 27
        }
        }
        })
     */
    public function getCategoryInformation()
    {
        //资讯文章
        $page =  !empty($this->params['page']) ? $this->params['page'] : 1;
        $size =  !empty($this->params['size']) ? $this->params['size'] : 10;
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
            if (!empty($decode['match'])) {
                $matches = AdminMatch::getInstance()->where('match_id', $decode['match'], 'in')->all();
                $formatMatches = FrontService::formatMatchThree($matches, 0, []);
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
                $format_matches = FrontService::formatMatchThree($matches, 0, true);

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
     * 赛事内容
     * @Api(name="赛事内容",path="/api/information/competitionContent",version="3.0")
     * @ApiDescription(value="serverClient for competitionContent")
     * @Method(allow="{GET}")
     * @Param(name="competition_id",type="int",required="",description="赛事id")
     * @Param(name="page",type="int",required="",description="页码")
     * @Param(name="size",type="int",required="",description="每页数")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
        "matches": [
        {
        "home_team_name": "比路朴",
        "home_team_logo": "https://cdn.sportnanoapi.com/football/team/df2700ad1fa9437dabde18b62b9add70.png",
        "away_team_name": "里杰卡",
        "away_team_logo": "https://cdn.sportnanoapi.com/football/team/8089e760b9d728fbecc6efe12cdf52a1.png",
        "round": "",
        "competition_id": 330,
        "competition_name": "克亚甲",
        "competition_color": "#ec008c",
        "match_time": "22:00",
        "format_match_time": "2020-12-20 22:00",
        "user_num": 0,
        "match_id": 3422317,
        "is_start": false,
        "status_id": 1,
        "is_interest": false,
        "neutral": 0,
        "matching_time": 0,
        "matching_info": null,
        "has_living": 0,
        "living_url": {
        "liveUrl": "",
        "liveUrl2": "",
        "liveUrl3": ""
        },
        "note": "",
        "home_scores": "[0,0,0,0,0,0,0]",
        "away_scores": "[0,0,0,0,0,0,0]",
        "coverage": "",
        "steamLink": ""
        }
        ],
        "information": {
        "list": [],
        "count": 0
        }
        }
        })
     */
    public function competitionContent()
    {
        $competition_id = !empty($this->params['competition_id']) ? (int)$this->params['competition_id'] : 0;
        if ($competition_id) {
            $matches = AdminMatch::getInstance()->where('competition_id', $competition_id)->where('status_id', FootballApi::STATUS_NO_START)->order('match_time', 'ASC')->limit(2)->all();
            $format_matches = FrontService::formatMatchThree($matches, 0, []);

            $page = !empty($this->params['page']) ? (int)$this->params['page'] : 1;
            $size = !empty($this->params['size']) ? (int)$this->params['size'] : 10;
            $model = AdminInformation::getInstance()->where('competition_id', $competition_id)->field(['id', 'title', 'fabolus_number', 'respon_number', 'img'])->where('status', AdminInformation::STATUS_NORMAL)->where('created_at', date('Y-m-d H:i:s'), '>=')->getLimit($page, $size);
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
     * @Api(name="资讯详情",path="/api/information/competitionContent",version="3.0")
     * @ApiDescription(value="serverClient for informationInfo")
     * @Method(allow="{GET}")
     * @Param(name="information_id",type="int",required="",description="文章id")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
        "relate_match": [],
        "information_info": {
        "id": "157",
        "user_id": "1",
        "title": "超然之战:巴塞罗那vs尤文图斯，双子巨星巅峰对决！",
        "status": "1",
        "content":"",
        "fabolus_number": "6",
        "created_at": "2020-12-08 19:55:00",
        "updated_at": "2020-12-11 17:17:53",
        "respon_number": "13",
        "img": "http://backgroundtest.ymtyadmin.com/upload/article/8eeda40749b36884560910e3a6e06d98.png",
        "competition_id": "46",
        "collect_number": "3",
        "match_id": "0",
        "type": "1",
        "sport_type": "0",
        "user_info": {
        "id": "1",
        "nickname": "夜猫官方",
        "photo": "http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/c9f0f56cdcae3695.jpg",
        "is_offical": "1",
        "level": "1"
        },
        "is_fabolus": false,
        "is_collect": false
        },
        "comments": [
        {
        "id": 6,
        "content": "11447785223698",
        "created_at": "2020-12-18 13:27:05",
        "respon_number": "1",
        "fabolus_number": "1",
        "child_comment_list": [
        {
        "id": 45,
        "information_id": 157,
        "information_title": "超然之战:巴塞罗那vs尤文图斯，双子巨星巅峰对决！",
        "content": "这是啥？？？",
        "parent_id": "6",
        "created_at": "2020-12-25 18:40:18",
        "respon_number": "0",
        "fabolus_number": "0",
        "is_fabolus": false,
        "user_info": {
        "id": "74",
        "photo": "https://www.gravatar.com/avatar/24d418de7deb7231eeb443678b0f0441?s=120&d=identicon",
        "nickname": "number3",
        "level": "1",
        "is_offical": "0"
        },
        "t_u_info": {
        "id": "70",
        "photo": "https://www.gravatar.com/avatar/f86e1cf930d2b38baf2a563b456cee6d?s=120&d=identicon",
        "nickname": "rgzzsdjqr",
        "level": "1",
        "is_offical": "0"
        },
        "is_follow": false
        }
        ],
        "child_comment_count": "1",
        "is_fabolus": false,
        "is_follow": false,
        "user_info": {
        "id": "70",
        "nickname": "rgzzsdjqr",
        "photo": "https://www.gravatar.com/avatar/f86e1cf930d2b38baf2a563b456cee6d?s=120&d=identicon",
        "is_offical": "0",
        "level": "1"
        }
        }
        ],
        "count": 7
        }
        })
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
        $information['user_info']['is_follow'] = AppFunc::isFollow($authId, $information['id']);
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
        $tmp = AdminMatch::create()->where('match_id', $information['match_id'])->get();

        if (!empty($tmp)) {
            $tmp = FrontService::formatMatchThree([$tmp], 0, []);
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
     * 发表资讯评论
     * @Api(name="发表资讯评论",path="/api/information/informationComment",version="3.0")
     * @ApiDescription(value="serverClient for informationComment")
     * @Method(allow="{POST}")
     * @Param(name="content",type="string",required="",description="内容")
     * @Param(name="top_comment_id",type="int",required="",description="以及评论id")
     * @Param(name="parent_id",type="int",required="",description="父评论id")
     * @Param(name="t_u_id",type="int",required="",description="被回复人id")
     * @ApiSuccess({"code":0,"msg":"OK","data":null})
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
     * @Api(name="二级评论列表",path="/api/information/informationChildComment",version="3.0")
     * @ApiDescription(value="serverClient for informationChildComment")
     * @Method(allow="{GET}")
     * @Param(name="top_comment_id",type="int",required="",description="一级评论id")
     * @Param(name="page",type="int",required="",description="页码")
     * @Param(name="size",type="int",required="",description="每页数")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
        "fatherComment": {
        "id": 2,
        "information_id": 85,
        "information_title": "亚冠东亚区四强出炉：国安、蔚山、神户、水原",
        "content": "哈哈哈",
        "parent_id": 0,
        "created_at": "2020-12-09 16:23:51",
        "respon_number": 0,
        "fabolus_number": 1,
        "is_fabolus": false,
        "user_info": {
        "id": "4",
        "photo": "http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/77e37f8fe3181d5f.jpg",
        "nickname": "Hdhdh",
        "level": "3",
        "is_offical": "0"
        },
        "t_u_info": [],
        "is_follow": false
        },
        "childComment": [
        {
        "id": 4,
        "information_id": 85,
        "information_title": "亚冠东亚区四强出炉：国安、蔚山、神户、水原",
        "content": "早日康复(ﾟДﾟ)ﾉ",
        "parent_id": 0,
        "created_at": "2020-12-15 18:03:21",
        "respon_number": 0,
        "fabolus_number": 0,
        "is_fabolus": false,
        "user_info": {
        "id": "4",
        "photo": "http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/77e37f8fe3181d5f.jpg",
        "nickname": "Hdhdh",
        "level": "3",
        "is_offical": "0"
        },
        "t_u_info": [],
        "is_follow": false
        }
        ],
        "count": 1
        }
        })
     */
    public function informationChildComment()
    {
        $top_comment_id = !empty($this->params['top_comment_id']) ? (int)$this->params['top_comment_id'] : 0;
        $page = isset($this->params['page']) ? $this->params['page'] : 1;
        $size = isset($this->params['size']) ? $this->params['size'] : 10;
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

    /**
     * 篮球资讯title栏
     * @Api(name="篮球资讯title栏",path="/api/information/basketballInformationTitleBar",version="3.0")
     * @ApiDescription(value="serverClient for basketballInformationTitleBar")
     * @Method(allow="{GET}")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": [
        {
        "competition_id": 0,
        "short_name_zh": "头条",
        "type": 1
        },
        {
        "competition_id": 0,
        "short_name_zh": "转会",
        "type": 2
        },
        {
        "competition_id": 1,
        "short_name_zh": "NBA",
        "type": 3
        },
        {
        "competition_id": 2,
        "short_name_zh": "WNBA",
        "type": 3
        },
        {
        "competition_id": 3,
        "short_name_zh": "CBA",
        "type": 3
        },
        {
        "competition_id": 4,
        "short_name_zh": "NBL",
        "type": 3
        },
        {
        "competition_id": 3943,
        "short_name_zh": "金龙杯",
        "type": 3
        }
        ]
        })
     */
    public function basketballInformationTitleBar()
    {
        $format = [
            [
                'competition_id' => 0,
                'short_name_zh' => '头条',
                'type' => 1
            ],
            [
                'competition_id' => 0,
                'short_name_zh' => '转会',
                'type' => 2
            ]
        ];
        if ($basketball = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::BASKETBALL_COMPETITION)->get()) {
            $basCompetitionIds = json_decode($basketball->sys_value, true);
            if ($basCompetitionArr = BasketBallCompetition::getInstance()->where('competition_id', $basCompetitionIds, 'in')->all()) {
                foreach ($basCompetitionArr as $item) {
                    $format[] = ['competition_id' => $item['competition_id'], 'short_name_zh' => $item['short_name_zh'], 'type' => 3];
                }
            }
        }


        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $format);

    }
    /**
     * 根据条件查询资讯列表
     * @Api(name="根据条件查询资讯列表",path="/api/information/basketballInformationList",version="3.0")
     * @ApiDescription(value="serverClient for basketballInformationList")
     * @Method(allow="{GET}")
     * @Param(name="type",type="int",required="",description="头条 ｜转会 ｜ 普通赛事")
     * @Param(name="basketComid",type="int",required="",description="普通赛事id")
     * @Param(name="page",type="int",required="",description="页码")
     * @Param(name="size",type="int",required="",description="每页数")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
        "list": [
        {
        "id": 213,
        "title": "篮球资讯",
        "img": "http://backgroundtest.ymtyadmin.com/upload/article/42b71372e97ed99e02bfc7e2e0ccfd9e.jpeg",
        "status": 1,
        "is_fabolus": false,
        "fabolus_number": 0,
        "respon_number": 0,
        "competition_id": 1,
        "created_at": "2021-02-02 13:25:27",
        "is_title": false,
        "competition_short_name_zh": "世界杯",
        "user_info": {
        "id": 2,
        "nickname": "321",
        "photo": "http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/b57075a366d9c6b7.jpg",
        "level": 1,
        "is_offical": 0
        }
        }
        ],
        "count": 1
        }
        })
     */
    public function basketballInformationList()
    {
        $type = !empty($this->params['type']) ? (int)$this->params['type'] : 1;
        if (!isset($this->params['basketComid'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        }
        $userId = (int)$this->auth['id'];
        $page = !empty($this->params['page']) ? (int)$this->params['page'] : 1;
        $size = !empty($this->params['size']) ? (int)$this->params['size'] : 15;
        //头条或转会
        if (!$this->params['basketComid']) {
            $basketballInformation = AdminInformation::create()->where('type', $type)->where('sport_type', 2)->getLimit($page, $size, 'created_at', 'DESC');
        } else {
            $basketballInformation = AdminInformation::create()->where('type', 3)->where('sport_type', 2)->where('competition_id', (int)$this->params['basketComid'])->getLimit($page, $size, 'created_at', 'DESC');
        }
        $list   = $basketballInformation->all(null);
        $count = $basketballInformation->lastQueryResult()->getTotalCount();
        $formatInformation = FrontService::handInformation($list, $userId);

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => $formatInformation, 'count' => $count]);

    }


    public function informationPusher()
    {
        $information_id = !empty($this->params['information_id']) ? (int)$this->params['information_id'] : 0;
        if (!$information_id || !$information = AdminInformation::getInstance()->where('id', $information_id)->get()) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);
        }
        $user = AdminUserSetting::create()->field(['user_id'])->where('push REGEXP \'\"open_push\":1,\"information\":1\'')->all();
        $uids = array_column($user, 'user_id');
        $cids = AdminUser::create()->field(['cid'])->where('id', $uids, 'in')->where('cid', '', '<>')->all();
        $cidArr = array_column($cids, 'cid');
        $formatCid = array_keys(array_flip($cidArr));
        if (!$res = AdminNoticeMatch::getInstance()->where('match_id', $information_id)->where('item_type', 3)->get()) {
            $insertData = [
                'uids' => json_encode($uids),
                'match_id' => $information_id,
                'title' => $information->title,
                'content' => '',
                'item_type' => 3,
                'type' => 0
            ];
            $rs = AdminNoticeMatch::getInstance()->insert($insertData);
            $info['rs'] = $rs;  //开赛通知
            $pushInfo = [
                'title' => $information->title,
                'content' => mb_substr(preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", " ", strip_tags($information->content)), 0, 20),
                'payload' => ['item_id' => $information_id, 'item_type' => 3],
                'notice_id' => $rs,

            ];
            $batchPush = new BatchSignalPush();

            $res = $batchPush->pushMessageToList($formatCid, $pushInfo);
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $res);


        }
    }


}