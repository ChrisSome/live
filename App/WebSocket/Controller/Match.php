<?php


namespace App\WebSocket\Controller;


use App\Common\AppFunc;
use App\lib\Tool;
use App\Model\AdminMatch;
use App\Model\AdminMatchTlive;
use App\Model\AdminUser;
use App\Model\ChatHistory;
use App\Storage\MatchLive;
use App\Storage\OnlineUser;
use App\Utility\Log\Log;
use App\WebSocket\WebSocketStatus;
use easySwoole\Cache\Cache;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\ORM\DbManager;

class Match extends Base
{

    /**
     * 进入直播间
     * @throws \Throwable
     */
    public function enter()
    {
        $client = $this->caller()->getClient();
        $fd = $client->getFd();
        $args = $this->caller()->getArgs();
        $tool = Tool::getInstance();
        if (!isset($args['match_id'])) {
            //参数不正确
            $this->response()->setMessage($tool->writeJson(403, '参数不正确'));

            return;
        }
        if (!OnlineUser::getInstance()->get($fd)) {
            $this->response()->setMessage($tool->writeJson(WebSocketStatus::STATUS_CONNECTION_FAIL, WebSocketStatus::$msg[WebSocketStatus::STATUS_CONNECTION_FAIL]));
            return;
        }
        //用户进入房间
        $user = $this->currentUser($fd);

        //记录房间内用户
        $matchId = $args['match_id'];
        OnlineUser::getInstance()->update($fd, ['match_id' => $matchId]);

        $user['match_id'] = $matchId;
        $user['fd'] = $fd;
        //设置房间对象
        AppFunc::userEnterRoom($args['match_id'], $fd);
        //最近二十条聊天记录
        $lastMessages = ChatHistory::getInstance()->where('match_id', $args['match_id'])->order('created_at', 'DESC')->limit(20)->all();
        //比赛状态
        $match = DbManager::getInstance()->invoke(function ($client) use ($matchId) {
            $matchModel = AdminMatch::invoke($client);
            $data = $matchModel->where('match_id', $matchId)->get();
            return $data;
        });
        if ($match) {
            if ($match_data_info = AppFunc::getMatchingInfo($match->match_id)) {
                /**
                 * 比赛未结束 信息从cache中拿
                 *
                 */
                $return_data = json_decode($match_data_info, true);
                $tlive = json_decode(Cache::get('match_tlive_' . $matchId), true) ?: [];
                $stats = json_decode(Cache::get('match_stats_' . $matchId), true) ?: [];
            } else if ($matchTlive = AdminMatchTlive::create()->where('match_id', $matchId)->get()) {
                $tlive = json_decode($matchTlive->tlive, true);
                $stats = json_decode($matchTlive->stats, true);
                $score = json_decode($matchTlive->score, true);
                $match_trend = json_decode($matchTlive->match_trend, true);
                $goal_tlive = [];
                $corner_tlive = [];
                $yellow_card_tlive = [];
                $red_card_tlive = [];
                if ($tlive) {
                    foreach ($tlive as $item) {
                        $item['time'] = intval($item['time']);
                        if ($item['type'] == 1) { //进球
                            $goal_tlive[] = $item;
                        } else if ($item['type'] == 2) { //角球
                            $corner_tlive[] = $item;
                        } else if ($item['type'] == 3) { //黄牌
                            $yellow_card_tlive[] = $item;
                        } else if ($item['type'] == 4) { //红牌
                            $red_card_tlive[] = $item;
                        } else {
                            continue;
                        }

                        unset($item);
                    }
                }

                $matchStats = [];
                if ($stats) {
                    foreach ($stats as $stat) {
                        if ($stat['type'] == 21 || $stat['type'] == 22 || $stat['type'] == 23 || $stat['type'] == 24 ||  $stat['type'] == 25) {
                            $matchStats[] = $stat;
                        }
                    }
                }

                $return_data = [
                    'signal_count' => ['goal' => $goal_tlive, 'corner' => $corner_tlive, 'yellow_card' => $yellow_card_tlive, 'red_card' => $red_card_tlive],
                    'match_trend' => $match_trend,
                    'match_id' => $matchId,
                    'time' => 0,
                    'status' => 8,
                    'match_stats' => $matchStats,
                    'score' => ['home' => $score[2], 'away' => $score[3]],

                ];
            } else {
                $return_data = [];
                $tlive = [];
                $stats = [];
            }

        } else {
            $this->response()->setMessage($tool->writeJson(WebSocketStatus::STATUS_WRONG_MATCH, WebSocketStatus::$msg[WebSocketStatus::STATUS_WRONG_MATCH]));
            return;
        }
        $messages = [];

        if ($lastMessages) {
            foreach ($lastMessages as $lastMessage) {
                $senderUser = $lastMessage->getSenderNickname();
                $data['message_id'] = $lastMessage['id'];
                $data['sender_user_id'] = $lastMessage['sender_user_id'];
                $data['sender_user_nickname'] = $senderUser['nickname'];
                $data['sender_user_level'] = $senderUser['level'];
                $data['at_user_id'] = $lastMessage['at_user_id'];
                $data['at_user_nickname'] = $lastMessage->getAtNickname()['nickname'];
                $data['content'] = $lastMessage['content'];
                $messages[] = $data;
                unset($data);
            }
        }
        $respon = [
            'event' => 'match-enter',
            'data' => [
                'userInfo' => $user,
                'match_info' => $return_data,
                'lastMessage' => $messages,
                'stats' => $stats,
                'tlive' => $tlive,
            ],
        ];
        $this->response()->setMessage($tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $respon));

