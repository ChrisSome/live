<?php


namespace App\Process;


use App\Common\AppFunc;
use App\HttpController\User\WebSocket;
use App\lib\Tool;
use App\Model\AdminMatch;
use App\Model\AdminMatchTlive;
use App\Storage\MatchLive;
use App\Task\MatchNotice;
use App\Task\MatchUpdate;
use App\Utility\Log\Log;
use App\WebSocket\WebSocketStatus;
use easySwoole\Cache\Cache;
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Component\Timer;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Task\TaskManager;

class NamiPushTask extends AbstractProcess
{
    protected $taskData;
    private $url = 'https://open.sportnanoapi.com/api/sports/football/match/detail_live?user=%s&secret=%s';
    protected $trend_detail = 'https://open.sportnanoapi.com/api/v4/football/match/trend/detail?user=%s&secret=%s&id=%s'; //获取比赛趋势详情

    private $user = 'mark9527';
    private $secret = 'dbfe8d40baa7374d54596ea513d8da96';

    function run($args)
    {

        Timer::getInstance()->loop(30 * 1000, function () {
            $res = Tool::getInstance()->postApi(sprintf($this->url, $this->user, $this->secret));

            if ($decode = json_decode($res, true)) {
                $match_info = [];
                foreach ($decode as $item) {

                    //无效比赛 跳过
                    if (!$match = AdminMatch::getInstance()->where('match_id', $item['id'])->get()) {
                        continue;
                    }

                    //比赛结束 跳过
                    if (AdminMatchTlive::getInstance()->where('match_id', $item['id'])->get()) {
                        continue;
                    }
                    $status = $item['score'][1];
                    if (!in_array($status, [1, 2, 3, 4, 5, 7, 8])) { //上半场 / 下半场 / 中场 / 加时赛 / 点球决战 / 结束
                        continue;
                    }

                    //比赛结束通知
                    if ($item['score'][1] == 8) { //结束
                        TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'],  'item' => $item,'score' => $item['score'],  'type'=>12]));
                    }

                    //不在热门赛事中  跳过
                    if (!AppFunc::isInHotCompetition($match->competition_id)) {
                        continue;
                    }

                    //比赛趋势
                    $match_res = Tool::getInstance()->postApi(sprintf($this->trend_detail, 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', $item['id']));
                    $match_trend = json_decode($match_res, true);
                    if ($match_trend['code'] != 0) {
                        $match_trend_info = [];
                    } else {
                        $match_trend_info = $match_trend['results'];
                    }
                    //设置比赛进行时间
                    AppFunc::setPlayingTime($item['id'], $item['score']);
                    //比赛开始的通知
                    if ($item['score'][1] == 2 && !Cache::get('match_notice_start:' . $item['id'])) { //开始
                        TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'], 'score' => $item['score'],'item' => $item,  'type'=>10]));
                        Cache::set('match_notice_start:' . $item['id'], 1, 60 * 240);
                    }
                    $matchStats = [];
                    if (isset($item['stats'])) {
                        foreach ($item['stats'] as $ki => $vi) {
                            // 21：射正 22：射偏  23:进攻  24危险进攻 25：控球率
                            if ($vi['type'] == 21 || $vi['type'] == 22 || $vi['type'] == 23 || $vi['type'] == 24 || $vi['type'] == 25) {
                                $matchStats[] = $vi;
                            }

                        }
                        Cache::set('match_stats_' . $item['id'], json_encode($matchStats), 60 * 240);

                    }
                    $corner_count_tlive = [];
                    $match_tlive_count_new = 0;

                    if (isset($item['tlive'])) {
                        $match_tlive_count_old = Cache::get('match_tlive_count' . $item['id']) ?: 0;
                        $match_tlive_count_new = count($item['tlive']);
                        if ($match_tlive_count_new > $match_tlive_count_old) { //直播文字
                            Cache::set('match_tlive_count' . $item['id'], $match_tlive_count_new, 60 * 240);
                            $diff = array_slice($item['tlive'], $match_tlive_count_old);
                            (new WebSocket())->contentPush($diff, $item['id']);
                        }

                        $corner_count_new = 0;

                        foreach ($item['tlive'] as $signal_tlive) {

                            if ($signal_tlive['type'] == 2) { //角球
                                $corner_count_new += 1;
                                $format_signal_corner_tlive = ['time' => intval($signal_tlive['time']), 'type' => $signal_tlive['type'], 'position' => $signal_tlive['position']];
                                $corner_count_tlive[] = $format_signal_corner_tlive;
                            } else {
                                continue;
                            }
                        }
                        Cache::set('match_tlive_' . $item['id'], json_encode($item['tlive']), 60 * 240);

                    }
                    $last_goal_tlive = [];
                    $last_yellow_card_tlive = [];
                    $last_red_card_tlive = [];

                    $goal_count_new = 0;
                    $yellow_card_count_new = 0;
                    $red_card_count_new = 0;

                    $goal_tlive_total = [];
                    $yellow_card_tlive_total = [];
                    $red_card_tlive_total = [];

                    if (isset($item['incidents'])) {

                        $goal_count_old = Cache::get('goal_count_' . $item['id']);
                        $yellow_card_count_old = Cache::get('yellow_card_count' . $item['id']);
                        $red_card_count_old = Cache::get('red_card_count' . $item['id']);

                        foreach ($item['incidents'] as $item_tlive) {
                            $match_tlive_count_new += 1;
                            $signal_incident = [
                                'time' => intval($item_tlive['time']),
                                'type' => $item_tlive['type'],
                                'position' => $item_tlive['position'],
                            ];
                            if ($signal_incident['type'] == 1 || $signal_incident['type'] == 8) { //进球 /点球
                                $last_goal_tlive = $signal_incident;
                                $goal_count_new += 1;
                                $goal_tlive_total[] = $signal_incident;
                            } else if ($signal_incident['type'] == 3) { //黄牌
                                $last_yellow_card_tlive = $signal_incident;
                                $yellow_card_count_new += 1;
                                $yellow_card_tlive_total[] = $signal_incident;
                            }else if ($signal_incident['type'] == 4) { //红牌
                                $last_red_card_tlive = $signal_incident;
                                $red_card_count_new += 1;
                                $red_card_tlive_total[] = $signal_incident;
                            } else {
                                continue;
                            }

                        }

                        if ($goal_count_new > $goal_count_old) {//进球
                            TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'], 'last_incident' => $last_goal_tlive, 'score' => $item['score'], 'type'=>1]));
                            Cache::set('goal_count_' . $item['id'], $goal_count_new, 60 * 240);
                        }

                        if ($yellow_card_count_new > $yellow_card_count_old) {//黄牌

                            TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'], 'last_incident' => $last_yellow_card_tlive, 'score' => $item['score'], 'type'=>3]));
                            Cache::set('yellow_card_count' . $item['id'], $yellow_card_count_new, 60 * 240);

                        }

                        if ($red_card_count_new > $red_card_count_old) {//红牌

                            TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'], 'last_incident' => $last_red_card_tlive, 'score' => $item['score'], 'type'=>4]));
                            Cache::set('red_card_count' . $item['id'], $red_card_count_new, 60 * 240);

                        }


                    }
                    $signal_match_info['signal_count'] = ['corner' => $corner_count_tlive, 'goal' => $goal_tlive_total, 'yellow' => $yellow_card_tlive_total, 'red' => $red_card_tlive_total];
                    $signal_match_info['match_trend'] = $match_trend_info;
                    $signal_match_info['match_id'] = $item['id'];
                    $signal_match_info['time'] = AppFunc::getPlayingTime($item['id']);
                    $signal_match_info['status'] = $status;
                    $signal_match_info['match_stats'] = $matchStats;
                    $signal_match_info['score'] = [
                        'home' => $item['score'][2],
                        'away' => $item['score'][3]
                    ];

                    $match_info[] = $signal_match_info;
                    Cache::set('match_data_info' .$item['id'], json_encode($signal_match_info), 60 * 240);
                    unset($signal_match_info);
                }
                /**
                 * 异步的话要做进程间通信，本身也有开销，不如做成同步的，push将数据交给底层，本身不等待
                 */
//            $update_task_status = TaskManager::getInstance()->async(new MatchUpdate(['match_info_list' => $match_info]));
//            if ($update_task_status <= 0) {
//                Log::getInstance()->info('delivery failed, match list info-' . json_encode($match_info));
//            }

                if (!empty($match_info)) {
//                    if (empty($match_info)) {
//                        return;
//                    }
                    $tool = Tool::getInstance();
                    $server = ServerManager::getInstance()->getSwooleServer();
                    $start_fd = 0;
                    $returnData = [
                        'event' => 'match_update',
                        'match_info_list' => isset($match_info) ? $match_info : []
                    ];
                    while (true) {
                        $conn_list = $server->getClientList($start_fd, 10);
                        if (!$conn_list || count($conn_list) === 0) {
                            break;
                        }
                        $start_fd = end($conn_list);

                        foreach ($conn_list as $fd) {
                            $connection = $server->connection_info($fd);
                            if (is_array($connection) && $connection['websocket_status'] == 3) {  // 用户正常在线时可以进行消息推送
                                Log::getInstance()->info('push succ' . $fd);
                                $server->push($fd, $tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $returnData));
                            } else {
                                Log::getInstance()->info('lost-connection-' . $fd);
                            }
                        }
                    }
                } else {
                    Log::getInstance()->info('333333333');

                }

            }

        });
    }








    public function onShutDown()
    {
        // TODO: Implement onShutDown() method.
    }

    public function onReceive(string $str)
    {
        // TODO: Implement onReceive() method.
    }
}