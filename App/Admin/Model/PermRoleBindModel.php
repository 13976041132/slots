<?php
/**
 * 角色绑定模块
 */

namespace FF\App\Admin\Model;


use FF\Extend\MyModel;

class PermRoleBindModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_ADMIN, 't_perm_role_bind');
    }

    public function bind($aid, $roleId)
    {
        $data = array('aid' => $aid, 'roleId' => $roleId);

        return $this->insert($data);
    }

    public function unbind($aid, $roleId)
    {
        $where = array('aid' => $aid, 'roleId' => $roleId);

        return $this->delete($where);
    }

    public function getBindRoles($aid, $roleIds = null)
    {
        $where = array('aid' => $aid);
        if ($roleIds && is_array($roleIds)) {
            $where['roleId'] = array('in', $roleIds);
        }

        $result = $this->fetchAll($where);

        return $result ? array_column($result, 'roleId') : array();
    }

    public function getBindUsers($roleId)
    {
        $where = array('roleId' => $roleId);

        $result = $this->fetchAll($where);

        return $result ? array_column($result, 'aid') : array();
    }

    public function deleteByRoles($roleIds)
    {
        if (!$roleIds || !is_array($roleIds)) return false;

        $where = array(
            'roleId' => array('in', $roleIds)
        );

        return $this->delete($where, 0);
    }

    public function deleteByUsers($aids)
    {
        if (!$aids || !is_array($aids)) return false;

        $where = array(
            'aid' => array('in', $aids)
        );

        return $this->delete($where, 0);
    }
}