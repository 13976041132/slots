<?php

namespace FF\Constants;
use Exception;

class Exceptions extends Exception
{
    const UNKNOWN_EXCEPTION = -1; //未知错误

    const SUCCESS = 0; //成功
    const FAIL = 1; //失败
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
}
