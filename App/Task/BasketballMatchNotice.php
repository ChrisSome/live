<?php

namespace App\Task;

use App\Common\AppFunc;
use App\GeTui\BatchSignalPush;
use App\lib\Tool;
use App\Model\AdminInterestMatches;
use App\Model\AdminMatch;
use App\Model\AdminMatchTlive;
use App\Model\AdminNoticeMatch;
use App\Model\AdminUser;
use App\Model\BasketballMatch;
use App\Model\BasketballMatchTlive;
use App\Storage\OnlineUser;
use App\Utility\Log\Log;
use App\WebSocket\WebSocketStatus;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class BasketballMatchNotice  implements TaskInterface
{

    protected $taskData;
    protected $trend_detail;

    protected $user = 'mark9527';
    protected $secret = 'dbfe8d40baa7374d54596ea513d8da96';
    public function __construct($taskData)
    {
        $this->trend_detail = 'https://open.sportnanoapi.com/api/v4/football/match/trend/detail?user=%s&secret=%s&id=%s';
        $this->taskData = $taskData;
    }

    /**
     * @param int $taskId
     * @param int $workerIndex
     * @throws \Exception
     */
    function run(int $taskId, int $workerIndex)
    {

        /**
         * 用户不登录 不推送 会提示
         */
        // TODO: Implement run() method.
        $type = $this->taskData['type'];
        $item = $this->taskData['item'];
        $match_id = $this->taskData['match_id'];
        $matchModel = $this->taskData['matchModel'];
        $score = $item['score'];
        $matchModel->home_scores = json_encode($score[3]);
        $matchModel->away_scores = json_encode($score[4]);
        $matchModel->status_id = $score[1];
        $matchModel->update();
        Log::getInstance()->info('basketball-np-stat-' . $match_id . '-type-' . $type);
        //主客队总得分
        $homeTotalScore = $awayTotalScore = 0;
        for ($i = 0; $i <= 4; $i++) {
            if (!isset($score[3][$i]) || !isset($score[4][$i])) break;
            $homeTotalScore += $score[3][$i];
            $awayTotalScore += $score[4][$i];
        }
        if ($type == 1) { //比赛结束  推送 + 提示
            $item = $this->taskData['item'];
            $columnScore = isset($item['score']) ? json_encode($item['score']) : '';
            $columnStats = isset($item['stats']) ? json_encode($item['stats']) : '';
            $columnTlive = isset($item['tlive']) ? json_encode($item['tlive']) : '';
            $columnPlayers = isset($item['players']) ? json_encode($item['players']) : '';
            if (!$matchTlive = BasketballMatchTlive::getInstance()->where('match_id', $match_id)->get()) {
                $match_res = Tool::getInstance()->postApi(sprintf($this->trend_detail, $this->user, $this->secret, $match_id));
                $match_trend = json_decode($match_res, true);
                if ($match_trend['code'] != 0) {
                    $match_trend_info = [];
                } else {
                    $match_trend_info = $match_trend['results'];
                }
                $insertData = [
                    'match_id' => $match_id,
                    'match_trend' => json_encode($match_trend_info),
                    'score' => $columnScore,
                    'stats' => $columnStats,
                    'tlive' => $columnTlive,
                    'players' => $columnPlayers,
                    'is_stop' => 1
                ];
                BasketballMatchTlive::create()->insert($insertData);
            } else {
                $matchTlive->score = $columnScore;
                $matchTlive->stats = $columnStats;
                $matchTlive->tlive = $columnTlive;
                $matchTlive->players = $columnPlayers;
                $matchTlive->is_stop = 1;
                $matchTlive->update();
            }
            $this->basketballPush($type, $matchModel, $homeTotalScore, $awayTotalScore);
            $this->basketballNotice($type, $matchModel, $homeTotalScore, $awayTotalScore);
            return;
        } else if ($type == 2)  { //比赛开始   提示
            $this->basketballPush($type, $matchModel, $homeTotalScore, $awayTotalScore);
            $this->basketballNotice($type, $matchModel, $homeTotalScore, $awayTotalScore);
            return;
        } else {
            return;
        }


    }

    public  function  basketballPush($type, $match, $home, $away)
    {
        $match_id = $match->match_id;
        $user_ids = AppFunc::getUsersInterestMatch($match_id, 2);
        if (!$user_ids) return;
        $home_name_zh = $match->home_team_name;
        $away_name_zh = $match->away_team_name;
        $competition_name_zh = $match->competition_name;
        $users = AdminUser::getInstance()->where('id', $user_ids, 'in')->all();
        $prepare_cid_arr = [];
        $uids = [];
        foreach ($users as $user) {
            $user_setting = $user->userSetting();
            //{"start":1,"over":1,"only_notice_my_interest":1}
            if (!empty(json_decode($user_setting->basketball_push, true)['over'])) {
                $prepare_cid_arr[] = $user->cid;
                $uids[] = $user->id;
            }
        }
        if (!$prepare_cid_arr || !$uids) {
            return;
        }
        if ($type == 1) { //完赛
            $title = '完赛通知';
            $content = sprintf("%s %s(%s)-%s(%s),比赛结束",  $competition_name_zh, $home_name_zh, $home, $away_name_zh, $away);
            $insertData = [
                'uids' => json_encode($uids),
                'match_id' => $match_id,
                'type' => $type,
                'title' => $title,
                'content' => $content,
                'item_type' => 4
            ];
            $rs = AdminNoticeMatch::getInstance()->insert($insertData);
            $pushInfo['title'] = $title;
            $pushInfo['content'] = $content;
            $pushInfo['payload'] = ['item_id' => $match_id, 'item_type' => 4];
            $pushInfo['notice_id'] = $rs;  //开赛通知
            $batchPush = new BatchSignalPush();
            $batchPush->pushMessageToList($prepare_cid_arr, $pushInfo);
        } else if ($type == 2) { //开始
            $title = '开赛通知';
            $content = sprintf("%s %s(%s)-%s(%s),比赛开始",  $competition_name_zh, $home_name_zh, $home, $away_name_zh, $away);
            $insertData = [
                'uids' => json_encode($uids),
                'match_id' => $match_id,
                'type' => $type,
                'title' => $title,
                'content' => $content,
                'item_type' => 4
            ];
            //足球:1:进球 10 即将开赛 12结束      篮球:1结束 2即将开始
            $rs = AdminNoticeMatch::getInstance()->insert($insertData);
            $pushInfo['title'] = $title;
            $pushInfo['content'] = $content;
            $pushInfo['payload'] = ['item_id' => $match_id, 'item_type' => 4];
            $pushInfo['notice_id'] = $rs;  //开赛通知
            $batchPush = new BatchSignalPush();
            $batchPush->pushMessageToList($prepare_cid_arr, $pushInfo);
        }

    }

    //篮球提示 开始 结束
    public function basketballNotice($type, $match, $homeTotalScore, $awayTotalScore, $position = 0)
    {
        $tool = Tool::getInstance();
        $server = ServerManager::getInstance()->getSwooleServer();
        $match_id = $match->match_id;

        list($home_name_zh, $away_name_zh) = [$match->home_team_name, $match->away_team_name];
        $returnData = [
            'event' => 'basketball_match_notice',
            'type' => $type,
            'match_id' => $match_id,
            'contents' => [
                'home' => $homeTotalScore,
                'away' => $awayTotalScore,
                'home_name_zh' => $home_name_zh,
                'away_name_zh' => $away_name_zh,
                'match_id' => $match_id,
                'basic' => AppFunc::getBasicBasketballMatch($match_id),
                'position' => $position
            ]
        ];
        $onlineUsers = OnlineUser::getInstance()->table();
        foreach ($onlineUsers as $fd => $onlineUser) {
            if (!$onlineUser['user_id']) { //未登录
                $is_interest = false;
            } else {
                if (!$interest = AdminInterestMatches::getInstance()->where('uid', $onlineUser['user_id'])->where('type', AdminInterestMatches::BASKETBALL_TYPE)->get()) {
                    $is_interest = false;
                } else {
                    if (in_array($match_id, json_decode($interest->match_ids))) {
                        $is_interest = true;
                    } else {
                        $is_interest = false;

                    }
                }
            }
            $returnData['is_interest'] = $is_interest;
            $connection = $server->connection_info($fd);
            if (is_array($connection) && $connection['websocket_status'] == 3) {  // 用户正常在线时可以进行消息推送
                Log::getInstance()->info('basketball-notice-' . $match_id . '-type-' . $type);
                $server->push($fd, $tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $returnData));
            }
        }
    }


    function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }
}