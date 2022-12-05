<?php
/**
 * 版本模块
 */

namespace FF\App\GameMain\Model\Config;

use FF\Extend\MyModel;
use FF\Framework\Common\DBResult;

class VersionModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_CONFIG, 't_version');
    }

    public function getVersion($machineId, $bigVersion, $version)
    {
        $where = array(
            'machineId' => $machineId,
            'bigVersion' => $bigVersion,
            'version' => $version,
        );

        return $this->fetchOne($where);
    }

    public function getAllVersions($bigVersion)
    {
        $where = array(
            'bigVersion' => $bigVersion,
        );

        $result = $this->fetchAll($where);

        $versions = array();
        foreach ($result as $row) {
            $versions[$row['machineId']][$row['version']] = $row;
        }

        return $versions;
    }

    public function getReleasedVersions($machineId)
    {
        $where = array(
            'machineId' => $machineId,
            'status' => array('in', [1, 2]),
        );

        return $this->fetchAll($where);
    }

    public function getAllReleasedVersions($bigVersion)
    {
        $where = array(
            'bigVersion' => $bigVersion,
            'status' => array('in', [1, 2]),
        );

        return $this->fetchAll($where, null, 'version asc');
    }

    public function getNewestVersions($bigVersion)
    {
        $subSql = "SELECT machineId, MAX(`version`) AS `version` FROM {$this->table()} WHERE bigVersion = '{$bigVersion}' AND `status` IN (1, 2) GROUP BY machineId";
        $sql = "SELECT a.* FROM {$this->table()} a, ({$subSql}) b WHERE a.bigVersion = '{$bigVersion}' AND a.machineId = b.machineId AND a.version = b.version;";

        $result = $this->db()->query($sql, [], DBResult::FETCH_ALL);

        return array_column($result, null, 'machineId');
    }

    public function deleteByBuildId($buildId)
    {
        $where = array(
            'buildId' => $buildId
        );

        return $this->delete($where, null);
    }

    public function setBigVersionByBuildId($buildId, $bigVersion)
    {
        $update = array(
            'bigVersion' => $bigVersion
        );

        $where = array(
            'buildId' => $buildId
        );

        return $this->update($update, $where, null);
    }

    public function setStatusByBuildId($buildId, $status)
    {
        $update = array(
            'status' => $status
        );

        $where = array(
            'buildId' => $buildId
        );

        return $this->update($update, $where, null);
    }

    public function getVersionFilePath($buildId, $fields = null)
    {
        $where = array(
            'buildId' => $buildId
        );

        return $this->fetchOne($where, $fields);
    }
}