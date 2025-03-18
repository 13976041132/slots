<?php /** @noinspection ALL */

/**
 * 缓存业务逻辑层
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

abstract class RedisCacheBll
{
    protected $cacheEmpty = false;
    protected $fields = array();
    protected $droppedFields = array();

    protected $uniqueKey = 'uid';

    protected $defttl = 86400;

    /**
     * @return string
     */
    abstract function getCacheKey($uuid);

    /**
     * 获取redis实例
     */
    protected function redis()
    {
        return Dao::redis();
    }

    /**
     * 获取缓存数据
     */
    public function getCacheData($uuid, $fields = null)
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
        $cacheKey = $this->getCacheKey($uuid);

        if (!$fields) {
            $result = $redis->hGetAll($cacheKey);
        } else {
            $_fields = array_values(array_unique(array_merge([$this->uniqueKey], $fields)));
            $result = $redis->hMGet($cacheKey, $_fields);
        }

        //强制检查uid字段，防止意外情况下产生脏数据
        if (!$result || empty($result[$this->uniqueKey])) {
            $result[$this->uniqueKey] = $uuid;
            $redis->hMSet($cacheKey, $result);
            $redis->expire($cacheKey, $this->defttl);
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
     * 获取用户某个字段值
     */
    public function getField($uuid, $field = null)
    {
        $result = $this->getCacheData($uuid, $field);

        return $result && isset($result[$field]) ? $result[$field] : null;
    }

    /**
     * 更新缓存数据
     */
    public function updateCacheData($uuid, $data = null)
    {
        $key = $this->getCacheKey($uuid);
        $data[$this->uniqueKey] = $uuid;
        return $this->redis()->hMSet($key, $data);
    }

    public function clean($uuid = [])
    {
        $key = $this->getCacheKey($uuid);
        $this->redis()->del($key);
    }
}