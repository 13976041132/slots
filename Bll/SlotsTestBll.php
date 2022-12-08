<?php
/**
 * 机台测试业务逻辑
 */

namespace FF\Bll;

use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\Keys;
use FF\Factory\Model;
use FF\Framework\Common\Code;
use FF\Framework\Common\DBResult;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Log;
use FF\Library\Utils\MysqlCli;

class SlotsTestBll
{
    private $featureStats = array();
    private $coinsAwardStats = array();
    private $betResultStats = [];
    private $keywordStats = [];

    public function getTestInfo($testId)
    {
        $cacheKey = Keys::slotsTestInfo($testId);
        $result = Dao::redis()->hGetAll($cacheKey);

        if (!$result) {
            $result = Model::slotsTest()->getOneById($testId);
        }

        return $result;
    }

    public function clearTestCache($testId, $machineIds)
    {
        $deleteKeys = [];
        $deleteKeys[] = Keys::slotsTestInfo($testId);
        if (is_string($machineIds)) {
            $machineIds = array_unique(explode(',', $machineIds));
        }
        foreach ($machineIds as $machineId) {
            $deleteKeys[] = Keys::slotsTestResult($testId, $machineId);
        }
        Dao::redis()->del($deleteKeys);
    }

    /**
     * 启动测试
     */
    public function start($testId)
    {
        $test = Model::slotsTest()->getOneById($testId);
        if (!$test || !in_array($test['status'], [0, 3])) return false;

        //控制同时运行的测试实例数量，超限时设为等待状态
        $runningTest = Model::slotsTest()->fetchAll(array('status' => 1), 'testId');
        if (count($runningTest) >= 2) {
            if ($test['status'] == 0) {
                return Model::slotsTest()->setWaiting($testId);
            } else {
                return false;
            }
        }

        if (!Model::slotsTest()->setStarted($testId)) return false;

        //创建表;
        $this->createLogTable($test['testId']);

        $cacheKey = Keys::slotsTestInfo($testId);
        Dao::redis()->hMSet($cacheKey, array_merge($test, array(
            'status' => 1,
            'startTime' => now()
        )));

        $cacheKey = Keys::slotsTestPlans();
        Dao::redis()->sAdd($cacheKey, $testId);

        return true;
    }

    /**
     * 启动下个等待中的测试
     */
    public function startNext()
    {
        $test = Model::slotsTest()->getWaitingOne();
        if (!$test) return;

        $this->start($test['testId']);
    }

    /**
     * 终止测试
     */
    public function stop($testId)
    {
        $result = Model::slotsTest()->setEnded($testId);

        if (!$result) return false;

        $cacheKey = Keys::slotsTestPlans();
        Dao::redis()->sRem($cacheKey, $testId);

        $cacheKey = Keys::slotsTestInfo($testId);
        Dao::redis()->hMSet($cacheKey, array(
            'status' => 2,
            'endTime' => now()
        ));

        $this->startNext();

        return true;
    }

    /**
     * 测试结束时的逻辑
     */
    public function onEnded($testId, $logPath = '/home/www/testData/')
    {
        $cacheKey = Keys::slotsTestInfo($testId);
        $testInfo = Dao::redis()->hGetAll($cacheKey);
        if (!$testInfo) {
            FF::throwException(Code::FAILED, 'Test cache info is missed');
        }

        Dao::redis()->sRem(Keys::slotsTestPlans(), $testId);

        if ($testInfo['status'] == 1) {
            Dao::redis()->hMSet($cacheKey, array(
                'status' => 2,
                'endTime' => now()
            ));
        }

        Model::slotsTest()->updateById($testId, array(
            'bettedTimes' => (int)$testInfo['bettedTimes'],
            'bettedUsers' => (int)$testInfo['bettedUsers'],
            'endTime' => $testInfo['endTime'] ?: now(),
            'error' => $testInfo['error'] ?: '',
            'logPath' => $logPath,
            'status' => 2,
        ));

        $this->saveBetLog($testId, $logPath);

        //执行测试统计脚本
        $statFile = PATH_ROOT . '/Scripts/SlotsTest/stat.php';
        exec_php_file($statFile, array('testId' => $testId), true);

        //启动下一个排队中的测试
        Bll::slotsTest()->startNext();
    }

