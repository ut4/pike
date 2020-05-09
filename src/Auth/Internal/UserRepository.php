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
     * @param \stdClass $data {id?: string; username: string, email: string, passwordHash: string, role: int, activationKey?: string; accountCreatedAt?: int;}, olettaa että validi
     * @return int $lastInsertId
     * @throws \Pike\PikeException
     */
    public function putUser(\stdClass $data): string {
        [$qs, $params, $columns] = $this->db->makeInsertBinders($data);
        // @allow \Pike\PikeException
        $numRows = $this->db->exec("INSERT INTO \${p}users ({$columns}) VALUES ({$qs})",
                                   $params);
        if ($numRows > 0)
            return $data->id ?? $this->db->lastInsertId();
        throw new PikeException('Expected $numRows to be > 0',
                                PikeException::FAILED_DB_OP);
    }
    /**
     * @param string $userId
     * @return User|null
     * @throws \Pike\PikeException
     */
    public function getUserByUserId(string $userId): ?User {
        return $this->getUser('`id` = ?', [$userId]);
    }
    /**
     * @param string $resetKey
     * @return User|null
     * @throws \Pike\PikeException
     */
    public function getUserByResetKey(string $resetKey): ?User {
        return $this->getUser('`resetKey` = ?', [$resetKey]);
    }
    /**
     * @param string $activationKey
     * @return User|null
     * @throws \Pike\PikeException
     */
    public function getUserByActivationKey(string $activationKey): ?User {
        return $this->getUser('`activationKey` = ?', [$activationKey]);
    }
    /**
     * @param string $username
     * @return User|null
     * @throws \Pike\PikeException
     */
    public function getUserByUsername(string $username): ?User {
        return $this->getUser('`username` = ?', [$username]);
    }
    /**
     * @param string $username
     * @param string $email
     * @return User|null
     * @throws \Pike\PikeException
     */
    public function getUserByUsernameOrEmail(string $username, string $email): ?User {
        return $this->getUser('`username` = ? OR `email` = ?', [$username, $email]);
    }
    /**
     * @param \stdClass $data {username?: string, email?: string, passwordHash?: string, role?: int, activationKey?: string, accountCreatedAt?: int, resetKey?: string, resetRequestedAt?: int, accountStatus?: int}, olettaa että validi
     * @param string $userId
     * @return bool
     * @throws \Pike\PikeException
     */
    public function updateUserByUserId(\stdClass $data, string $userId): bool {
        return $this->updateUser($data, '`id` = ?', [$userId]);
    }
    /**
     * @param string $userId
     * @return bool
     * @throws \Pike\PikeException
     */
    public function deleteUserByUserId(string $userId): bool {
        // @allow \Pike\PikeException
        return $this->db->exec('DELETE FROM ${p}users' .
                               ' WHERE `id` = ?',
                               [$userId]) === 1;
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
     * @access private
     */
    private function getUser(string $wherePlaceholders, array $whereVals): ?User {
        // @allow \Pike\PikeException
        return $this->db->fetchOne('SELECT `id`,`username`,`email`,`passwordHash`' .
                                   ',`role`,`activationKey`,`accountCreatedAt`' .
                                   ',`resetKey`,`resetRequestedAt`,`accountStatus`' .
                                   ' FROM ${p}users' .
                                   ' WHERE ' . $wherePlaceholders,
                                   $whereVals,
                                   \PDO::FETCH_CLASS,
                                   User::class);
    }
    /**
     * @access private
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
    public $activationKey;
    public $accountCreatedAt;
    public $resetKey;
    public $resetRequestedAt;
    public $accountStatus;
    /**
     * Normalisoi \PDO:n asettamat arvot.
     */
    public function __construct() {
        $this->role = (int) $this->role;
        $this->activationKey = strlen($this->activationKey ?? '') ? $this->activationKey : null;
        $this->accountCreatedAt = $this->accountCreatedAt
            ? (int) $this->accountCreatedAt
            : null;
        $this->resetKey = strlen($this->resetKey ?? '') ? $this->resetKey : null;
        $this->resetRequestedAt = $this->resetRequestedAt
            ? (int) $this->resetRequestedAt
            : null;
        $this->accountStatus = (int) $this->accountStatus;
    }
}
