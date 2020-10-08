<?php

declare(strict_types=1);

namespace Pike\Interfaces;

use Pike\Entities\User;

interface UserRepositoryInterface {
    /**
     * @param \Pike\Entities\User $user
     * @return string
     */
    public function createUser(User $user): string;
    /**
     * @param string $column 'id'|'username'|'email'|'usernameOrEmail'|'activationKey'|'resetKey'|'loginId'
     * @param string $value
     * @return ?\Pike\Entities\User
     */
    public function getUserByColumn(string $column, string $value): ?User;
    /**
     * @param \Pike\Entities\User $user
     * @param string[] $fields array<int, keyof \Pike\Entities\User>
     * @param string $userId
     * @return int $numAffectedRows
     */
    public function updateUserByUserId(User $user, array $fields, string $userId): int;
    /**
     * @param string $userId
     * @return int $numAffectedRows
     */
    public function deleteUserByUserId(string $userId): int;
    /**
     * @param \Closure $fn
     * @return mixed $retval = $fn()
     */
    public function runInTransaction(\Closure $fn);
}