    /**
     * 删除测试任务时清理相关资源
     */
    public function onDeleted($testId, $testInfo)
    {
        $machineIds = array_unique(explode(',', $testInfo['machineIds']));
        $this->clearTestCache($testId, $machineIds);
        $this->deleteLogTable($testId);

        $logFile = $this->getSpinLogFile($testId, $testInfo['logPath']);
        if (file_exists($logFile)) {
            unlink($logFile);
        }
    }

    /**
     * 为每个测试任务单独创建日志表
     */
    public function createLogTable($testId)
    {
        $table = $this->getTableName($testId);
        $sql = "CREATE TABLE IF NOT EXISTS {$table} LIKE t_bet_log";

        try {
            Dao::db(DB_LOG)->execute($sql);
            return $table;
        } catch (\Exception $e) {
            Log::error([$e->getMessage(), $e->getCode()], 'db_error.log');
            return false;
        }
    }

    /**
     * 删除日志表
     */
    public function deleteLogTable($testId)
    {
        $table = $this->getTableName($testId);

        Dao::db(DB_LOG)->query("DROP TABLE IF EXISTS `{$table}`", DBResult::AFFECTED_ROWS);
    }

    /**
     * 获取测试实例表名
     */
    public function getTableName($testId)
    {
        return "t_bet_log_test{$testId}";
    }

    /**
     * 获取spin日志文件路径
     */
    public function getSpinLogFile($testId, $logPath = '')
    {
        $logPath = $logPath ?: '/home/www/testData';

        return $logPath . '/test_spin_log_' . $testId . '.log';
    }

    /**
     * 保存下注记录（从csv文件读入db）
     */
    public function saveBetLog($testId, $logPath)
    {
        $csvFile = $this->getSpinLogFile($testId, $logPath);
        if (!file_exists($csvFile)) {
            return;
        }
        //创建临时表
        $sufix = $testId . '_tmp';
        $table = $this->createLogTable($sufix);
        if (!$table) {
            FF::throwException(Code::SYSTEM_ERROR, 'failed to create log table ' . $table);
        }

        MysqlCli::loadCsv($csvFile, DB_LOG, $table, '|');
        unlink($csvFile);

        $sTable = $this->getTableName($testId);

        $sSql = "SELECT *  FROM `{$table}` LIMIT 1";
        $row = Dao::db(DB_LOG)->execute($sSql, [], DBResult::FETCH_ONE);
        unset($row['id']);
        if (!$row) {
            Dao::db(DB_LOG)->execute("DROP TABLE `{$table}`");
            return;
        }

        $fields = '`' . implode('`,`', array_keys($row)) . '`';
        $sql = "INSERT INTO `{$table}` ({$fields}) SELECT {$fields} FROM `{$sTable}`";

        Dao::db(DB_LOG)->execute($sql);
        Dao::db(DB_LOG)->execute("TRUNCATE TABLE `{$sTable}`");

        //保存到目标表中
        $iSql = "INSERT INTO `{$sTable}` ({$fields}) (SELECT {$fields}  FROM {$table} ORDER BY `microtime`)";

        Dao::db(DB_LOG)->execute($iSql);
        Dao::db(DB_LOG)->execute("DROP TABLE `{$table}`");
    }

