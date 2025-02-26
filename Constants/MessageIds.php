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
    const JOIN_CLUB_FAIL_NOTIFY = 1009; //加入俱乐部失败
    const JOIN_CLUB_SUCCESS_NOTIFY = 1010; //加入俱乐部成功
    const REFUSE_JOIN_CLUB_NOTIFY = 1011; //拒绝加入俱乐部
    const CLUB_MUTE_NOTIFY = 1012; //俱乐部禁言通知
    const CLUB_MUTE_CANCEL_NOTIFY = 1012; //俱乐部取消禁言通知
    const CLUB_KICK_OUT_NOTIFY = 1013; //俱乐部踢出通知
    const CLUB_INVITE_JOIN_NOTIFY = 1014; //俱乐部邀请加入通知
    const CLUB_INVITE_JOIN_SUCCESS_NOTIFY = 1015; //俱乐部邀请加入成功通知
    const CLUB_INVITE_JOIN_REFUSE_NOTIFY = 1016; //俱乐部邀请加入拒绝通知
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
    const CLEAR_USER_INFO_REPORT = 100021; //清除玩家信息上报
    const FETCH_SUGGEST_FRIEND_LIST = 100022; //获取好友推荐列表
    const ADD_SUGGEST_FRIENDS = 100023; //批量添加好友请求
    const FETCH_REQUEST_INFO = 100024; //获取请求的信息
    const INVITE_AWARD = 100025; //邀请奖励
    const CREATE_CLUB = 100026; //创建俱乐部
    const FETCH_CLUB_LIST = 100027; //获取俱乐部列表
    const JOIN_CLUB = 100028; //加入俱乐部
    const FETCH_CLUB_INFO = 100029; //获取俱乐部信息
    const FETCH_CLUB_MEMBER_LIST = 100030; //获取俱乐部成员列表
    const DISSOLVE_CLUB = 100031; //解散俱乐部
    const CLUB_CHAT = 100032; //俱乐部发言
    const UPDATE_CLUB_INFO = 100033; //修改俱乐部信息
    const SET_MUTE_STATUS = 100034; //设置禁言状态
    const ACCEPT_INVITE_JOIN_CLUB = 100035; //接受邀请加入俱乐部
    const REFUSE_INVITE_JOIN_CLUB = 100036; //拒绝邀请加入俱乐部
    const INVITE_JOIN_CLUB = 100037; //邀请加入俱乐部
    const QUIT_CLUB = 100038;//退出俱乐部
    const KICK_OUT_CLUB_MEMBER = 100039; //踢出俱乐部成员

    const FETCH_CLUB_SEARCH_LIST = 100040; //查询俱乐部列表

    //发布援助
    const CLUB_PUBLISH_HELP = 100041;

    //掉落拼图碎片
    const CLUB_DROP_PUZZLE = 100042;

    //获取俱乐部排行榜
    const FETCH_CLUB_RANK_LIST = 100043;

    //成员积分上报
    const CLUB_MEMBER_POINTS_REPORT = 100044;

    //成员捐赠金币
    const CLUB_MEMBER_DONATE_COINS = 100045;

    //jackpot上报
    const CLUB_JACKPOT_REPORT = 100046;

    const FETCH_CLUB_WALL_INFO = 100047; //俱乐部墙信息

    const CLUB_MACHINE_POINTS_REPORT= 100048; //俱乐部机台积分上报

    const FETCH_MACHINE_POINTS_RANK_LIST = 100049; //获取机台积分排行榜

    //俱乐部段位汇总
    const FETCH_CLUB_DAN_SUMMARY = 100050;

    //获取俱乐部历史排名
    const FETCH_CLUB_HISTORY_RANK_LIST = 100051;
}