<?php
/**
 * 版本业务逻辑
 */

namespace FF\Bll;

use FF\Factory\Bll;
use FF\Factory\Model;
use FF\Framework\Utils\Config;

class VersionBll
{
    /**
     * 获取应用/机台版本配置
     */
    public function getVersionConfig($bigVersion = null, $machineId = null)
    {
        $key = '';
        if ($bigVersion) {
            $key .= $bigVersion;
            if (!is_empty($machineId)) {
                $key .= '/' . $machineId;
            }
        }

        return Config::get('app-versions', $key, false);
    }

    /**
     * 提取大版本号
     */
    public function getBigVersion($version)
    {
        $bigVersion = implode('.', array_slice(explode('.', $version), 0, 2));

        return $bigVersion;
    }

    /**
     * 获取用户当前大版本号
     */
    public function getUserBigVersion()
    {
        $version = Bll::session()->get('version');

        return $this->getBigVersion($version);
    }

    /**
     * 获取应用当前大版本号
     * 从已生成的版本配置文件中获取
     */
    public function getNowBigVersion()
    {
        $versions = $this->getVersionConfig();
        if (!$versions) return '1.0';

        foreach ($versions as $bigVersion => $_versions) {
            if (!isset($_versions[0])) continue;
            if ($_versions[0]['status'] == 2) {
                return $bigVersion;
            }
        }

        return '1.0';
    }

    /**
     * 获取某模块在指定大版本下最新版本号
     */
    public function getNowVersion($bigVersion, $machineId)
    {
        $versionCfg = $this->getVersionConfig($bigVersion, $machineId);

        return $versionCfg ? $versionCfg['version'] : 0;
    }

    /**
     * 获取应用所有历史版本号列表(倒序)
     */
    public function getAllVersions($limit = null)
    {
        $versions = Model::version()->getReleasedVersions(0);
        foreach ($versions as $k => $version) {
            $versions[$k] = $version['bigVersion'] . '.' . $version['version'];
        }

        usort($versions, function ($a, $b) {
            return Bll::version()->versionCompare($a, $b);
        });

        $versions = array_reverse($versions);

        return $limit ? array_slice($versions, 0, $limit) : $versions;
    }

    /**
     * 获取应用已发布的大版本号列表(倒序)
     */
    public function getBigVersions($limit = null)
    {
        $versions = Model::version()->getReleasedVersions(0);
        $versions = array_unique(array_column($versions, 'bigVersion'));

        usort($versions, function ($a, $b) {
            return Bll::version()->versionCompare($a, $b);
        });

        $versions = array_reverse($versions);

        return $limit ? array_slice($versions, 0, $limit) : $versions;
    }

    /**
     * 获取应用最新版本号
     */
    public function getNewestVersion()
    {
        $bigVersion = $this->getNowBigVersion();

        $version = $bigVersion . '.' . $this->getNowVersion($bigVersion, 0);

        return $version;
    }

    /**
     * 获取应用某个大版本下各模块最新版本信息(小版本)
     */
    public function getNewestVersions($bigVersion)
    {
        return Model::version()->getNewestVersions($bigVersion);
    }

    /**
     * 检查模块小版本更新(热更)
     */
    public function checkHotFix($machineId, $bigVersion, $version, $quality = 1)
    {
        $data = array();
        $data['machineId'] = $machineId;
        $data['version'] = $version;
        $data['packageUrl'] = '';
        $data['hasUpdate'] = false;
        $data['md5'] = '';


        $versionCfg = $this->getVersionConfig($bigVersion, $machineId);
        if (!$versionCfg) return $data;

        $data['version'] = $versionCfg['version'];

        if ($version != $versionCfg['version']) {
            $package = $this->getUpdatePackage($version, $versionCfg['version'], $versionCfg['packages'], $quality);
            if (!$package) return $data;
            $filePath = !empty($versionCfg['filePath']) ? ($versionCfg['filePath'] . '/') : '';
            $md5 = !empty($versionCfg['filesMd5'][$package]) ? $versionCfg['filesMd5'][$package] : '';
            $data['packageUrl'] = CDN_URL . $filePath . $package;
            $data['hasUpdate'] = true;
            $data['md5'] = $md5;
        }

        return $data;
    }