    /**
     * 测试完成后自动进行统计分析
     */
    public function stats($testId)
    {
        $testInfo = Model::slotsTest()->getOneById($testId);
        if (!$testInfo || $testInfo['status'] != 2 || $testInfo['stats']) return;

        $machineIds = array_unique(explode(',', $testInfo['machineIds']));

        //获取测试用户列表
        $table = $this->getTableName($testId);
        $result = Dao::db(DB_LOG)->query("SELECT DISTINCT uid FROM {$table} WHERE machineId={$machineIds[0]}", DBResult::FETCH_ALL);
        $testers = array_column($result, 'uid');

        //获取每个机台的统计数据以及汇总数据
        $stats = array();
        array_push($machineIds, 0);
        foreach ($machineIds as $machineId) {
            $stats[$machineId] = $this->getStats($testId, $machineId, $testInfo['totalBet']);
        }
        ksort($stats);

        $updates = array(
            'testers' => json_encode($testers),
            'stats' => json_encode($stats),
        );

        Model::slotsTest()->updateById($testId, $updates);

        $cacheKey = Keys::slotsTestInfo($testId);
        if (Dao::redis()->exists($cacheKey)) {
            Dao::redis()->hMSet($cacheKey, $updates);
        }
    }

    /**
     * 获取Feature统计数据
     */
    public function getFeatureStats($testId, $machineId, $totalBet)
    {
        $key = Keys::slotsTestResult($testId, $machineId);
        $data = Dao::redis()->get($key);
        Dao::redis()->del($key);

        $data = json_decode($data ?: '{}', true);
        if (!$data) return array();

        foreach ($data as $k => &$v) {
            if (in_array($k, ['feature', 'others'])) {
                continue;
            }
            if (is_numeric($v)) {
                $data['others']['key'][] = $k;
                $data['others']['value'][] = $v;
                unset($data[$k]);
                continue;
            }
            if ($k == 'coinsAward') {
                $v = array(
                    'reason' => $v['reason'],
                    'coins' => $v['coins'],
                );
            } else {
                $v = array(
                    'value' => $v['value'],
                    'times' => $v['times'],
                );
            }
        }

        $data['base'] = array();
        if (!empty($data['feature'])) {
            foreach ($data['feature'] as $stat) {
                $data['base'][$stat['name']] = array(
                    'cost' => $stat['betCoin'] ?: ($stat['triggerTimes'] * $totalBet),
                    'trigger' => $stat['triggerTimes'],
                    'hit' => $stat['winTimes'],
                    'coins' => $stat['winCoin'],
                );
            }
        }
        unset($data['feature']);

        return $data;
    }

