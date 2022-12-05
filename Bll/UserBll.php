<?php
/**
 * 用户业务逻辑
 */

namespace FF\Bll;

use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Factory\Model;
use FF\Factory\Sdk;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;
use FF\Framework\Utils\Log;
use FF\Library\Utils\Utils;
use FF\Service\Lib\Service;
use GPBClass\Enum\BUFF;
use GPBClass\Enum\PLATFORM;
use GPBClass\Enum\RET;

class UserBll
{
    private $buffers = array();

    private $dataTypes = array(
        'uid' => 'int',
        'nickname' => 'string',
        'avatar' => 'string',
        'platformAvatar' => 'string',
        'email' => 'string',
        'exp' => 'double',
        'level' => 'int',
        'vip' => 'int',
        'vipPts' => 'float',
        'recharge' => 'float',
        'coins' => 'double',
        'diamond' => 'int',
        'lang' => 'string',
    );

    /**
     * 获取用户当前的sessionId
     */
    public function getSessionId($uid)
    {
        $key = Keys::sessionId($uid);

        return Dao::redis()->get($key);
    }

    /**
     * 设置用户当前的sessionId
     */
    public function setSessionId($uid, $sessionId)
    {
        $key = Keys::sessionId($uid);

        return Dao::redis()->set($key, $sessionId);
    }

    /**
     * 清除用户当前的session
     */
    public function clearSession($uid)
    {
        $key = Keys::sessionId($uid);
        $sessionId = Dao::redis()->get($key);

        if ($sessionId) {
            Bll::session()->destroy($sessionId);
        }
    }

    /**
     * 验证用户token是否有效
     */
    public function verify($openid, $token, $platform)
    {
        if (!$openid) return RET::PARAMS_INVALID;

        if ($platform == PLATFORM::PLATFORM_GUEST) {
            return RET::SUCCESS;
        } elseif ($platform == PLATFORM::PLATFORM_FACEBOOK) {
            try {
                Sdk::facebook()->authLogin($openid, $token);
                return RET::SUCCESS;
            } catch (\Exception $e) {
                $log = array('openid' => $openid, 'token' => $token, 'code' => $e->getCode(), 'message' => $e->getMessage());
                Log::error($log, 'facebook.log');
                if ($e->getCode() == 401) {
                    return RET::RET_TOKEN_INVALID;
                } else {
                    return RET::FAILED;
                }
            }
        } elseif ($platform == PLATFORM::PLATFORM_APPLE) {
            try {
                Sdk::apple()->loginVerifyJwt($openid, $token);
                return RET::SUCCESS;
            } catch (\Exception $e) {
                $log = array('openid' => $openid, 'token' => $token, 'code' => $e->getCode(), 'message' => $e->getMessage());
                Log::error($log, 'applelogin.log');
                return RET::RET_TOKEN_INVALID;
            }
        } else {
            return RET::FAILED;
        }
    }

    /**
     * 根据openid查询用户账号信息
     */
    public function getAccountByOpenId($openid, $platform)
    {
        $account = Model::account()->getOneByOpenid($openid, $platform);

        //游客账号绑定到了另外一个非游客账号上
        if ($platform == PLATFORM::PLATFORM_GUEST && $account) {
            if ($account['bindUid']) {
                $account = Model::account()->getOneById($account['bindUid']);
            } elseif ($account['bindOpenid']) {
                $account['platform'] = PLATFORM::PLATFORM_FACEBOOK;
            }
        }

        //非游客账号绑定在一个游客账号上
        if ($platform == !PLATFORM::PLATFORM_GUEST && !$account) {
            $account = Model::account()->getOneByBindOpenid($openid);
            if ($account) $account['platform'] = $platform;
        }

        return $account;
    }

    /**
     * 获取用户账号信息
     */
    public function getAccountInfo($uid, $fields = null)
    {
        return Model::account()->getOneById($uid, $fields);
    }

