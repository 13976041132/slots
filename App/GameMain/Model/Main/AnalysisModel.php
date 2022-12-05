<?php
/**
 * 数据分析业务模块
 */

namespace FF\App\GameMain\Model\Main;

use FF\Extend\MyModel;

class AnalysisModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_MAIN, 't_analysis', 'uid');
    }

    public function init($uid, $regCoins)
    {
        $data = array('uid' => $uid, 'regCoins' => $regCoins);

        return $this->insert($data);
    }
}