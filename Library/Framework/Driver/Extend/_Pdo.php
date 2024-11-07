<?php
/**
 * 数据库操作类
 * 基于PDO
 */

namespace FF\Framework\Driver\Extend;

use FF\Framework\Common\Code;
use FF\Framework\Common\DBResult;
use FF\Framework\Utils\Log;
use FF\Framework\Utils\Str;

class _Pdo
{
    /**
     * @var \PDO
     */
    private $pdo;

    /**
     * @var \PDOStatement
     */
    private $statement;

    private $config;

    public function __construct($dsn, $username, $passwd, $options)
    {
        $this->config = array(
            'dsn' => $dsn,
            'username' => $username,
            'passwd' => $passwd,
            'options' => $options,
        );
    }

    /**
     * @return \PDO
     */
    private function pdo($retry = 0)
    {
        if ($this->pdo) {
            return $this->pdo;
        }

        set_error_handler(function (...$args) {
            restore_error_handler();
            throw new \PDOException("Database connect failed", Code::DB_CONNECT_FAILED);
        });

        try {
            $config = $this->config;
            $this->pdo = new \PDO($config['dsn'], $config['username'], $config['passwd'], $config['options']);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, false);
            $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        } catch (\PDOException $e) {
            if ($retry < 3) {
                $this->pdo = null;
                return $this->pdo(++$retry);
            }
            throw $e;
        }

        restore_error_handler();

        return $this->pdo;
    }

    /**
     * 查询参数过滤
     * @param $str
     * @return string
     */
    public function quote($str)
    {
        return $this->pdo()->quote($str);
    }

    /**
     * 获取最近插入行ID
     * @return int
     */
    public function lastInsertId()
    {
        return (int)$this->pdo()->lastInsertId();
    }

    /**
     * 获取最后一次DB错误
     * @return \Error
     */
    public function getLastError()
    {
        if (!$this->pdo) {
            return new \Error('', 0);
        }

        $errorInfo = null;
        if ($this->statement) {
            $errorInfo = $this->statement->errorInfo();
        }
        if (!$errorInfo || !$errorInfo[2]) {
            $errorInfo = $this->pdo->errorInfo();
        }

        if ($errorInfo) {
            return new \Error($errorInfo[2], $errorInfo[1]);
        } else {
            return new \Error('', 0);
        }
    }

    /**
     * 判断数据表是否存在
     * @param string $table
     * @return bool
     */
    public function tableExists($table)
    {
        $result = $this->query("SHOW TABLES LIKE '{$table}'", DBResult::FETCH_ONE);

        return $result ? true : false;
    }

    /**
     * 获取数据表记录行数
     * @param string $table
     * @return int
     */
    public function getRowCount($table)
    {
        $result = $this->query("SELECT COUNT(1) AS `count` FROM {$table}", DBResult::FETCH_ONE);

        return $result['count'];
    }

    /**
     * 进行sql查询
     * @param string $sql 执行SQL
     * @param int $mode 返回值类型
     * @return array|int|\PDOStatement
     */
    public function query($sql, $mode = DBResult::FETCH_ONE)
    {
        return $this->execute($sql, array(), $mode, false);
    }

    /**
     * 执行数据库操作
     * @param string $sql SQL语句
     * @param array $params 绑定参数
     * @param int $mode 返回值类型
     * @param bool $prepare 是否走prepare模式
     * @return array|int|\PDOStatement
     */
    public function execute($sql, $params = array(), $mode = DBResult::AFFECTED_ROWS, $prepare = true, $retry = 0)
    {
        try {
            $time = microtime(true);
            set_error_handler(function (...$args) {
                restore_error_handler();
                throw new \PDOException("", Code::DB_EXECUTE_FAILED);
            });
            if ($prepare) {
                $this->statement = $this->pdo()->prepare($sql);
            } else {
                $this->statement = $this->pdo()->query($sql);
            }
            if (!$this->statement) {
                $this->error($sql, $params, $prepare ? 'prepare' : 'query');
            }
            if ($prepare) {
                $result = $this->statement->execute($params);
                if (!$result) {
                    $this->error($sql, $params, 'execute');
                }
            }
            restore_error_handler();
            //慢查询日志
            $row_count = $this->statement->rowCount();
            $cost = (int)((microtime(true) - $time) * 1000);
            if ($cost > 200) {
                $log = array('cost' => $cost, 'sql' => $sql, 'params' => $params, 'rows' => $row_count);
                Log::warning($log, 'db-slow.log');
            }
            $result = $this->fetchResult($mode);
            return $result;
        } catch (\PDOException $e) {
            if ($this->hasLostConnection($e) && $retry < 3) {
                Log::warning('Database reconnect...');
                $this->pdo = null;
                return $this->execute($sql, $params, $mode, $prepare, ++$retry);
            } else {
                $this->error($sql, $params, $prepare ? 'prepare' : 'query');
                return false;
            }
        }
    }

    /**
     * 获取返回值
     * @param int $mode
     * @return int|array|\PDOStatement
     */
    private function fetchResult($mode)
    {
        $row_count = $this->statement->rowCount();

        if ($mode == DBResult::AFFECTED_ROWS) {
            return $row_count;
        }
        if ($mode == DBResult::FETCH_ONE) {
            return $row_count ? $this->statement->fetch(\PDO::FETCH_ASSOC) : array();
        }
        if ($mode == DBResult::FETCH_ALL) {
            return $row_count ? $this->statement->fetchAll(\PDO::FETCH_ASSOC) : array();
        }
        if ($mode == DBResult::STATEMENT) {
            return $this->statement;
        }

        return 0;
    }

    /**
     * 判断是否已断开连接
     * @param $e \Exception
     * @return bool
     */
    protected function hasLostConnection($e)
    {
        if (!$e->getMessage()) {
            $e = $this->getLastError();
        }

        return Str::contains($e->getMessage(), [
            'server has gone away',
            'no connection to the server',
            'Lost connection',
            'is dead or not enabled',
            'Error while sending',
            'decryption failed or bad record mac',
            'SSL connection has been closed unexpectedly',
        ]);
    }

    /**
     * 错误处理
     * @param string $sql
     * @param array $params
     * @param string $action
     */
    private function error($sql, $params, $action)
    {
        $error = $this->getLastError();
        $errorMessage = '[' . $error->getMessage() . '][' . $sql . ']' . json_encode($params);
        Log::error("Database {$action} failed {$errorMessage}", 'db_error.log');

        throw new \PDOException("Database {$action} failed", Code::DB_EXECUTE_FAILED);
    }

    public function transaction()
    {
        $this->pdo()->beginTransaction();
    }

    public function commit()
    {
        $this->pdo()->commit();
    }

    public function rollback()
    {
        $this->pdo()->rollBack();
    }
}