    /**
     * 更新用户设备信息
     */
    public function updateDeviceInfo($uid, $deviceId, $deviceToken, $appsflyerId)
    {
        $updates = array(
            'deviceId' => $deviceId,
            'deviceToken' => $deviceToken,
            'appsflyerId' => $appsflyerId,
        );

        return Model::account()->updateById($uid, $updates);
    }

    /**
     * 获取用户登录天数
     */
    public function getLoginDays($uid)
    {
        return $this->getAccountInfo($uid, 'loginDays')['loginDays'];
    }

    /**
     * 获取用户注册总天数
     */
    public function getRegisterDays($uid)
    {
        $regTime = $this->getAccountInfo($uid, 'regTime')['regTime'];
        $regTime = strtotime($regTime);
        $nowTime = strtotime(date('Y-m-d'));
        return ceil(($nowTime - $regTime) / 86400);
    }

    public function getUidByInvitationCode($invitationCode)
    {
        $result = Model::user()->fetchOne(['invitationCode' => $invitationCode], 'uid');
        return $result ? $result['uid'] : '';
    }

    /**
     * 判断用户是否是回归用户
     * @param $lastTime
     * @return bool
     */
    public function isReturn($lastLoginTime)
    {
        $intervalTime = 7;
        if (is_null($intervalTime)) {
            return false;
        }
        $lastLoginTime = strtotime($lastLoginTime);
        $returnTime = $lastLoginTime + $intervalTime * 86400;
        return $returnTime >= time() ? false : true;
    }

    /**
     * 获取用户连续登录天数
     */
    public function getContinueLoginDays($uid)
    {
        return $this->getAccountInfo($uid, 'continued')['continued'];
    }

    /**
     * 用户注册
     */
    public function register($openid, $platform, $token, $version, $deviceId, $deviceToken, $appsflyerId)
    {
        $email = $nickname = $avatar = '';

        if ($platform == PLATFORM::PLATFORM_FACEBOOK) {
            $info = Sdk::facebook()->getUser($token);
            if (!$info) FF::throwException(RET::RET_TOKEN_INVALID);
            $nickname = $info['name'];
            $avatar = $info['picture'];
            $email = $info['email'] ?: '';
        }

        $uid = Model::account()->addOne($openid, $platform, $version, $deviceId, $deviceToken, $appsflyerId);

        if (!$uid) FF::throwException(RET::RET_ACCOUNT_CREATE_FAILED);

        if ($platform == PLATFORM::PLATFORM_GUEST) {
            $nickname = "G" . $uid;
        }

        $coins = 200000;
        $result = Model::user()->addOne($uid, $nickname, $avatar, $email, $coins);
        if (!$result) {
            Log::error(array($uid, $nickname, $avatar, $coins), 'register.log');
        }

        //防止缓存脏数据
        $key = Keys::userInfo($uid);
        if (Dao::redis()->exists($key)) {
            Dao::redis()->del($key);
        }

        return $uid;
    }

    /**
     * 获取用户信息
     */
    public function getOne($uid, $fields = null)
    {
        $key = Keys::userInfo($uid);

        if (!$fields || $fields == '*') {
            $fields = null;
        } elseif (!is_array($fields)) {
            $fields = explode(',', str_replace(' ', '', $fields));
        }

        if (Dao::redis()->hGet($key, 'uid')) {
            if (!$fields) {
                $result = Dao::redis()->hGetAll($key);
            } else {
                $result = Dao::redis()->hMGet($key, $fields);
            }
        } else {
            $result = Model::user()->getOne($uid);
            if ($result) Dao::redis()->hMset($key, $result);
            if ($result && $fields) {
                $result = array_recombine($result, $fields);
            }
        }

        foreach ($result as $field => $value) {
            $result[$field] = Utils::dataFormat($value, $this->dataTypes[$field]);
        }

        return $result;
    }

