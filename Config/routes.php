<?php
/**
 * 路由配置
 */
use FF\Constants\MessageIds;

$config = array(
    MessageIds::FETCH_FRIENDS => array('/Friend/getFriends'),
    MessageIds::ADD_FRIEND => array('/Friend/addFriend'),
    MessageIds::FETCH_FRIENDS_REQUESTS => array('/Friend/getFriendsRequests'),
    MessageIds::ACCEPT_FRIEND => array('/Friend/acceptFriend'),
    MessageIds::REFUSE_FRIEND => array('/Friend/refuseFriend'),
    MessageIds::DEL_FRIEND => array('/Friend/delFriend'),
    MessageIds::FETCH_FRIEND_GIVING_STAMP_LIST => array('/Friend/fetchReceiveFriendStampList'),
    MessageIds::FETCH_FRIEND_GIVING_COIN_LIST => array('/Friend/fetchReceiveFriendCoinList'),
    MessageIds::GIVING_FRIEND_STAMP => array('/Friend/givingFriendStamp'),
    MessageIds::GIVING_FRIEND_COINS => array('/Friend/givingFriendCoins'),
    MessageIds::BIND_INVITER_CODE => array('/Friend/bindInviter'),
    MessageIds::AWARD_FRIEND_GIVING_STAMP => array('/Friend/awardFriendStamp'),
    MessageIds::AWARD_FRIEND_GIVING_COINS => array('/Friend/awardFriendCoins'),
    MessageIds::SEND_CHAT_MESSAGE => array('/Chat/sendMessage'),
    MessageIds::FETCH_CHAT_MESSAGE_LIST => array('/Chat/fetchMessageList'),
    MessageIds::READ_ALL_CHAT_MESSAGE => array('/Chat/readAll'),
    MessageIds::FETCH_BLL_MESSAGE_LIST => array('/BllMessage/fetchMessageList'),
    MessageIds::FETCH_BLL_MESSAGE_STAT_LIST => array('/BllMessage/fetchMsgStatInfo'),
    MessageIds::CLEAR_BLL_MESSAGE => array('/BllMessage/clearBllMessage'),
    MessageIds::CLEAR_USER_INFO_REPORT => array('/User/dataReport'),
);

return $config;