    /**
     * 获取测试统计数据
     */
    public function getStats($testId, $machineId, $totalBet)
    {
        $featureStats = $this->getFeatureStats($testId, $machineId, $totalBet);

        $t_bet_log = $this->getTableName($testId);
        $wheres = $machineId ? "machineId = {$machineId}" : '1';

        //统计下注总次数、下注消耗金币
        $fields = "COUNT(1) AS count, SUM(cost) AS cost";
        $sql = "SELECT {$fields} FROM {$t_bet_log} WHERE {$wheres} AND isFreeSpin = 0";
        $result = Dao::db(DB_LOG)->query($sql, DBResult::FETCH_ONE);
        $spinTimes = $result['count'];
        $coinsCost = $result['cost'];

        //统计中奖次数、奖励总额
        $sql = "SELECT COUNT(1) AS count, SUM(totalWin) AS totalWin FROM {$t_bet_log} WHERE {$wheres} AND totalWin > 0 AND settled = 1";
        $result = Dao::db(DB_LOG)->query($sql, DBResult::FETCH_ONE);
        $hitTimes = $result['count'];
        $coinsReturn = $result['totalWin'];
        if (!$machineId) { //发放的周边奖励加入总返还
            $coinsReturn += array_sum($featureStats['coinsAward']['coins'] ?: []);
        }

        //统计freespin次数
        $fields = "COUNT(1) AS count";
        $sql = "SELECT {$fields} FROM {$t_bet_log} WHERE {$wheres} AND isFreeSpin = 1";
        $result = Dao::db(DB_LOG)->query($sql, DBResult::FETCH_ONE);
        $freespinTimes = $result['count'];

        //统计freespin中奖次数
        $sql = "SELECT COUNT(1) AS count FROM {$t_bet_log} WHERE {$wheres} AND isFreeSpin = 1 AND coinsAward > 0";
        $result = Dao::db(DB_LOG)->query($sql, DBResult::FETCH_ONE);
        $freespinHitTimes = $result['count'];

        //中奖率、RTP
        $hitRate = $spinTimes ? round($hitTimes * 100 / $spinTimes, 2) : 0;
        $freespinHitRate = $freespinTimes ? round($freespinHitTimes * 100 / $freespinTimes, 2) : 0;
        $rtp = $coinsCost ? round($coinsReturn * 100 / $coinsCost, 2) : 0;

        if (empty($featureStats['base'])) {
            $featureStats['base'] = [];
        }
        //计算feature命中率、rtp
        foreach ($featureStats['base'] as $feature => &$stat) {
            $stat['triggerRate'] = $spinTimes ? round($stat['trigger'] * 100 / $spinTimes, 5) : 0;
            $stat['hitRate'] = $spinTimes ? round($stat['hit'] * 100 / $spinTimes, 5) : 0;
            $stat['rtpInFeature'] = $stat['cost'] ? round($stat['coins'] * 100 / $stat['cost'], 2) : 0;
            $stat['rtpTotal'] = round($stat['triggerRate'] * $stat['rtpInFeature'] / 100, 2);
            $stat['rtpRate'] = $rtp ? round($stat['rtpTotal'] * 100 / $rtp, 2) : 0;
        }

        return array(
            'spinTimes' => $spinTimes,
            'hitTimes' => $hitTimes,
            'hitRate' => $hitRate,
            'freespinTimes' => $freespinTimes,
            'freespinHitTimes' => $freespinHitTimes,
            'freespinHitRate' => $freespinHitRate,
            'coinsCost' => $coinsCost,
            'coinsReturn' => $coinsReturn,
            'features' => $featureStats,
            'rtp' => $rtp,
        );
    }

    /**
     * 获取中奖倍数分布
     */
    public function getWinMultiples($testId, $type, $limitRect = null)
    {
        $test = Model::slotsTest()->getOneById($testId);
        if (!$test) FF::throwException(Code::PARAMS_INVALID);

        $fields = "CONVERT(totalWin / totalBet, DECIMAL(10,1)) AS winMultiple, COUNT(1) AS count";
        $where = array('settled' => 1, 'totalWin' => array('>', 0));
        $result = Model::betLog($testId)->fetchAll($where, $fields, array('winMultiple' => 'asc'), 'winMultiple');

        $data = array();

        if ($type == 1) {
            $multipleRects = array(
                [0, 0.5], [0.5, 1], [1, 1.5], [1.5, 2], [2, 3], [3, 4], [4, 5], [5, 6], [6, 7], [7, 8],
                [8, 9], [9, 10], [10, 12.5], [12.5, 15], [15, 17.5], [17.5, 20], [20, 25], [25, 30], [30, 40], [40, 50], [50, 100], [100, 0],
            );
            foreach ($result as $row) {
                $rect = $this->getWinMultipleRect($row['winMultiple'], $multipleRects, $limitRect);
                if ($rect) {
                    $data[$rect] += $row['count'];
                }
            }
        } else {
            $data = array_column($result, 'count', 'winMultiple');
        }

        return $data;
    }

