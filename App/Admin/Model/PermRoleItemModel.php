<?php
/**
 * 角色权限绑定模型
 */

namespace FF\App\Admin\Model;

use FF\Extend\MyModel;

class PermRoleItemModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_ADMIN, 't_perm_role_item');
    }

    public function addRoleItems($roleId, $itemIds)
    {
        if (!$itemIds || !is_array($itemIds)) {
            return 0;
        }

        $data = array();
        foreach ($itemIds as $itemId) {
            $data[] = array('roleId' => (int)$roleId, 'itemId' => $itemId);
        }

        return $this->insertMulti($data);
    }

    public function removeRoleItems($roleId, $itemIds)
    {
        if (!$itemIds || !is_array($itemIds)) {
            return 0;
        }

        $where = array(
            'roleId' => $roleId,
            'itemId' => array('in', $itemIds)
        );

        return $this->delete($where, count($itemIds));
    }

    public function getRoleItems($roleIds)
    {
        if (!$roleIds || !is_array($roleIds)) {
            return array();
        }

        $result = $this->fetchAll(array('roleId' => array('in', $roleIds)));

        $data = array();
        foreach ($result as $row) {
            $roleId = $row['roleId'];
            if (!isset($data[$roleId])) $data[$roleId] = array();
            $data[$roleId][] = $row['itemId'];
        }

        return $data;
    }

    public function getRolesByItem($itemId)
    {
        $result = $this->fetchAll(array('itemId' => $itemId));

        return $result ? array_column($result, 'roleId') : array();
    }

    public function getRolesByItems($itemIds)
    {
        $result = $this->fetchAll(array('itemId' => array('in', $itemIds)));

        $data = array();
        foreach ($result as $row) {
            $data[$row['itemId']][] = $row['roleId'];
        }

        return $data;
    }

    public function deleteByItems($itemIds)
    {
        if (!$itemIds || !is_array($itemIds)) return false;

        $where = array(
            'itemId' => array('in', $itemIds)
        );

        return $this->delete($where, 0);
    }

    public function deleteByRoles($roleIds)
    {
        if (!$roleIds || !is_array($roleIds)) return false;

        $where = array(
            'roleId' => array('in', $roleIds)
        );

        return $this->delete($where, 0);
    }
}