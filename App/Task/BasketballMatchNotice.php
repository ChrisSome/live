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

    const TYPE_OVER = 1;
    const TYPE_START = 2;
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
        $match_id = $this->taskData['match_id'];
        $match = BasketballMatch::getInstance()->where('match_id', $match_id)->get();
        $score = $this->taskData['score'];
        $match->home_scores = json_encode($score[3]);
        $match->away_scores = json_encode($score[4]);
        $match->status_id = $score[1];
        $match->update();
        //主客队总得分
        $homeTotalScore = $awayTotalScore = 0;
        for ($i = 0; $i <= 4; $i++) {
            $homeTotalScore += $score[3][$i];
            $awayTotalScore += $score[4][$i];
        }
        if ($type == 1) { //比赛结束  推送 + 提示
            $item = $this->taskData['item'];
            $matchTlive = BasketballMatchTlive::getInstance()->where('match_id', $match_id)->get();
            $matchTlive->score = isset($item['scores']) ? json_encode($item['scores']) : '';
            $matchTlive->stats = isset($item['stats']) ? json_encode($item['stats']) : '';
            $matchTlive->tlive = isset($item['tlive']) ? json_encode($item['tlive']) : '';
            $matchTlive->is_stop = 1;
            $matchTlive->update();

            $this->basketballPush($type, $match, $homeTotalScore, $awayTotalScore);
            $this->basketballNotice($type, $match, $homeTotalScore, $awayTotalScore);
            return;
        } else if ($type == 2) { //进球   只做提示
            //只做提示
            $position = $this->taskData['position'];
            $this->basketballNotice($type, $match, $homeTotalScore, $awayTotalScore, $position);
        } else if ($type == 3)  { //比赛开始   提示

            $this->basketballNotice($type, $match, $homeTotalScore, $awayTotalScore);
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
        $competition = $match->getCompetition();
        $competition_name_zh = isset($competition->short_name_zh) ? $competition->short_name_zh : '';

        $users = AdminUser::getInstance()->where('id', $user_ids, 'in')->all();
        $prepare_cid_arr = [];
        $uids = [];
        foreach ($users as $user) {
            $user_setting = $user->userSetting();
            $over = isset(json_decode($user_setting->push, true)['over']) ? json_decode($user_setting->push, true)['over'] : 0;
            if ($over) {
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
                'item_type' => 3
            ];
            $rs = AdminNoticeMatch::getInstance()->insert($insertData);
            $pushInfo['title'] = $title;
            $pushInfo['content'] = $content;
            $pushInfo['payload'] = ['item_id' => $match_id, 'item_type' => 3];
            $pushInfo['notice_id'] = $rs;  //开赛通知
            $batchPush = new BatchSignalPush();
            $batchPush->pushMessageToList($prepare_cid_arr, $pushInfo);
        } else if ($type == 3) { //开始

        }

    }

    //篮球提示
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
                'basic' => AppFunc::getBasicFootballMatch($match_id),
                'position' => $position
            ]
        ];
        $onlineUsers = OnlineUser::getInstance()->table();
        foreach ($onlineUsers as $fd => $onlineUser) {
            if (!$user = OnlineUser::getInstance()->get($fd)) {
                continue;
            } else {
                if (!$user['user_id']) { //未登录
                    $is_interest = false;
                } else {
                    if (!$interest = AdminInterestMatches::getInstance()->where('uid', $user['user_id'])->where('type', AdminInterestMatches::FOOTBALL_TYPE)->get()) {
                        $is_interest = false;
                    } else {
                        if (in_array($match_id, json_decode($interest->match_ids))) {
                            $is_interest = true;
                        } else {
                            $is_interest = false;

                        }
                    }

                }
            }
            $returnData['is_interest'] = $is_interest;
            $connection = $server->connection_info($fd);
            if (is_array($connection) && $connection['websocket_status'] == 3) {  // 用户正常在线时可以进行消息推送
                Log::getInstance()->info('match-notice-4' . $match_id . '-type-' . $type . '-fd-' . $fd);

                $server->push($fd, $tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $returnData));
            }
        }
    }


    function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }
}