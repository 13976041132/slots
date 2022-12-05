<?php
/**
 * 管理员业务逻辑
 */

namespace FF\Bll;

use FF\Factory\Bll;
use FF\Factory\Model;
use FF\Framework\Common\Code;
use FF\Framework\Core\FF;

class AdminBll
{
    public function login($account, $password)
    {
        if (!$account || !$password) {
            FF::throwException(Code::PARAMS_MISSED);
        }

        $admin = Model::admin()->getOneByAccount($account);

        if (!$admin) {
            FF::throwException(Code::PARAMS_INVALID, '账户不存在');
        }

        if ($admin['password'] != md5($password)) {
            FF::throwException(Code::PARAMS_INVALID, '密码错误');
        }

        if (!$admin['status']) {
            FF::throwException(Code::FAILED, '账户已禁止登陆');
        }

        Model::admin()->updateLogin($admin['id']);

        $session = array(
            'id' => $admin['id'],
            'account' => $admin['account'],
            'realname' => $admin['realname']
        );

        $sessionId = Bll::session()->create($admin['id'], $session, 30 * 86400);

        return array('sessionId' => $sessionId);
    }

    public function isValid($id)
    {
        $admin = Model::admin()->getOne($id);

        return $admin && $admin['status'] == 1;
    }

    public function modifyPassword($id, $oldPassword, $newPassword)
    {
        $admin = Model::admin()->getOne($id);

        if ($admin['password'] != md5($oldPassword)) {
            FF::throwException(Code::FAILED, '旧密码不正确');
        }

        if ($oldPassword == $newPassword) {
            FF::throwException(Code::FAILED, '新密码和旧密码不能完全一致');
        }

        $data = array('password' => $newPassword);

        return Model::admin()->updateInfo($id, $data);
    }
}