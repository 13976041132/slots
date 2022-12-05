<?php
/**
 * 权限相关
 */

namespace FF\App\Admin\Controller;

use FF\Factory\Bll;
use FF\Framework\Core\FF;
use FF\Framework\Common\Code;
use FF\Factory\Model;
use FF\Library\Utils\Pager;

class PermController extends BaseController
{
    /**
     * @ignore permission
     */
    public function initPerms()
    {
        Bll::perm()->initPerms();
        exit;
    }

    /**
     * 查询权限组
     */
    public function group()
    {
        $data['groups'] = Model::permGroup()->fetchAll(null, null, array('createTime' => 'asc'));

        $this->display('group.html', $data);
    }

    /**
     * 查询权限项
     */
    public function item()
    {
        $page = (int)$this->getParam('page', false, 1);
        $limit = (int)$this->getParam('limit', false, 10);
        $groupId = $this->getParam('groupId', false);
        $keywords = $this->getParam('keywords', false);

        $where = array();
        if ($groupId) {
            $where['groupId'] = $groupId;
        }
        if ($keywords !== '') {
            $keywords = addslashes($keywords);
            $where[] = array('sql', "id LIKE '%{$keywords}%' OR name LIKE '%{$keywords}%'");
        }
        $orderBy = array('createTime' => 'asc');
        $pageData = Model::permItem()->getPageList($page, $limit, $where, null, $orderBy);

        $groups = Model::permGroup()->fetchAll(null, null, array('createTime' => 'asc'));
        $data['groups'] = array_column($groups, null, 'id');
        $data['list'] = $pageData['list'];
        $data['pager'] = new Pager($pageData);

        $this->display('item.html', $data);
    }

    /**
     * 查询角色
     */
    public function role()
    {
        $page = (int)$this->getParam('page', false, 1);
        $limit = (int)$this->getParam('limit', false, 10);
        $keywords = $this->getParam('keywords', false);

        $where = array();
        if ($keywords !== '') {
            $keywords = addslashes($keywords);
            $where['name'] = array('like', "%{$keywords}%");
        }
        $orderBy = array('id' => 'desc');
        $pageData = Model::permRole()->getPageList($page, $limit, $where, null, $orderBy);
        $roleIds = array_column($pageData['list'], 'id');
        $roleItems = Model::permRoleItem()->getRoleItems($roleIds);
        $itemIds = array();
        foreach ($roleItems as $ids) {
            $itemIds = array_merge($itemIds, $ids);
        }
        $itemIds = array_unique($itemIds);

        $data['roles'] = $pageData['list'];
        $data['roleItems'] = $roleItems;
        $data['items'] = Model::permItem()->getItems($itemIds);
        $data['pager'] = new Pager($pageData);

        $this->display('role.html', $data);
    }

    /**
     * 创建权限组
     */
    public function createGroup()
    {
        $data = array(
            'id' => $this->getParam('id'),
            'name' => $this->getParam('name'),
            'createTime' => now()
        );

        return $this->_create('permGroup', $data);
    }

    /**
     * 创建权限项
     */
    public function createItem()
    {
        $data = array(
            'id' => $this->getParam('id'),
            'groupId' => $this->getParam('groupId'),
            'name' => $this->getParam('name'),
            'createTime' => now()
        );

        return $this->_create('permItem', $data);
    }

    /**
     * 创建角色
     */
    public function createRole()
    {
        $data = array(
            'name' => $this->getParam('name')
        );

        return $this->_create('permRole', $data);
    }

    protected function checkData($modelName, &$data, $oldData, $action)
    {
        if (in_array($modelName, ['permGroup', 'permItem']) && isset($data['id'])) {
            if (call_user_func(array($this->model($modelName), 'getOneById'), $data['id'])) {
                FF::throwException(Code::FAILED, 'Id已存在');
            }
        }
    }

    protected function getEditableFields($model)
    {
        $fields = array(
            'permGroup' => ['name'],
            'permItem' => ['name'],
            'permRole' => ['name'],
        );

        if (!isset($fields[$model])) return array();

        return $fields[$model];
    }

    /**
     * 删除权限组
     */
    public function deleteGroup()
    {
        $id = $this->getParam('id');

        $result = $this->_delete('permGroup', $id);

        Bll::perm()->deletePermItemByGroup($id);

        return $result;
    }

    /**
     * 删除权限项
     */
    public function deleteItem()
    {
        $id = $this->getParam('id');

        $result = $this->_delete('permItem', $id);

        Bll::perm()->deletePermItemById($id);
        Bll::perm()->deleteRoleItemByItem($id);

        return $result;
    }

    /**
     * 删除角色
     */
    public function deleteRole()
    {
        $id = $this->getParam('id');

        $result = $this->_delete('permRole', $id);

        Bll::perm()->deleteRoleItemByRole($id);
        Bll::perm()->deleteRoleBindByRole($id);

        return $result;
    }

    /**
     * @ignore permission
     */
    public function getGroups()
    {
        return Model::permGroup()->fetchAll(null, null, array('createTime' => 'asc'));
    }

    /**
     * @ignore permission
     */
    public function getRoles()
    {
        return Model::permRole()->fetchAll();
    }

    /**
     * @ignore permission
     */
    public function getGroupItems()
    {
        $groupId = $this->getParam('groupId');

        return Model::permItem()->getItemsByGroup($groupId);
    }

    /**
     * @ignore permission
     */
    public function getRoleItems()
    {
        $roleId = (int)$this->getParam('roleId');

        $items = Model::permRoleItem()->getRoleItems([$roleId]);

        return $items ? $items[$roleId] : array();
    }

    /**
     * @ignore permission
     */
    public function getBindUsers()
    {
        $roleId = (int)$this->getParam('roleId');

        return Model::permRoleBind()->getBindUsers($roleId);
    }

    /**
     * @ignore permission
     */
    public function grant()
    {
        $roleId = (int)$this->getParam('roleId');
        $itemIds = $this->getParam('itemIds');
        $itemIds = explode(',', $itemIds);

        Model::permRoleItem()->addRoleItems($roleId, $itemIds);

        return array('message' => '已授权');
    }

    /**
     * @ignore permission
     */
    public function removeGrant()
    {
        $roleId = (int)$this->getParam('roleId');
        $itemIds = $this->getParam('itemIds');
        $itemIds = explode(',', $itemIds);

        Model::permRoleItem()->removeRoleItems($roleId, $itemIds);

        return array('message' => '已解除授权');
    }

    /**
     * @ignore permission
     */
    public function bindRole()
    {
        $aid = (int)$this->getParam('aid');
        $roleId = (int)$this->getParam('roleId');

        Model::permRoleBind()->bind($aid, $roleId);

        return array('message' => '已绑定');
    }

    /**
     * @ignore permission
     */
    public function unbindRole()
    {
        $aid = (int)$this->getParam('aid');
        $roleId = (int)$this->getParam('roleId');

        Model::permRoleBind()->unbind($aid, $roleId);

        return array('message' => '已解除绑定');
    }
}