    /**
     * 获取更新包，兼容模式
     */
    public function getUpdatePackage($fromVersion, $toVersion, $packages, $quality)
    {
        $packageIds = array();

        $packageIds[] = "{$fromVersion}-{$toVersion}-{$quality}";
        if ($quality != 1) {
            $packageIds[] = "{$fromVersion}-{$toVersion}-1";
        }

        $packageIds[] = "0-{$toVersion}-{$quality}";
        if ($quality != 1) {
            $packageIds[] = "0-{$toVersion}-1";
        }

        foreach ($packageIds as $packageId) {
            if (!empty($packages[$packageId])) {
                return $packages[$packageId];
            }
        }

        return null;
    }

    /**
     * 比较两个版本号的大小
     * 0 (version1 = version2)
     * -1 (version1 < version2)
     * 1 (version1 > version2)
     */
    public function versionCompare($version1, $version2)
    {
        if ($version1 === $version2) return 0;

        $version1 = explode('.', $version1);
        $version2 = explode('.', $version2);

        $versionLen = max(count($version1), count($version2));

        for ($k = 0; $k < $versionLen; $k++) {
            $ver1 = isset($version1[$k]) ? (int)$version1[$k] : 0;
            $ver2 = isset($version2[$k]) ? (int)$version2[$k] : 0;
            if ($ver1 < $ver2) return -1;
            if ($ver1 > $ver2) return 1;
        }

        return 0;
    }

    /**
     * 从版本表达式集合里面获取与指定版本匹配的一个
     */
    public function versionMatch($version, $expressions)
    {
        $matched = 'all';

        foreach ($expressions as $expression) {
            if ($expression == 'all') continue;
            if ($this->isMatch($version, $expression)) {
                $matched = $expression;
                break;
            }
        }

        return $matched;
    }

    /**
     * 判断给定版本号是否与指定版本表达式匹配
     * 版本表达式支持四种形式：
     * 1. {version}+
     * 2. {version}-
     * 3. {version1}-{version2}
     * 4. {version}
     */
    public function isMatch($version, $expression)
    {
        if (substr($expression, -1) == '+') {
            if ($this->versionCompare($version, substr($expression, 0, -1)) >= 0) {
                return true;
            }
        } elseif (substr($expression, -1) == '-') {
            if ($this->versionCompare($version, substr($expression, 0, -1)) < 0) {
                return true;
            }
        } elseif (strpos($expression, '-')) {
            $rect = explode('-', $expression);
            if ($this->versionCompare($version, $rect[0]) >= 0 && $this->versionCompare($version, $rect[1]) <= 0) {
                return true;
            }
        } elseif ($version == $expression) {
            return true;
        }

        return false;
    }

    /**
     * 根据用户设备评分获取资源质量等级
     */
    public function getPackageQuality($score, $memory)
    {
        $quality = 1;
        $qualities = Config::get('common/general', 'resourceQuality');
        if ($memory && $memory < 1024 * 5) {
            return count($qualities);
        }

        $qualities = array_reverse($qualities);
        foreach ($qualities as $k => $rect) {
            if ($score >= $rect[0] && (!$rect[1] || $score <= $rect[1])) {
                $quality = $k + 1;
                break;
            }
        }

        return $quality;
    }

    /**
     * 版本同步数据检测
     * @return bool
     */
    public function syncVersionDataCheck($version,$description,$realName,$packageList,$savePath){

        $res = Model::versionBuild()->getDateByVersion($version,'id,localZipPath');
        if (empty($res)){
            $this->createVersionBuild($version,$description,$realName,$packageList,$savePath);
        }else{
            $packageList = $packageList != $res['localZipPath'] ? $packageList : '';
            $this->updateVersionBuild($res['id'],$version,$description,$packageList,$savePath);
        }

        return true;
    }

    /**
     * 构建版本
     * @param $version
     * @param $description
     * @param $realName
     * @param $packageList
     * @return bool
     */
    public function createVersionBuild($version,$description,$realName,$packageList,$savePath,$syncAllAndroid=''){

        $appId = APP_ID;

        $data = array(
            'appId' => $appId,
            'version' => $version,
            'description' => (string)$description,
            'modules' => '',
            'status' => 0,
            'syncStatus' => 0,
            'createTime' => now(),
            'createBy' => $realName,
            'localZipPath' => $packageList,
            'syncAllAndroid' => $syncAllAndroid,
            'updateTime' => now(),
        );

        if (empty($packageList)) {
            return false;
        }

        $bigVersion = implode('.', array_slice(explode('.', $version), 0, 2));
        $packages = $this->parsePackage($bigVersion,$packageList);
        $filesMd5 = explode('||',$packageList)[1];
        if (!$packages) return false;
        $data['modules'] = implode(',', array_keys($packages));

        $id = Model::versionBuild()->insert($data);

        $this->createVersions($id, $bigVersion, $packages,$filesMd5,$savePath);

        return true;
    }

