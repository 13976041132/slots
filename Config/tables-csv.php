<?php
/**
 * csv配置表映射
 */

$config = array(
    '机台' => array(
        'payline' => 'Machine_Paylines.csv',
        'machine' => 'Machine.csv',
        'bet' => 'Machine_Bet.csv',
        'machine_item' => 'Machine_Item.csv',
        'machine_reel_items' => 'Machine_item_weight.csv',
        'feature_game' => 'Feature_Games.csv',
        'jackpot' => 'Machine_Jackpot.csv',
        'paytable' => 'Machine_Paytable.csv',
    ),

//    '轴样本' => array(
//        'sample' => 'Sample.csv',
//        'sample_items' => 'Sample_Item.csv',
//    ),
//    '系统功能' => array(
//        'wheel' => 'Wheel.csv',
//        'wheel_item' => 'Wheel_Item.csv',
//    )
);

return $config;