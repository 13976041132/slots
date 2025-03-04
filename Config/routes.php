<?php
/**
 * 路由配置
 */
use FF\Constants\MessageIds;

$config = array(
    MessageIds::FETCH_FRIENDS => array('/Friend/fetchFriends'),
    MessageIds::ADD_FRIEND => array('/Friend/addFriend'),
    MessageIds::FETCH_FRIENDS_REQUESTS => array('/Friend/fetchFriendsRequests'),
    MessageIds::ACCEPT_FRIEND => array('/Friend/acceptFriend'),
    MessageIds::REFUSE_FRIEND => array('/Friend/refuseFriend'),
    MessageIds::DEL_FRIEND => array('/Friend/delFriend'),
    MessageIds::FETCH_SUGGEST_FRIEND_LIST => array('/Friend/fetchSuggestFriends'),
    MessageIds::ADD_SUGGEST_FRIENDS => array('/Friend/addSuggestFriends'),
    MessageIds::FETCH_FRIEND_GIVING_STAMP_LIST => array('/Friend/fetchReceiveFriendStampList'),
    MessageIds::FETCH_FRIEND_GIVING_COIN_LIST => array('/Friend/fetchReceiveFriendCoinList'),
    MessageIds::GIVING_FRIEND_STAMP => array('/Friend/givingFriendStamp'),
    MessageIds::GIVING_FRIENDS_COINS => array('/Friend/givingFriendsCoins'),
    MessageIds::BIND_INVITER_CODE => array('/Friend/bindInviter'),
    MessageIds::AWARD_FRIEND_GIVING_STAMP => array('/Friend/awardFriendStamp'),
    MessageIds::AWARD_FRIEND_GIVING_COINS => array('/Friend/awardFriendCoins'),
    MessageIds::SEND_CHAT_MESSAGE => array('/Chat/sendMessage'),
    MessageIds::FETCH_CHAT_MESSAGE_LIST => array('/Chat/fetchMessageList'),
    MessageIds::READ_ALL_CHAT_MESSAGE => array('/Chat/readAll'),
    MessageIds::FETCH_BLL_MESSAGE_LIST => array('/BllMessage/fetchMessageList'),
    MessageIds::FETCH_BLL_MESSAGE_STAT_LIST => array('/BllMessage/fetchMsgStatInfo'),
    MessageIds::USER_LOGIN => array('/User/login'),
    MessageIds::CLEAR_USER_INFO_REPORT => array('/User/dataReport'),
    MessageIds::FETCH_REQUEST_INFO => array('/User/fetchRequestInfo'),
    MessageIds::INVITE_AWARD => array('/User/inviteAward'),

    MessageIds::CREATE_CLUB => array('/Club/createClub'),
    MessageIds::FETCH_CLUB_LIST => array('/Club/fetchSuggestList'),
    MessageIds::JOIN_CLUB => array('/Club/joinClub'),
    MessageIds::FETCH_CLUB_INFO => array('/Club/fetchClubInfo'),
    MessageIds::FETCH_CLUB_MEMBER_LIST => array('/Club/fetchMemberList'),
    MessageIds::DISSOLVE_CLUB => array('/Club/dissolveClub'),
    MessageIds::CLUB_CHAT => array('/Club/chat'),
    MessageIds::UPDATE_CLUB_INFO => array('/Club/updateClubInfo'),
    MessageIds::SET_MUTE_STATUS => array('/Club/setMuteStatus'),
    MessageIds::ACCEPT_INVITE_JOIN_CLUB => array('/Club/acceptInviteJoinClub'),
    MessageIds::REFUSE_INVITE_JOIN_CLUB => array('/Club/refuseInviteJoinClub'),
    MessageIds::INVITE_JOIN_CLUB => array('/Club/inviteJoinClub'),
    MessageIds::QUIT_CLUB => array('/Club/quitClub'),
    MessageIds::KICK_OUT_CLUB_MEMBER => array('/Club/kickOutClubMember'),
    MessageIds::CLUB_PUBLISH_HELP => array('/Club/publishHelp'),
    MessageIds::CLUB_DROP_PUZZLE => array('/Club/dropPuzzle'),
    MessageIds::CLUB_MEMBER_POINTS_REPORT => array('/Club/pointsReport'),
    MessageIds::CLUB_MEMBER_DONATE_COINS => array('/Club/donateCoins'),
    MessageIds::FETCH_CLUB_RANK_LIST => array('/Club/fetchClubRankList'),
    MessageIds::CLUB_JACKPOT_REPORT => array('/Club/jackpotReport'),
    MessageIds::FETCH_CLUB_WALL_INFO => array('/Club/jackpotReport'),
    MessageIds::CLUB_MACHINE_POINTS_REPORT => array('/Club/machinePointsReport'),
    MessageIds::FETCH_MACHINE_POINTS_RANK_LIST => array('/Club/fetchMachinePointsRankList'),
    MessageIds::FETCH_CLUB_SEARCH_LIST => array('/Club/searchClubList'),
    MessageIds::FETCH_CLUB_DAN_SUMMARY => array('/Club/fetchDanSummary'),
    MessageIds::FETCH_CLUB_HISTORY_RANK_LIST => array('/Club/fetchHistoryRankList'),
    MessageIds::ONE_CLICK_ADD_CLUB_FRIENDS => array('/Friend/oneClickAddClubFriend'),
    MessageIds::FETCH_CLUB_REWARD_LIST => array('/Club/fetchClubRewardList'),
    MessageIds::FETCH_CLUB_REWARD_INFO => array('/Club/fetchClubRewardInfo'),
    MessageIds::CLAIM_CLUB_REWARD => array('/Club/claimClubReward'),







    MessageIds::FETCH_USER_INFO => array('/User/fetchUserInfo', middleware('checkSignature')),
);

return $config;