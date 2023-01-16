<?php
/**
 * 用户管理
 */

namespace FF\App\Admin\Controller;

use FF\Factory\Bll;
use FF\Factory\Model;
use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;
use GPBClass\Enum\PLATFORM;

class UserController extends BaseController
{
    /**
     * 查询用户
     */
    public function index()
    {
        $uid = (int)$this->getParam('uid', false);
        $accountInfo = [];
        if ($uid) {
            $accountInfo = Model::account()->fetchOne($uid);
        }

        $data = array();
        if ($accountInfo) {
            $userInfo = array_merge($accountInfo, Bll::user()->getOne($uid));
            $userInfo['spinTimes'] = Bll::analysis()->getField($uid,'spinTimes');
            $countries = array_flip(Config::get('country-codes'));
            $userInfo['country'] = $countries[$userInfo['country']] ?? $userInfo['country'];
            $data['userInfo'] = $userInfo;
        }

        $this->display('index.html', $data);
    }

    /**
     * 打开金手指工具
     */
    public function edit()
    {
        $this->display('edit.html');
    }

    /**
     * 封号/解封
     */
    public function setStatus()
    {
        $uid = (int)$this->getParam('uid');
        $status = (int)$this->getParam('status');

        Model::account()->updateById($uid, array('status' => $status));

        return array('reload' => true, 'message' => $status ? '已解封' : '已封号');
    }

    /**
     * FB账号解绑
     */
    public function unbind()
    {
        $uid = (int)$this->getParam('uid');

        $account = Model::account()->getOneById($uid);
        if ($account['platform'] == PLATFORM::PLATFORM_GUEST) {
            FF::throwException(Code::FAILED, '该账号是游客账号，无需解绑');
        }

        $newOpenId = $account['bindGuest'];
        if (Model::account()->getOneByOpenid($account['bindGuest'], PLATFORM::PLATFORM_GUEST)) {
            $newOpenId .= '_' . time();
        }

        Model::account()->updateById($uid, array(
            'openid' => $newOpenId,
            'platform' => PLATFORM::PLATFORM_GUEST,
            'bindGuest' => ''
        ));

        return array('reload' => true, 'message' => '已解绑');
    }

    /**
     * 删除账号
     */
    public function invalid()
    {
        $uid = (int)$this->getParam('uid');

        $account = Model::account()->getOneById($uid, 'openid');
        if (!$account) FF::throwException(Code::FAILED, '用户不存在');

        $openid = explode('|:|', $account['openid'])[0];
        $newOpenid = $openid . '|:|' . time();
        Model::account()->updateById($uid, array('openid' => $newOpenid, 'status' => 2));

        return array('reload' => true, 'message' => '账号已删除');
    }

    /**
     * 金手指修改用户数据
     */
    public function updateInfo()
    {
        $uid = (int)$this->getParam('uid');
        $coins = (int)$this->getParam('coins', false);
        $regtime = (string)$this->getParam('regtime', false);
        $loginTime = (string)$this->getParam('loginTime', false);

        if (!$coins && !$regtime && !$loginTime) {
            FF::throwException(Code::PARAMS_INVALID, '请至少修改一项数据');
        }

        if (!Bll::user()->getOne($uid)) {
            FF::throwException(Code::PARAMS_INVALID, '用户不存在');
        }

        if ($coins > 0) Bll::user()->addCoins($uid, $coins, 'GoldFinger');
        if ($coins < 0) Bll::user()->decCoins($uid, -$coins, 'GoldFinger');
        if ($regtime) {
            $regtime = date('Y-m-d H:i:s', strtotime($regtime));
            Model::account()->updateById($uid, array('regtime' => $regtime));
        }
        if ($loginTime) {
            $loginTime = date('Y-m-d H:i:s', strtotime($loginTime));
            Model::account()->updateById($uid, array('lastLoginTime' => $loginTime));
        }

        return array('reload' => true, 'message' => '已保存');
    }

    /**
     * 查看玩家余额波动
     */
    public function balances()
    {
        $uid = $this->getParam('uid');
        $page = $this->getParam('page', false, 1);
        $limit = $this->getParam('limit', false, 500);
        $table = $this->getParam('table', false);
        $start = $this->getParam('start', false);
        $end = $this->getParam('end', false);

        $where = array();
        $where['uid'] = $uid;
        if ($start) $where['w1'] = array('sql', "time >= '" . addslashes($start) . "'");
        if ($end) $where['w2'] = array('sql', "time <= '" . addslashes($end) . "'");
        $where['settled'] = 1;

        $data = Model::betLog($table)->getPageList($page, $limit, $where, 'id,machineId,betSeq,balance', 'time asc');

        $machines = Model::machine()->getAll();
        $machinesList = array_column($machines, null, 'machineId');

        $points = [];
        $plotLines = [];
        $curMachineId = '';
        foreach ($data['list'] as $index => $listPoint) {
            $points[] = array(
                'y' => $listPoint['balance'],
                'x' => $index,
                'id' => $listPoint['id'],
                'betSeq' => $listPoint['betSeq'],
                'machineId' => $listPoint['machineId'],
                'machineName' => $machinesList[$listPoint['machineId']]['name']
            );
            if ($curMachineId != $listPoint['machineId']) {
                $curMachineId = $listPoint['machineId'];
                $plotLines[] = array(
                    'color' => '#FF0000',
                    'width' => 2,
                    'value' => $index
                );
            }
        }
        $data['list'] = $points;
        $data['plotLines'] = $plotLines;

        //历史表
        $data['tables'] = Model::betLog()->getHistoryTables();

        $this->display('balances.html', $data);
    }

    /**
     * 用户分组信息
     */
    public function userGroup()
    {
        $uid = (int)$this->getParam('uid', false);

        if ($uid) {
            $accountInfo = Bll::account()->getAccountInfo($uid);
            if ($accountInfo['appId'] != APP_ID) {
                $accountInfo = null;
            }
        } else {
            $accountInfo = null;
        }

        $data = array();
        if ($accountInfo) {
            $uid = $accountInfo['uid'];
            $analysisInfo = Bll::analysis()->getAnalysisInfo($uid, 'spinTimes');
            $userInfo = array_merge($accountInfo, Bll::user()->getUserInfo($uid), $analysisInfo);
            $countries = array_flip(Config::get('country-codes'));
            $userInfo['country'] = $countries[$userInfo['country']] ?? $userInfo['country'];
            $data['userInfo'] = $userInfo;

            $groupInfo = array();
            $groupInfo['biGroup'] = Bll::userGroup()->getUserGroup($uid, 'Threshold_Label');
            $groupInfo['userCfg'] = Config::get('common/user-class-id');
            $groupInfo['groupId'] = Bll::userGroup()->getUserGroupId($uid);

            $config = array();
            foreach (['activity/activities', 'common/ad_reward', 'common/general', 'common/popup-item'] as $name) {
                $config[$name]['ids'] = Bll::userGroup()->getUserGroupCfg($uid, $name);
                $config[$name]['cfg'] = Config::get($name);
            }
            $groupInfo['config'] = $config;

            $data['groupInfo'] = $groupInfo;
        }

        $this->display('userGroup.html', $data);
    }
}