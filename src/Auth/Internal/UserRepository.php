<?php

declare(strict_types=1);

namespace Pike\Auth\Internal;

use Pike\Db;
use Pike\PikeException;

final class UserRepository {
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
     * @param string $userId
     * @return object|null {id: string, username: string, email: string, passwordHash: string, role: string, resetKey: string, resetRequestedAt: int}
     * @throws \Pike\PikeException
     */
    public function getUserByUserId(string $userId): ?User {
        return $this->getUser('`id` = ?', [$userId]);
    }
    /**
     * @param string $resetKey
     * @return object|null {id: string, username: string, email: string, passwordHash: string, role: string, resetKey: string, resetRequestedAt: int}
     * @throws \Pike\PikeException
     */
    public function getUserByResetKey(string $resetKey): ?User {
        return $this->getUser('`resetKey` = ?', [$resetKey]);
    }
    /**
     * @param string $username
     * @return object|null {id: string, username: string, email: string, passwordHash: string, role: string, resetKey: string, resetRequestedAt: int}
     * @throws \Pike\PikeException
     */
    public function getUserByUsername(string $username): ?User {
        return $this->getUser('`username` = ?', [$username]);
    }
    /**
     * @param string $username
     * @param string $email
     * @return object|null {id: string, username: string, email: string, passwordHash: string, role: string, resetKey: string, resetRequestedAt: int}
     * @throws \Pike\PikeException
     */
    public function getUserByUsernameOrEmail(string $username, string $email): ?User {
        return $this->getUser('`username` = ? OR `email` = ?', [$username, $email]);
    }
    /**
     * @param \stdClass $data {username?: string, email?: string, passwordHash?: string, role?: string, resetKey?: string, resetRequestedAt?: int}, olettaa että validi
     * @param string $wherePlaceholders
     * @param array $whereVals
     * @return bool
     * @throws \Pike\PikeException
     */
    public function updateUserByUserId(\stdClass $data, string $userId): bool {
        return $this->updateUser($data, '`id` = ?', [$userId]);
    }
    /**
     * @param \Closure $fn
     * @return mixed $retval = $fn()
     */
    public function runInTransaction(\Closure $fn) {
        // @allow \Pike\PikeException
        return $this->db->runInTransaction($fn);
    }
    /**
     * @param string $wherePlaceholders
     * @param array $whereVals
     * @return object|null {id: string, username: string, email: string, passwordHash: string, role: string, resetKey: string, resetRequestedAt: int}
     * @throws \Pike\PikeException
     */
    private function getUser(string $wherePlaceholders, array $whereVals): ?User {
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
    private function updateUser(\stdClass $data,
                                string $wherePlaceholders,
                                array $whereVals): bool {
        [$placeholders, $vals] = $this->db->makeUpdateBinders($data);
        // @allow \Pike\PikeException
        return $this->db->exec('UPDATE ${p}users' .
                               ' SET ' . $placeholders .
                               ' WHERE ' . $wherePlaceholders,
                               array_merge($vals, $whereVals)) === 1;
    }
}

final class User {
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
