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
     * @param string $column 'id'|'resetKey'|'activationKey'|'username'|'loginId'
     * @param string $value
     * @return \Pike\Auth\User|null
     */
    public abstract function getUserByColumn(string $column, string $value): ?User;
    /**
     * @deprecated
     * @param string $username
     * @return \Pike\Auth\User|null
     */
    public abstract function getUserByUsername(string $username): ?User;
    /**
     * @param string $username
     * @param string $email
     * @return \Pike\Auth\User|null
     */
    public abstract function getUserByUsernameOrEmail(string $username, string $email): ?User;
    /**
     * @deprecated
     * @param string $userId
     * @return \Pike\Auth\User|null
     */
    public abstract function getUserByUserId(string $userId): ?User;
    /**
     * @deprecated
     * @param string $activationKey
     * @return \Pike\Auth\User|null
     */
    public abstract function getUserByActivationKey(string $activationKey): ?User;
    /**
     * @deprecated
     * @param string $resetKey
     * @return \Pike\Auth\User|null
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
    /** @var string */
    public $id;
    /** @var string */
    public $username;
    /** @var string */
    public $email;
    /** @var string */
    public $passwordHash;
    /** @var int */
    public $role;
    /** @var ?string */
    public $activationKey;
    /** @var ?string */
    public $loginId;
    /** @var ?string */
    public $loginIdValidatorHash;
    /** @var ?string */
    public $loginData;
    /** @var int */
    public $accountCreatedAt;
    /** @var ?string */
    public $resetKey;
    /** @var int */
    public $resetRequestedAt;
    /** @var int */
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
        $this->resetRequestedAt = (int) $this->resetRequestedAt;
        $this->accountStatus = (int) $this->accountStatus;
    }
}