    /**
     * 根据用户ID批量获取用户信息
     */
    public function getMulti($uids, $fields = null)
    {
        $list = array();
        foreach ($uids as $uid) {
            $info = $this->getOne($uid, $fields);
            if ($info) $list[$uid] = $info;
        }
        return $list;
    }

    /**
     * 获取用户某个字段值
     */
    public function getField($uid, $field)
    {
        $result = $this->getOne($uid, $field);

        return $result && isset($result[$field]) ? $result[$field] : null;
    }

    /**
     * 获取用户游戏币数
     */
    public function getCoins($uid)
    {
        return (int)$this->getField($uid, 'coins');
    }

    /**
     * 获取用户钻石数
     */
    public function getDiamond($uid)
    {
        return (int)$this->getField($uid, 'diamond');
    }

    /**
     * 获取用户等级
     */
    public function getLevel($uid)
    {
        return (int)$this->getField($uid, 'level');
    }

    /**
     * 获取用户等级进度
     */
    public function getLevelProgress($level, $exp)
    {
        $nextLevel = $level + 1;
        $node = ceil($nextLevel / 100);
        $nextLevelCfg = Config::get('level/levels-' . $node, $nextLevel);
        if (!$nextLevelCfg) return 0;

        $progress = round($exp * 100 / $nextLevelCfg['exp'], 2);

        return min(100, $progress);
    }

    /**
     * 用户是否第一次使用facebook登录游戏
     * @param $loginType
     * @param $platform
     * @return bool
     */
    public function isFacebookFirstLogin($loginType, $platform)
    {
        return $loginType == 1 && $platform == PLATFORM::PLATFORM_FACEBOOK ? true : false;
    }

    /**
     * 获取用户VIP级别(兼容体验VIP)
     */
    public function getVip($uid)
    {
        $bufferKey = "VipLevel-{$uid}";
        if (!is_cli() && !Service::isRunning() && isset($this->buffers[$bufferKey])) {
            return $this->buffers[$bufferKey];
        }

        $vip = (int)$this->getField($uid, 'vip');

        if ($vip < VIP_MIN_LEVEL) {
            if ($this->getBuff($uid, BUFF::VIP_TRIAL)) {
                $vip = VIP_MIN_LEVEL;
            } else {
                $vip = VIP_MIN_LEVEL - 1;
            }
        }

        if (!is_cli() && !Service::isRunning()) {
            $this->buffers[$bufferKey] = $vip;
        }

        return $vip;
    }

    /**
     * 获取用户VIP进度
     */
    public function getVipProgress($vip, $vipPts)
    {
        $nextVip = $vip + 1;
        $vipCfg = Config::get('common/vip', $vip, false);
        $nextVipCfg = Config::get('common/vip', $nextVip, false);
        if (!$nextVipCfg) return 0;

        $startPts = $vipCfg ? $vipCfg['pts'] : 0;
        $progress = round(($vipPts - $startPts) * 100 / ($nextVipCfg['pts'] - $startPts), 2);

        return min(100, $progress);
    }

    /**
     * 判断用户是否是VIP(兼容体验VIP)
     */
    public function isVip($uid)
    {
        $vip = $this->getVip($uid);

        return $vip >= VIP_MIN_LEVEL;
    }

    /**
     * 判断用户是否是真正的VIP
     */
    public function isRealVip($uid)
    {
        $vip = (int)$this->getField($uid, 'vip');

        return $vip >= VIP_MIN_LEVEL;
    }

    /**
     * 获取用户充值额度
     */
    public function getRecharge($uid)
    {
        return (float)$this->getField($uid, 'recharge');
    }

    /**
     * 获取用户Spin积分
     */
    public function getSpinPts($uid)
    {
        return (float)$this->getField($uid, 'spinPts');
    }

