<?php

namespace FF\Constants;

class MessageIds
{
    const ADD_FRIEND_REQUEST_NOTIFY = 1001; //添加好友请求通知
    const ACCESS_FRIEND_NOTIFY = 1002; //添加好友通知
    const REFUSE_FRIEND_NOTIFY = 1003; //拒绝好友通知
    const DEL_FRIEND_NOTIFY = 1004; // 删除好友通知
    const RECEIVE_FRIEND_COINS_NOTIFY = 1005; //赠送好金币通知
    const RECEIVE_FRIEND_STAMP_NOTIFY = 1006; //赠送好邮票通知
    const CHAT_MSG_RECEIVE_NOTIFY = 1007; //聊天信息接收通知
    const INVITED_BIND_AWARD_NOTIFY = 1008; //邀请奖励通知
    const FETCH_FRIENDS = 100001; //获取好友列表
    const ADD_FRIEND = 100002; //发送添加好友请求
    const FETCH_FRIENDS_REQUESTS = 100003; //获取好友请求
    const ACCEPT_FRIEND = 100004; // 同意好友添加
    const REFUSE_FRIEND = 100005; //绝交添加好友
    const DEL_FRIEND = 100006; //删除好友
    const GIVING_FRIEND_STAMP = 100007; //赠送邮票
    const GIVING_FRIENDS_COINS = 100008; //赠送金币
    const FETCH_FRIEND_GIVING_STAMP_LIST = 100009;//获取朋友赠送邮票记录列表
    const FETCH_FRIEND_GIVING_COIN_LIST = 100010;//获取朋友赠送金币记录列表
    const FETCH_FRIEND_INFO = 100011;//获取朋友信息
    const BIND_INVITER_CODE = 100012;//绑定邀请者的邀请码
    const AWARD_FRIEND_GIVING_COINS = 100013;//领取朋友赠送的coin
    const AWARD_FRIEND_GIVING_STAMP = 100014;//领取朋友赠送的邮票
    const SEND_CHAT_MESSAGE = 100015; //发聊天记录
    const FETCH_CHAT_MESSAGE_LIST = 100016; //获取聊天列表
    const READ_ALL_CHAT_MESSAGE = 100017; //设置消息已读
    const FETCH_BLL_MESSAGE_LIST = 100018; //拉取业务信息列表
    const FETCH_BLL_MESSAGE_STAT_LIST = 100019; //拉取业务信息统计列表
    const USER_LOGIN = 100020; //玩家登录
    const CLEAR_USER_INFO_REPORT = 100021; //玩家信息上报
    const FETCH_SUGGEST_FRIEND_LIST = 100022; //获取好友推荐列表
    const ADD_SUGGEST_FRIENDS = 100023; //批量添加好友请求
    const FETCH_REQUEST_INFO = 100024; //获取请求的信息
    const INVITE_AWARD = 100025; //邀请奖励
}