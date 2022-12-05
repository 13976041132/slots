<?php
/**
 * 权限业务逻辑
 */

namespace FF\Bll;

use FF\Factory\Model;
use FF\Framework\Utils\Config;

class PermBll
{
    public function hasPerm($aid, $permItemId)
    {
        //检查权限项是否存在，不存在则默认允许访问
        $permItem = Model::permItem()->getOneById($permItemId);
        if (!$permItem) return true;

        //获取绑定了此权限项的角色
        $roleIds = Model::permRoleItem()->getRolesByItem($permItemId);
        if (!$roleIds) return false;

        //获取授权给用户的所有角色
        $bindRoles = Model::permRoleBind()->getBindRoles($aid);

        //检查角色交集
        if ($bindRoles && array_intersect($roleIds, $bindRoles)) {
            return true;
        } else {
            return false;
        }
    }

    public function getPermsInGroup($aid, $groupId)
    {
        //获取权限组下的权限项
        $permItems = Model::permItem()->getItemsByGroup($groupId);
        if (!$permItems) return array();

        //获取分配了这些权限项的角色
        $itemIds = array_column($permItems, 'id');
        $roleItems = Model::permRoleItem()->getRolesByItems($itemIds);
        if (!$roleItems) return array();

        //获取用户被分配的角色
        $roleIds = Model::permRoleBind()->getBindRoles($aid);
        if (!$roleIds) return array();

        //扫描用户拥有的权限项
        $permOwn = array();
        foreach ($roleItems as $itemId => $_roleIds) {
            if (array_intersect($roleIds, $_roleIds)) {
                $permOwn[] = $itemId;
            }
        }

        return $permOwn;
    }

    //按组删除权限项
    public function deletePermItemByGroup($groupId)
    {
        $permItems = Model::permItem()->getItemsByGroup($groupId);
        if (!$permItems) return;

        $itemIds = array_column($permItems, 'id');
        $this->deleteRoleItemByItem($itemIds);
    }

    //按权限ID删除权限项
    public function deletePermItemById($itemId)
    {
        Model::permItem()->deleteById($itemId);
    }

    //按权限项删除角色授权
    public function deleteRoleItemByItem($itemIds)
    {
        if (!$itemIds) return;
        if (!is_array($itemIds)) $itemIds = [$itemIds];

        Model::permRoleItem()->deleteByItems($itemIds);
    }

    //按角色删除角色授权
    public function deleteRoleItemByRole($roleIds)
    {
        if (!$roleIds) return;
        if (!is_array($roleIds)) $roleIds = [$roleIds];

        Model::permRoleItem()->deleteByRoles($roleIds);
    }

    //按角色删除用户角色绑定
    public function deleteRoleBindByRole($roleIds)
    {
        if (!$roleIds) return;
        if (!is_array($roleIds)) $roleIds = [$roleIds];

        Model::permRoleBind()->deleteByRoles($roleIds);
    }

    //按用户删除用户角色绑定
    public function deleteRoleBindByUser($aids)
    {
        if (!$aids) return;
        if (!is_array($aids)) $aids = [$aids];

        Model::permRoleBind()->deleteByUsers($aids);
    }

    //进行权限初始化
    public function initPerms()
    {
        $groups = array();
        $permItems = array();
        $menus = Config::get('menus');
        $time = strtotime('2019-02-27 14:30:00');

        $groups[] = array('id' => 'menu1', 'name' => '一级菜单', 'createTime' => date('Y-m-d H:i:s', $time++));
        $groups[] = array('id' => 'menu2', 'name' => '二级菜单', 'createTime' => date('Y-m-d H:i:s', $time++));

        //权限分组
        //一级菜单权限项
        foreach ($menus as $menu) {
            if ($menu['name'] == '首页') continue;
            if (!empty($menu['hidden'])) continue;
            $groupId = strtolower($menu['permGroup']);
            $groupName = str_replace('管理', '', $menu['name']) . '管理';
            $groups[] = array('id' => $groupId, 'name' => $groupName, 'createTime' => date('Y-m-d H:i:s', $time++));
            $permItemId = '/menu1/' . $groupId;
            $permItems[] = array(
                'id' => $permItemId, 'groupId' => 'menu1', 'name' => $menu['name'], 'createTime' => date('Y-m-d H:i:s', $time++)
            );
        }

        //二级菜单权限项
        foreach ($menus as $menu) {
            if (empty($menu['children'])) continue;
            foreach ($menu['children'] as $m) {
                if (!empty($m['hidden'])) continue;
                $permItemId = '/menu2' . strtolower($m['uri']);
                $permItems[] = array(
                    'id' => $permItemId, 'groupId' => 'menu2', 'name' => $m['name'], 'createTime' => date('Y-m-d H:i:s', $time++)
                );
            }
        }

        //所有路由权限项
        $excludeMethods = array();
        $ref = new \ReflectionClass('FF\\App\\Admin\\Controller\\BaseController');
        $methods = $ref->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $excludeMethods[$method->getName()] = 1;
        }
        $files = scandir(PATH_ROOT . '/App/Admin/Controller');
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            $className = substr($file, 0, -4);
            $classGroup = substr($className, 0, -10);
            if (in_array($classGroup, ['Base', 'SamplePlan', 'SlotsTest', 'Tools'])) continue;
            $fullClassName = 'FF\\App\\Admin\\Controller\\' . $className;
            $ref = new \ReflectionClass($fullClassName);
            $methods = $ref->getMethods(\ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $method) {
                $methodName = $method->getName();
                if (isset($excludeMethods[$methodName])) continue;
                //获取方法注释，第一行有效注释识别为权限名称
                $comment = $method->getDocComment();
                if ($comment === false) $comment = "/**\n * {$methodName}\n */";
                if (strpos($comment, '@ignore permission') !== false) continue;
                $comments = explode("\n", $comment);
                $comments = array_values(array_filter($comments));
                $comment = mb_substr(trim($comments[1]), 2);
                $groupId = strtolower($classGroup);
                if ($classGroup == 'Machine') {
                    $groups[] = array('id' => 'machine', 'name' => '机台管理', 'createTime' => date('Y-m-d H:i:s', $time++));
                }
                if ($classGroup == 'SampleGroup') {
                    $groupId = strtolower('SampleLib');
                } elseif ($classGroup == 'AbTest') {
                    $groupId = 'test';
                }
                $permItemId = '/' . strtolower($classGroup . '/' . $methodName);
                $permItems[] = array(
                    'id' => $permItemId, 'groupId' => $groupId, 'name' => $comment, 'createTime' => date('Y-m-d H:i:s', $time++)
                );
                echo $permItemId . ' ' . $comment . '<br>';
            }
        }

        Model::permGroup()->insertMulti($groups, true);
        Model::permItem()->insertMulti($permItems, true);
    }
}