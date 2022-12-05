<?php
/**
 * 路由配置
 */

use GPBClass\Enum\MSG_ID;

$config = array(
    MSG_ID::MSG_CHECK_APP_VERSION => array('/Version/checkAppVersion', 'PB_CheckAppVersion'),
    MSG_ID::MSG_CHECK_MODULE_VERSION => array('/Version/checkModuleVersion', 'PB_CheckModuleVersion'),
    MSG_ID::MSG_EVENT_REPORT => array('/Public/eventReport', 'PB_EventReport'),
    MSG_ID::MSG_PING => array('/Public/onPing', 'PB_Ping'),
    MSG_ID::MSG_USER_LOGIN => array('/User/login', 'PB_UserLogin'),
    MSG_ID::MSG_USER_BIND => array('/User/bindUser', 'PB_UserBind'),
    MSG_ID::MSG_SET_USER_NICKNAME => array('/User/setNickname', 'PB_SetNickname'),
    MSG_ID::MSG_SET_USER_AVATAR => array('/User/setAvatar', 'PB_SetAvatar'),
    MSG_ID::MSG_ENTER_MACHINE => array('/Game/enterMachine', 'PB_EnterMachine'),
    MSG_ID::MSG_EXIT_MACHINE => array('/Game/exitMachine', 'PB_ExitMachine'),
    MSG_ID::MSG_INIT_COLLECT_GAME => array('/Game/initCollectGame', 'PB_InitCollectGame'),
    MSG_ID::MSG_INIT_JACKPOTS => array('/Game/initJackpots', 'PB_InitJackpots'),
    MSG_ID::MSG_SLOTS_BETTING => array('/Game/slotsBetting', 'PB_SlotsBetting'),
    MSG_ID::MSG_RESUME_FREEGAME => array('/Feature/resumeFreeGame', 'PB_ResumeFreeGame'),
    MSG_ID::MSG_RECOVER_LIGHTNING => array('/Feature/recoverLightning', 'PB_RecoverLightning'),
    MSG_ID::MSG_HOLD_AND_SPIN => array('/Feature/holdAndSpin', 'PB_HoldAndSpin'),
    MSG_ID::MSG_WHEEL_SPIN => array('/Feature/wheelSpin', 'PB_WheelSpin'),
//    MSG_ID::MSG_COLLECT_AWARD => array('/Game/collectAward', 'PB_CollectAward'),

);

return $config;