    /**
     * 更新用户信息
     */
    public function updateInfo($uid, $data, $sync = false)
    {
        $key = Keys::userInfo($uid);
        if (!Dao::redis()->hGet($key, 'uid')) {
            return Model::user()->updateInfo($uid, $data);
        } else {
            if ($sync) {
                Model::user()->updateInfo($uid, $data);
            }
            return Dao::redis()->hMset($key, $data);
        }
    }

    /**
     * 增量更新用户某个字段值
     */
    public function updateFieldByInc($uid, $field, $num, $reason = '', &$newValue = null)
    {
        if (!$num) return false;

        $key = Keys::userInfo($uid);

        if (!Dao::redis()->hGet($key, 'uid')) {
            $updates = array($field => array('+=', $num));
            $where = $num < 0 ? array($field => array('>=', -$num)) : array();
            $result = Model::user()->updateInfo($uid, $updates, $where);
            if ($result) {
                $newValue = $this->getField($uid, $field);
            }
        } else {
            if ($this->dataTypes[$field] == 'float') {
                $num = (float)$num;
                $newValue = Dao::redis()->hIncrByFloat($key, $field, $num);
            } else {
                $num = (int)$num;
                $newValue = Dao::redis()->hIncrBy($key, $field, $num);
            }
            if ($num < 0 && $newValue < 0) {
                if ($this->dataTypes[$field] == 'float') {
                    Dao::redis()->hIncrByFloat($key, $field, -$num);
                } else {
                    Dao::redis()->hIncrBy($key, $field, -$num);
                }
                $result = false;
            } else {
                $result = true;
            }
        }

        if ($reason && $result && in_array($field, ['coins', 'diamond'])) {
            Bll::log()->addDataLog($uid, $field, $num, $newValue, $reason);
        }
        return $result;
    }

    /**
     * 设置用户头像
     */
    public function setAvatar($uid, $avatar)
    {
        return $this->updateInfo($uid, array('avatar' => $avatar), true);
    }

    /**
     * 设置用户客户端语言
     */
    public function setLang($uid, $lang)
    {
        return $this->updateInfo($uid, array('lang' => $lang), true);
    }

    /**
     * 给用户增加游戏币
     */
    public function addCoins($uid, $coins, $reason = '', &$balance = null)
    {
        if ($coins <= 0) return false;

        return $this->updateFieldByInc($uid, 'coins', $coins, $reason, $balance);
    }

    /**
     * 扣除用户游戏币
     */
    public function decCoins($uid, $coins, $reason = '', $check = true)
    {
        if ($coins <= 0) return false;

        if ($check) {
            if ($this->getCoins($uid) < $coins) return false;
        }

        return $this->updateFieldByInc($uid, 'coins', -$coins, $reason);
    }

    /**
     * 给用户增加钻石
     */
    public function addDiamond($uid, $diamond, $reason = '', &$balance = null)
    {
        if ($diamond <= 0) return false;

        return $this->updateFieldByInc($uid, 'diamond', $diamond, $reason, $balance);
    }

    /**
     * 扣除用户钻石
     */
    public function decDiamond($uid, $diamond, $reason = '')
    {
        if ($diamond <= 0) return false;
        if ($this->getDiamond($uid) < $diamond) return false;

        return $this->updateFieldByInc($uid, 'diamond', -$diamond, $reason);
    }

    /**
     * 设置用户等级
     */
    public function setLevel($uid, $level)
    {
        $updates = array('level' => $level, 'exp' => 0);

        return $this->updateInfo($uid, $updates, true);
    }

    /**
     * 设置用户昵称
     */
    public function setNickname($uid, $nickname)
    {
        return $this->updateInfo($uid, array('nickname' => $nickname), true);
    }

