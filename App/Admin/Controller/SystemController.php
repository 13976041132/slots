<?php
/**
 * 系统管理
 */

namespace FF\App\Admin\Controller;

use FF\Factory\Dao;
use FF\Factory\Model;
use FF\Framework\Common\Code;
use FF\Framework\Core\FF;
use FF\Framework\Utils\Config;
use FF\Library\Utils\Utils;

class SystemController extends BaseController
{
    /**
     * Redis管理
     */
    public function redis()
    {
        $data['servers'] = Config::get('redis');
        $data['keyMap'] = $this->getRedisKeyMap();

        $this->display('redis.html', $data);
    }

    private function getRedisKeyMap()
    {
        $keyMap = array();

        $keyClass = '\FF\Factory\Keys';
        $keyClassRef = new \ReflectionClass($keyClass);
        $methods = $keyClassRef->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->getName();
            $methodRef = new \ReflectionMethod($keyClass, $methodName);
            $comment = Utils::getDocComment($methodRef);
            $keyMap[$methodName] = $comment;
        }

        unset($keyMap['buildKey']);

        return $keyMap;
    }

    /**
     * 查看Redis状态
     */
    public function getRedisStatus()
    {
        $server = (string)$this->getParam('server');
        $section = (string)$this->getParam('section');

        if (!Config::get('redis', $server)) {
            FF::throwException(Code::PARAMS_INVALID);
        }

        $info = Dao::redis($server)->info($section);

        return $info;
    }

    /**
     * 查询RedisKey
     */
    public function getRedisKeys()
    {
        $server = (string)$this->getParam('server');
        $keyword = (string)$this->getParam('keyword', false);
        $keyType = (string)$this->getParam('keyType', false);

        if (!Config::get('redis', $server)) {
            FF::throwException(Code::PARAMS_INVALID);
        }

        if ($keyword === '' && $keyType === '') {
            FF::throwException(Code::PARAMS_INVALID, '搜索条件不能全部为空');
        }

        $prefix = Config::get('core', 'cache_key_prefix');
        $pattern = "{$prefix}:";
        if ($keyType) $pattern .= ucfirst($keyType);
        if ($keyword) $pattern .= ":*" . $keyword;
        $pattern .= "*";

        $keys = Dao::redis()->keys($pattern);
        sort($keys);

        return $keys;
    }

    /**
     * 查询Redis数据
     */
    public function getRedisData()
    {
        $server = (string)$this->getParam('server');
        $key = (string)$this->getParam('key');

        if (!Config::get('redis', $server)) {
            FF::throwException(Code::PARAMS_INVALID);
        }

        $type = Dao::redis($server)->type($key);

        $data = null;

        switch ($type) {
            case \Redis::REDIS_STRING:
                $data = Dao::redis()->get($key);
                break;
            case \Redis::REDIS_SET:
                $data = Dao::redis()->sMembers($key);
                break;
            case \Redis::REDIS_LIST:
                $data = Dao::redis()->lRange($key, 0, -1);
                break;
            case \Redis::REDIS_ZSET:
                $data = Dao::redis()->zRange($key, 0, -1);
                break;
            case \Redis::REDIS_HASH:
                $data = Dao::redis()->hGetAll($key);
                break;
            case \Redis::REDIS_NOT_FOUND:
                break;
        }

        $ttl = Dao::redis()->ttl($key);

        return array(
            'key' => $key,
            'data' => $data,
            'ttl' => $ttl
        );
    }

    /**
     * 操作日志（operationLog）
     */
    public function operationLog()
    {
        $page = (int)$this->getParam('page', false, 1);
        $limit = (int)$this->getParam('limit', false, 15);
        $category = $this->getParam('category', false);
        $target = $this->getParam('target', false);

        $where = array();
        $where['appId'] = APP_ID;
        if ($category) $where['category'] = $category;
        if ($target) $where['target'] = $target;

        $data = Model::operationLog()->getPageList($page, $limit, $where, null, array('id' => 'desc'));

        // 使用菜单内容作为操作类别选项
        $categories = \AdminOpCategory::getAll();
        $targetList = Model::operationTarget()->fetchAll();

        $data['categories'] = $categories;
        $data['targets'] = $targetList;

        $this->display('operationLog.html', $data);
    }

    /**
     * 操作日志内容
     */
    public function operationLogContent()
    {
        $id = (int)$this->getParam('id');

        $logData = Model::operationLog()->getOneById($id, 'content');
        if (!$logData) FF::throwException(Code::PARAMS_INVALID);

        $this->display('operationLogContent.html', $logData);
    }

}