<?php
/**
 * 管理员模块
 */

namespace FF\App\Admin\Model;

use FF\Extend\MyModel;

class AdminModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_ADMIN, 't_admin');
    }

    public function addOne($data)
    {
        return $this->insert($data);
    }

    public function getOne($id)
    {
        return $this->fetchOne(array('id' => $id));
    }

    public function getOneByAccount($account)
    {
        return $this->fetchOne(array('account' => $account));
    }

    public function getOneByName($name)
    {
        return $this->fetchOne(array('realname' => $name));
    }

    public function setStatus($id, $status)
    {
        return $this->update(array('status' => $status), array('id' => $id));
    }

    public function updateLogin($id)
    {
        $data = array(
            'lastLoginTime' => now(),
            'lastLoginIp' => get_ip()
        );

        return $this->update($data, array('id' => $id));
    }

    public function updateInfo($id, $data)
    {
        if (isset($data['password'])) {
            $data['password'] = md5($data['password']);
        }
        return $this->update($data, array('id' => $id));
    }
}