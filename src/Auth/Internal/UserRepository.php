<?php

declare(strict_types=1);

namespace Pike\Auth\Internal;

use Pike\Db;

class UserRepository {
    private $db;
    /**
     * @param \Pike\Db $db
     */
    public function __construct(Db $db) {
        $this->db = $db;
    }
    /**
     * @param \stdClass $data {username: string, email: string, passwordHash: string, resetKey: string, resetRequestedAt: int}
     * @return int $lastInsertId
     */
    public function putUser(\stdClass $data): int {
        return 0;
    }
    /**
     * @param string $wherePlaceholders
     * @param array $whereVals
     * @return object|null {id: string, username: string, email: string, passwordHash: string, role: string, resetKey: string, resetRequestedAt: int}
     * @throws \Pike\PikeException
     */
    public function getUser(string $wherePlaceholders,
                            array $whereVals): ?User {
        // @allow \Pike\PikeException
        return $this->db->fetchOne('SELECT `id`,`username`,`email`,`passwordHash`' .
                                   ',`role`,`resetKey`,`resetRequestedAt`' .
                                   ' FROM ${p}users' .
                                   ' WHERE ' . $wherePlaceholders,
                                   $whereVals,
                                   \PDO::FETCH_CLASS,
                                   User::class);
    }
    /**
     * @param \stdClass $data {username?: string, email?: string, passwordHash?: string, role?: string, resetKey?: string, resetRequestedAt?: int}, olettaa että validi
     * @param string $wherePlaceholders
     * @param array $whereVals
     * @return bool
     * @throws \Pike\PikeException
     */
    public function updateUser(\stdClass $data,
                               string $wherePlaceholders,
                               array $whereVals): bool {
        [$placeholders, $vals] = $this->db->makeUpdateBinders($data);
        // @allow \Pike\PikeException
        return $this->db->exec('UPDATE ${p}users' .
                               ' SET ' . $placeholders .
                               ' WHERE ' . $wherePlaceholders,
                               array_merge($vals, $whereVals)) === 1;
    }
    /**
     * @param \Closure $fn
     * @return mixed $retval = $fn()
     */
    public function runInTransaction(\Closure $fn) {
        // @allow \Pike\PikeException
        return $this->db->runInTransaction($fn);
    }
}

class User {
    public $id;
    public $username;
    public $email;
    public $passwordHash;
    public $role;
    public $resetKey;
    public $resetRequestedAt;
    /**
     * Normalisoi \PDO:n asettamat arvot.
     */
    public function __construct() {
        $this->role = (int) $this->role;
        $this->resetKey = strlen($this->resetKey ?? '') ? $this->resetKey : null;
        $this->resetRequestedAt = $this->resetRequestedAt
            ? (int) $this->resetRequestedAt
            : null;
    }
}
