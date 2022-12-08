<?php
/**
 * 用户相关接口
 */

namespace FF\App\GameMain\Controller;

use FF\Factory\Bll;
use FF\Factory\Model;
use FF\Factory\Sdk;
use FF\Framework\Core\FF;
use GPBClass\Enum\PLATFORM;
use GPBClass\Enum\RET;

class UserController extends BaseController
{
    /**
     * 用户登录
     */
    public function login()
    {
        $openid = $this->getParam('openid');
        $token = $this->getParam('token', false);
        $platform = $this->getParam('platform', false, PLATFORM::PLATFORM_GUEST);
        $version = $this->getParam('version', false);
        $deviceId = $this->getParam('deviceId', false);
        $deviceToken = $this->getParam('deviceToken', false);
        $appsflyerId = $this->getParam('appsflyerId', false);
        $ip = get_ip();

        if (!$version) {
            $version = Bll::version()->getNewestVersion();
        }

        //验证openid与token
        if ($error = Bll::user()->verify($openid, $token, $platform)) {
            FF::throwException($error);
        }

        //查询账号信息（兼容绑定账号）
        $accountInfo = Bll::user()->getAccountByOpenId($openid, $platform);

        $loginType = 0; //登录类型 0普通登录 1注册 2今日首次登录

        if ($accountInfo) {
            if ($accountInfo['status'] != 1) { //账号被封禁
                FF::throwException(RET::RET_ACCOUNT_FORBIDDEN);
            }
            $uid = (int)$accountInfo['uid'];
            $platform = $accountInfo['platform'];
            $lastLoginTime = $accountInfo['lastLoginTime'];
            $loginDays = $accountInfo['loginDays'];
            $continued = (int)$accountInfo['continued'];
            $regTime = strtotime($accountInfo['regTime']);
            if (!$accountInfo['deviceId'] || !$accountInfo['deviceToken'] || ($appsflyerId && !$accountInfo['appsflyerId'])) {
                Bll::user()->updateDeviceInfo($uid, $deviceId, $deviceToken, $appsflyerId);
            }
        } else {
            //未注册，进行注册
            $uid = Bll::user()->register($openid, $platform, $token, $version, $deviceId, $deviceToken, $appsflyerId);
            $loginType = 1;
            $loginDays = 1;
            $continued = 1;
            $lastLoginTime = now();
            $regTime = time();
        }

        $userInfo = Bll::user()->getOne($uid);
        if (!$userInfo) FF::throwException(RET::RET_SYSTEM_ERROR);

        //更新登录时间、连续登录天数
        if ($loginType != 1) {
            if ($lastLoginTime < today()) {
                $loginDays++;
                $continued = $lastLoginTime < yesterday() ? 1 : ($continued + 1);
                $loginType = 2;
            }
            Model::account()->updateLogin($uid, $deviceId, $loginDays, $continued, $version);
        }

        //留存天数
        $retention = (strtotime(date('Ymd')) - strtotime(date('Ymd', $regTime))) / 86400;

        //是否破产
        $isBankrupt = $userInfo['coins'] < 100000;

        Bll::asyncTask()->addTask(EVENT_LOGIN, array(
            'uid' => $uid, 'loginType' => $loginType, 'continued' => $continued, 'retention' => $retention,
            'isBankrupt' => $isBankrupt, 'version' => $version, 'platform' => $platform, 'ip' => $ip
        ));
        $isReturn = Bll::user()->isReturn($lastLoginTime);
        //先使旧session失效
        Bll::user()->clearSession($uid);
        //生成新的sessionId
        $sessionData = array('uid' => $uid, 'platform' => $platform, 'version' => $version, 'isReturn' => $isReturn);
        $sessionId = Bll::session()->create($uid, $sessionData, 7 * 86400);
        Bll::user()->setSessionId($uid, $sessionId);

        //等级进度与vip进度
//        $userInfo['levelProgress'] = Bll::user()->getLevelProgress($userInfo['level'], $userInfo['exp']);
//        $userInfo['vipProgress'] = Bll::user()->getVipProgress($userInfo['vip'], $userInfo['vipPts']);

        $data = array();
        $data['sessionId'] = $sessionId;
        $data['roleInfo'] = $userInfo;
        $data['buffs'] = Bll::user()->getBuffList($uid);
        $data['loginType'] = $loginType;
        $data['systemTime'] = time();
        $data['tomorrowTime'] = strtotime(today()) + 86400;
        $data['regTime'] = $regTime;
        $data['platform'] = $platform;
        $data['loginDays'] = $loginDays;
        $data['regularContinuedLoginDays'] = $continued;

        return $data;
    }