        return;
    }


    /**
     * 离开直播间
     */
    public function leave()
    {

        $client = $this->caller()->getClient();
        $fd = (int)$client->getFd();



        $args = $this->caller()->getArgs();
        $tool = Tool::getInstance();
        if (!isset($args['match_id'])) {
            //参数不正确
            $this->response()->setMessage($tool->writeJson(WebSocketStatus::STATUS_W_PARAM, WebSocketStatus::$msg[WebSocketStatus::STATUS_W_PARAM]));

            return  ;
        }


        if ($onlineInfo = OnlineUser::getInstance()->get($fd)) {
            if ($onlineInfo['match_id'] == 0) {
                $this->response()->setMessage($tool->writeJson(WebSocketStatus::STATUS_NOT_IN_ROOM, WebSocketStatus::$msg[WebSocketStatus::STATUS_NOT_IN_ROOM]));
                return  ;
            }

            if ($onlineInfo['match_id'] != $args['match_id']) {
                $this->response()->setMessage($tool->writeJson(WebSocketStatus::STATUS_NOT_IN_ROOM, WebSocketStatus::$msg[WebSocketStatus::STATUS_NOT_IN_ROOM]));
                return  ;
            }
            $res_outroom = AppFunc::userOutRoom($args['match_id'], $fd);
            $resp = [
                'event' => 'match-leave'
            ];
            if ($res_outroom) {
                $this->response()->setMessage($tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $resp));
                return  ;
            } else {
                $this->response()->setMessage($tool->writeJson(WebSocketStatus::STATUS_LEAVE_ROOM, WebSocketStatus::$msg[WebSocketStatus::STATUS_LEAVE_ROOM]));

                return  ;
            }

        } else {
            $this->response()->setMessage($tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC]));

            return  ;
        }

    }

    public function getOnlineUser()
    {
        $tool = Tool::getInstance();
        $server = ServerManager::getInstance()->getSwooleServer();
        $start_fd = 0;
        $fdServer = $onlineUserArr = [];
        while (true) {
            $conn_list = $server->getClientList($start_fd, 100);
            if (!$conn_list || count($conn_list) === 0) {
                break;
            }
            $start_fd = end($conn_list);

            foreach ($conn_list as $fd) {
                $fdServer[] = $fd;
            }
        }

        $onLineUsers = OnlineUser::getInstance()->table();
        foreach ($onLineUsers as $fd => $user) {
            $onlineUserArr[] = $user;
        }

        $return['fdServer'] = $fdServer;
        $return['fdOnline'] = $onlineUserArr;
        $this->response()->setMessage($tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $return));



    }



}