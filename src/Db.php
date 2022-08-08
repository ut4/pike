<?php

declare(strict_types=1);

namespace Pike;

/**
 * @template T
 */
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
     * @param array $pdoOptions = []
     * @param bool $doSwallowExceptionError = true
     * @return bool
     * @throws \Pike\PikeException
     */
    public function open(array $pdoOptions = [],
                         bool $doSwallowExceptionError = true): bool {
        $doOpenDb = function () use ($pdoOptions) {
            $this->pdo = new \PDO(
                ($this->config['db.driver'] ?? '') === 'sqlite'
                    ? sprintf(
                        'sqlite:%s',
                        $this->config['db.database'] ?? '',
                    )
                    : sprintf(
                        'mysql:host=%s;dbname=%s;charset=%s',
                        $this->config['db.host'] ?? '127.0.0.1',
                        $this->config['db.database'] ?? '',
                        $this->config['db.charset'] ?? 'utf8mb4'
                    ),
                $this->config['db.user'] ?? null,
                $this->config['db.pass'] ?? null,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION] + $pdoOptions
            );
            $this->config = [];
            return true;
        };
        if ($doSwallowExceptionError) {
            try {
                return $doOpenDb();
            } catch (\PDOException $e) {
                throw new PikeException("The database connection failed: {$e->getCode()}",
                                        PikeException::ERROR_EXCEPTION,
                                        $e);
            }
        }
        // @allow \Pike\PikeException
        return $doOpenDb();
    }
    /**
     * @param string $query
     * @param mixed[] $params = null
     * @param int $fetchStyle = \PDO::FETCH_ASSOC
     * @param mixed $fetchArgument = null
     * @param array $fetchCtorArgs = []
     * @return array<int, T>
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
     * @return T|array|null
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
                if (is_array($val))
                    throw new PikeException(sprintf("Can't use array (%s) for bindValue()", json_encode($val)),
                                            PikeException::BAD_INPUT);
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
     * @return string|false
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
        return $value === null
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
     * @return \PDO
     */
    public function getPdo() {
        return $this->pdo;
    }
    /**
     * @return string
     */
    public function getTablePrefix(): string {
        return $this->tablePrefix;
    }
    /**
     * @return string
     */
    public function compileQuery(string $query): string {
        return str_replace('${p}', $this->tablePrefix, $query);
    }
}