    /**
     * 更新版本
     * @param $id
     * @param $version
     * @param $description
     * @param $packageList
     * @param string $syncAllAndroid
     * @return bool
     */
    public function updateVersionBuild($id,$version,$description,$packageList,$savePath,$syncAllAndroid=''){

        $data = array(
            'version' => $version,
            'description' => (string)$description,
            'syncAllAndroid' => $syncAllAndroid,
            'updateTime' => now(),
        );

        $bigVersion = implode('.', array_slice(explode('.', $version), 0, 2));

        if (!empty($packageList)) {
            $packages = $this->parsePackage($bigVersion,$packageList,$id);
            if (!$packages) return false;
            $data['modules'] = implode(',', array_keys($packages));
            $data['syncStatus'] = 0;
            $data['localZipPath'] = $packageList;
            $data['syncProgress'] = '';
        } else {
            $packages = null;
        }

        Model::versionBuild()->updateById($id, $data);

        if ($packages) {
            Model::version()->deleteByBuildId($id);
            $filesMd5 = explode('||',$packageList)[1];
            $this->createVersions($id, $bigVersion, $packages,$filesMd5,$savePath);
        } else {
            Model::version()->setBigVersionByBuildId($id, $bigVersion);
        }

        return true;
    }

    private function parsePackage($bigVersion,$packageList, $buildId = null)
    {
        $versions = array();
        $packages = array();
        $zipFiles = json_decode(explode('||',$packageList)[1],true);

        foreach ($zipFiles as $zipFile=>$fileMd5) {
            if (substr($zipFile, -3) != 'zip') {
                return false;
            }
            $fields = explode('_', substr($zipFile, 0, -4));
            $module = array_shift($fields);
            $machineId = $module == 'Main' ? 0 : (int)substr($module, 1);
            $toVersion = array_pop($fields);
            $fromVersion = array_pop($fields);
            $quality = $fields ? array_pop($fields) : 1;
            $packageId = "{$fromVersion}-{$toVersion}-{$quality}";
            $packages[$module][$packageId] = $zipFile;
            $versions[$machineId] = $toVersion;
        }
        ksort($packages);

        if (!$this->checkVersions($bigVersion, $versions, $buildId)){
            return false;
        }

        return $packages;
    }

    private function checkVersions($bigVersion, $versions, $buildId = null)
    {
        $nowVersions = Model::version()->getNewestVersions($bigVersion);

        foreach ($versions as $machineId => $version) {
//            $module = $machineId ? "M{$machineId}" : 'Main';
            //新版本号不能比当前版本号小
            if (isset($nowVersions[$machineId])) {
                $nowVersion = $nowVersions[$machineId]['version'];
                if ($version <= $nowVersion) {
                    return false;
                }
            }
            //版本号不能重复
            $data = Model::version()->getVersion($machineId, $bigVersion, $version);
            if ($data && (!$buildId || $data['buildId'] != $buildId)) {
                return false;
            }
        }

        return true;
    }

    private function createVersions($buildId, $bigVersion, $packages,$filesMd5,$savePath)
    {
        $now = now();
        $versions = array();

        $filesMd5 = json_decode($filesMd5, true);

        foreach ($packages as $module => $_packages) {
            ksort($_packages);
            $_filesMd5 = array();
            $machineId = $module == 'Main' ? 0 : (int)substr($module, 1);

            $version = (int)explode('-', array_keys($_packages)[0])[1];
            foreach ($_packages as $zipFile) {
                $_filesMd5[$zipFile] = $filesMd5[$zipFile];
            }
            $versions[] = array(
                'buildId' => $buildId,
                'machineId' => $machineId,
                'bigVersion' => $bigVersion,
                'version' => $version,
                'filePath' => $savePath,
                'packages' => json_encode($_packages),
                'filesMd5' => json_encode($_filesMd5),
                'status' => 0,
                'createTime' => $now
            );
        }

        return Model::version()->insertMulti($versions);
    }

    /**
     * 是否支持更新
     * @param $bigVersion
     */
    public function isSupportUpdate($bigVersion)
    {
        $noSupportVersions = (array)Config::get('version-ignore');
        foreach($noSupportVersions as $noSupportVersion)
            if($this->versionCompare($bigVersion,$noSupportVersion) === 0) {
                return false;
            }
        return true;
    }

}