<?php
/**
 * 机台管理
 */

namespace FF\App\Admin\Controller;

use FF\Bll\MachineBll;
use FF\Factory\Bll;
use FF\Factory\Model;
use FF\Framework\Utils\Config;

class MachineController extends BaseController
{
    /**
     * 查看机台配置
     */
    public function index()
    {
        $page = (int)$this->getParam('page', false, 1);
        $limit = (int)$this->getParam('limit', false, 15);

        $data = Model::machine()->getPageList($page, $limit, null, null, array('machineId' => 'asc'));

        $this->display('index.html', $data);
    }

    /**
     * 查看下注选项配置
     */
    public function bet()
    {
        $machineId = $this->getParam('machineId');

        $data = array();
        $data['machineBet'] = Config::get('machine/common-bet');

        $this->display('bet.html', $data);
    }

    /**
     * 查看机台元素配置
     */
    public function element()
    {
        $machineId = $this->getParam('machineId');

        $data['elements'] = Bll::machine()->getSourceData($machineId, MachineBll::DATA_MACHINE_ITEMS);

        $this->display('element.html', $data);
    }

    /**
     * 查看机台中奖线配置
     */
    public function payline()
    {
        $machineId = $this->getParam('machineId');

        $data['paylines'] = Bll::machine()->getSourceData($machineId, MachineBll::DATA_PAYLINES);

        $this->display('payline.html', $data);
    }

    /**
     * 查看机台Paytable配置
     */
    public function paytable()
    {
        $machineId = $this->getParam('machineId');

        $data['cols'] = Bll::machine()->getMachine($machineId)['cols'];
        $data['paytable'] = Bll::machine()->getSourceData($machineId, MachineBll::DATA_PAYTABLE);

        $this->display('paytable.html', $data);
    }

    /**
     * 查看机台Feature配置
     */
    public function feature()
    {
        $machineId = $this->getParam('machineId');

        $features = Bll::machine()->getSourceData($machineId, MachineBll::DATA_FEATURE_GAMES);
        foreach ($features as &$feature) {
            $feature['triggerOptions'] = str_replace(['"', ',', ":"], ['', ', ', ": "], json_encode($feature['triggerOptions']));
            $feature['triggerLines'] = str_replace(['"', ','], ['', ', '], json_encode($feature['triggerLines']));
            $feature['itemAwardLimit'] = str_replace(['"', ',', ":"], ['', ', ', ": "], json_encode($feature['itemAwardLimit']));
        }

        $data['features'] = array_values($features);

        $this->display('feature.html', $data);
    }

    /**
     * 查看机台轴样本配置
     */
    public function sample()
    {
        $machineId = $this->getParam('machineId');

        $samples = Bll::machine()->getSourceData($machineId, MachineBll::DATA_SAMPLES);

        $data['samples'] = $samples;

        $this->display('sample.html', $data);
    }

    /**
     * 查看机台收集配置
     */
    public function collect()
    {
        $machineId = $this->getParam('machineId');

        $data['collects'] = Config::get('machine/machine-collect', $machineId);

        $this->display('collect.html', $data);
    }

    /**
     * 查看机台Jackpot配置
     */
    public function jackpot()
    {
        $machineId = $this->getParam('machineId');

        $data['jackpots'] = Bll::jackpot()->getJackpots($machineId, 'general');

        $this->display('jackpot.html', $data);
    }

    /**
     * @ignore permission
     */
    public function getBetOptions()
    {
        $machineId = $this->getParam('machineId');

        $betOptions = Bll::machine()->getBetOptions($machineId);

        $list = array();
        foreach ($betOptions as $betMultiple => $totalBet) {
            $list[] = [$betMultiple, $totalBet];
        }

        return $list;
    }

    /**
     * @ignore permission
     */
    public function getFeatures()
    {
        $machineId = $this->getParam('machineId');

        $features = array();
        $featureGames = Bll::machine()->getFeatureGames($machineId);

        foreach ($featureGames as $featureId => $feature) {
            $features[] = array($featureId, $feature['featureName']);
        }

        return $features;
    }

    /**
     * @ignore permission
     */
    public function getFeatureNames()
    {
        $machineId = $this->getParam('machineId');

        $features = array();
        $featureGames = Bll::machine()->getFeatureGames($machineId);

        foreach ($featureGames as $featureId => $feature) {
            $features[] = $feature['featureName'];
        }

        $features = array_values(array_unique($features));

        return $features;
    }

    /**
     * 查看机台轴样本元素列表
     */
    public function sampleItems()
    {
        $machineId = $this->getParam('machineId');
        $sampleId = $this->getParam('sampleId');
        $samples = Bll::machine()->getSourceData($machineId, MachineBll::DATA_SAMPLE_ITEMS);

        $data['colNum'] = $samples[$sampleId] ? count($samples[$sampleId]) : 0;
        $sampleItems = array();
        if ($samples[$sampleId]) {
            foreach ($samples[$sampleId] as $col => $itemsStr) {
                $items = explode(',', $itemsStr);
                foreach ($items as $index => $elementId) {
                    $sampleItems[$index][$col] = $elementId;
                }
            }
        }
        $data['sampleItems'] = $sampleItems;
        $data['machineId'] = $machineId;
        $data['machineItems'] = Bll::machine()->getMachineItems($machineId);

        $this->display('sampleItems.html', $data);
    }
}