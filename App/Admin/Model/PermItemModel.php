<?php
/**
 * 权限项模型
 */

namespace FF\App\Admin\Model;

use FF\Extend\MyModel;

class PermItemModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_ADMIN, 't_perm_item');
    }

    public function getItems($itemIds)
    {
        if (!$itemIds || !is_array($itemIds)) {
            return array();
        }

        $result = $this->fetchAll(array('id' => array('in', $itemIds)));

        return array_column($result, null, 'id');
    }

    public function getItemsByGroup($groupId)
    {
        return $this->fetchAll(array('groupId' => $groupId), null, array('createTime' => 'asc'));
    }
}