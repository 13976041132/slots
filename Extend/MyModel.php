<?php
/**
 * 数据模型扩展
 */

namespace FF\Extend;

use FF\Framework\Driver\DbDriver;
use FF\Factory\Dao;
use FF\Framework\Core\FFModel;
use FF\Library\Utils\Pager;

class MyModel extends FFModel
{
    private $dbAlias = ''; //数据库别名(配置名)
    private $table = ''; //数据真实表名

    protected $idKey = 'id'; //主键字段名

    public function __construct($dbAlias = '', $table = '', $idKey = '')
    {
        $this->dbAlias = $dbAlias;
        $this->table = $table;
        if ($idKey) $this->idKey = $idKey;
    }

    /**
     * 获取数据库操作实例
     * @return DbDriver
     */
    public function db()
    {
        $pdo = Dao::db($this->dbAlias);

        return new DbDriver($pdo, $this->table);
    }

    /**
     * 获取数据表名
     * @return string
     */
    public function table()
    {
        return $this->table;
    }

    /**
     * 设置数据表名
     * @param string $table
     */
    public function setTable($table)
    {
        $this->table = $table;
    }

    /**
     * 分页查询
     * @param int $page
     * @param int $limit
     * @param array $where
     * @param string $fields
     * @param array $orderBy
     * @param string $groupBy
     * @return array
     */
    public function getPageList($page, $limit, $where = array(), $fields = null, $orderBy = array(), $groupBy = '')
    {
        $page = max(1, (int)$page);
        $offset = ($page - 1) * $limit;

        $data = array(
            'total' => 0, 'limit' => $limit, 'page' => $page, 'list' => array()
        );

        $result = $this->fetchOne($where, 'COUNT(1) AS count', null, $groupBy);
        $data['total'] = $result ? (int)$result['count'] : 0;

        if ($data['total']) {
            $data['list'] = $this->fetchAll($where, $fields, $orderBy, $groupBy, $limit, $offset);
        }

        $data['pager'] = new Pager($data);

        return $data;
    }

    public function getOneById($id, $fields = null)
    {
        return $this->fetchOne(array($this->idKey => $id), $fields);
    }

    public function getMulti($ids, $fields = null)
    {
        if (!$ids || !is_array($ids)) return array();

        $where = array(
            $this->idKey => array('in', $ids)
        );

        $data = $this->fetchAll($where, $fields);

        return array_column($data, null, $this->idKey);
    }

    public function updateById($id, $data, $where = null)
    {
        if (!$data || !is_array($data)) return false;
        if ($where && !is_array($where)) return false;

        $idWhere = array($this->idKey => $id);
        $where = $where ? array_merge($idWhere, $where) : $idWhere;

        return $this->update($data, $where);
    }

    public function updateMulti($ids, $data, $where = null)
    {
        if (!$ids || !is_array($ids)) return false;
        if (!$data || !is_array($data)) return false;
        if ($where && !is_array($where)) return false;

        $idWhere = array($this->idKey => array('in', $ids));
        $where = $where ? array_merge($idWhere, $where) : $idWhere;

        return $this->update($data, $where, count($ids));
    }

    public function deleteById($id)
    {
        return $this->delete(array($this->idKey => $id));
    }

    public function deleteMulti($ids)
    {
        if (!$ids || !is_array($ids)) return false;

        $where = array(
            $this->idKey => array('in', $ids)
        );

        return $this->delete($where, count($ids));
    }

    public function getFieldValue($id, $field)
    {
        $data = $this->getOneById($id, $field);

        return $data ? $data[$field] : null;
    }
}