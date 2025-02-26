<?php

namespace FF\Constants;

use Exception;

class Exceptions extends Exception
{
    const UNKNOWN_EXCEPTION = -1; //未知错误

    const SUCCESS = 0; //成功
    const FAILED = 1; //失败
    const SYSTEM_ERROR = 500; //系统错误
    const PARAM_INVALID_ERROR = 10000; //参数无效
    const PARAM_MISS_ERROR = 10001; //缺少参数
    const RET_REPEAT_REQUEST_ERROR = 10002; //重复请求接口
    const RET_SOCIAL_FRIENDS_ACCEPT_FAILED = 10003; // 同意好友申请失败
    const RET_SOCIAL_LIMIT_ADD_FRIEND = 10004; //申请好友请求上限
    const RET_ACCOUNT_NOT_EXIST = 10005; //用户不存在
    const RET_REQUEST_ADD_FRIEND_EXISTS = 10006; //已存在申请好友请求
    const RET_SOCIAL_FRIENDS_IS_FULL = 10007; //添加朋友到达上限
    const RET_SOCIAL_FRIENDS_ADDED = 10008; //好友经添加
    const RET_SOCIAL_FRIENDS_DELETE_FAILED = 10009; //删除好友失败
    const RET_SOCIAL_INVITE_UNDONE = 10010; //邀请好友失败
    const RET_SOCIAL_NOT_FRIEND = 10011; //不是该用户好友
    const RET_SOCIAL_LIMIT_SENT_FRIEND_COINS = 10012; //赠送金币次数到达上限
    const RET_USER_INVALID = 10013; //无效用户
    const RET_VERSION_TOO_OLD = 10014;
    const RET_REQUEST_TO_FREQUENT = 10015; //接口请求频率限制
    const RET_SYSTEM_MAINTAIN = 10016;
    const RET_CHAT_SEND_FAIL = 10017; //聊天发送失败
    const RET_CHAT_DENY_SEND_MYSELF = 10018; //禁止给自己发信息
    const RET_CHAT_NOT_FRIEND_ERROR = 10019; //非好友不能发信息
    const RET_HAS_BIND_INVITER_ERROR = 10020; //已经绑定邀请者
    const RET_INVITE_CODE_NOT_EXISTS_ERROR = 10021;//邀请码不存在
    const RET_BIND_INVITER_FAIL = 10022;//绑定邀请者失败
    const RET_REWARD_CLAIMED_ERROR = 10023;//奖励已经领取
    const RET_REWARD_EXPIRED_ERROR = 10024; //奖励已经过期
    const RET_GIVING_COIN_ERROR = 10025; //赠送金币值有误
    const RET_SOCIAL_LIMIT_SENT_FRIEND_STAMP = 10026;//赠送邮票达到上限
    const RET_DENY_BIND_MYSELF_CODE_ERROR = 10027; //不能绑定自己的邀请码
    const RET_CLUB_NOT_EXISTS_ERROR = 10028; //俱乐部不存在
    const RET_CLUB_ALREADY_EXISTS_ERROR = 10029; //俱乐部已存在
    const RET_CLUB_CREATE_ERROR = 10030; //创建俱乐部失败
    const RET_CLUB_JOIN_FAILED_ERROR = 10031; //加入俱乐部失败
    const RET_USER_ALREADY_IN_CLUB_ERROR = 10032;//玩家已经加入其他俱乐部
    const RET_CLUB_MEMBER_LIMIT_ERROR = 10033; //俱乐部成员数量达到上限
    const RET_CLUB_OPT_NO_PERMISSION_ERROR = 10034;
    const RET_CLUB_NOT_JOIN_ERROR = 10035;//没有加入俱乐部
    const RET_CLUB_LEADER_NOT_QUIT_ERROR = 10036; //管理者不能退出俱乐部
    const RET_CLUB_NOT_ALLOW_JOIN_ERROR = 10037; //不能申请加入俱乐部
    const RET_VIP_LEVEL_NOT_ENOUGH_ERROR = 10038; //玩家vip等级不足
    const RET_CHAT_CONTENT_TOO_LONG = 10039; //聊天内容过长
    const RET_CHAT_CONTENT_HAS_EMOJI = 10040; //聊天内容含有emoji
    const RET_CHAT_FORBIDDEN_ERROR = 10041; //禁言
    const RET_CHAT_CONTENT_EMPTY = 10042; //聊天内容为空
    const RET_CHAT_CONTENT_INVALID = 10043; //聊天内容无效
    const RET_CLUB_UPDATE_ERROR = 10044; //更新俱乐部信息失败
    const RET_CLUB_PUZZLE_FINISH_ERROR = 10045; //俱乐部拼图已完成
    const RET_CLUB_PUBLISH_HELP_COOL_DOWN = 10046; //俱乐部发布求助冷却中
    const RET_CLUB_PUBLISH_HELP_TYPE_ERROR = 10047;//发布援助类型有误
}