    /**
     * 给用户增加经验值
     */
    public function addExp($uid, &$num, $levelInfo = null, $isVipSpeed = false)
    {
        if ($num <= 0) return false;

        if (!$levelInfo) {
            $levelInfo = $this->getOne($uid, 'exp,level');
            if (!$levelInfo) return false;
        }
        $levelUpBonus = 0;
        $totalBonusExp = 0;
        $vipLevel = $this->getVip($uid);
        $vipConfig = Config::get('common/vip', $vipLevel);

        //经验加速
        $buffInfo = $this->getBuff($uid, BUFF::LEVEL_BURST);
        if ($buffInfo && !empty($buffInfo['options']['value'])) {
            $totalBonusExp += ceil((float)bcmul($num, $buffInfo['options']['value'], 2));
        }
        if ($isVipSpeed && $vipConfig) {
            $levelUpBonus = $vipConfig['level-up-bonus'];
            $totalBonusExp += ceil((float)bcmul($num, $vipConfig['exp-bonus'], 2));
        }
        $num += $totalBonusExp;
        $exp = $levelInfo['exp'];
        $level = $levelInfo['level'];
        $node = ceil(($level + 1) / 100);
        $nextLevelConfig = Config::get('level/levels-' . $node, $level + 1);

        $exp += $num;
        $levelUp = false;
        $coins = 0;

        //判断是否需要升级，支持连续升级
        while ($exp >= $nextLevelConfig['exp']) {
            $exp -= $nextLevelConfig['exp'];
            if (!empty($nextLevelConfig['coins'])) {
                $coins += $isVipSpeed ? (int)bcmul($nextLevelConfig['coins'], $levelUpBonus, 2) : 0;
                $coins += (int)$nextLevelConfig['coins'];
            }
            $levelUp = true;
            $level += 1;
            $node = ceil(($level + 1) / 100);
            $nextLevelConfig = Config::get('level/levels-'.$node, $level + 1);
            if (!$nextLevelConfig) {
                $exp = 0;
                break;
            }
        }
        $updates = array();
        if ($level != $levelInfo['level']) $updates['level'] = $level;
        if ($exp != $levelInfo['exp']) $updates['exp'] = $exp;

        if (!defined('TEST_ID')) {
            if ($updates) {
                $this->updateInfo($uid, $updates, $levelUp);
            }
            if ($levelUp && $coins) {
                $this->addCoins($uid, $coins, 'LevelUp');
            }
        }

        return array(
            'exp' => $exp,
            'level' => $level,
            'coins' => $coins,
            'levelUp' => $levelUp,
        );
    }

    /**
     * 给用户增加vip积分
     */
    public function addVipPts($uid, $pts, $reason = '')
    {
        $userInfo = $this->getOne($uid, 'vip,vipPts');
        if (!$userInfo) return false;

        //vip加速
        $buffInfo = $this->getBuff($uid, BUFF::VIP_BOOSTER);
        if ($buffInfo && !empty($buffInfo['options']['value'])) {
            $pts = (float)bcmul($pts, $buffInfo['options']['value'], 2);
        }

        $vip = $userInfo['vip'];
        $vipPts = $userInfo['vipPts'];

        $vipPts = (float)bcadd($vipPts, $pts, 2);
        $nextVipConfig = Config::get('common/vip', $vip + 1);
        $levelUp = false;
        $levelUpped = 0;

        while ($nextVipConfig && $vipPts >= $nextVipConfig['pts']) {
            $vip += 1;
            $nextVipConfig = Config::get('common/vip', $vip + 1);
            $levelUp = true;
            $levelUpped += 1;
        }

        $updates = array('vipPts' => $vipPts);
        if ($levelUp) $updates['vip'] = $vip;

        //成为vip时，移除体验vip状态
        if ($levelUp) {
            $this->removeBuff($uid, BUFF::VIP_TRIAL);
            //Bll::biData()->eventReport($uid, 'vip_level_up', array('vip' => $vip));
        }

        $this->updateInfo($uid, $updates, $levelUp);

        return array(
            'vip' => $vip,
            'vipPts' => $vipPts
        );
    }

    /**
     * 更新用户充值额度
     */
    public function addRecharge($uid, $recharge)
    {
        $this->updateFieldByInc($uid, 'recharge', $recharge);
    }

