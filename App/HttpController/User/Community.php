<?php


namespace App\HttpController\User;


use App\Base\FrontUserController;
use App\Common\AppFunc;
use App\Common\Time;
use App\lib\FrontService;
use App\lib\Utils;
use App\Model\AdminInformation;
use App\Model\AdminInformationComment;
use App\Model\AdminMatch;
use App\Model\AdminMessage;
use App\Model\AdminNormalProblems;
use App\Model\AdminPostComment;
use App\Model\AdminSensitive;
use App\Model\AdminSysSettings;
use App\Model\AdminTeam;
use App\Model\AdminUser;
use App\Model\AdminUserFeedBack;
use App\Model\AdminUserPost;
use App\Model\AdminUserPostsCategory;
use App\Task\SerialPointTask;
use App\Utility\Log\Log;
use App\Utility\Message\Status as Statuses;
use easySwoole\Cache\Cache;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use App\Utility\Message\Status;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\DbManager;
use EasySwoole\Validate\Validate;
use EasySwoole\HttpAnnotation\AnnotationController;
use EasySwoole\HttpAnnotation\AnnotationTag\Api;
use EasySwoole\HttpAnnotation\AnnotationTag\Param;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiDescription;
use EasySwoole\HttpAnnotation\AnnotationTag\Method;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiSuccess;


class Community extends FrontUserController
{
    /**
     * 社区板块
     * @var bool
     */
    protected $isCheckSign = false;
    public $needCheckToken = false;




    /**
     * 帖子二级评论列表
     * @Api(name="帖子二级评论列表",path="/api/community/getAllChildComments",version="3.0")
     * @ApiDescription(value="serverClient for getAllChildComments")
     * @Method(allow="{GET}")
     * @Param(name="comment_id",type="int",required="",description="评论id")
     * @Param(name="page",type="int",required="",description="页码")
     * @Param(name="size",type="int",required="",description="每页数")
     * @ApiSuccess({"code":0,"msg":"ok","data":{"fatherComment":{"id":1,"post_id":1,"post_title":"999999","parent_id":0,"parent_content":"","content":"所得到的所","created_at":"2020-09-07 19:37:32","fabolus_number":1,"is_fabolus":false,"user_info":{"id":4,"nickname":"Hdhdh","photo":"http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/77e37f8fe3181d5f.jpg","level":3,"is_offical":0},"is_follow":false,"respon_number":4,"top_comment_id":0,"t_u_info":{"id":4,"mobile":"17343214247","nickname":"Hdhdh","photo":"http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/77e37f8fe3181d5f.jpg","level":3,"is_offical":0}},"childComment":{{"id":17,"post_id":102,"post_title":"加加看","parent_id":3,"parent_content":"胜多负少大","content":"sdfsdfsdf","created_at":"2020-10-05 16:21:01","fabolus_number":0,"is_fabolus":false,"user_info":{"id":2,"nickname":"321","photo":"http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/b57075a366d9c6b7.jpg","level":1,"is_offical":0},"is_follow":false,"respon_number":0,"top_comment_id":1,"t_u_info":{"id":2,"mobile":"15227249561","nickname":"321","photo":"http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/b57075a366d9c6b7.jpg","level":1,"is_offical":0}},{"id":5,"post_id":102,"post_title":"加加看","parent_id":1,"parent_content":"所得到的所","content":"单声道","created_at":"2020-09-07 19:44:05","fabolus_number":1,"is_fabolus":false,"user_info":{"id":4,"nickname":"Hdhdh","photo":"http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/77e37f8fe3181d5f.jpg","level":3,"is_offical":0},"is_follow":false,"respon_number":0,"top_comment_id":1,"t_u_info":{"id":4,"mobile":"17343214247","nickname":"Hdhdh","photo":"http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/77e37f8fe3181d5f.jpg","level":3,"is_offical":0}},{"id":2,"post_id":1,"post_title":"999999","parent_id":1,"parent_content":"所得到的所","content":"6IOc5aSa6LSf5bCR5aSn","created_at":"2020-09-07 19:37:37","fabolus_number":1,"is_fabolus":false,"user_info":{"id":2,"nickname":"321","photo":"http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/b57075a366d9c6b7.jpg","level":1,"is_offical":0},"is_follow":false,"respon_number":0,"top_comment_id":1,"t_u_info":{"id":2,"mobile":"15227249561","nickname":"321","photo":"http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/b57075a366d9c6b7.jpg","level":1,"is_offical":0}}},"count":3}})
     */
    public function getAllChildComments()
    {
        if (empty($this->params['comment_id'])) {
            $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        } else {
            $page = !empty($this->params['page']) ? (int)$this->params['page']: 1;
            $size = !empty($this->params['size']) ? (int)$this->params['size'] : 10;
            $cId = (int)$this->params['comment_id'];
            $comment = AdminPostComment::getInstance()->find($cId);
            if (!$comment) {
                return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

            }

            if ($comment->top_comment_id == 0) {
                $fatherComment = [$comment];
            } else {
                $fatherComment = AdminPostComment::getInstance()->where('id', $comment->top_comment_id)->where('status', AdminUserPost::NEW_STATUS_DELETED, '<>')->all();

            }
            $childCommentModel = AdminPostComment::getInstance()->where('top_comment_id', $fatherComment[0]['id'])->where('status', AdminUserPost::STATUS_DEL, '<>')->getAll($page, $size);
            $childComments = $childCommentModel->all();

            $count = $childCommentModel->lastQueryResult()->getTotalCount();
            $formatFComments = FrontService::handComments($fatherComment, $this->auth['id'] ?: 0);
            $formatCComments = FrontService::handComments($childComments, $this->auth['id'] ?: 0);
            $return = [
                'fatherComment' => $formatFComments ? $formatFComments[0] : [],
                'childComment' => $formatCComments,
                'count' => $count
            ];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

        }

    }


