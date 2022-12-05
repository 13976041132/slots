<?php
/**
 * Sql构造器
 */

namespace FF\Framework\Driver;

use FF\Framework\Common\Code;
use FF\Framework\Driver\Extend\_Pdo;

class SqlBuilder
{
    /**
     * @var _Pdo
     */
    private $pdo;

    private $fieldCounts = array();

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * 查询参数过滤
     * @param $str
     * @return string
     */
    public function quote($str)
    {
        return $this->pdo->quote($str);
    }

    /**
     * 构造set语句
     * @param array $data
     * @param array $params
     * @throws \Exception
     * @return string
     */
    public function sets($data, &$params)
    {
        if (!$data || !is_array($data)) {
            return '';
        }

        $sets = array();

        foreach ($data as $field => $val) {
            $key = 's_' . $field;
            if (is_array($val)) {
                //支持字段增量和乘除，格式 key => ['+=|-=|*=|/=', 值]
                if (isset($val[0]) && isset($val[1]) && in_array($val[0], ['+=', '-=', '*=', '/='], true) && is_numeric($val[1])) {
                    $sets[] = '`' . $field . '`' . ' = `' . $field . '` ' . substr($val[0], 0, 1) . ' :' . $key;
                    $params[$key] = $val[1];
                } elseif (isset($val[0]) && isset($val[1]) && strtoupper($val[0]) == 'SQL') {
                    $sets[] = '`' . $field . '`' . ' = ' . $val[1];
                } else {
                    throw new \Exception('sql sets error: ' . $field . ' = ' . json_encode($val), Code::SYSTEM_ERROR);
                }
            } else {
                $sets[] = '`' . $field . '`' . ' = :' . $key;
                $params[$key] = $val;
            }
        }

        return implode(', ', $sets);
    }

    /**
     * 构造where语句
     * @param array $where
     * @param array $params
     * @return string
     * @example
     * array('Id'=>1)
     * array('Id'=>array('>', 2))
     * array('Id'=>array('>', 2, '<', 5))
     * array('Id'=>array('in', array(1,2)))
     * @throws \Exception
     */
    public function where($where, &$params)
    {
        if (!$where || !is_array($where)) {
            return '';
        }

        $wheres = array();
        $conditions = array();

        //支持同一字段上多个条件
        //所有条件都是AND关系，不支持OR
        foreach ($where as $field => $val) {
            $k = 0;
            if (is_array($val)) {
                while (isset($val[$k])) {
                    $op = strtoupper($val[$k]);
                    $value = isset($val[$k + 1]) ? $val[$k + 1] : null;
                    $conditions[] = array($field, $op, $value);
                    $k += 2;
                }
            } elseif ($val === null) {
                $conditions[] = array($field, 'IS', null);
            } else {
                $conditions[] = array($field, '=', $val);
            }
        }

        foreach ($conditions as $condition) {
            $wheres[] = $this->_where($condition[0], $condition[1], $condition[2], $params);
        }

        return implode(' AND ', $wheres);
    }

    /**
     * where语句拼接
     * @param string $field
     * @param string $op
     * @param mixed $val
     * @param array $params
     * @return string
     * @throws \Exception
     */
    private function _where($field, $op, $val, &$params)
    {
        $key = 'w_' . $field;
        if (isset($this->fieldCounts[$field])) {
            $key .= '_' . $this->fieldCounts[$field];
        }

        switch ($op) {
            case 'IN':
            case 'NOT IN':
                $where = '`' . $field . '` ' . $op . ' (' . $this->wherein($val) . ')';
                break;
            case 'BETWEEN':
                $where = '`' . $field . "` BETWEEN :{$key}__1 AND :{$key}__2";
                $params[$key . '__1'] = $val[0];
                $params[$key . '__2'] = $val[1];
                break;
            case 'IS':
            case 'IS NOT':
                $where = '`' . $field . '` ' . $op . ' NULL';
                break;
            case 'LIKE':
                $where = '`' . $field . '` ' . $op . ' :' . $key;
                $params[$key] = $val;
                break;
            case 'SQL':
                $where = '(' . $val . ')';
                break;
            default:
                if (is_numeric($field)) {
                    $where = $field . ' ' . $op . ' :' . $key;
                } else {
                    $where = '`' . $field . '` ' . $op . ' :' . $key;
                }
                $params[$key] = $val;
                break;
        }

        if (!isset($this->fieldCounts[$field])) {
            $this->fieldCounts[$field] = 0;
        }
        $this->fieldCounts[$field]++;

        return $where;
    }

    /**
     * 转换where in
     * @param array | string $data
     * @return string
     * @throws \Exception
     */
    public function wherein($data)
    {
        //可支持子查询
        if (is_string($data)) return $data;

        if (!is_array($data)) {
            throw new \Exception('Wherein is invalid', Code::SYSTEM_ERROR);
        }

        $in = array();

        //过滤掉非法值
        foreach ($data as $val) {
            if (is_string($val)) {
                $in[] = $this->quote($val);
            } elseif (is_numeric($val)) {
                $in[] = (int)$val;
            }
        }

        if (!$in) {
            throw new \Exception('Wherein could not be empty', Code::SYSTEM_ERROR);
        }

        return implode(', ', $in);
    }

    /**
     * 构造排序
     * 支持两种参数格式
     * 格式1：'Id DESC, Time ASC'
     * 格式2：array('Id' => 'DESC', 'Time' => 'ASC')
     * @param array $orderBy
     * @return string
     */
    public function orderBy($orderBy)
    {
        if (is_string($orderBy)) return $orderBy;
        if (!is_array($orderBy)) return '';

        $_order_by = array();

        foreach ($orderBy as $field => $order) {
            if (!is_string($field) || !is_string($order)) {
                continue;
            }
            $field = trim($field);
            $order = strtoupper(trim($order));
            if (!$field || !in_array($order, array('ASC', 'DESC'), true)) {
                continue;
            }
            // 兼容函数
            if (preg_match("/\(*\)/", $field)) {
                $_order_by[] =  $field . ' ' . $order;
            } else {
                $_order_by[] = '`' . $field . '` ' . $order;
            }
        }

        return implode(', ', $_order_by);
    }
}