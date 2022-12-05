<?php
/**
 * 版本构建模型
 */

namespace FF\App\Admin\Model;

use FF\Extend\MyModel;

class VersionBuildModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_ADMIN, 't_version_build');
    }

    public function setAuditId($id, $auditId)
    {
        $update = array(
            'auditStatus' => 1,
            'auditId' => $auditId,
        );

        return $this->updateById($id, $update);
    }

    public function setAuditStatus($id, $status)
    {
        $update = array(
            'auditStatus' => $status
        );

        return $this->updateById($id, $update);
    }

    public function setStatus($id, $status)
    {
        $update = array(
            'status' => $status
        );

        return $this->updateById($id, $update);
    }

    public function getDateByVersion($version,$fields = null)
    {
        $where = array(
            'version' => $version,
            'appId' => APP_ID
        );

        return $this->fetchOne($where,$fields);
    }
}