    /**
     * 获取中奖弹窗分布
     */
    public function getWinPopups($testId)
    {
        $test = Model::slotsTest()->getOneById($testId);
        if (!$test) FF::throwException(Code::PARAMS_INVALID);

        $popups = array('all' => []);
        $multipleRects = array(
            [10, 25],
            [25, 50],
            [50, 0],
        );

        //单次spin倍数
        foreach (['all', 'normal'] as $type) {
            $fields = "CONVERT(totalWin / totalBet, DECIMAL(10,2)) AS winMultiple, COUNT(1) AS count";
            if ($type == 'normal') {
                $where = array('totalWin' => array('>', 0), 'settled' => 1, 'feature' => '', 'features' => '');
            } else {
                $where = array('totalWin' => array('>', 0), 'settled' => 1);
            }
            $result = Model::betLog($testId)->fetchAll($where, $fields, array('winMultiple' => 'asc'), 'winMultiple');
            foreach ($result as $row) {
                $rect = $this->getWinMultipleRect($row['winMultiple'], $multipleRects);
                if ($rect) {
                    $popups[$type][$rect] += $row['count'];
                }
            }
        }

        //freespin最大倍数与总倍数
        $fields = "MAX(coinsAward) AS coinsAward, MAX(totalWin) AS totalWin";
        $where = array('isFreeSpin' => 1);
        $result = Model::betLog($testId)->fetchAll($where, $fields, null, 'featureNo');

        //若freespin的totalWin倍数与maxWin不在一个倍数区间，则视为跳档，totalWin会额外弹窗
        foreach ($result as $row) {
            $rect1 = $this->getWinMultipleRect($row['coinsAward'] / $test['totalBet'], $multipleRects);
            $rect2 = $this->getWinMultipleRect($row['totalWin'] / $test['totalBet'], $multipleRects);
            if ($rect1 != $rect2) {
                $popups['all'][$rect2]++;
            }
        }

        //计算出feature中奖弹窗次数
        foreach ($popups['all'] as $rect => $count) {
            if (isset($popups['normal'][$rect])) {
                $popups['feature'][$rect] = $count - $popups['normal'][$rect];
            } else {
                $popups['feature'][$rect] = $count;
            }
            if ($popups['feature'][$rect] == 0) {
                unset($popups['feature'][$rect]);
            }
        }

        return $popups;
    }

    /**
     * 获取中奖倍数所属的倍数范围
     */
    public function getWinMultipleRect($winMultiple, $rects, $limitRect = null)
    {
        $rectStr = '';

        foreach ($rects as $rect) {
            if ($winMultiple >= $rect[0] && (!$rect[1] || $winMultiple < $rect[1])) {
                if (!$rect[1]) $rect[1] = '∞';
                $rectStr = "[{$rect[0]}-{$rect[1]})";
                if ($limitRect) {
                    if ($limitRect == $rectStr) {
                        $rectStr = $winMultiple;
                    } else {
                        $rectStr = '';
                    }
                }
                break;
            }
        }

        return $rectStr;
    }

    /**
     * 执行测试任务
     */
    public function machineTestTaskExec($testId)
    {
        $test = $this->getTestInfo($testId);

        if (!$test || $test['status'] != 1) return;

        $betUserCnt = $test['betUsers'];
        $progressNum = min(8, $betUserCnt);

        for ($i = 1; $i <= $progressNum; $i++) {
            $userCnt = floor($betUserCnt / $progressNum);
            if ($i == $progressNum) {
                $userCnt = $betUserCnt - $userCnt * ($i - 1);
            }
            $args = ['testId' => $testId, 'env' => ENV, 'group' => $i, 'betUserCnt' => $userCnt, 'totalGroup' => $progressNum];
            $runFile = PATH_ROOT . '/Scripts/SlotsTest/runner.php';
            $logFile = PATH_LOG . "/SlotsTest/test-{$testId}.log";

            exec_php_file($runFile, $args, true, $logFile);
        }

    }

