<?php
/**
 * 综合业务控制器
 */

namespace FF\App\GameMain\Controller;

use FF\Factory\Bll;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Log;
use GPBClass\Enum\RET;

class PublicController extends BaseController
{
    /**
     * 客户端事件上报
     */
    public function eventReport()
    {
        $uid = $this->getUid() ? : 0;
        $event = $this->getParam('event');
        $extras = $this->getParam('extras');

        if ($extras === 'null') $extras = '';

        if (is_string($extras)) {
            $extras = $extras ? json_decode($extras, true) : array();
            if ($extras === null) {
                FF::throwException(RET::PARAMS_INVALID);
            }
        } elseif (!is_array($extras)) {
            FF::throwException(RET::PARAMS_INVALID);
        }

        return array();
    }

    /**
     * 用户Ping
     */
    public function onPing()
    {
        $uid = $this->getUid();
        $isPlaying = (int)$this->getParam('isPlaying', false, 0);

        Bll::asyncTask()->addTask(EVENT_PING, array(
            'uid' => $uid,
            'isPlaying' => $isPlaying
        ));

        return array();
    }

}