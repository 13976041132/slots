<?php
/**
 * 数据表格配置
 */

$config = array(
    'machine' => array(
        ['machineId',  'name', 'className', 'cols', 'rows',  'unlockLevel'],
        ['ID', 'Machine_Name', 'Machine_Name', 'Cols', 'Rows', 'UnlockLevel']
    ),

    'machine_item' => array(
        ['elementId', 'machineId', 'iconType', 'iconDescription', 'iconImage'],
        ['ID', 'Element_Machine', 'Machine_Icon_Type', 'Detail_Descreption', 'Icon_Name']
    ),

    'machine_item_reel_weights' => array(
        ['machineId', 'featureName', 'reelWeights', 'elementId'],
        ['Machine_Id', 'Feature_Name', 'Weight', 'Element_Id']
    ),

    'payline' => array(
        ['machineId', 'seq', 'route'],
        ['Machine_Id', 'Paylines_Id', 'Route']
    ),
    'paytable' => array(
        ['machineId', 'resultId', 'elements', 'prize', 'freeSpinOnly'],
        ['Machine_Id', 'Result_Id', 'Col', 'Prize', 'Used_in_freespin']
    ),
    'feature_game' => array(
        ['machineId',  'featureId', 'featureName',  'triggerItems', 'triggerItemNum',  'freespinAward', 'itemAwardLimit','extraTimes'],
        ['Machine_Id',  'ID', 'Feature_name', 'Trig_item_ID', 'Trig_item_num', 'Times',  'Option', 'Extra_Times']
    ),
);

return $config;