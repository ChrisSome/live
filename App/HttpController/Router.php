<?php
namespace App\HttpController;

use EasySwoole\Http\AbstractInterface\AbstractRouter;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Template\Render;
use FastRoute\RouteCollector;

class Router extends AbstractRouter
{
    public function initialize(RouteCollector $routes)
    {
//        // 未找到路由对应的方法
        $this->setMethodNotAllowCallBack(function (Request $request, Response $response) {
            $result = [
                "code" => -1,
                "msg"  => '错误路由',
                "data" => []
            ];
            $response->write(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $response->withHeader('Content-type', 'application/json;charset=utf-8');
            $response->end();
        });


        $routes->addGroup('/api', function (RouteCollector $r) {
            $r->addRoute(['GET'], '/user/login', '/User/Login');
            $r->addRoute(['GET'], '/user/{id:\d+}', '/User/User/test');
            $r->addRoute(['POST'], '/user/upload', '/User/Upload');
            $r->addRoute(['POST'], '/user/ossUpload', '/User/Upload/ossUpload');  //oss上传
            $r->addRoute(['POST'], '/user/info', '/User/User/info');
            $r->addRoute(['POST'], '/user/doLogin', '/User/Login/userLogin'); //登陆接口
            $r->addRoute(['POST'], '/user/wxLogin', '/User/Login/wxLogin'); //微信登陆接口
            $r->addRoute(['POST'], '/user/forgetPass', '/User/Login/forgetPass'); //忘记密码

            $r->addRoute(['POST'], '/user/thirdLogin', '/User/Login/bindWx'); //绑定微信
            $r->addRoute(['GET'], '/user/userSendSmg', '/User/Login/userSendSmg'); //获取验证码接口
            $r->addRoute(['GET'], '/user/checkPhoneCode', '/User/Login/checkPhoneCode'); //检查验证码
            $r->addRoute(['POST'], '/user/logon', '/User/Login/logon'); //注册
            $r->addRoute(['GET'], '/user/logout', '/User/Login/doLogout'); //退出接口


            $r->addRoute(['GET'], '/user/websocket', 'User/WebSocket');


            $r->addRoute(['GET'], '/system/hotreload', '/User/System/hotreload');   //
            $r->addRoute(['GET'], '/system/adImgs', '/User/System/adImgs');   //  启动页后的广告页
            $r->addRoute(['GET'], '/system/advertisement', '/User/System/advertisement');   //  启动页后的广告页


            //用户动作
            $r->addRoute(['POST'], '/user/userFollow', '/User/User/userFollowings'); //关注用户
            $r->addRoute(['POST'], '/information/informationOperate', '/User/User/informationOperate');   //帖子 评论操作
            $r->addRoute(['POST'], '/community/doComment', '/User/User/doComment'); //用户评论
            $r->addRoute(['POST'], '/user/userInterestMatch', '/User/User/userInterestMatch');   //用户关注比赛
            $r->addRoute(['POST'], '/user/interestCompetition', '/User/User/userInterestCompetition');   //  用户关注赛事
            $r->addRoute(['GET'], '/user/unBindWx', '/User/User/unBindWx');   //  用户解绑微信
            $r->addRoute(['GET'], '/system/sensitiveWord', '/User/system/sensitiveWord');   //  敏感词
            $r->addRoute(['GET'], '/user/checkUser', '/User/User/checkUserStatus');   //  检查用户状态


            //社区部分
            $r->addRoute(['GET'], '/community/getContent', '/User/Community/getContent');
            $r->addRoute(['GET'], '/community/getContentByKeyWord', '/User/Community/getContentByKeyWord');//搜索
            $r->addRoute(['GET'], '/community/myFollowUserPosts', '/User/Community/myFollowUserPosts');   //我关注的人的帖子列表
            $r->addRoute(['POST'], '/community/postAdd', '/User/Community/postAdd');   //发帖
            $r->addRoute(['GET'], '/community/hotSearch', '/User/Community/hotSearch');   //热搜榜
            $r->addRoute(['GET'], '/community/detail', '/User/Community/detail'); //帖子详情
            $r->addRoute(['GET'], '/community/getAllChildComments', '/User/Community/getAllChildComments');   //二级评论列表
            $r->addRoute(['GET'], '/community/userFirstPage', '/User/UserCenter/userFirstPage');   //用户详情页
            $r->addRoute(['GET'], '/user/myFollowings', '/User/Community/myFollowings');   //用户关注列表
            $r->addRoute(['GET'], '/community/userInfo', '/User/Community/userInfo');   //用户基本信息
            $r->addRoute(['GET'], '/community/normalProblemList', '/User/Community/normalProblemList');   //常见问题

            //数据脚本
            $r->addRoute(['GET'], '/footBall/getTeamList', '/Match/Crontab/getTeamList');   //球队列表
            $r->addRoute(['GET'], '/footBall/getTodayMatches', '/Match/Crontab/getTodayMatches');   //今日比赛
            $r->addRoute(['GET'], '/footBall/getWeekMatches', '/Match/Crontab/getWeekMatches');   //未来一周比赛
            $r->addRoute(['GET'], '/footBall/getCompetitiones', '/Match/Crontab/getCompetitiones');   //赛事列表
            $r->addRoute(['GET'], '/footBall/getSteam', '/Match/Crontab/getSteam');   //直播源
            $r->addRoute(['GET'], '/footBall/players', '/Match/Crontab/players');   //球员列表
            $r->addRoute(['GET'], '/footBall/clashHistory', '/Match/Crontab/clashHistory');   //获取比赛历史同赔统计数据列表
            $r->addRoute(['GET'], '/footBall/noticeUserMatch', '/Match/Crontab/noticeUserMatch');   //推送用户比赛即将开始 1次/分钟
            $r->addRoute(['GET'], '/footBall/deleteMatch', '/Match/Crontab/deleteMatch');   //取消或者删除的比赛
            $r->addRoute(['GET'], '/footBall/updateYesMatch', '/Match/Crontab/updateYesMatch');   //更新昨天比赛
            $r->addRoute(['GET'], '/footBall/matchTlive', '/Match/Crontab/matchTlive');   //推送

            $r->addRoute(['GET'], '/footBall/updateSeason', '/Match/Crontab/updateSeason');   //更新赛季
            $r->addRoute(['GET'], '/footBall/updatePlayerStat', '/Match/Crontab/updatePlayerStat');   //更新赛季排行
            $r->addRoute(['GET'], '/footBall/playerChangeClubHistory', '/Match/Crontab/playerChangeClubHistory');   //转会记录
            $r->addRoute(['GET'], '/footBall/teamHonor', '/Match/Crontab/teamHonor');   //球队荣誉
            $r->addRoute(['GET'], '/footBall/honorList', '/Match/Crontab/honorList');   //更新赛季
            $r->addRoute(['GET'], '/footBall/allStat', '/Match/Crontab/allStat');   //更新赛季
            $r->addRoute(['GET'], '/footBall/stageList', '/Match/Crontab/stageList');   //更新阶段列表
            $r->addRoute(['GET'], '/footBall/managerList', '/Match/Crontab/managerList');   //更新教练列表
            $r->addRoute(['GET'], '/footBall/getLineUp', '/Match/Crontab/getLineUp');   //阵容列表
            $r->addRoute(['GET'], '/footBall/playerHonorList', '/Match/Crontab/playerHonorList');   //阵容列表
            $r->addRoute(['GET'], '/footBall/matchBroadcast', '/Match/Crontab/matchBroadcast');   //比赛直播
            $r->addRoute(['GET'], '/footBall/competitionRule', '/Match/Crontab/competitionRule');   //赛事赛制
            $r->addRoute(['GET'], '/footBall/updateAlphaMatch', '/Match/Crontab/updateAlphaMatch');
            $r->addRoute(['GET'], '/footBall/seasonAllStatDetail', '/Match/Crontab/seasonAllStatDetail');
            $r->addRoute(['GET'], '/footBall/updateMatchSeason', '/Match/Crontab/updateMatchSeason');   //赛季比赛
            $r->addRoute(['GET'], '/footBall/updateSeasonTeamPlayer', '/Match/Crontab/updateSeasonTeamPlayer');   //赛季比赛
            $r->addRoute(['GET'], '/footBall/updateYesterdayMatch', '/Match/Crontab/updateYesterdayMatch');   //赛季比赛
            $r->addRoute(['GET'], '/footBall/updateMatchTrend', '/Match/Crontab/updateMatchTrend');   //赛季比赛




            //数据中心
            $r->addRoute(['GET'], '/footBall/CategoryCountry', '/Match/DataApi/CategoryCountry');   //国家分类
            $r->addRoute(['GET'], '/footBall/FIFAMaleRank', '/Match/DataApi/FIFAMaleRank');   //FIFA男子排名
            $r->addRoute(['GET'], '/footBall/competitionInfo', '/Match/DataApi/competitionInfo');   //赛事信息
            $r->addRoute(['GET'], '/footBall/getHotCompetition', '/Match/DataApi/getHotCompetition');   //热门赛事
            $r->addRoute(['GET'], '/footBall/getPlayerInfo', '/Match/DataApi/getPlayerInfo');   //球员信息
            $r->addRoute(['GET'], '/footBall/teamInfo', '/Match/DataApi/teamInfo');   //球员信息
            $r->addRoute(['GET'], '/footBall/contentByKeyWord', '/Match/DataApi/contentByKeyWord');   //搜索
            $r->addRoute(['GET'], '/footBall/teamChangeClubHistory', '/Match/DataApi/teamChangeClubHistory');   //转会记录
            $r->addRoute(['GET'], '/footBall/hotSearchCompetition', '/Match/DataApi/hotSearchCompetition');   //热搜赛事
            $r->addRoute(['GET'], '/footBall/getCompetitionByCountry', '/Match/DataApi/getCompetitionByCountry');   //
            $r->addRoute(['GET'], '/footBall/getContinentCompetition', '/Match/DataApi/getContinentCompetition');   //

            //资讯中心
            $r->addRoute(['GET'], '/information/titleBar', '/Match/InformationApi/titleBar');   //顶部
            $r->addRoute(['GET'], '/information/competitionContent', '/Match/InformationApi/competitionContent');   //头条内容
            $r->addRoute(['GET'], '/information/informationInfo', '/Match/InformationApi/informationInfo');   //资讯内容
            $r->addRoute(['POST'], '/information/informationComment', '/Match/InformationApi/informationComment');   //发表评论
            $r->addRoute(['GET'], '/information/informationChildComment', '/Match/InformationApi/informationChildComment');   //二级评论列表
            $r->addRoute(['GET'], '/information/getCategoryInformation', '/Match/InformationApi/getCategoryInformation');   //二级评论列表

            //篮球资讯
            $r->addRoute(['GET'], '/information/basketballInformationTitleBar', '/Match/InformationApi/basketballInformationTitleBar');   //篮球title栏
            $r->addRoute(['GET'], '/information/basketballInformationList', '/Match/InformationApi/basketballInformationList');   //篮球资讯列表



            //个人中心
            $r->addRoute(['GET'], '/user/UserCenter', '/User/UserCenter/UserCenter');   //个人中心
            $r->addRoute(['GET'], '/user/userBookMark', '/User/UserCenter/userBookMark');   //收藏夹
            $r->addRoute(['POST'], '/user/editUser', '/User/UserCenter/editUser'); //用户编辑资料
            $r->addRoute(['GET'], '/user/messageCenter', '/User/UserCenter/messageCenter');   //消息中心
            $r->addRoute(['GET'], '/user/readMessage', '/User/UserCenter/readMessage');   //读消息
            $r->addRoute(['GET'], '/user/userSetting', '/User/UserCenter/userSetting');   //用户设置
            $r->addRoute(['POST'], '/user/userSetting', '/User/UserCenter/userSetting');   //用户设置
            $r->addRoute(['GET'], '/user/basketballSetting', '/User/UserCenter/basketballSetting');   //篮球设置
            $r->addRoute(['POST'], '/user/basketballSetting', '/User/UserCenter/basketballSetting');   //篮球设置
            $r->addRoute(['POST'], '/user/changePassword', '/User/UserCenter/changePassword');   //用户设置
            $r->addRoute(['GET'], '/user/myFabolusInfo', '/User/UserCenter/myFabolusInfo'); //用户被点赞的帖子及评论列表
            $r->addRoute(['GET'], '/user/foulCenter', '/User/UserCenter/foulCenter'); //违规中心
            $r->addRoute(['GET'], '/user/foulCenterOne', '/User/UserCenter/foulCenterOne'); //违规中心
            $r->addRoute(['GET'], '/user/foulItemInfo', '/User/UserCenter/foulItemInfo'); //违规中心
            $r->addRoute(['GET'], '/user/myBlackList', '/User/UserCenter/myBlackList'); //黑名单
            $r->addRoute(['GET'], '/user/addInBlackList', '/User/UserCenter/addInBlackList'); //黑名单
            $r->addRoute(['GET'], '/user/drafts', '/User/UserCenter/drafts'); //草稿箱
            $r->addRoute(['POST'], '/user/delItem', '/User/UserCenter/delItem'); //删除
            $r->addRoute(['GET'], '/user/getAvailableTask', '/User/UserCenter/getAvailableTask');   // 获取每日任务
            $r->addRoute(['POST'], '/user/userDoTask', '/User/UserCenter/userDoTask');   // 签到与分享
            $r->addRoute(['GET'], '/user/getPointList', '/User/UserCenter/getPointList');   // 积分列表
            $r->addRoute(['POST'], '/user/userFeedBack', '/User/UserCenter/userFeedBack');   // 用户反馈



            //赛事
            $r->addRoute(['GET'], '/footBall/competitionList', '/Match/FootballApi/getCompetition');   //赛事列表
            $r->addRoute(['GET'], '/footBall/matchListPlaying', '/Match/FootballApi/matchListPlaying');   //正在进行中比赛列表
            $r->addRoute(['GET'], '/footBall/userInterestMatchList', '/Match/FootballApi/userInterestMatchList');   //用户关注的比赛列表
            $r->addRoute(['GET'], '/footBall/matchSchedule', '/Match/FootballApi/matchSchedule');   //赛程列表
            $r->addRoute(['GET'], '/footBall/matchResult', '/Match/FootballApi/matchResult');   //赛果列表
            $r->addRoute(['GET'], '/footBall/lineUpDetail', '/Match/FootballApi/lineUpDetail');   //阵容详情
            $r->addRoute(['GET'], '/footBall/getClashHistory', '/Match/FootballApi/getClashHistory');   //历史交锋
            $r->addRoute(['GET'], '/footBall/noticeInMatch', '/Match/FootballApi/noticeInMatch');   //直播间公告
            $r->addRoute(['GET'], '/footBall/matchInfo', '/Match/FootballApi/getMatchInfo');   //比赛信息
            $r->addRoute(['GET'], '/footBall/getTodayAllMatch', '/Match/FootballApi/getTodayAllMatch');   //今天所有比赛

            $r->addRoute(['GET'], '/footBall/test', '/Match/FootballApi/test');   //历史交锋


            $r->addRoute(['GET'], '/footBall/fixMatch', '/Match/FootballMatch/fixMatch');   //比赛查询
            $r->addRoute(['GET'], '/footBall/fixSomeDayMatch', '/Match/FootballMatch/fixSomeDayMatch');   //修正某天的比赛



        });


    }
}
