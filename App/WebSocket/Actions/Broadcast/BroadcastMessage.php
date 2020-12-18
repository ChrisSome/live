<?php
/**
 * Created by PhpStorm.
 * User: evalor
 * Date: 2018-12-02
 * Time: 01:49
 */

namespace App\WebSocket\Actions\Broadcast;

use App\WebSocket\Actions\ActionPayload;
use App\WebSocket\WebSocketAction;

/**
 * 广播客户消息
 * Class BroadcastMessage
 * @package App\WebSocket\Actions\Broadcast
 */
class BroadcastMessage extends ActionPayload
{
    protected $action = WebSocketAction::BROADCAST_MESSAGE;
    protected $fromUserFd;
    protected $fromUserId;
    protected $content;
    protected $type;
    protected $sendTime;
    protected $messageId;
    protected $matchId;
    protected $mid;

    /**
     * @param mixed $fromUserFd
     */
    public function getMid()
    {
        return $this->mid;
    }

    /**
     * @param mixed $matchId
     */
    public function setMid($mid): void
    {
        $this->mid = $mid;
    }



    /**
     * @param mixed $fromUserFd
     */
    public function getMatchId()
    {
        return $this->matchId;
    }

    /**
     * @param mixed $matchId
     */
    public function setMatchId($matchId): void
    {
        $this->matchId = $matchId;
    }


    /**
     * @param mixed $fromUserFd
     */
    public function getMessageId()
    {
       return $this->messageId;
    }

    /**
     * @param mixed $fromUserFd
     */
    public function setMessageId($messageId): void
    {
        $this->messageId = $messageId;
    }

    /**
     * @return mixed
     */
    public function getFromUserFd()
    {
        return $this->fromUserFd;
    }

    /**
     * @param mixed $fromUserFd
     */
    public function setFromUserFd($fromUserFd): void
    {
        $this->fromUserFd = $fromUserFd;
    }

    public function setFromUserId($fromUserID):void
    {
        $this->fromUserId = $fromUserID;
    }
    /**
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param mixed $content
     */
    public function setContent($content): void
    {
//        'content'        => base64_encode(htmlspecialchars(addslashes($aMessage['content']))),

        $this->content = base64_encode(addslashes($content));
    }

    /**
     * @param mixed $type
     */
    public function setType($type): void
    {
        $this->type = $type;
    }

    /**
     * @param mixed $sendTime
     */
    public function setSendTime($sendTime): void
    {
        $this->sendTime = $sendTime;
    }

    /**
     * @param mixed $matchId
     */
    public function setUserMatchId($matchId) : void
    {
        $this->matchId = $matchId;
    }

    /**
     * @param $atUserId
     */
    public function setAtUserId($atUserId) :void
    {
        $this->atUserId = $atUserId;
    }
}