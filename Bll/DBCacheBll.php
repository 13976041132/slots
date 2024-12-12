<?php /** @noinspection ALL */

/**
 * 数据库缓存业务逻辑层
 */

namespace FF\Bll;

use FF\Extend\MyModel;
use FF\Factory\Bll;
use FF\Factory\Dao;
use FF\Factory\MQ;
use FF\Framework\Utils\Log;
use FF\Library\Utils\Utils;
use FF\Service\Lib\Service;
use GPBClass\Enum\MSG_ID;

abstract class DBCacheBll
{
    protected $cacheEmpty = false;
    protected $fields = array();
    protected $droppedFields = array();

    protected $uniqueKey = 'uid';

    public $onlyDQL = false;
    /**
     * @return MyModel
     */
    abstract function model($uid);

    /**
     * @return string
     */
    abstract function getCacheKey($uid, $wheres);

    /**
     * 获取redis实例
     */
    protected function redis()
    {
        return Dao::redis();
    }

    /**
     * 构造用户匹配条件
     * 主键/唯一索引可能是uid和其他字段的联合键
     */
    public function makeWheres($uid, $wheres)
    {
        if ($wheres) {
            $wheres = array_merge(array($this->uniqueKey => $uid), $wheres);
        } else {
            $wheres = array($this->uniqueKey => $uid);
        }

        return $wheres;
    }

    /**
     * 获取缓存数据
     */
    public function getCacheData($uid, $fields = null, $wheres = null)
    {
        if (is_string($fields)) {
            $fields = str_replace(' ', '', $fields);
        }

        if ($fields == '*' || is_empty($fields)) {
            $fields = null;
        } elseif (!is_array($fields)) {
            $fields = explode(',', $fields);
        }

        $redis = $this->redis();
        $cacheKey = $this->getCacheKey($uid, $wheres);

        if (!$fields) {
            $result = $redis->hGetAll($cacheKey);
        } else {
            $_fields = array_values(array_unique(array_merge([$this->uniqueKey], $fields)));
            $result = $redis->hMGet($cacheKey, $_fields);
        }

        //强制检查uid字段，防止意外情况下产生脏数据
        if (!$result || empty($result[$this->uniqueKey])) {
            Log::info(['getCacheData', $uid, $cacheKey, $fields, $wheres, $result], 'redis-loss.log');
            $result = $this->fetchDataFromDB($uid, '*', $wheres);
            if ($result || $this->cacheEmpty) {
                $result[$this->uniqueKey] = $uid;
                $redis->hMSet($cacheKey, $result);
                $redis->expire($cacheKey, 12 * 3600);
            }
        }

        $data = array();
        $fields = $fields ?: array_keys($this->fields);

        //脏数据检查以及数据类型校正
        if ($this->droppedFields) {
            $droppedFields = array_intersect($this->droppedFields, array_keys($result));
            if ($droppedFields) {
                $droppedFields = array_values($droppedFields);
                $redis->hDel($cacheKey, ...$droppedFields);
            }
        }
        foreach ($fields as $field) {
            if (!isset($this->fields[$field])) {
                continue;
            }
            if (!isset($result[$field]) || $result[$field] === '') {
                $data[$field] = $this->fields[$field][1]; //default value
            } else {
                $format = $this->fields[$field][0];
                $data[$field] = Utils::dataFormat($result[$field], $format);
            }
        }

        return $data;
    }

    /**
     * 从数据库获取源数据
     */
    public function fetchDataFromDB($uid, $fields = '*', $wheres = null)
    {
        $wheres = $this->makeWheres($uid, $wheres);
        $result = $this->model($uid)->fetchOne($wheres, $fields);

        if (!$result) {
            $this->initDataInDB($uid, $wheres);
            $result = $this->model($uid)->fetchOne($wheres, $fields);
        }

        return $result;
    }

    /**
     * 用户数据初始化入库
     */
    public function initDataInDB($uid, $data)
    {
        //to override
    }

    /**
     * 获取用户某个字段值
     */
    public function getField($uid, $field, $wheres = null)
    {
        $result = $this->getCacheData($uid, $field, $wheres);

        return $result && isset($result[$field]) ? $result[$field] : null;
    }

    /**
     * 更新缓存数据
     */
    public function updateCacheData($uid, $data, $wheres = null, $sync = false)
    {
        $key = $this->getCacheKey($uid, $wheres);
        $wheres = $this->makeWheres($uid, $wheres);

        if ($this->redis()->hGet($key, $this->uniqueKey)) {
            // if ($sync || ENV == Env::DEVELOPMENT) {
            if ($sync && $this->onlyDQL == false) {
                $this->model($uid)->update($data, $wheres);
            }
            return $this->redis()->hMSet($key, $data);
        } elseif ($sync && $this->onlyDQL == false) {
            return $this->model($uid)->update($data, $wheres);
        }
    }

