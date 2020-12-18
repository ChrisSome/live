<?php

namespace App\HttpController\User;

use App\Base\FrontUserController;
use App\Common\AppFunc;
use App\lib\FrontService;
use App\lib\PasswordTool;
use App\Model\AdminInformation;
use App\Model\AdminInformationComment;
use App\Model\AdminMessage;
use App\Model\AdminPostComment;
use App\Model\AdminUser;
use App\Model\AdminUserFeedBack;
use App\Model\AdminUserFoulCenter;
use App\Model\AdminUserOperate;
use App\Model\AdminUserPhonecode;
use App\Model\AdminUserPost;
use App\Model\AdminUserSerialPoint;
use App\Model\AdminUserSetting;
use App\Model\ChatHistory;
use App\Task\SerialPointTask;
use App\Utility\Log\Log;
use App\Utility\Message\Status;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\DbManager;
use EasySwoole\Validate\Validate;

/**
 * 用户个人中心
 * Class UserCenter
 * @package App\HttpController\User
 */
class UserCenter   extends FrontUserController{

    public $needCheckToken = true;
    public $isCheckSign = false;
    /**
     * 个人中心首页
     */
    public function UserCenter()
    {

        $uid = $this->auth['id'];

        $user_info = AdminUser::getInstance()->where('id', $uid)->field(['id', 'nickname', 'photo', 'level', 'is_offical'])->get();
        //我的粉丝数
        $fansCount = count(AppFunc::getUserFans($uid));


        //我的关注数
        $followCount = count(AppFunc::getUserFollowing($uid));

        //我的获赞数

        $fabolus_number = AdminUserOperate::getInstance()->where('author_id', $this->auth['id'])->where('type', 1)->count();
        $data = [
            'user_info' => $user_info,
            'fans_count' => AppFunc::changeToWan($fansCount, ''),
            'follow_count' => AppFunc::changeToWan($followCount, ''),
            'fabolus_count' => AppFunc::changeToWan($fabolus_number, ''),
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);


    }

    /**
     * 收藏夹
     * @return bool
     */
    public function userBookMark()
    {

        $uid = $this->auth['id'];
        if (!$uid) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $key_word = $this->params['key_word'];
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $type = $this->params['type'] ?: 1;
        if ($type == 1) {

            if ($key_word) {
                $queryBuild = new QueryBuilder();
                $queryBuild->raw("select i.id, i.title, i.content, i.user_id, i.fabolus_number, i.`collect_number`, i.respon_number, i.created_at, i.status from admin_user_operates o inner join `admin_user_posts` i on o.item_id=i.id  where o.item_type=1 and i.status in (1, 2, 6)  and o.user_id=? and o.type=2 and i.title like '%" . $key_word . "%'",[$uid, $key_word]);
                $data = DbManager::getInstance()->query($queryBuild, true, 'default')->toArray();
                $queryBuild->raw("select count(*) total from admin_user_operates o inner join `admin_user_posts` i on o.item_id=i.id  where o.item_type=1  and o.user_id=? and o.type=2 and i.title like '%" . $key_word . "%'",[$uid, $key_word]);
                $count = DbManager::getInstance()->query($queryBuild, true, 'default')->toArray();
                $total = $count['result'][0]['total'];
                if ($data['result']) {
                    foreach ($data['result'] as $pk => $post) {
                        $user = AdminUser::getInstance()->where('id', $post['user_id'])->field(['id', 'photo', 'nickname', 'level', 'is_offical'])->get()->toArray();
                        $data['result'][$pk]['user_info'] = $user;
                    }
                }
                $return_data = ['list' => $data['result'], 'total' => $total];

            } else {
                $operate = AdminUserOperate::getInstance()->where('user_id', $uid)->where('item_type', 1)->where('type', AdminUserOperate::TYPE_BOOK_MARK)->getLimit($page, $size);
                $operate = $operate->getLimit($page, $size);
                $limit = $operate->all(null);
                $total = $operate->lastQueryResult()->getTotalCount();
                $post_ids = array_column($limit, 'item_id');
                if ($limit) {
                    $posts = AdminUserPost::getInstance()->where('id', $post_ids, 'in')->all();
                    $format_posts = FrontService::handPosts($posts, $this->auth['id']);
                } else {
                    $format_posts = [];
                }

                $return_data = ['list' => $format_posts, 'count' => $total];
            }

        } else if ($type == 2) {//资讯
            if ($key_word) {


                $queryBuild = new QueryBuilder();
                $queryBuild->raw("select i.id, i.title, i.content, i.user_id, i.fabolus_number, i.`collect_number`, i.respon_number, i.created_at, i.status from admin_user_operates o inner join `admin_information` i on o.item_id=i.id  where o.item_type=3  and o.user_id=? and o.type=2 and i.title like '%" . $key_word . "%'",[$uid, $key_word]);
                $data = DbManager::getInstance()->query($queryBuild, true, 'default')->toArray();

                $queryBuild->raw("select count(*) total from admin_user_operates o inner join `admin_information` i on o.item_id=i.id  where o.item_type=3  and o.user_id=? and o.type=2 and i.title like '%" . $key_word . "%'",[$uid, $key_word]);
                $count = DbManager::getInstance()->query($queryBuild, true, 'default')->toArray();
                $total = $count['result'][0]['total'];

                if ($data['result']) {
                    foreach ($data['result'] as $ik => $information) {
                        $user = AdminUser::getInstance()->where('id', $information['user_id'])->field(['id', 'photo', 'nickname', 'level', 'is_offical'])->get()->toArray();
                        $data['result'][$ik]['user_info'] = $user;
                    }
                }
                $return_data = ['list' => $data['result'], 'total' => $total];

            } else {

                $operate = AdminUserOperate::getInstance()->where('user_id', $uid)->where('item_type', 3)->where('type', AdminUserOperate::TYPE_BOOK_MARK)->getLimit($page, $size);
                $operate = $operate->getLimit($page, $size);
                $limit = $operate->all(null);

                $total = $operate->lastQueryResult()->getTotalCount();

                $information_ids = array_column($limit, 'item_id');
                if ($information_ids) {
                    $informations= AdminInformation::getInstance()->where('id', $information_ids, 'in')->all();
                    $format_informations = FrontService::handInformation($informations, $this->auth['id']);

                } else {
                    $format_informations = [];
                }

                $return_data = ['list' => $format_informations, 'count' => $total];
            }

        } else {
            $return_data = [];
        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return_data);


    }


