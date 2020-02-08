<?php

namespace Pike;

class Db {
    protected $tablePrefix;
    protected $config;
    private $pdo;
    private $transactionLevel = 0;
    /**
     * @param array $config ['db.host' => string, ...]
     */
    public function __construct(array $config) {
        $this->setConfig($config);
    }
    /**
     * @return bool
     * @throws \Pike\PikeException
     */
    public function open() {
        try {
            $this->pdo = new \PDO(
                'mysql:host=' . $this->config['db.host'] .
                     ';dbname=' . $this->config['db.database'] .
                     ';charset=' . $this->config['db.charset'] ?? 'utf8',
                $this->config['db.user'],
                $this->config['db.pass'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            return true;
        } catch (\PDOException $e) {
            throw new PikeException('The database connection failed: ' . $e->getCode(),
                                    PikeException::ERROR_EXCEPTION);
        }
    }
    /**
     * @param string $query
     * @param array $params = null
     * @return array
     * @throws \PDOException
     */
    public function fetchAll($query, array $params = null) {
        $prep = $this->pdo->prepare($this->compileQ($query));
        $prep->execute($params);
        return $prep->fetchAll(\PDO::FETCH_ASSOC);
    }
    /**
     * @param string $query
     * @param array $params = null
     * @return object|bool
     * @throws \PDOException
     */
    public function fetchOne($query, array $params = null) {
        $prep = $this->pdo->prepare($this->compileQ($query));
        $prep->execute($params);
        return $prep->fetch(\PDO::FETCH_ASSOC);
    }
    /**
     * @param string $query
     * @param array $params = null
     * @return int
     */
    public function exec($query, array $params = null) {
        $prep = $this->pdo->prepare($this->compileQ($query));
        $prep->execute($params ? array_map(function ($val) {
            return !is_bool($val) ? $val : (int)$val;
        }, $params) : $params);
        return $prep->rowCount();
    }
    /**
     * @return int $this->transactionLevel or -1 on failure
     */
    public function beginTransaction() {
        if (++$this->transactionLevel === 1) {
            if (!$this->pdo->beginTransaction()) return -1;
        }
        return $this->transactionLevel;
    }
    /**
     * @return int $this->transactionLevel or -1 on failure
     */
    public function commit() {
        if ($this->transactionLevel > 0 && --$this->transactionLevel === 0) {
            if (!$this->pdo->commit()) return -1;
        }
        return $this->transactionLevel;
    }
    /**
     * @return int $this->transactionLevel or -1 on failure
     */
    public function rollback() {
        if ($this->transactionLevel > 0 && --$this->transactionLevel === 0) {
            if (!$this->pdo->rollBack()) return -1;
        }
        return $this->transactionLevel;
    }
    /**
     * @param \Closure $fn
     */
    public function runInTransaction(\Closure $fn) {
        if ($this->beginTransaction() < 0) {
            throw new PikeException('Failed to start a transaction',
                                    PikeException::FAILED_DB_OP);
        }
        try {
            $fn();
            if ($this->commit() < 0) {
                throw new PikeException('Failed to commit a transaction',
                                        PikeException::FAILED_DB_OP);
            }
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    /**
     * @return string
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    /**
     * @param int $attr
     * @param mixed $value = null
     * @return mixed|bool
     */
    public function attr($attr, $value = null) {
        return !$value
            ? $this->pdo->getAttribute($attr)
            : $this->pdo->setAttribute($attr, $value);
    }
    /**
     * @param array $config ['db.host' => string, ...]
     */
    public function setConfig(array $config) {
        $this->config = $config;
        $this->tablePrefix = $config['db.tablePrefix'] ?? '';
    }
    /**
     * @return string
     */
    private function compileQ($query) {
        return str_replace('${p}', $this->tablePrefix, $query);
    }
}
