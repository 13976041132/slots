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
        'machine_item_reel_weights' => 'Machine_Item_Weight.csv',
        'feature_game' => 'Feature_Games.csv',
        'jackpot' => 'Machine_Jackpot.csv',
        'paytable' => 'Machine_Paytable.csv',
        'bonus_value' => 'Machine_Ball.csv',
        'hold_and_spin' => 'Machine_Holdspin.csv'
    ),

//    '系统功能' => array(
//        'wheel' => 'Wheel.csv',
//        'wheel_item' => 'Wheel_Item.csv',
//    )
);

return $config;