    /**
     * 草稿箱列表
     * @return bool
     */
    public function drafts()
    {

        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 20;

        $model = AdminUserPost::getInstance()->where('status', AdminUserPost::NEW_STATUS_SAVE)->where('user_id', $this->auth['id'])->getLimit($page, $size);

        $list = $model->all(null);
        $count = $model->lastQueryResult()->getTotalCount();
        $returnData = ['data' => $list, 'count' => $count];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);
    }



    /**
     * 用户资料编辑
     * @return bool
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function editUser()
    {

        $params = $this->params;
        $uid = $this->auth['id'];
        $type = $this->params['type'];
        $validate = new Validate();
        $update_data = [];

        if (!$user = AdminUser::getInstance()->where('id', $uid)->get()) {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

        }
        if (isset($params['nickname']) && $type == 1) {
            $isExists = AdminUser::create()->where('nickname', $this->params['nickname'])
                ->where('id', $this->auth['id'], '<>')
                ->count();

            if ($isExists) {
                return $this->writeJson(Status::CODE_USER_DATA_EXIST, Status::$msg[Status::CODE_USER_DATA_EXIST]);
            }
            $validate->addColumn('nickname', '申请昵称')->required()->lengthMax(32)->lengthMin(4);
            $update_data = ['nickname' => $params['nickname']];
        }
        if (isset($params['photo']) && $type == 2) {
            $validate->addColumn('photo', '申请头像')->required()->lengthMax(128);
            $update_data = ['photo' => $params['photo']];

        }

        if (isset($params['old_password']) && $type == 3 && isset($params['new_password'])) {
            $password = $this->params['new_password'];
            $res = preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,12}$/', $password);
            if (!$res) {
                return $this->writeJson(Status::CODE_W_FORMAT_PASS, Status::$msg[Status::CODE_W_FORMAT_PASS]);
            }
            $user = AdminUser::getInstance()->where('id', $this->auth['id'])->get();
            if (!PasswordTool::getInstance()->checkPassword($params['old_password'], $user->password_hash)) {
                return $this->writeJson(Status::CODE_W_FORMAT_PASS, '旧密码输入错误');

            }

            $password_hash = PasswordTool::getInstance()->generatePassword($password);
            $update_data = ['password_hash' => $password_hash];

        }

        if (isset($params['mobile']) && $type == 4) {
            if (AdminUser::getInstance()->where('mobile', $params['mobile'])->get()) {
                return $this->writeJson(Status::CODE_PHONE_EXIST, Status::$msg[Status::CODE_PHONE_EXIST]);

            }
            if(!preg_match("/^1[3456789]\d{9}$/", $params['mobile'])) {
                return $this->writeJson(Status::CODE_W_PHONE, Status::$msg[Status::CODE_W_PHONE]);

            }
            $update_data = ['mobile' => $params['mobile']];

        }

        if (!isset($update_data)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

        if (AdminUser::getInstance()->update($update_data, ['id' => $uid])) {
            if ($code = AdminUserPhonecode::getInstance()->where('mobile', $this->params['mobile'])->where('code', $this->params['code'])->get()) {
                $code->status = AdminUserPhonecode::STATUS_USED;
                $code->update();
            }

            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

        } else {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

        }


    }



    /**
     * 正确  消息中心
     * @return bool
     */
    public function messageCenter()
    {
        $type = !empty($this->params['type']) ? $this->params['type'] : 0;
        $uid = $this->auth['id'];
        $page = isset($this->params['page']) ? $this->params['page'] : 1;
        $size = isset($this->params['size']) ? $this->params['size'] : 10;



        if (!$type) {
            $sys_un_read_count = AdminMessage::getInstance()->where('user_id', $uid)->where('type', 1)

                ->where('status', AdminMessage::STATUS_UNREAD)
                ->count();

            $fabolus_un_read_count = AdminMessage::getInstance()->where('user_id', $uid)->where('type', 2)
                ->where('status', AdminMessage::STATUS_UNREAD)
                ->count();

            $comment_un_read_count = AdminMessage::getInstance()->where('user_id', $uid)->where('type', 3)
                ->where('status', AdminMessage::STATUS_UNREAD)
                ->count();

            $interest_un_read_count = AdminMessage::getInstance()->where('user_id', $uid)->where('type', 4)
                ->where('status', AdminMessage::STATUS_UNREAD)
                ->count();

            //首条通知
            $last_sys_message = AdminMessage::getInstance()->where('status', AdminMessage::STATUS_DEL, '<>')
                ->where('type', 1)
                ->where('user_id', $uid)
                ->field(['id', 'content', 'created_at'])
                ->order('created_at', 'DESC')
                ->limit(1)->get();

            $data = [
                'sys_un_read_count' => $sys_un_read_count,  //系统消息未读数
                'fabolus_un_read_count' => $fabolus_un_read_count,//点赞未读
                'comment_un_read_count' => $comment_un_read_count,//评论回复未读
                'interest_un_read_count' => $interest_un_read_count,//关注未读
                'last_sys_message' => isset($last_sys_message) ? $last_sys_message : []
            ];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

        } else if ($type == 1) {
            //系统消息
            $page = $this->params['page'] ?: 1;
            $size = $this->params['size'] ?: 10;

            //我的通知
            $model = AdminMessage::getInstance()->where('status', AdminMessage::STATUS_DEL, '<>')
                ->where('type', 1)
                ->where('user_id', $uid)->getLimit($page, $size);
            $list = $model->all(null);
            $total = $model->lastQueryResult()->getTotalCount();
            //系统消息未读
            $format_data = [];
            foreach ($list as $item) {
                $post = AdminUserPost::getInstance()->where('id', $item['item_id'])->get();
                $data['message_id'] = $item['id'];
                $data['created_at'] = $item['created_at'];
                $data['post_info'] = $post ? ['id' => $post->id, 'title' => $post->title, 'created_at' => $post->created_at] : [];
                $data['content'] = $item['content'];
                $data['title'] = $item['title'];
                $data['status'] = $item['status'];
                $format_data[] = $data;
                unset($data);
            }

            $formatData = ['data' => $format_data, 'count' => $total];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $formatData);
        } else if ($type == 2) {
            $model = AdminMessage::getInstance()->where('user_id', $uid)->where('type', 2)->where('status', AdminMessage::STATUS_DEL, '<>')->getLimit($page, $size);
            $list = $model->all(null);
            $total = $model->lastQueryResult()->getTotalCount();

            $format_data = [];

            foreach ($list as $item) {
                $data['item_type'] = $item['item_type'];
                $data['created_at'] = $item['created_at'];
                $data['status'] = $item['status'];
                $data['message_id'] = $item['id'];
                $user_info = AdminUser::getInstance()->where('id', $item['did_user_id'])->field(['id', 'nickname', 'photo', 'level', 'is_offical'])->get();
                $data['user_info'] = $user_info ? $user_info : [];

                if ($item['item_type'] == 1) { //赞我的帖子
                    $post = AdminUserPost::getInstance()->where('id', $item['item_id'])->get();
                    $data['post_info'] = $post ? ['id' => $post->id, 'title' => $post->title, 'content' => $post->content] : [];
                } else if ($item['item_type'] == 2) { //赞帖子回复
                    $post_comment = AdminPostComment::getInstance()->where('id', $item['item_id'])->get();

                    if ($post_comment) {
                        $post = $post_comment->postInfo();
                        $data['post_comment_info'] = $post_comment ? ['id' => $post_comment->id, 'content' => $post_comment->content] : [];
                        $data['post_info'] = ['id' => $post->id, 'title' => $post->title, 'content' => $post->content];
                    } else {
                        $data['post_comment_info'] = [];
                        $data['post_info'] = [];
                    }

                } else if ($item['item_type'] == 4) { //赞资讯回复
                    $information_commnet = AdminInformationComment::getInstance()->where('id', $item['item_id'])->get();
                    $information = $information_commnet->getInformation();
                    $data['information_comment_info'] = $information_commnet ? ['id' => $information_commnet->id, 'content' => mb_substr($information_commnet->content, 0, 20)] : [];
                    $data['information_info'] = $information ? ['id' => $information->id, 'title' => $information->title, 'content' => mb_substr($information->content, 0, 20)] : [];
                } else {
                    continue;
                }
                $format_data[] = $data;
                unset($data);
            }


            $formatData = ['data' => $format_data, 'count' => $total];

            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $formatData);

        } else if ($type == 3) { //评论与回复
            $model = AdminMessage::getInstance()->where('user_id', $uid)->where('type', 3)->where('status', AdminMessage::STATUS_DEL, '<>')->getLimit($page, $size);
            $list = $model->all(null);
            $total = $model->lastQueryResult()->getTotalCount();

            $format_data = [];
            foreach ($list as $item) {


                if ($item['item_type'] == 1) {//帖子
                    $post_comment = AdminPostComment::getInstance()->where('id', $item['item_id'])->get();
                    $post = $post_comment->postInfo();
                    $data['item_type'] = $item['item_type'];
                    $data['user_info'] = $post_comment->uInfo();
                    $data['post_comment_info'] = $post_comment ? ['id' => $post_comment->id, 'content' => $post_comment->content] : [];
                    $data['post_info'] = $post ? ['id' => $post->id, 'title' => $post->title, 'content' => $post->content] : [];
                    $data['status'] = $item['status'];

                } else if ($item['item_type'] == 2) { //帖子评论
                    $post_comment = AdminPostComment::getInstance()->where('id', $item['item_id'])->get();
                    $post = $post_comment->postInfo();
                    $data['item_type'] = $item['item_type'];
                    $data['parent_comment_info'] = $post_comment->getParentContent();
                    $data['post_comment_info'] = $post_comment ? ['id' => $post_comment->id, 'content' => $post_comment->content] : [];
                    $data['post_info'] = $post ? ['id' => $post->id, 'title' => $post->title, 'content' => mb_substr($post->content, 0, 30)] : [];
                    $data['user_info'] = $post_comment->uInfo();
                    $data['status'] = $item['status'];

                } else if ($item['item_type'] == 4) { //资讯回复

                    $information_comment = AdminInformationComment::getInstance()->where('id', $item['item_id'])->get();
                    $information = $information_comment->getInformation();
                    $data['information_comment_info'] = $information_comment ? ['id' => $information_comment->id, 'content' => $information_comment->content] : [];
                    $data['information_info'] = $information ? ['id' => $information->id, 'title' => $information->title, 'content' => mb_substr($information->content, 0, 30)] : [];
                    $data['item_type'] = $item['item_type'];
                    $data['status'] = $item['status'];
                    $data['user_info'] = $information_comment->getUserInfo();
                } else {
                    continue;
                }
                $data['message_id'] = $item['id'];
                $data['created_at'] = $item['created_at'];

                $format_data[] = $data;
                unset($data);
            }
            $formatData = ['data' => $format_data, 'count' => $total];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $formatData);
        } else if ($type == 4) {//用户关注我
            $model = AdminMessage::getInstance()->where('user_id', $uid)->where('type', 4)->where('status', AdminMessage::STATUS_DEL, '<>')->getLimit($page, $size);
            $list = $model->all(null);
            $total = $model->lastQueryResult()->getTotalCount();
            $format_data = [];
            foreach ($list as $item) {
                $user = AdminUser::getInstance()->where('id', $item['did_user_id'])->get();
                $data['message_id'] = $item['id'];
                $data['created_at'] = $item['created_at'];
                $data['user_info'] = $user ? ['id' => $user->id, 'nickname' => $user->nickname, 'photo' => $user->photo] : [];
                $data['status'] = $item['status'];
                $format_data[] = $data;
            }

            $formatData = ['data' => $format_data, 'count' => $total];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $formatData);
        } else {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
    }

    /**
     * 读消息
     * @return bool
     */
    public function readMessage()
    {

        $type = $this->params['type'];
        if ($type == 1) {
            $message_id = $this->params['message_id'];

            if (!$message = AdminMessage::getInstance()->where('id', $message_id)->get()) {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
            } else {
                $message->status = AdminMessage::STATUS_READ;
                $message->update();
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
            }
        } else if ($type == 2) {
            AdminMessage::getInstance()->update(['status'=>AdminMessage::STATUS_READ], ['user_id' => $this->auth['id']]);
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

        }

    }


    /**
     * 回复我的
     * @return bool
     */
    public function commentsToMe()
    {
        //评论回复我的
        $post_comments = AdminPostComment::getInstance()->field(['id', 'content', 'post_id', 'created_at', 'top_comment_id', 'user_id', 'parent_id'])
            ->where('t_u_id', $this->auth['id'])
            ->where('status', AdminPostComment::STATUS_DEL, '<>')
            ->all();
        foreach ($post_comments as $post_comment) {
            $user = AdminUser::getInstance()->where('id', $post_comment['user_id'])->get();
            $data['comment_id'] = $post_comment['id'];
            $data['comment_content'] = $post_comment['content'];
            $data['father_comment_content'] = isset($post_comment->getParentContent()->content) ? $post_comment->getParentContent()->content : '';
            $data['post_title'] = AdminUserPost::getInstance()->find($post_comment['post_id'])->title;
            $data['post_id'] = $post_comment['post_id'];
            $data['created_at'] = $post_comment['created_at'];
            $data['top_comment_id'] = $post_comment['top_comment_id'];
            $data['comment_type'] = 1;
            $data['user_info'] = ['user_id' => $user['id'], 'nickname' => $user['nickname'], 'photo' => $user['photo']];
            $format_post_comment[] = $data;
            unset($data);

        }
        //回复我的资讯评论
        $uid = $this->auth['id'];
        $information_comments = AdminInformationComment::getInstance()->where('t_u_id', $uid)->where('status', AdminInformationComment::STATUS_DELETE, '<>')->all();
        foreach ($information_comments as $information_comment) {
            $user = AdminUser::getInstance()->where('id', $information_comment['user_id'])->get();
            $data['information_id'] = $information_comment['id'];
            $data['comment_content'] = $information_comment['content'];
            $data['father_comment_content'] = $information_comment->getParentComment()->content;
            $data['information_id'] = $information_comment['information_id'];
            $data['information_title'] = AdminInformation::getInstance()->where('id', $information_comment['information_id'])->get()->title;
            $data['created_at'] = $information_comment['created_at'];
            $data['top_comment_id'] = $information_comment['top_comment_id'];
            $data['comment_type'] = 2;
            $data['user_info'] = ['user_id' => $user['id'], 'nickname' => $user['nickname'], 'photo' => $user['photo']];
            $format_information_comment[] = $data;
            unset($data);
        }

        $comments = array_merge($format_post_comment, $format_information_comment);
        $comment_created_at = array_column($comments, 'created_at');
        array_multisort($comment_created_at,SORT_DESC,$comments);
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $comments);
    }



    /**
     * 用户设置
     * @return bool
     */
    public function userSetting()
    {
        Log::getInstance()->info('params-' . json_encode($this->params));
        if (!$type = $this->params['type']) { //1notice 2push 3private
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        if ($this->request()->getMethod() == 'GET') {
            if (!$setting = AdminUserSetting::getInstance()->where('user_id', $this->auth['id'])->get()) {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

            }
            if ($type == 1) {
                $data = json_decode($setting->notice, true);
            } else if ($type == 2) {
                $data = json_decode($setting->push, true);
            } else if ($type == 3) {
                $data = json_decode($setting->private, true);
            } else {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

            }

            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

        } else {
            if ($type == 1) {
                $decode = json_decode($this->params['notice'], true);
                if (!isset($decode['start']) || !isset($decode['goal']) || !isset($decode['over']) || !isset($decode['only_notice_my_interest'])) {
                    return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
                }
                $column = 'notice';
                $data = $this->params['notice'];//start goal over only_notice_my_interest
            } else if ($type == 2) {
                $decode = json_decode($this->params['push'], true);
                if (!isset($decode['start']) || !isset($decode['goal']) || !isset($decode['over']) || !isset($decode['open_push']) || !isset($decode['information'])) {
                    return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
                }
                $column = 'push';
                $data = $this->params['push'];//start goal over
            } else if ($type == 3) {
                $decode = json_decode($this->params['private'], true);
                if (!isset($decode['see_my_post']) || !isset($decode['see_my_post_comment']) || !isset($decode['see_my_information_comment'])) {
                    return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

                }
                $column = 'private';
                $data = $this->params['private'];//see_my_post(1所有 2我关注的 3我的粉丝 4仅自己)  see_my_post_comment(1所有 2我关注的 3我的粉丝 4仅自己) see_my_information_comment(1所有 2我关注的 3我的粉丝 4仅自己)
            }  else {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

            }

            if (!$user_setting = AdminUserSetting::getInstance()->where('user_id', $this->auth['id'])->get()) {

                AdminUserSetting::getInstance()->insert(['user_id' => $this->auth['id'], $column=>$data]);
            } else {
                AdminUserSetting::getInstance()->update([$column=>$data], ['user_id' => $this->auth['id']]);
            }
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);


        }
    }

    /**
     * 修改密码
     * @return bool
     * @throws \Exception
     */
    public function changePassword()
    {
        if (!isset($this->params['new_pass'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $password = $this->params['new_pass'];
        $res = preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,12}$/', $password);
        if (!$res) {
            return $this->writeJson(Status::CODE_W_FORMAT_PASS, Status::$msg[Status::CODE_W_FORMAT_PASS]);
        }
        $phoneCode = AdminUserPhonecode::getInstance()->getLastCodeByMobile($this->params['mobile']);

//        if (!$phoneCode || $phoneCode->status != 0 || $phoneCode->code != $this->params['phone_code']) {
//
//            return $this->writeJson(Status::CODE_W_PHONE_CODE, Status::$msg[Status::CODE_W_PHONE_CODE]);
//
//        }
        $user = AdminUser::getInstance()->find($this->auth['id']);
        $password_hash = PasswordTool::getInstance()->generatePassword($password);

        $user->password_hash = $password_hash;
        if ($user->update()) {

            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

        } else {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

        }

    }


    /**
     * 用户被点赞列表 包括帖子与评论
     */

    public function myFabolusInfo()
    {

        $params = $this->params;
        $valitor = new Validate();

        $valitor->addColumn('type')->required()->inArray(["1", "2", "3", "4", "5" , "6"]);
        $valitor->addColumn('item_type')->required()->inArray([1,2,4]); //1帖子 2帖子评论 4资讯评论
        if (!$valitor->validate($params)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $uid = $this->auth['id'];
        //帖子
        $posts = AdminUserOperate::getInstance()->func(function ($builder) use($uid){
            $builder->raw('select o.created_at, o.item_type, o.user_id, p.id, p.title from `admin_user_operates` o left join `admin_user_posts` p on o.author_id=p.user_id where o.item_id=p.id and o.type=? and o.item_type=?   and o.author_id=? ',[1, 1, $uid]);
            return true;
        });
        if ($posts) {
            foreach ($posts as $k=>$post) {
                $user = AdminUser::getInstance()->find($post['user_id']);
                $posts[$k]['user_info'] = ['id' => $user->id, 'nickname' => $user->nickname, 'photo' => $user->photo];
            }
        }
        //帖子评论
        $post_comments = AdminUserOperate::getInstance()->func(function ($builder) use($uid){
            $builder->raw('select m.*, o.user_id, o.created_at, o.item_type from `admin_user_operates` o left join (select c.id, c.content, p.title from `admin_user_post_comments` c left join `admin_user_posts` p on  c.post_id=p.id) m on o.item_id=m.id where o.type=? and o.item_type=? and o.author_id=?',[1, 2, $uid]);
            return true;
        });
        if ($post_comments) {
            foreach ($post_comments as $kc=>$comment) {
                $user = AdminUser::getInstance()->find($comment['user_id']);

                $post_comments[$kc]['user_info'] = ['id' => $user->id, 'nickname' => $user->nickname, 'photo' => $user->nickname];
            }
        }

        //资讯评论
        $information_comments = AdminUserOperate::getInstance()->func(function ($builder) use($uid){
            $builder->raw('select m.*, o.user_id, o.created_at, o.item_type from `admin_user_operates` o left join (select c.id, c.content, i.title from `admin_information_comments` c left join `admin_information` i on  c.information_id=i.id) m on o.item_id=m.id where o.type=? and o.item_type=? and o.author_id=?',[1, 4, $uid]);
            return true;
        });

        if ($information_comments) {
            foreach ($information_comments as $ic=>$icomment) {
                $user = AdminUser::getInstance()->find($icomment['user_id']);

                $information_comments[$kc]['user_info'] = ['id' => $user->id, 'nickname' => $user->nickname, 'photo' => $user->nickname];
            }
        }
        $result = array_merge($posts, $post_comments, $information_comments);
        $creates_at = array_column($result, 'created_at');
        array_multisort($creates_at, SORT_DESC, $result);



        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $result);
    }

    public function foulCenter()
    {
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $res = AdminUserFoulCenter::getInstance()->field(['id', 'reason', 'info', 'created_at', 'item_type', 'item_id', 'item_punish_type', 'user_punish_type'])
            ->where('user_id', $this->auth['id'])->order('created_at', 'DESC')
            ->limit(($page - 1) * $size, $size)->withTotalCount();
        $list = $res->all(null);
        $total = $res->lastQueryResult()->getTotalCount();

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => $list, 'count' => $total]);


    }

    /**
     * 站务中心
     * @return bool
     */
    public function foulCenterOne()
    {
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $operates = AdminUserOperate::getInstance()->where('author_id', $this->auth['id'])->where('type', 3)->where('item_type', [1,2,4,5], 'in')->getLimit($page, $size);
        $list = $operates->all(null);
        $total = $operates->lastQueryResult()->getTotalCount();
        if ($list) {

            foreach ($list as $item) {

                if (!$item['res_item'] && !$item['res_user']) {

                    continue;
                } else {
                    //1帖子 2帖子评论 3资讯 4资讯评论 5直播间发言
                    if ($item['item_type'] == 1) { //帖子
                        if ($post = AdminUserPost::getInstance()->where('id', $item['item_id'])->get()) {
                            $data['item_type'] = 1;
                            $data['item_id'] = $post->id;
                            $data['post_title'] = $post->title;
                            $data['created_at'] = $item->created_at;

                            $data['res_item'] = $item->res_item;
                            $data['res_user'] = $item->res_item;
                            $data['last_time'] = $item->last_time;

                        } else {
                            continue;
                        }
                    } else if ($item['item_type'] == 2) {
                        if ($post_comment = AdminPostComment::getInstance()->where('id', $item['item_id'])->get()) {
                            $data['item_type'] = 2;
                            $data['item_id'] = $post_comment->id;
                            $data['item_content'] = $post_comment->comment;
                            $data['created_at'] = $item->created_at;
                            $data['res_item'] = $item->res_item;
                            $data['res_user'] = $item->res_item;
                            $data['last_time'] = $item->last_time;
                        } else {
                            continue;
                        }

                    } else if ($item['item_type'] == 4) {
                        if ($information_comment = AdminInformationComment::getInstance()->where('id', $item['item_id'])->get()) {
                            $data['item_type'] = 4;
                            $data['item_id'] = $information_comment->id;
                            $data['item_content'] = $information_comment->content;
                            $data['created_at'] = $item->created_at;
                            $data['res_item'] = $item->res_item;
                            $data['res_user'] = $item->res_item;
                            $data['last_time'] = $item->last_time;
                        } else {
                            continue;
                        }
                    } else if ($item['item_type'] == 5) {
                        if ($chat_message = ChatHistory::getInstance()->where('id', $item['item_id'])->get()) {
                            $data['item_type'] = 5;
                            $data['item_id'] = $chat_message->id;
                            $data['item_content'] = $chat_message->comment;
                            $data['created_at'] = $item->created_at;
                            $data['res_item'] = $item->res_item;
                            $data['res_user'] = $item->res_item;
                            $data['last_time'] = $item->last_time;
                        } else {
                            continue;
                        }
                    }
                    $data['id'] = $item['id'];

                    if (!isset($data)) {
                        continue;
                    }
                    if ($item['res_item'] == AdminUserOperate::TYPE_RES_ITEM_DELETE || $item['res_user'] == AdminUserOperate::TYPE_RES_USER_FOBIDDEN || $item['res_user'] == AdminUserOperate::TYPE_RES_USER_BAN) {
                        $datas[] = $data;
                    }
                }
            }
            $return_data = ['list' => $datas, 'count' => $total];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return_data);

        } else {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => [], 'count' => 0]);

        }


    }

    /**
     * 违规记录详情
     */
    public function foulItemInfo()
    {
        $id = $this->params['operate_id'];
        if (!$operate = AdminUserFoulCenter::getInstance()->where('id', $id)->get()) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $data = [];
        if ($operate->item_type == 1) {
            $post = AdminUserPost::getInstance()->where('id', $operate->item_id)->field(['id', 'content'])->get();
            $data = ['item_id' => $id, 'item_type' => 1, 'content' => $post->content, 'title' => $post->title];
        } else if ($operate->item_type == 2) {
            $post_comment = AdminPostComment::getInstance()->where('id', $id)->field(['id', 'content'])->get();
            $data = ['item_id' => $id, 'item_type' => 2, 'content' => $post_comment->content];
        } else if ($operate->item_type == 4) {
            $information_comment = AdminInformationComment::getInstance()->where('id', $id)->field(['id', 'content'])->get();
            $data = ['item_id' => $id, 'item_type' => 3, 'content' => $information_comment->content];
        } else if ($operate->item_type== 5) {
            $chat_message = ChatHistory::getInstance()->where('id', $id)->get();
            $data = ['item_id' => $id, 'item_type' => 5, 'content' => $chat_message->content];
        }
        $data['info'] = $operate->info;
        $data['reason'] = $operate->reason;
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);

    }

    /**
     * 任务列表
     */

    public function getAvailableTask()
    {
        $user_tasks = AdminUserSerialPoint::USER_TASK;
        foreach ($user_tasks as $k => $task) {
            if ($task['status'] != AdminUserSerialPoint::TASK_STATUS_NORMAL) {
                continue;
            }
            $done_times = AdminUserSerialPoint::getInstance()->where('task_id', $task['id'])->where('created_at', date('Y-m-d'))->where('user_id', $this->auth['id'])->count();
            $user_tasks[$k]['done_times'] = $done_times;
        }


        $user_info = AdminUser::getInstance()->field(['id', 'photo', 'level', 'is_offical', 'level', 'point'])->where('id', $this->auth['id'])->get();

        $return = ['user_info' => $user_info, 'task_list' => $user_tasks];
        $return['d_value'] = AppFunc::getPointsToNextLevel($user_info->id);
        $return ['t_value'] = AppFunc::getPointOfLevel($user_info->level);
        if (!$user_info->third_wx_unionid) {
            $return ['special'] = ['id' => 4, 'name' => '分享好友', 'status' => 1, 'times_per_day' => 1, 'icon' =>'http://test.ymtyadmin.com/image/system/2020/10/7775b4a856bcef57.jpg', 'points_per_time' => 200];
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

    }

    /**
     * 做任务加积分，这里只能是每日签到与分享
     * @return bool
     */
    public function userDoTask()
    {
        $task_id = $this->params['task_id'];
        $user_id = $this->auth['id'];
        if (!in_array($task_id, [1, 4])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $user_task_list = AdminUserSerialPoint::USER_TASK;
        if (!$user_task = $user_task_list[$task_id]) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $user_did_task = AdminUserSerialPoint::getInstance()->where('user_id', $this->auth['id'])->where('task_id', $task_id)->where('created_at', date('Y-m-d'))->count();
        if ($user_task['times_per_day'] <= $user_did_task) {
            return $this->writeJson(Status::CODE_TASK_LIMIT, Status::$msg[Status::CODE_TASK_LIMIT]);

        }
        $user = DbManager::getInstance()->invoke(function ($client) use ($task_id, $user_id, $user_task) {
            $intvalModel = AdminUserSerialPoint::invoke($client);
            $intvalModel->task_id = $task_id;
            $intvalModel->user_id = $user_id;
            $intvalModel->point = $user_task['points_per_time'];
            $intvalModel->task_name = $user_task['name'];
            $intvalModel->type = 1;
            $intvalModel->created_at = date('Y-m-d');
            $intvalModel->save();

            $user = AdminUser::invoke($client)->where('id', $user_id)->get();
            $user->point += $user_task['points_per_time'];
            $user->level = AppFunc::getUserLvByPoint($user->point);
            $user->update();
            return $user;
        });
        $user_info = [
            'level' => $user->level,
            'point' => $user->point,
            'd_value' => AppFunc::getPointsToNextLevel($user_id)
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $user_info);



    }

    /**
     * 积分明细
     * @return bool
     */
    public function getPointList()
    {
        $page = $this->params['page'] ?: 1;
        $size = $this->params['size'] ?: 10;
        $model = AdminUserSerialPoint::getInstance()->where('user_id', $this->auth['id'])
            ->field(['id', 'task_name', 'type', 'point', 'created_at'])
            ->getLimit($page, $size);
        $list = $model->all(null);
        $total = $model->lastQueryResult()->getTotalCount();
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => $list, 'total' => $total]);

    }

    /**
     * 删除
     * @return bool
     */
    public function delItem()
    {
        if ((!$type = $this->params['type']) || (!$item_id = $this->params['item_id'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

        $uid = $this->auth['id'];
        if ($type == 1) {//删除帖子
            if (!$post = AdminUserPost::getInstance()->where('id', $item_id)->where('user_id', $uid)->get()) {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
            } else {
                $post->status = AdminUserPost::NEW_STATUS_DELETED;
                $post->update();
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

            }
        } else if ($type == 2) {//帖子评论
            if (!$post_comment = AdminPostComment::getInstance()->where('id', $item_id)->where('user_id', $uid)->get()) {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

            } else {
                $post_comment->status = AdminPostComment::STATUS_DEL;
                $post_comment->update();
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

            }
        } else if ($type == 3) {
            if (!$information_comment = AdminInformationComment::getInstance()->where('id', $item_id)->where('user_id', $uid)->get()) {
                return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

            } else {
                $information_comment = AdminInformationComment::STATUS_DELETE;
                $information_comment->update();
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

            }
        } else {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

    }



    /**
     * 用户反馈
     */
    public function userFeedBack()
    {

        $validator = new Validate();
        $validator->addColumn('content')->required();
        if (!$validator->validate($this->params)) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $data['content'] = addslashes(htmlspecialchars($this->params['content']));
        $data['user_id'] = $this->auth['id'];
        if ($this->params['img']) {
            $data['img'] = $this->params['img'];

        }
        if (AdminUserFeedBack::getInstance()->insert($data)) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

        } else {
            return $this->writeJson(Status::CODE_ERR, '提交失败，请联系客服');

        }

    }



}