<?php
/**
 * 版本相关接口
 */

namespace FF\App\GameMain\Controller;

use FF\Factory\Bll;
use FF\Framework\Utils\Config;

class VersionController extends BaseController
{
    /**
     * 检查应用版本更新
     */
    public function checkAppVersion()
    {
        $bigVersion = $this->getParam('bigVersion');
        $smallVersion = $this->getParam('smallVersion');

        $appConfig = Config::get('app-store');
        $compatibleVersion = $appConfig['compatibleVersion'];
        $nowBigVersion = Bll::version()->getNowBigVersion();

        if ((float)$bigVersion == (int)$bigVersion) { //兼容模式
            $bigVersion = (int)$bigVersion . '.0';
        }

        $data = array();
        $data['bigVersion'] = $nowBigVersion;
        if (Bll::version()->versionCompare($bigVersion, $nowBigVersion) > 0) {
            $data['bigVersion'] = $bigVersion;
        }
        $data['smallVersion'] = 0;
        $data['packageUrl'] = '';
        $data['hasUpdate'] = false;
        $data['forceUpdate'] = false;
        $data['md5'] = '';

        if (!Bll::version()->isSupportUpdate($bigVersion)) {
            $data['bigVersion'] = $bigVersion;
            return $data;
        }

        if (Bll::version()->versionCompare($bigVersion, $compatibleVersion) < 0) {
            $data['packageUrl'] = $appConfig['packageUrl'];
            $data['forceUpdate'] = true;
            return $data;
        }

        $versionInfo = Bll::version()->checkHotFix(0, $bigVersion, $smallVersion, 1);
        $versionInfo['smallVersion'] = $versionInfo['version'];
        unset($versionInfo['machineId']);
        unset($versionInfo['version']);

        $data = array_merge($data, $versionInfo);

        return $data;
    }

    /**
     * 检查机台版本更新
     */
    public function checkMachineVersion()
    {
        $machineId = $this->getParam('machineId');
        $bigVersion = $this->getParam('bigVersion', false, '1.0');
        $version = $this->getParam('version');
        $quality = $this->getParam('quality', false, 1);

        if ((float)$bigVersion == (int)$bigVersion) {
            $bigVersion = (int)$bigVersion . '.0';
        }

        return Bll::version()->checkHotFix($machineId, $bigVersion, $version, $quality);
    }

    /**
     * 检查机台版本更新(批量)
     */
    public function checkMachinesVersion()
    {
        $bigVersion = $this->getParam('bigVersion', false, '1.0');
        $versions = $this->getParam('versions');
        $quality = $this->getParam('quality', false, 1);

        $data = array();
        $versions = json_decode($versions, true);
        if (!$versions) return $data;

        if ((float)$bigVersion == (int)$bigVersion) {
            $bigVersion = (int)$bigVersion . '.0';
        }

        foreach ($versions as $machineId => $version) {
            $data[] = Bll::version()->checkHotFix($machineId, $bigVersion, $version, $quality);
        }

        return array(
            'versions' => $data
        );
    }

    /**
     * 跳转到应用商店
     */
    public function gotoAppStore()
    {
        $app = Config::get('app-store');

        redirect($app['packageUrl']);
    }

    /**
     * 同步代码
     */
    public function syncVersionData(){

        $version = $this->getParam('version');
        $description = $this->getParam('description');
        $createBy = $this->getParam('createBy');
        $localZipPath = $this->getParam('localZipPath');
        $savePath = $this->getParam('savePath');

        Bll::version()->syncVersionDataCheck($version,$description,$createBy,$localZipPath,$savePath);

        return true;
    }
}