    /**
     * 评论内容详情
     * @return bool
     */
    public function commentInfo()
    {
        $comment_id = $this->params['comment_id'];
        if (!$comment_id) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

        $comment = AdminPostComment::getInstance()->find($comment_id);
        if (!$comment) {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

        } else if ($comment->status == AdminPostComment::STATUS_DEL) {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);
        }
        $commentInfo = FrontService::handComments([$comment], $this->auth['id'] ?: 0);
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $commentInfo[0]);

    }


    /**
     * 帖子详情
     * @Api(name="帖子详情",path="/api/community/detail",version="3.0")
     * @ApiDescription(value="serverClient for detail")
     * @Method(allow="{GET}")
     * @Param(name="page",type="int",required="",description="页码")
     * @Param(name="size",type="int",required="",description="每页数")
     * @Param(name="post_id",type="int",required="",description="帖子id")
     * @ApiSuccess({"code":0,"msg":"ok","data":{"basic":{"id":308,"hit":2903,"user_id":1,"title":"郭德纲的订单","cat_id":3,"status":1,"created_at":"2020-12-06 19:42:43","is_refine":0,"respon_number":1,"updated_at":"2020-12-06 19:42:53","fabolus_number":0,"collect_number":0,"content":"大师傅士大夫是<!--173--><!--173-->","is_me":false,"cat_name":"足球天地","cat_color":{"background":"#ECEFF9","font":"#4B6EE4"},"imgs":"","user_info":{"id":"1","photo":"http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/c9f0f56cdcae3695.jpg","nickname":"夜猫官方","level":"1","is_offical":"1"},"is_follow":false,"is_collect":false,"lasted_resp":"2020-12-09 11:22:52","is_fabolus":false},"comment":{{"id":323,"user_info":{"id":1,"mobile":"15227249568","nickname":"夜猫官方","photo":"http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/c9f0f56cdcae3695.jpg","level":1,"is_offical":1},"is_follow":false,"content":"滚滚滚","child_comment_list":{},"child_comment_count":0,"is_fabolus":false,"fabolus_number":0,"respon_number":0,"created_at":"2020-12-09 11:22:52"}},"count":1}})
     */

    public function detail()
    {

        $page = !empty($this->params['page']) ? (int)$this->params['page']: 1;
        $size = !empty($this->params['size']) ? (int)$this->params['size'] : 10;
        $id = !empty($this->params['post_id']) ? (int)$this->params['post_id'] : 0;

        $info = AdminUserPost::getInstance()->get(['id'=>$id]);

        if (!$info) {
            return $this->writeJson(Status::CODE_ERR, '对应帖子不存在');
        }
        if ($this->auth['id'] != $info['user_id']) {
            //增加逻辑，点击率增加
            $info->update([
                'hit' => QueryBuilder::inc(1)
            ],
                ['id'=>$id]);

        }
        $uid = $this->auth['id'] ? $this->auth['id'] : 0;
        $only_author = isset($this->params['only_author']) ? (int)$this->params['only_author'] : 0;
        $order_type = isset($this->params['order_type']) ? (int)$this->params['order_type'] : 1;
        $postInfo = FrontService::handPosts([$info], $this->auth['id'] ?: 0)[0];

        //展示最新评论
        $commentModel = AdminPostComment::getInstance();
        $commentModel = $commentModel->where('post_id', $id)
            ->where('status', [AdminPostComment::STATUS_NORMAL, AdminPostComment::STATUS_REPORTED], 'in')
            ->where('top_comment_id', 0);

        if ($only_author) {
            $commentModel = $commentModel->where('user_id', $info->user_id);
        }
        switch ($order_type){
            case 1: //热度  回复数
                $comments = $commentModel->order('fabolus_number', 'DESC')->order('created_at', 'ASC')->limit(($page - 1) * $size, $size)
                    ->withTotalCount();
                break;
            case 2://最新回复
                $comments = $commentModel->order('created_at', 'DESC')->limit(($page - 1) * $size, $size)
                    ->withTotalCount();
                break;
            case 3://最早回复
                $comments = $commentModel->order('created_at', 'ASC')->limit(($page - 1) * $size, $size)
                    ->withTotalCount();
                break;
            default:
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);


        }

        $list = $comments->all(null);
        $count = $comments->lastQueryResult()->getTotalCount();
        $format_comments = [];

        if ($list) {
            foreach ($list as $item) {

                $child_comments = AdminPostComment::getInstance()->where('top_comment_id', $item['id'])->where('status', [AdminPostComment::STATUS_NORMAL, AdminPostComment::STATUS_REPORTED], 'in')->order('created_at', 'DESC')->limit(3)->all();
                $child_comments_count = AdminPostComment::getInstance()->where('top_comment_id', $item['id'])->where('status', [AdminPostComment::STATUS_NORMAL, AdminPostComment::STATUS_REPORTED], 'in')->order('created_at', 'DESC')->count('id');
                $data['id'] = $item->id;
                $data['user_info'] = $item->uInfo();
                $data['is_follow'] = AppFunc::isFollow($this->auth['id'], $item['user_id']);
                $data['content'] = $item['content'];
                $data['child_comment_list'] = FrontService::handComments($child_comments, $uid);
                $data['child_comment_count'] = $child_comments_count;
                $data['is_fabolus'] = $uid ? ($item->isFabolus($uid, $item->id) ? true : false) : false;
                $data['fabolus_number'] = $item['fabolus_number'];
                $data['respon_number'] = $item['respon_number'];
                $data['created_at'] = $item['created_at'];
                $format_comments[] = $data;
                unset($data);

            }
        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], [
            'basic' => $postInfo,
            'comment' => $format_comments,
            'count' => $count
        ]);
    }


    /**
     * 社区首页
     * @Api(name="社区首页",path="/api/community/getContent",version="3.0")
     * @ApiDescription(value="serverClient for getContent")
     * @Method(allow="{GET}")
     * @Param(name="category_id",type="int",required="",description="分类id")
     * @Param(name="order_type",type="int",required="",description="排序类型")
     * @Param(name="page",type="int",required="",description="页码")
     * @Param(name="size",type="int",required="",description="每页数")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
        "count": 244,
        "title": [
        {
        "id": "1",
        "name": "全部",
        "icon": "http://backgroundtest.ymtyadmin.com/upload/post_category/2c95295e-d81d-4d8f-a897-7e98c31e4322.png"
        }
        ],
        "banner": [
        {
        "title": "文章22",
        "img": "http://backgroundtest.ymtyadmin.com/upload/post_category/8a61e1fd-1e5f-4937-977e-6e4ec85b1e31.jpeg",
        "sort": 0,
        "show_time": "2020-11-30 00:00:00 - 2021-01-30 23:59:00",
        "type": 2,
        "url": "",
        "id": 2,
        "item_id": "22",
        "start_time": "2020-11-30 00:00:00",
        "end_time": "2021-01-30 23:59:00"
        }
        ],
        "top_posts": [
        {
        "id": "308",
        "title": "郭德纲的订单"
        }
        ],
        "normal_posts": [
        {
        "id": 18,
        "hit": "40",
        "user_id": 1,
        "title": "123456",
        "cat_id": 2,
        "status": "1",
        "created_at": "2020-07-22 18:10:21",
        "is_refine": 1,
        "respon_number": "110",
        "updated_at": "2020-07-22 22:09:55",
        "fabolus_number": "80",
        "collect_number": "8",
        "content": "333333333",
        "is_me": false,
        "cat_name": "",
        "cat_color": "",
        "imgs": [],
        "user_info": {
        "id": "1",
        "photo": "http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/c9f0f56cdcae3695.jpg",
        "nickname": "夜猫官方",
        "level": "1",
        "is_offical": "1"
        },
        "is_follow": false,
        "is_collect": false,
        "lasted_resp": "2020-09-30 12:11:43",
        "is_fabolus": false
        }
        ]
        }
        })
     */
    public function getContent(): bool
    {
        // 参数过滤
        $params = $this->params;
        $categoryId = empty($params['category_id']) || intval($params['category_id']) < 1 ? 1 : intval($params['category_id']);
        $orderType = empty($params['order_type']) || intval($params['order_type']) < 1 ? 1 : intval($params['order_type']);
        $isRefine = empty($params['is_refine']) || intval($params['is_refine']) < 1 ? false : true;
        $page = empty($params['page']) || intval($params['page']) < 1 ? 1 : intval($params['page']);
        $size = empty($params['size']) || intval($params['size']) < 1 ? 15 : intval($params['size']);
        // 当前登录用户ID
        $authId = empty($this->auth['id']) || intval($this->auth['id']) < 1 ? 0 : intval($this->auth['id']);
        // 状态条件
        $statusStr = AdminUserPost::NEW_STATUS_NORMAL . ',' . AdminUserPost::NEW_STATUS_REPORTED . ',' . AdminUserPost::NEW_STATUS_LOCK;
        // 获取帖子清单助手
        $getPostListHandler = function ($where) use ($isRefine, $orderType, $authId, $page, $size) {
            if ($isRefine > 0) $where .= ' and is_refine=1'; // 精华
            if ($orderType < 1 || $orderType > 4) return false;
            // 1热度 回复数 2最新发帖 3最早发帖 4最新回复
            $order = $orderType == 1 ? 'respon_number desc' : ($orderType == 2 ? 'created_at desc' :
                ($orderType == 3 ? 'created_at asc' : 'last_respon_time desc'));
            $data = Utils::queryHandler(AdminUserPost::getInstance(), $where, null,
                '*', false, $order, null, $page, $size);
            $list = FrontService::handPosts($data['list'], $authId);
            return ['normal_posts' => $list, 'count' => $data['total']];
        };
        // 关注的人 帖子列表
        if ($categoryId == 2) {
            $userIds = $authId > 0 ? AppFunc::getUserFollowing($authId) : false;
            if (empty($userIds)) return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => [], 'count' => 0]);
            $where = 'status in(' . $statusStr . ') and user_id in(' . join(',', $userIds) . ')';
            $result = $getPostListHandler($where);
            if($result === false) return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
        }
        // 输出数据
        $result = ['count' => 0, 'title' => [], 'banner' => [], 'top_posts' => [], 'normal_posts' => []];
        // 模块标题
        $result['title'] = Utils::queryHandler(AdminUserPostsCategory::getInstance(),
            'status=?', AdminUserPostsCategory::STATUS_NORMAL, 'id,name,icon', false);
        // 模块轮播
        $tmp = Utils::queryHandler(AdminUserPostsCategory::getInstance(), 'id=?', $categoryId, 'id,dispose');
        if (!empty($tmp['dispose'])) {
            $tmp = json_decode($tmp['dispose'], true);
            foreach ($tmp as $v) {
                if (Time::isBetween($v['start_time'], $v['end_time'])) $result['banner'][] = $v;
            }
        }
        //置顶帖子
        $where = 'status in (' . $statusStr . ') and ' . ($categoryId == 1 ? 'is_all_top=1' : 'is_top=1 and cat_id=' . $categoryId);
        $result['top_posts'] = Utils::queryHandler(AdminUserPost::getInstance(),
            $where, null, 'id,title', false, 'created_at desc');
        //普通帖子
        $where = 'status in (' . $statusStr . ')';
        if ($categoryId != 1) $where .= ' and cat_id=' . $categoryId;
        $result = array_merge($result, $getPostListHandler($where));
        if($result === false) return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
    }

    /**
     * 关键字搜索
     * @Api(name="关键字搜索",path="/api/community/getContentByKeyWord",version="3.0")
     * @ApiDescription(value="serverClient for getContentByKeyWord")
     * @Method(allow="{GET}")
     * @Param(name="key_word",type="string",required="",description="关键词")
     * @ApiSuccess({"code":0,"msg":"ok","data":{"format_posts":{"data":{},"count":0},"format_matches":{"data":{},"count":0},"information":{"data":{{"id":122,"title":"澳媒：中国足协推出工资帽制度","img":"http://backgroundtest.ymtyadmin.com/upload/article/c2ae16c139872554dfb0d004d23db363.png","status":1,"is_fabolus":false,"fabolus_number":0,"respon_number":0,"competition_id":590,"ed_at":"2020-12-01 15:00:00","is_title":false,"competition_short_name_zh":"澳超","user_info":{"id":1,"nickname":"夜猫官方","photo":"http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/c9f0f56695.jpg","level":1,"is_offical":1}}},"count":1},"users":{"data":{},"count":0}}})
     */
    public function getContentByKeyWord()
    {

       if (!isset($this->params['key_word']) || !$key_word = $this->params['key_word']) {
           return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

       }
        $uid = !empty($this->auth['id']) ? (int)$this->auth['id'] : 0;
        $page = !empty($this->params['page']) ? $this->params['page'] : 1;
        $size = !empty($this->params['size']) ? $this->params['size'] : 10;
        //帖子
        $posts = AdminUserPost::getInstance()->where('status', [AdminUserPost::NEW_STATUS_NORMAL, AdminUserPost::NEW_STATUS_REPORTED, AdminUserPost::NEW_STATUS_LOCK], 'in')
            ->where('title', '%' . $key_word . '%', 'like')->getLimit($page, $size);
        $format_posts = FrontService::handPosts($posts->all(null), $this->auth['id']);
        $post_count = $posts->lastQueryResult()->getTotalCount();

        //资讯
        $information = AdminInformation::getInstance()->where('status', AdminInformation::STATUS_NORMAL)->where('title',  '%' . $key_word . '%', 'like')->getLimit($page, $size);
        $format_information = FrontService::handInformation($information->all(null), $this->auth['id']);
        $information_count = $information->lastQueryResult()->getTotalCount();
        //比赛
        list($selectCompetitionIdArr, $interestMatchArr) = AdminUser::getUserShowCompetitionId($uid);
        if ($team = AdminTeam::getInstance()->where('name_zh', '%' . $key_word . '%', 'like')->all()) {
            $team_ids = array_column($team, 'team_id');
            if ($team_ids) {
                $team_ids_str = AppFunc::changeArrToStr($team_ids);
                $matches = AdminMatch::getInstance()->where('home_team_id in ' . $team_ids_str . ' or away_team_id in ' . $team_ids_str)->getLimit($page, $size);
                $match_list = $matches->all(null);

            } else {
                $match_list = [];

            }
            $format_match = FrontService::formatMatchThree($match_list, $uid, $interestMatchArr);
            $match_count = count($format_match);

        } else {
            $format_match = [];
            $match_count = 0;
        }


        //用户
        $users = AdminUser::getInstance()->where('nickname',  '%' . $key_word . '%', 'like')->where('status', [AdminUser::STATUS_NORMAL, AdminUser::STATUS_REPORTED, AdminUser::STATUS_FORBIDDEN], 'in')->getLimit($page, $size);
        if (!$users) {
            $format_users = [];
            $user_count = 0;
        } else {
            $format_users = FrontService::handUser($users->all(null), $this->auth['id']);
            $user_count = $users->lastQueryResult()->getTotalCount();
        }
        $data = [
            'format_posts' => ['data' => $format_posts, 'count' => $post_count],
            'format_matches' => ['data' => $format_match, 'count' => $match_count],
            'information' =>['data' => $format_information, 'count' => $information_count],
            'users' => ['data' => $format_users, 'count' => $user_count]
        ];
        $type = !empty($this->params['type']) ? $this->params['type'] : 1;//1：全部 2帖子 3资讯 4赛事 5用户
        if ($type == 1) {
            $return_data = $data;
        } else if ($type == 2) {
            $return_data = ['data' => $format_posts, 'count' => $post_count];
        } else if ($type == 3) {
            $return_data = ['data' => $format_information, 'count' => $information_count];

        } else if ($type == 4) {
            $return_data = ['data' => $format_match, 'count' => $match_count];

        } else {
            $return_data = ['data' => $format_users, 'count' => $user_count];

        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return_data);



    }

    /**
     * 热搜
     * @Api(name="热搜",path="/api/community/hotSearch",version="3.0")
     * @ApiDescription(value="serverClient for getContentByKeyWord")
     * @Method(allow="{GET}")
     * @ApiSuccess({"code":0,"msg":"ok","data":{"hot_search":[{"id":1,"type":1,"content":"变形金刚大战美猴王"},{"id":1,"type":1,"content":"哥斯拉智斗猪八戒"},{"id":1,"type":1,"content":"国足赢得大力神杯"},{"id":1,"type":1,"content":"张飞怒斩潘金莲"},{"id":1,"type":1,"content":"霸道总裁之魔教教主哦"}],"default_search_content":"欧冠"}})
     */
    public function hotSearch()
    {
        $settings = DbManager::getInstance()->invoke(function ($client){
            $hotSearch = AdminSysSettings::invoke($client)->where('sys_key', [AdminSysSettings::SETTING_HOT_SEARCH, AdminSysSettings::SETTING_HOT_SEARCH_CONTENT], 'in')->all();
            return $hotSearch;
        });
        $res = $content = [];
        foreach ($settings as $setting) {
            if ($setting->sys_key == AdminSysSettings::SETTING_HOT_SEARCH) {
                $res = json_decode($setting->sys_value, true);
            } else if ($setting->sys_key == AdminSysSettings::SETTING_HOT_SEARCH_CONTENT) {
                $content = $setting->sys_value;
            }
        }
        $return = [
            'hot_search' => $res,
            'default_search_content' => $content
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

    }

    /**
     * 我关注的人的帖子列表
     * @Api(name="我关注的人的帖子列表",path="/api/community/myFollowUserPostss",version="3.0")
     * @ApiDescription(value="serverClient for myFollowUserPosts")
     * @Method(allow="{GET}")
     * @Param(name="page",type="int",required="",description="页码")
     * @Param(name="size",type="int",required="",description="每页数")
     * @ApiSuccess({"code":0,"msg":"ok","data":{"data":[],"count":0}})
     */
    public function myFollowUserPosts()
    {
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;

        $followUids = AppFunc::getUserFollowing($this->auth['id']);
        /**
         * var $followUsers AdminUser
         */

        if (!$followUids) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['data' => [], 'count' => 0]);

        }


        $model = AdminUserPost::getInstance()->where('status', AdminUserPost::STATUS_EXAMINE_SUCC)->where('user_id', $followUids, 'in')->field(['id', 'cat_id', 'user_id',  'title', 'imgs', 'created_at', 'hit', 'fabolus_number', 'content', 'respon_number', 'collect_number'])->getLimit($page, $size);

        $list = $model->all(null);
        $total = $model->lastQueryResult()->getTotalCount();
        $datas = FrontService::handPosts($list, $this->auth['id'] ?: 0);
        $data = ['data' => $datas, 'count' => $total];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

    }

    /**
     * 发帖
     * @Api(name="发帖",path="/api/community/postAdd",version="3.0")
     * @ApiDescription(value="serverClient for postAdd")
     * @Method(allow="{POST}")
     * @Param(name="cat_id",type="int",required="",description="分类id")
     * @Param(name="title",type="string",required="",description="标题")
     * @Param(name="content",type="string",required="",description="内容")
     * @Param(name="imgs",type="string",required="",description="配图")
     * @ApiSuccess({"code":0,"msg":"ok","data":{"id":410,"hit":0,"user_id":4,"title":"上课的分内事","cat_id":1,"status":1,"created_at":"2021-01-09 20:45:46","is_refine":0,"respon_number":0,"updated_at":"2021-01-09 20:45:46","fabolus_number":0,"collect_number":0,"content":"dsmlfkmsalknfaksnf;lksndfksa","is_me":true,"cat_name":"","cat_color":"","imgs":[],"user_info":{"id":"4","photo":"http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/77e37f8fe3181d5f.jpg","nickname":"Hdhdh","level":"3","is_offical":"0"},"is_follow":false,"is_collect":false,"lasted_resp":"2021-01-09 20:45:46","is_fabolus":false}})
     */
    public function postAdd()
    {
        if (Cache::get('user_publish_post_' . $this->auth['id'])) {
            return $this->writeJson(Status::CODE_WRONG_LIMIT, Status::$msg[Status::CODE_WRONG_LIMIT]);
        }
        if (!$uid = $this->auth['id']) {
            return $this->writeJson(Status::CODE_LOGIN_ERR, Status::$msg[Status::CODE_LOGIN_ERR]);
        }
        $validate = new Validate();
        $validate->addColumn('cat_id')->required('请先选择分类')->min(1, '请选择分类');
        $validate->addColumn('title')->required('请填写标题')->lengthMin(1, '请填写标题');
        $validate->addColumn('content')->required('请填写内容')->lengthMin(1, '请前些内容');
        if (!$validate->validate($this->params)) {
            return $this->writeJson(Status::CODE_W_PARAM, $validate->getError()->__toString());
        } else if (AppFunc::have_special_char($this->params['title'])) {
            return $this->writeJson(Status::CODE_UNVALID_CODE, Status::$msg[Status::CODE_UNVALID_CODE]);
        }

        $data = $this->params;
        $info = [
            'title' => trim($data['title']),
            'content' => base64_encode(addslashes(htmlspecialchars($data['content']))),
            'cat_id' => (int)$data['cat_id'],
            'user_id' => (int)$this->auth['id'],
            'imgs' => $this->params['imgs']
        ];
        //保存
        if ($this->params['is_save']) {
            $info['status'] = AdminUserPost::NEW_STATUS_SAVE;
            if (!empty($this->params['pid'])) {
                $bool = AdminUserPost::create()->update($info, ['id' => (int)$data['pid']]);
            } else {
                $bool = AdminUserPost::create()->insert($info);
            }
            if ($bool) {
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
            } else {
                return $this->writeJson(Status::CODE_ADD_POST, Status::$msg[Status::CODE_ADD_POST]);

            }

        } else {
            //发布
            $sensitiveTitle = AppFunc::checkSensitive(trim($info['title']));
            $sensitiveContent = AppFunc::checkSensitive(trim($data['content']));
            if ($sensitiveTitle || $sensitiveContent) {
                //发送站内信
                $message = [
                    'title' => '帖子未通过审核',
                    'content' => sprintf('您发布的帖子【%s】包含敏感词【%s】，未发送成功，已移交至草稿箱，请检查修改后再提交', $data['title'], $sensitiveTitle ? $sensitiveTitle : $sensitiveContent),
                    'status' => 0,
                    'user_id' => $this->auth['id'],
                    'type' => 1,
                    'post_id' => (int)$this->params['pid'],

                ];
                AdminMessage::getInstance()->insert($message);
            }

            if ($postId = $this->params['pid']) {
                $info['status'] = AdminUserPost::NEW_STATUS_NORMAL;
                $info['created_at'] = date('Y-m-d H:i:s');
                $boolInsert = AdminUserPost::create()->update($info, ['id' =>$postId]);
                $postInfo = AdminUserPost::create()->where('id', $this->params['pid'])->get();
            } else {
                $boolInsert = AdminUserPost::create()->insert($info);
                $postInfo = AdminUserPost::create()->where('id', $boolInsert)->get();

            }
            //帖子信息
            $formatPost = FrontService::handPosts([$postInfo], $this->auth['id']);
            $returnPost = isset($formatPost[0]) ? $formatPost[0] : [];
            if ($boolInsert) {
                //加积分
                $data['task_id'] = 2;
                $data['user_id'] = $uid;
                \EasySwoole\EasySwoole\Task\TaskManager::getInstance()->async(new SerialPointTask($data));
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnPost);
            }

            return $this->writeJson(Status::CODE_ADD_POST, Status::$msg[Status::CODE_ADD_POST]);
        }

    }





    /**
     * 用户基本信息
     * @Api(name="用户基本信息",path="/api/user/userInfo",version="3.0")
     * @ApiDescription(value="serverClient for forgetPass")
     * @Method(allow="{GET}")
     * @Param(name="user_id",type="int",required="",description="用户id")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
            "id": 4,
            "nickname": "Hdhdh",
            "photo": "http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/77e37f8fe3181d5f.jpg",
            "level": 3,
            "point": 1010,
            "is_offical": 0,
            "fans_count": 5,
            "follow_count": 10,
            "is_me": true,
            "is_follow": false,
            "item_total": {
            "post_total": 39,
            "comment_total": 107,
            "information_comment_total": 13
            }
        }
        })
     */
    public function userInfo()
    {
        if (!$uid = $this->params['user_id']) {
            return $this->writeJson(Status::CODE_LOGIN_ERR, Status::$msg[Status::CODE_LOGIN_ERR]);
        }

        $user_info = AdminUser::getInstance()->where('id', $uid)->field(['id', 'nickname', 'photo', 'level', 'point', 'is_offical'])->get();
        $user_info['fans_count'] = count(AppFunc::getUserFans($uid));
        $user_info['follow_count'] = count(AppFunc::getUserFollowing($uid));
        if ($this->auth['id']) {
            $user_info['is_me'] = ($this->auth['id'] == $uid) ? true : false;
            $user_info['is_follow'] = AppFunc::isFollow($this->auth['id'], $uid);
        } else {
            $user_info['is_me'] = false;
            $user_info['is_follow'] = false;
        }

        $total = [
            'post_total' => AdminUserPost::getInstance()->where('user_id', $uid)
                ->where('status', AdminUserPost::NEW_STATUS_NORMAL)->count('id'),
            'comment_total' => AdminPostComment::getInstance()->where('user_id', $uid)->where('status', AdminPostComment::STATUS_DEL, '<>')->count('id'),
            'information_comment_total' => AdminInformationComment::getInstance()->where('user_id', $uid)->where('status', AdminInformationComment::STATUS_DELETE, '<>')->count(),
        ];

        $user_info['item_total'] = $total;
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $user_info);

    }



    /**
     * 我的关注列表
     * @Api(name="我的关注列表",path="/api/user/myFollowings",version="3.0")
     * @ApiDescription(value="serverClient for myFollowings")
     * @Method(allow="{GET}")
     * @Param(name="type",type="int",required="",description="类型 1关注列表 2粉丝列表")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
            "data": [
            {
            "is_follow": true,
            "is_me": false,
            "id": 1,
            "nickname": "夜猫官方",
            "photo": "http://live-broadcast-avatar.oss-cn-hongkong.aliyuncs.com/c9f0f56cdcae3695.jpg",
            "level": 1,
            "is_offical": 1
            }
            ],
            "count": 10
            }
        })
     */
    public function myFollowings()
    {

        if (!$this->params['type']) {
            return $this->writeJson(Status::CODE_W_PARAM, Statuses::$msg[Status::CODE_W_PARAM]);

        }
        $uid = isset($this->params['uid']) ? $this->params['uid'] : $this->auth['id'];
        if (!$uid) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        if ($this->params['type'] == 1) { //关注列表
            $ids = AppFunc::getUserFollowing($uid);
        } else {
            $ids = AppFunc::getUserFans($uid);
        }
        if (!$ids) {
            $users = [];
        } else {
            $users = AdminUser::getInstance()->where('id', $ids, 'in')->field(['id', 'nickname', 'photo', 'level', 'is_offical'])->all();

        }
        $data = [];
        if ($users) {
            foreach ($users as $user) {
                $item['is_follow'] = AppFunc::isFollow($this->auth['id'], $user['id']);
                $item['is_me'] = ($user['id'] == $this->auth['id']) ? true : false;
                $item['id'] = $user['id'];
                $item['nickname'] = $user['nickname'];
                $item['photo'] = $user['photo'];
                $item['level'] = $user['level'];
                $item['is_offical'] = $user['is_offical'];
                $data[] = $item;
            }
        }
        $count = count($data);
        $returnData['data'] = $data;
        $returnData['count'] = $count;
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);

    }


    public function normalProblemList()
    {
        $normal_problems = AdminNormalProblems::getInstance()->where('status', 1)->all();
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $normal_problems);

    }

    /**
     * 个人中心首页
     * @Api(name="个人中心首页",path="/api/community/userFirstPage",version="3.0")
     * @ApiDescription(value="serverClient for userFirstPage")
     * @Method(allow="{GET}")
     * @Param(name="code",type="string",required="",description="验证码")
     * @Param(name="mobile",type="string",required="",description="手机号")
     * @ApiSuccess({"code":0,"msg":"OK","data":null})
     */
    public function userFirstPage()
    {

        $type = !empty($this->params['type']) ? (int)$this->params['type'] : 1; //1发帖 2回帖 3资讯评论
        $mid = (int)$this->auth['id'];
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $uid = !empty($this->params['user_id']) ? (int)$this->params['user_id'] : $mid;
        if (!$uid && !$mid) return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        if ($type == 1) { //发帖
            $model = AdminUserPost::getInstance()->where('user_id', $uid)->where('status', AdminUserPost::SHOW_IN_FRONT, 'in')->getLimit($page, $size);
            $list = $model->all(null);
            $total = $model->lastQueryResult()->getTotalCount();
            $format_post = FrontService::handPosts($list, $this->auth['id']);
            $return_data = ['data' => $format_post, 'count' => $total];
        } else if ($type == 2) {//回帖
            $comment_model = AdminPostComment::getInstance()->where('user_id', $uid)->where('status', AdminPostComment::SHOW_IN_FRONT, 'in')->getAll($page, $size);
            $list= $comment_model->all(null);
            $total = $comment_model->lastQueryResult()->getTotalCount();
            $format_comment = FrontService::handComments($list, $this->auth['id']);
            $return_data = ['data' => $format_comment, 'count' => $total];

        } else if ($type == 3) {
            $information_comment_model = AdminInformationComment::getInstance()->where('user_id', $uid)->where('status', AdminInformationComment::SHOW_IN_FRONT, 'in')->getLimit($page, $size);
            $list = $information_comment_model->all(null);
            $total = $information_comment_model->lastQueryResult()->getTotalCount();
            $format_comment = FrontService::handInformationComment($list, $this->auth['id']);
            $return_data = ['data' => $format_comment, 'count' => $total];

        } else {
            $return_data = ['data' => [], 'count' => 0];

        }


        $is_me = ($uid == $mid) ? true : false;
        $is_follow = AppFunc::isFollow($this->auth['id'], $uid);
        $return_info = ['is_me' => $is_me, 'is_follow' => $is_follow, 'list' => $return_data];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return_info);

    }




}