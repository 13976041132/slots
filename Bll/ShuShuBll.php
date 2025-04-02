<?php
/**
 * 数数上报
 */

namespace FF\Bll;

use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Framework\Common\Format;
use FF\Framework\Utils\Log;
use FF\Library\Utils\ApiRequester;

class ShuShuBll
{
    const REPORT_URL = 'https://global-receiver-ta.thinkingdata.cn/sync_json';
    const APP_ID_IOS = 'b108b0aa3d7f496bb51d3986d54d8894';
    const APP_ID_ANDROID = '818ac10a12e946bf88479e38eba9055b';

    function batchReportWithRetry($data, $maxRetries = 2)
    {
        $retryCount = 0;
        while ($retryCount < $maxRetries) {
            if ($this->batchReport($data)) {
                return true;
            }
            ++$retryCount;
        }
        return false;
    }

    public function batchReport($data)
    {
        if (!$data) {
            return true;
        }
        $reportData = [];
        foreach ($data as $row) {
            $reportData[] = [
                'appid' => $this->getAppId($row['#account_id"'] ?? ''),
                'data' => $row
            ];
        }
        $api = new ApiRequester(['format' => Format::JSON, 'url' => self::REPORT_URL, 'method' => 'POST']);
        $res = $api->requestData($reportData);
        if (!$res) {
            return false;
        }
        $result = json_decode($res, true);
        if (!$result || $result['code'] != 0) {
            Log::error('report shushu fail, res:' . $res . ',report:' . json_encode($data));
            return false;
        }
        return true;
    }

    public function asyncReport($event, $uid, $data)
    {
        $body = [
            "#account_id" => $uid,
            "#event_name" => $event,
            "#type" => "track",
            "#time" => now(),
            "properties" => $data
        ];
        $key = Keys::shushuList(date('i'));
        $exists = Dao::redis()->exists($key);
        Dao::redis()->lpush($key, json_encode($body));
        if (!$exists) {
            Dao::redis()->expire($key, 1800);
        }
    }

    public function clubHelp($clubId, $uid, $helpUid, $type)
    {
        $reportData = [
            'help_type' => (int)$type,
            'club_id' => (int)$clubId,
            'result' => 1,
            'player_id' => (int)$uid
        ];
        Bll::shushu()->asyncReport('Club_Chat_Help', $helpUid, $reportData);
    }

    public function getAppId($uid) {
        $flag = Bll::user()->isIos($uid);
        return $flag ? self::APP_ID_IOS : self::APP_ID_ANDROID;
    }
}