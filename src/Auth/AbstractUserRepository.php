<?php

declare(strict_types=1);

namespace Pike\Auth;

abstract class AbstractUserRepository {
    /**
     * @param \stdClass $data
     * @return string $insertId
     */
    public abstract function putUser(\stdClass $data): string;
    /**
     * @param string $username
     * @return \Pike\Auth\Internal\User|null
     */
    public abstract function getUserByUsername(string $username): ?User;
    /**
     * @param string $username
     * @param string $email
     * @return \Pike\Auth\Internal\User|null
     */
    public abstract function getUserByUsernameOrEmail(string $username, string $email): ?User;
    /**
     * @param string $userId
     * @return \Pike\Auth\Internal\User|null
     */
    public abstract function getUserByUserId(string $userId): ?User;
    /**
     * @param string $activationKey
     * @return \Pike\Auth\Internal\User|null
     */
    public abstract function getUserByActivationKey(string $activationKey): ?User;
    /**
     * @param string $resetKey
     * @return \Pike\Auth\Internal\User|null
     */
    public abstract function getUserByResetKey(string $resetKey): ?User;
    /**
     * @param \stdClass $data
     * @param string $userId
     * @return bool
     */
    public abstract function updateUserByUserId(\stdClass $data, string $userId): bool;
    /**
     * @param string $userId
     * @return bool
     */
    public abstract function deleteUserByUserId(string $userId): bool;
    /**
     * @param \Closure $fn
     * @return mixed $retval = $fn()
     */
    public abstract function runInTransaction(\Closure $fn);
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
