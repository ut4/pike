<?php

declare(strict_types=1);

namespace Pike;

class Db extends DbUtils {
    /** @var string */
    protected $tablePrefix;
    /** @var array<string, mixed> */
    protected $config;
    /** @var \PDO */
    private $pdo;
    /** @var int */
    private $transactionLevel = 0;
    /**
     * @param object|array $config ['db.host' => string, ...]
     */
    public function __construct($config) {
        $this->setConfig($config);
    }
    /**
     * @return bool
     * @throws \Pike\PikeException
     */
    public function open(): bool {
        try {
            $this->pdo = new \PDO(
                'mysql:host=' . ($this->config['db.host'] ?? '127.0.0.1') .
                     ';dbname=' . ($this->config['db.database'] ?? '') .
                     ';charset=' . ($this->config['db.charset'] ?? 'utf8mb4') ,
                $this->config['db.user'] ?? '',
                $this->config['db.pass'] ?? '',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            $this->config = [];
            return true;
        } catch (\PDOException $e) {
            throw new PikeException("The database connection failed: {$e->getCode()}",
                                    PikeException::ERROR_EXCEPTION,
                                    $e);
        }
    }
    /**
     * @param string $query
     * @param mixed[] $params = null
     * @param int $fetchStyle = \PDO::FETCH_ASSOC
     * @param mixed $fetchArgument = null
     * @param array $fetchCtorArgs = []
     * @return array
     * @throws \Pike\PikeException
     */
    public function fetchAll(string $query,
                             array $params = null,
                             ...$fetchConfig): array {
        try {
            $prep = $this->pdo->prepare($this->compileQuery($query));
            $prep->execute($params);
            return $fetchConfig
                ? $prep->fetchAll(...$fetchConfig)
                : $prep->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new PikeException($e->getMessage(),
                                    PikeException::FAILED_DB_OP,
                                    $e);
        }
    }
    /**
     * @param string $query
     * @param mixed[] $params = null
     * @param int $fetchStyle = \PDO::FETCH_ASSOC
     * @param mixed $fetchArgument = null
     * @param array $fetchCtorArgs = []
     * @return object|array|null
     * @throws \Pike\PikeException
     */
    public function fetchOne(string $query,
                             array $params = null,
                             ...$fetchConfig) {
        try {
            $prep = $this->pdo->prepare($this->compileQuery($query));
            $fetchConfig && $prep->setFetchMode(...$fetchConfig);
            $prep->execute($params);
            $row = $prep->fetch();
            return $row !== false ? $row : null;
        } catch (\PDOException $e) {
            throw new PikeException($e->getMessage(),
                                    PikeException::FAILED_DB_OP,
                                    $e);
        }
    }
    /**
     * @param string $query
     * @param mixed[] $params = null
     * @return int
     * @throws \Pike\PikeException
     */
    public function exec(string $query, array $params = null): int {
        try {
            $prep = $this->pdo->prepare($this->compileQuery($query));
            $prep->execute($params ? array_map(function ($val) {
                return !is_bool($val) ? $val : (int) $val;
            }, $params) : $params);
            return $prep->rowCount();
        } catch (\PDOException $e) {
            throw new PikeException($e->getMessage(),
                                    PikeException::FAILED_DB_OP,
                                    $e);
        }
    }
    /**
     * @return int $this->transactionLevel or -1 on failure
     * @throws \PDOException
     */
    public function beginTransaction(): int {
        if (++$this->transactionLevel === 1) {
            // @allow \PDOException
            if (!$this->pdo->beginTransaction()) return -1;
        }
        return $this->transactionLevel;
    }
    /**
     * @return int $this->transactionLevel or -1 on failure
     * @throws \PDOException
     */
    public function commit(): int {
        if ($this->transactionLevel > 0 && --$this->transactionLevel === 0) {
            // @allow \PDOException
            if (!$this->pdo->commit()) return -1;
        }
        return $this->transactionLevel;
    }
    /**
     * @return int $this->transactionLevel or -1 on failure
     * @throws \PDOException
     */
    public function rollBack(): int {
        if ($this->transactionLevel > 0 && --$this->transactionLevel === 0) {
            // @allow \PDOException
            if (!$this->pdo->rollBack()) return -1;
        }
        return $this->transactionLevel;
    }
    /**
     * @param \Closure $fn
     * @return mixed $retval = $fn()
     * @throws \Pike\PikeException|\PDOException|\Exception
     */
    public function runInTransaction(\Closure $fn) {
        // @allow \PDOException
        if ($this->beginTransaction() < 0) {
            throw new PikeException('Failed to start a transaction',
                                    PikeException::FAILED_DB_OP);
        }
        try {
            $result = $fn();
            // @allow \PDOException
            if ($this->commit() < 0) {
                throw new PikeException('Failed to commit a transaction',
                                        PikeException::FAILED_DB_OP);
            }
            return $result;
        } catch (\Exception $e) {
            // @allow \PDOException
            $this->rollBack();
            throw $e;
        }
    }
    /**
     * @return string
     */
    public function lastInsertId(): string {
        return $this->pdo->lastInsertId();
    }
    /**
     * @param int $attr
     * @param mixed $value = null
     * @return mixed|bool
     */
    public function attr(int $attr, $value = null) {
        return !$value
            ? $this->pdo->getAttribute($attr)
            : $this->pdo->setAttribute($attr, $value);
    }
    /**
     * @param object|array $config ['db.host' => string, ...]
     */
    public function setConfig($config): void {
        $this->config = is_array($config) ? $config : (array) $config;
        $this->tablePrefix = $this->config['db.tablePrefix'] ?? '';
    }
    /**
     * @return string
     */
    private function compileQuery(string $query): string {
        return str_replace('${p}', $this->tablePrefix, $query);
    }
}
