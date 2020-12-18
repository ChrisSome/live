<?php

namespace App\WebSocket;

class WebSocketStatus
{
    const STATUS_SUCC = 200; //连接成功


    const STATUS_W_PARAM = 301;
    const STATUS_ROOM_NOT_FOUND = 401; //未找到该房间
    const STATUS_USER_NOT_FOUND = 402; //未找到该房间

    const STATUS_LOGIN_ERROR = 403; //登录错误
    const STATUS_LEAVE_ROOM = 404; //退出房间错误
    const STATUS_NOT_IN_ROOM = 405; //用户不再当前直播间
    const STATUS_W_USER_RIGHT = 406; //用户权限错误
    const STATUS_NOT_LOGIN = 407;//用户未登录
    const STATUS_WRONG_MATCH = 408;//比赛错误
    const STATUS_OPERATE_UNUSUAL = 409;//操作异常
    const STATUS_CONNECTION_FAIL = 410;//连接异常

    static $msg = [
        self::STATUS_SUCC => '操作成功',

        self::STATUS_W_PARAM => '参数错误',
        self::STATUS_ROOM_NOT_FOUND => '未找到该房间',
        self::STATUS_USER_NOT_FOUND => '用户不存在',
        self::STATUS_LOGIN_ERROR => '系统错误，请重新登录',
        self::STATUS_W_USER_RIGHT => '权限错误，请重试',

        self::STATUS_LEAVE_ROOM => '退出房间失败',
        self::STATUS_NOT_IN_ROOM => '用户不再当前直播间',
        self::STATUS_NOT_LOGIN => '请登录',
        self::STATUS_WRONG_MATCH => '比赛源错误',
        self::STATUS_OPERATE_UNUSUAL => '操作异常,请重试',
        self::STATUS_CONNECTION_FAIL => '网络错误，请重新连接',
    ];
}