    /**
     * 更新用户Spin积分
     */
    public function addSpinPts($uid, &$pts)
    {
        //spin加速
        $buffInfo = $this->getBuff($uid, BUFF::SPIN_BURST);
        if ($buffInfo && !empty($buffInfo['options']['value'])) {
            $pts = (float)bcmul($pts, $buffInfo['options']['value'], 2);
        }

        $this->updateFieldByInc($uid, 'spinPts', $pts);
    }

    /**
     * 清除用户Spin积分
     */
    public function clearSpinPts($uid)
    {
        $this->updateInfo($uid, array('spinPts' => 0));
    }

    /**
     * 查询用户所有的Buff信息
     */
    public function getBuffs($uid)
    {
        $key = Keys::buffInfo($uid);
        $buffs = Dao::redis()->hGetAll($key);

        $now = time();
        $expired = array();
        foreach ($buffs as $buff => &$buffInfo) {
            $buffInfo = json_decode($buffInfo, true);
            if ($buffInfo['expire'] <= $now) {
                $expired[] = $buff;
                unset($buffs[$buff]);
            }
        }

        if ($expired) {
            Dao::redis()->hDel($key, ...$expired);
        }

        return $buffs;
    }

    /**
     * 查询用户所有的Buff信息(列表形式)
     */
    public function getBuffList($uid)
    {
        $buffs = $this->getBuffs($uid);

        $buffList = array();
        foreach ($buffs as $buff => $buffInfo) {
            $buffList[] = array(
                'buff' => $buff,
                'expire' => $buffInfo['expire'],
                'options' => json_encode($buffInfo['options'])
            );
        }

        return $buffList;
    }

    /**
     * 查询用户的单项Buff信息
     */
    public function getBuff($uid, $buff)
    {
        $key = Keys::buffInfo($uid);
        $buffInfo = Dao::redis()->hGet($key, $buff);
        $buffInfo = $buffInfo ? json_decode($buffInfo, true) : null;

        if (!$buffInfo) return null;
        if ($buffInfo['expire'] <= time()) return null;

        return $buffInfo;
    }

    /**
     * 给用户添加Buff
     */
    public function addBuff($uid, $buff, $duration, $options = array())
    {
        $key = Keys::buffInfo($uid);
        $buffInfo = Dao::redis()->hGet($key, $buff);
        $buffInfo = $buffInfo ? json_decode($buffInfo, true) : array();

        //设置Buff失效时间
        if (isset($buffInfo['expire'])) {
            $buffInfo['expire'] = max(time(), $buffInfo['expire']);
            $buffInfo['expire'] += $duration;
        } else {
            $buffInfo['expire'] = time() + $duration;
        }

        $buffInfo['options'] = $options ? $options : array();

        Dao::redis()->hSet($key, $buff, json_encode($buffInfo));

        return $buffInfo['expire'];
    }

    /**
     * 移除Buff状态
     */
    public function removeBuff($uid, $buff)
    {
        $key = Keys::buffInfo($uid);

        return Dao::redis()->hDel($key, $buff);
    }

    /**
     * 根据搜索条件查找用户
     */
    public function query($search)
    {
        if (!is_array($search) || !$search) {
            return null;
        }

        $user = null;

        if (!empty($search['uid'])) {
            $user = $this->getOne($search['uid']);
        } elseif (!empty($search['openid']) && !empty($search['platform'])) {
            $account = Model::account()->getOneByOpenid($search['openid'], $search['platform']);
            if ($account) {
                $user = $this->getOne($account['uid']);
            }
        }

        return $user;
    }

    /**
     * 机器人数据回写DB
     */
    public function restore($uid, $fields)
    {
        $key = Keys::userInfo($uid);
        if (!Dao::redis()->hGet($key, 'uid')) return;

        $info = Dao::redis()->hMGet($key, $fields);

        if ($info) {
            Model::user()->updateInfo($uid, $info);
        }
    }
}