    /**
     * 账号绑定
     */
    public function bindUser()
    {
        $uid = $this->getUid();
        $openid = $this->getParam('openid');
        $token = $this->getParam('token');
        $platform = $this->getParam('platform', false, PLATFORM::PLATFORM_FACEBOOK);

        //目前只支持绑定到FB账号
        if ($platform != PLATFORM::PLATFORM_FACEBOOK) {
            FF::throwException(RET::RET_FAILED);
        }

        //非游客账号不能进行绑定
        $account = Model::account()->getOneById($uid, 'platform,bindUid,bindOpenid');
        if ($account['platform'] != PLATFORM::PLATFORM_GUEST) {
            FF::throwException(RET::RET_FAILED);
        }

        //检测游客账号是否已绑定过
        if ($account['bindUid'] || $account['bindOpenid']) {
            FF::throwException(RET::RET_ACCOUNT_BIND_REPEAT);
        }

        //验证openid与token
        if ($error = Bll::user()->verify($openid, $token, $platform)) {
            FF::throwException($error);
        }

        $account = Model::account()->getOneByOpenid($openid, $platform);

        if (!$account) {
            if (Model::account()->getOneByBindOpenid($openid)) {
                FF::throwException(RET::RET_ACCOUNT_BIND_REPEAT);
            }
            $userInfo = null;
            if ($platform == PLATFORM::PLATFORM_FACEBOOK) {
                $userInfo = Sdk::facebook()->getUser($token);
            }
            if (!$userInfo) FF::throwException(RET::RET_FAILED);
            $result = Model::account()->setBindOpenid($uid, $openid);
            if (!$result) FF::throwException(RET::RET_FAILED);
            $newNickname = $userInfo['name'];
            $newAvatar = $userInfo['picture'];
            $newEmail = $userInfo['email'] ? : '';
            //更新用户昵称和头像
            Bll::user()->updateInfo($uid, array('nickname' => $newNickname, 'avatar' => $newAvatar, 'platformAvatar' => $newAvatar, 'email' => $newEmail), true);
            $newUid = $uid;
        } else {
            //游客账号跟已注册的fb账号绑定
            //注意多个游客账号可以绑定到同一个fb账号上
            $result = Model::account()->setBindUid($uid, $account['uid']);
            if (!$result) FF::throwException(RET::RET_FAILED);
            $newUid = $account['uid'];
            Bll::session()->save(array('uid' => $newUid, 'platform' => $platform));
            $userInfo = Bll::user()->getOne($newUid, 'nickname,avatar');
            $newNickname = $userInfo['nickname'];
            $newAvatar = $userInfo['avatar'];
        }

        return array(
            'uid' => (int)$newUid,
            'nickname' => $newNickname,
            'avatar' => $newAvatar
        );
    }

    /**
     * 设置头像
     */
    public function setAvatar()
    {
        $uid = $this->getUid();
        $avatar = $this->getParam('avatar');

        Bll::user()->setAvatar($uid, $avatar);

        return array(
            'avatar' => $avatar
        );
    }

    /**
     * 设置语言
     */
    public function setLanguage()
    {
        $uid = $this->getUid();
        $lang = $this->getParam('lang');

        $languages = array('ch', 'en');
        if (!in_array($lang, $languages)) FF::throwException(RET::PARAMS_INVALID);

        Bll::user()->setLang($uid, $lang);

        return array(
            'lang' => $lang
        );
    }

    /**
     * 设置昵称
     */
    public function setNickname()
    {
        $uid = $this->getUid();
        $nickname = $this->getParam('nickname');

        if ($nickname === '') FF::throwException(RET::RET_NICKNAME_IS_NULL);
        if (mb_strlen($nickname) > 32) FF::throwException(RET::RET_NICKNAME_TOO_LONG);

        $_nickname = Bll::user()->getField($uid, 'nickname');

        if ($nickname !== $_nickname) {
            Bll::user()->setNickname($uid, $nickname);
        }

        return array(
            'nickname' => $nickname,
        );
    }
}