    /**
     * 增量更新用户某个字段值
     */
    public function updateFieldByInc($uid, $field, $incValue, $reason = '', &$newValue = null, $wheres = null)
    {
        if (!$incValue) return false;

        $wheres = $wheres ?: array();
        $key = $this->getCacheKey($uid, $wheres);
        $redis = $this->redis();

        if ($redis->hGet($key, $this->uniqueKey)) {
            //缓存中有数据，则更新缓存
            $isFloat = $this->fields[$field][0] == 'float';
            $incFun = $isFloat ? 'hIncrByFloat' : 'hIncrBy';
            $incValue = $isFloat ? (float)$incValue : (int)$incValue;
            $newValue = $redis->$incFun($key, $field, $incValue);
            if ($newValue === false) return false;
            //更新后的值不能为负数，特别是在扣钱场景。若为负，则表示操作失败，需要回滚数据
            if ($incValue < 0 && $newValue < 0) {
                $redis->$incFun($key, $field, -$incValue);
                $newValue += -$incValue;
                $result = false;
            } else {
                $result = true;
            }
        } elseif($this->onlyDQL == false) {
            //缓存中无数据，则更新数据库
            $updates = array($field => array('+=', $incValue));
            $where = $incValue < 0 ? array($field => array('>=', -$incValue)) : array();
            $where = array_merge(array($this->uniqueKey => $uid), $wheres, $where);
            $result = $this->model($uid)->update($updates, $where);
            $newData = $this->fetchDataFromDB($uid, $field, $wheres);
            if ($newData) {
                $newValue = $newData[$field];
            }
        }

        if ($result) {
            $this->addDataLog($uid, $field, $incValue, $newValue, $reason);
        }

        return $result;
    }

    /**
     * 添加数据日志
     */
    public function addDataLog($uid, $field, $incValue, $newValue, $reason)
    {
        //to override
    }

    /**
     * 批量获取缓存数据
     */
    public function getCacheList(array $uids, $fields = null, $wheres = null)
    {
        if (is_string($fields)) {
            $fields = str_replace(' ', '', $fields);
        }

        if ($fields == '*' || is_empty($fields)) {
            $fields = null;
        } elseif (!is_array($fields)) {
            $fields = explode(',', $fields);
        }

        // 使用Redis管道
        $redis = $this->redis()->pipeline();

        // 组装带查询的KEY
        foreach ($uids as $uid) {
            $cacheKey = $this->getCacheKey($uid, $wheres);

            // 请求字段是否存在
            if (!$fields) {
                $redis->hGetAll($cacheKey);
            } else {
                $tempFields = array_values(array_unique(array_merge([$this->uniqueKey], $fields)));
                $redis->hMGet($cacheKey, $tempFields);
            }
        }
        $resultList = $redis->exec();

        //强制检查uid字段，防止意外情况下产生脏数据
        $dirtyList = [];
        foreach ($resultList as $index => $result) {
            if (!$result || empty($result[$this->uniqueKey])) {
                $dirtyList[$uids[$index]] = $index;
            }
        }

        // 处理脏数据
        if (!empty($dirtyList)) {
            // 带查询用户
            $dirtyUids = array_keys($dirtyList);

            // makeWheres
            if ($wheres) {
                $wheres = array_merge(array($this->uniqueKey => array('in' => $dirtyUids)), $wheres);
            } else {
                $wheres = array($this->uniqueKey => array('in' => $dirtyUids));
            }

            // fetchDataFromDB
            $dirtyResultList = $this->model(0)->fetchAll($wheres);

            // 存储数据
            $redis = $this->redis()->pipeline();
            foreach ($dirtyResultList as $result) {
                if ($result) {
                    $cacheKey = $this->getCacheKey($result[$this->uniqueKey], $wheres);
                    $resultList[$dirtyList[$result[$this->uniqueKey]]] = $result;
                    $redis->hMSet($cacheKey, $result);
                    $redis->expire($cacheKey, 12 * 3600);
                }
            }
            $redis->exec();
        }

        $dataList = [];
        $fields = $fields ?: array_keys($this->fields);

        //脏数据检查以及数据类型校正
        $redis = $this->redis()->pipeline();
        foreach ($resultList as $index => $result) {
            $cacheKey = $this->getCacheKey($result[$this->uniqueKey], $wheres);
            if ($this->droppedFields) {
                $droppedFields = array_intersect($this->droppedFields, array_keys($result));
                if ($droppedFields) {
                    $droppedFields = array_values($droppedFields);
                    $redis->hDel($cacheKey, ...$droppedFields);
                }
            }
            foreach ($fields as $field) {
                if (!isset($this->fields[$field])) {
                    continue;
                }
                if (!isset($result[$field]) || $result[$field] === '') {
                    $dataList[$result[$this->uniqueKey]][$field] = $this->fields[$field][1]; //default value
                } else {
                    $format = $this->fields[$field][0];
                    $dataList[$result[$this->uniqueKey]][$field] = Utils::dataFormat($result[$field], $format);
                }
            }
        }
        $redis->exec();

        return $dataList;
    }
}