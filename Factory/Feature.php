<?php
/**
 * Feature对象工厂
 */

namespace FF\Factory;

use FF\Framework\Mode\Factory;
use FF\Machines\Features\Chooser;
use FF\Machines\Features\CollectGame;
use FF\Machines\Features\Lightning;
use FF\Machines\SlotsModel\SlotsMachine;

class Feature extends Factory
{
    protected static $identifies = array();

    /**
     * @param $machineObj SlotsMachine
     */
    public static function _getInstance($featureClass, $machineObj, $featureId)
    {
        $machineInstanceId = $machineObj->getInstanceId();

        $identify = "{$machineInstanceId}:{$featureId}";

        $instance = self::getInstance($featureClass, $identify, [$machineObj, $featureId]);

        self::$identifies[$machineInstanceId][$featureId] = array(
            'class' => $featureClass,
            'identify' => $identify
        );

        return $instance;
    }

    public static function clearInstancesByMachine($machineInstanceId)
    {
        if (isset(self::$identifies[$machineInstanceId])) {
            foreach (self::$identifies[$machineInstanceId] as $identifyInfo) {
                self::delInstance($identifyInfo['class'], $identifyInfo['identify']);
            }
            unset(self::$identifies[$machineInstanceId]);
        }
    }

    public static function clearInstanceByFeature($machineInstanceId, $featureId)
    {
        if (isset(self::$identifies[$machineInstanceId][$featureId])) {
            $identifyInfo = self::$identifies[$machineInstanceId][$featureId];
            unset(self::$identifies[$machineInstanceId][$featureId]);
            self::delInstance($identifyInfo['class'], $identifyInfo['identify']);
        }
    }

    /**
     * @return Chooser
     */
    public static function chooser($machineObj, $featureId)
    {
        return self::_getInstance('FF\Machines\Features\Chooser', $machineObj, $featureId);
    }

    /**
     * @return Lightning
     */
    public static function lightning($machineObj, $featureId)
    {
        return self::_getInstance('FF\Machines\Features\Lightning', $machineObj, $featureId);
    }

    /**
     * @return CollectGame
     */
    public static function collectGame($machineObj, $featureId)
    {
        return self::_getInstance('FF\Machines\Features\CollectGame', $machineObj, $featureId);
    }

}