    /**
     * Feature相关数据统计
     */
    public function featureStats($featureId, $featureName, $data)
    {
        if (!empty($data['isLightning'])) {
            $this->featureStats[$featureId]['betCoin'] = $data['totalBet'];
            $this->featureStats[$featureId]['winCoin'] = $data['totalWin'];
            $this->featureStats[$featureId]['triggerTimes'] += 1;
            $this->featureStats[$featureId]['name'] = $featureName;
            if ($data['totalWin']) {
                $this->featureStats[$featureId]['winTimes'] += 1;
            }
        } else {
            foreach ($data as $k => $v) {
                if ($k == 'base') {
                    $this->featureStats[$featureId]['winCoin'] += $v['coins'];
                    $this->featureStats[$featureId]['triggerTimes'] += $v['trigger'];
                    $this->featureStats[$featureId]['winTimes'] += $v['hit'];
                    $this->featureStats[$featureId]['name'] = $featureName ?: $featureId;
                }
            }
        }

        if (!empty($data['collected'])) {
            $fName = $featureName ?: $featureId . '_collect';
            $this->keywordStats[$fName][$data['collected']] += 1;
        }

        if (!empty($data['keyword'])) {
            $fName = $featureName ?: $featureId . $data['keyword'];
            $this->keywordStats[$fName][$data['keyword']] += 1;
        }
    }

    /**
     * 更新统计数据
     */
    public function updateStats($machineId)
    {
        if (!defined('TEST_ID')) return;
        $key = Keys::buildKey('UpdateStats', TEST_ID);

        //数据加锁
        while (!Dao::redis()->setnx($key, 1)) {
            sleep(1);
        }

        Dao::redis()->expire($key, 2);

        $this->updateMachineStats($machineId);
        $this->updateTotalStats();

        Dao::redis()->del($key);
    }

    public function updateMachineStats($machineId)
    {
        $mStatsKey = Keys::slotsTestResult(TEST_ID, $machineId);
        $data = Dao::redis()->get($mStatsKey);

        $data = $data ? json_decode($data, true) : array();
        $featureStats = array_column($data['feature'] ?? [], null, 'name');

        foreach ($this->featureStats as $featureId => $statsInfo) {
            foreach ($statsInfo as $key => $value) {
                if (!is_numeric($value)) {
                    $featureStats[$statsInfo['name']][$key] = $value;
                    continue;
                }
                $featureStats[$statsInfo['name']][$key] += $value;
            }
        }
        $data['feature'] = $featureStats;

        foreach ($this->keywordStats as $_fName => $collectInfo) {
            $collectedData = !empty($data[$_fName]) ? array_combine($data[$_fName]['value'], $data[$_fName]['times']) : [];
            foreach ($collectInfo as $collected => $times) {
                $collectedData[$collected] = ($collectedData[$collected] ?? 0) + $times;
            }

            ksort($collectedData, SORT_NUMERIC);

            $data[$_fName] = array('times' => array_values($collectedData), 'value' => array_keys($collectedData));
        }

        Dao::redis()->set($mStatsKey, json_encode($data));
    }

    public function updateTotalStats()
    {
        $tStatsKey = Keys::slotsTestResult(TEST_ID, 0);
        $tStatsData = Dao::redis()->get($tStatsKey);
        $tStatsData = $tStatsData ? json_decode($tStatsData, true) : array();
        $coinsAwards = array_combine($tStatsData['coinsAward']['reason'] ?? [], $tStatsData['coinsAward']['coins'] ?? []);
        foreach ($this->coinsAwardStats as $reason => $coins) {
            $coinsAwards[$reason] += $coins;
            $tStatsData['totalAward'] += $coins;
        }

        $tStatsData['coinsAward'] = array(
            'coins' => array_values($coinsAwards),
            'reason' => array_keys($coinsAwards),
        );

        foreach ($this->betResultStats as $key => $value) {
            $tStatsData[$key] += $value;
        }

        Dao::redis()->set($tStatsKey, json_encode($tStatsData));
    }

    public function betResultStats($betResult)
    {
        foreach ($betResult as $key => $value) {
            $this->betResultStats[$key] += $value;
        }
    }
}