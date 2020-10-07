<?php

declare(strict_types=1);

namespace Pike\Defaults;

use Pike\Interfaces\UserRepositoryInterface;
use Pike\{Db, PikeException};
use Pike\Entities\User;

final class DefaultUserRepository implements UserRepositoryInterface {
    private const VALID_WHERE_COLUMNS = ['id', 'username', 'email', 'activationKey',
                                         'resetKey', 'loginId',];
    /** @var \Pike\Db */
    private $db;
    /**
     * @param \Pike\Db $db
     */
    public function __construct(Db $db) {
        $this->db = $db;
    }
    /**
     * @param \Pike\Entities\User $user
     * @param ?array $fields = null
     * @return string
     */
    public function createUser(User $user, ?array $fields = null): string {
        [$qList, $values, $columns] = $this->db->makeInsertQParts($user, $fields ?? []);
        // @allow \Pike\PikeException
        $numRows = $this->db->exec("INSERT INTO \${p}users ({$columns}) VALUES ({$qList})",
                                   $values);
        if ($numRows > 0)
            return $user->id ?? $this->db->lastInsertId();
        throw new PikeException('Expected $numRows to be > 0',
                                PikeException::FAILED_DB_OP);
    }
    /**
     * @param string $column 'id'|'username'|'email'|'activationKey'|'resetKey'|'loginId'
     * @param string $value
     * @return ?\Pike\Entities\User
     */
    public function getUserByColumn(string $column, string $value): ?User {
        if (!in_array($column, self::VALID_WHERE_COLUMNS, true))
            throw new PikeException("Invalid column `{$column}`",
                                    PikeException::BAD_INPUT);
        $user = $this->db->fetchOne("SELECT * FROM `\${p}users`" .
                                    " WHERE `{$column}` = ?",
                                    [$value],
                                    \PDO::FETCH_CLASS,
                                    User::class);
        self::normalizeUser($user);
        return $user;
    }
    /**
     * @param \Pike\Entities\User $user
     * @param string[] $fields
     * @param string $userId
     * @return int $numAffectedRows
     */
    public function updateUserByUserId(User $user,
                                       array $fields,
                                       string $userId): int {
        if (!$fields)
            throw new PikeException('Fields mustn\'t be empty',
                                    PikeException::BAD_INPUT);
        foreach ($fields as $field)
            if (!is_string($field) || !property_exists(User::class, $field))
                throw new PikeException("Invalid field `{$field}`",
                                        PikeException::BAD_INPUT);
        [$placeholders, $vals] = $this->db->makeUpdateQParts($user, $fields);
        // @allow \Pike\PikeException
        return $this->db->exec("UPDATE `\${p}users`" .
                               " SET {$placeholders}" .
                               " WHERE `id` = ?",
                               array_merge($vals, [$userId]));
    }
    /**
     * @param string $userId
     * @return int $numAffectedRows
     */
    public function deleteUserByUserId(string $userId): int {
        // @allow \Pike\PikeException
        return $this->db->exec('DELETE FROM `${p}users` WHERE `id` = ?',
                               [$userId]);
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
     * @param ?\Pike\Entities\User $user
     * @access private
     */
    private static function normalizeUser(?User $user): void {
        if (!$user) return;
        $user->role = (int) $user->role;
        $user->accountStatus = (int) $user->accountStatus;
        $user->accountCreatedAt = (int) $user->accountCreatedAt;
        $user->resetRequestedAt = (int) $user->resetRequestedAt;
    }
}
