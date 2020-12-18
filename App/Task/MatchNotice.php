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
use App\Storage\OnlineUser;
use App\Utility\Log\Log;
use App\WebSocket\WebSocketStatus;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class MatchNotice  implements TaskInterface
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
        $match = AdminMatch::getInstance()->where('match_id', $match_id)->get();
        $score = $this->taskData['score'];
        $match->home_scores = json_encode($score[2]);
        $match->away_scores = json_encode($score[3]);
        $match->status_id = $score[1];
        $match->update();

        if ($type == 1) { //进球(包含点球)
            $last_incident_goal = $this->taskData['last_incident'];
            list($home, $away) = AppFunc::getFinalScore($score[2], $score[3]);
            $time = $last_incident_goal['time'];
            $position = $last_incident_goal['position'];
            $this->userNotice($type, $match_id, $home, $away, $position, $time);
            $this->userPush($type, $match_id, $home, $away, $position, $time);
        } else if ($type == 3) { //黄牌  只下方提示
            $last_incident = $this->taskData['last_incident'];
            list($home, $away) = AppFunc::getYellowCard($score[2], $score[3]);
            $position = $last_incident['position'];
            $time = $last_incident['time'];
            $this->userNotice($type, $match_id, $home, $away, $position, $time);
        } else if ($type == 4) {//红牌
            list($home, $away) = AppFunc::getRedCard($score[2], $score[3]);
            $last_incident = $this->taskData['last_incident'];
            $position = $last_incident['position'];
            $time = $last_incident['time'];
            $this->userNotice($type, $match_id, $home, $away, $position, $time);

        } else if ($type == 10) { //比赛正式开始

            $this->userNotice($type, $match_id, 0, 0, 0, 0);
        } else if ($type == 12) { //结束通知
            //比赛可能出现0-0的情况，所以不能用last_incident_goal处理
            $item = $this->taskData['item'];
            $time = AppFunc::getPlayingTime($match_id);
            $match_res = Tool::getInstance()->postApi(sprintf($this->trend_detail, 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', $item['id']));
            $match_trend = json_decode($match_res, true);
            if ($match_trend['code'] != 0) {
                $match_trend_info = [];
            } else {
                $match_trend_info = $match_trend['results'];
            }

            if (!AdminMatchTlive::getInstance()->where('match_id', $match_id)->get()) {
//                $match_trend = $this->taskData['match_trend'];
                $data = [
                    'score' => isset($item['score']) ? json_encode($item['score']) : '',
                    'incidents' => isset($item['incidents']) ? json_encode($item['incidents']) : '',
                    'stats' => isset($item['stats']) ? json_encode($item['stats']) : '',
                    'tlive' => isset($item['tlive']) ? json_encode($item['tlive']) : '',
                    'match_trend' => json_encode($match_trend_info),
                    'match_id' => $item['id']
                ];
                AdminMatchTlive::getInstance()->insert($data);
            }
            if (!AppFunc::isInHotCompetition($match->competition_id)) return;
            list($home, $away) = AppFunc::getFinalScore($item['score'][2], $item['score'][3]);
            $this->userNotice($type, $match_id, $home, $away, 0, $time);
            $this->userPush($type, $match_id, $home, $away, $match->competition_id);

        } else {
            return;
        }

    }


    /**
     * 下方提示
     * @param $type
     * @param $match_id
     * @param $home
     * @param $away
     * @param $competition_id
     * @param int $position
     * @param int $time
     */
    public function userNotice($type, $match_id, $home, $away, $position = 0, $time = 0)
    {

        $tool = Tool::getInstance();
        $server = ServerManager::getInstance()->getSwooleServer();
        list($home_name_zh, $away_name_zh) = AppFunc::getMatchTeamName($match_id);
        $returnData = [
            'event' => 'match_notice',
            'type' => $type,
            'match_id' => $match_id,
            'contents' => [
                'home' => $home,
                'away' => $away,
                'home_name_zh' => $home_name_zh,
                'away_name_zh' => $away_name_zh,
                'match_id' => $match_id,
                'position' => $position,
                'time' => $time,
                'basic' => AppFunc::getBasic($match_id)
            ]
        ];

        $start_fd = 0;
        while (true) {
            $conn_list = $server->getClientList($start_fd, 10);
            if ($conn_list===false or count($conn_list) === 0) break;
            $start_fd = end($conn_list);
            foreach ($conn_list as $fd) {

                if (!$user = OnlineUser::getInstance()->get($fd)) {
                    continue;
                } else {
                    if (!AppFunc::isNotice($user['user_id'], $match_id, $type)) {

                    }
                    if (!$user['user_id']) { //未登录
                        $is_interest = false;
                    } else {
                        if (!$interest = AdminInterestMatches::getInstance()->where('uid', $user['user_id'])->get()) {
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


    }

    /**
     * @param $type
     * @param $match_id
     * @param $home
     * @param $away
     * @param $competition_id
     * @param int $position
     * @param int $time
     * @throws \Exception
     */
    public  function  userPush($type, $match_id, $home, $away, $position = 0, $time = 0)
    {
        if (!$match = AdminMatch::getInstance()->where('match_id', $match_id)->get()) {
            return ;
        }
        $user_ids = AppFunc::getUsersInterestMatch($match_id);
        $home_name_zh = $match->homeTeamName()->name_zh;
        $away_name_zh = $match->awayTeamName()->name_zh;
        $competition_name_zh = $match->competitionName()->short_name_zh;
        if (!$user_ids) return;
        $users = AdminUser::getInstance()->where('id', $user_ids, 'in')->all();
        $prepare_cid_arr = [];
        $uids = [];
        if ($type == 12) {//比赛结束
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
            $title = '完赛通知';
            $content = sprintf("%s %s(%s)-%s(%s),比赛结束",  $competition_name_zh, $home_name_zh, $home, $away_name_zh, $away);
            $insertData = [
                'uids' => json_encode($uids),
                'match_id' => $match_id,
                'type' => $type,
                'title' => $title,
                'content' => $content
            ];
            $rs = AdminNoticeMatch::getInstance()->insert($insertData);
            $pushInfo['title'] = $title;
            $pushInfo['content'] = $content;
            $pushInfo['payload'] = ['item_id' => $match_id, 'type' => 1];
            $pushInfo['notice_id'] = $rs;  //开赛通知

            $batchPush = new BatchSignalPush();

            $batchPush->pushMessageToList($prepare_cid_arr, $pushInfo);
        } else if ($type == 1) { //进球
            foreach ($users as $user) {
                $user_setting = $user->userSetting();
                if (isset(json_decode($user_setting->push, true)['goal']) && json_decode($user_setting->push, true)['goal'] == 1) {
                    $prepare_cid_arr[] = $user->cid;
                    $uids[] = $user->id;
                }
            }
            if (!$prepare_cid_arr || !$uids) {
                return;
            }

            $title = '进球通知';
            if ($position == 1) {
                $content = sprintf("%s' %s %s(进球)%s-%s %s", $time, $competition_name_zh, $home_name_zh, $home, $away, $away_name_zh);
            } else if ($position == 2) {
                $content = sprintf("%s' %s %s%s-%s %s(进球)", $time, $competition_name_zh, $home_name_zh, $home, $away, $away_name_zh);
            } else {
                return;
            }

            $insertData = [
                'uids' => json_encode($uids),
                'match_id' => $match->match_id,
                'type' => $type,
                'title' => $title,
                'content' => $content
            ];
            $rs = AdminNoticeMatch::getInstance()->insert($insertData);
            $pushInfo['title'] = $title;
            $pushInfo['content'] = $content;
            $pushInfo['payload'] = ['item_id' => $match_id, 'type' => 1];
            $pushInfo['notice_id'] = $rs;  //开赛通知

            $batchPush = new BatchSignalPush();

            $batchPush->pushMessageToList($prepare_cid_arr, $pushInfo);
        } else if ($type == 10) {  //正式开始不推送 即将开始推送在脚本中跑
            return;
            foreach ($users as $user) {
                $user_setting = $user->userSetting();

                if (isset(json_decode($user_setting->push, true)['start']) && json_decode($user_setting->push, true)['start'] == 1) {
                    $prepare_cid_arr[] = $user->cid;
                    $uids[] = $user->id;
                }

                if (!$prepare_cid_arr || !$uids) {
                    return;
                }

                $info['title'] = '开赛通知';
                $info['type'] = $type;
                $info['content'] = sprintf('您关注的【%s联赛】%s-%s将于15分钟后开始比赛，不要忘了哦', $info['competition_name'], $info['home_name_zh'], $info['away_name_zh']);
                $insertData = [
                    'uids' => json_encode($uids),
                    'match_id' => $match->match_id,
                    'type' => $type,
                    'title' => $info['title'],
                    'content' => $info['content']
                ];
                $rs = AdminNoticeMatch::getInstance()->insert($insertData);
                $info['rs'] = $rs;  //进球通知
                $batchPush = new BatchSignalPush();

                $batchPush->pushMessageToSingleBatch($prepare_cid_arr, $info);
            }
        } else {
            return;
        }




    }

    function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }
}