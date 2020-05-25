<?php

declare(strict_types=1);

namespace Pike\Auth\Internal;

use Pike\Auth\AbstractUserRepository;
use Pike\Auth\User;
use Pike\Db;
use Pike\PikeException;

class DefaultUserRepository extends AbstractUserRepository {
    private $db;
    /**
     * @param \Pike\Db $db
     */
    public function __construct(Db $db) {
        $this->db = $db;
    }
    /**
     * @param \stdClass $data {id?: string; username: string, email: string, passwordHash: string, role: int, activationKey?: string; accountCreatedAt?: int;}, olettaa että validi
     * @return string $lastInsertId
     * @throws \Pike\PikeException
     */
    public function putUser(\stdClass $data): string {
        [$qList, $values, $columns] = $this->db->makeInsertQParts($data);
        // @allow \Pike\PikeException
        $numRows = $this->db->exec("INSERT INTO \${p}users ({$columns}) VALUES ({$qList})",
                                   $values);
        if ($numRows > 0)
            return $data->id ?? $this->db->lastInsertId();
        throw new PikeException('Expected $numRows to be > 0',
                                PikeException::FAILED_DB_OP);
    }
    /**
     * @param string $userId
     * @return \Pike\Auth\User|null
     * @throws \Pike\PikeException
     */
    public function getUserByUserId(string $userId): ?User {
        return $this->getUser('`id` = ?', [$userId]);
    }
    /**
     * @param string $resetKey
     * @return \Pike\Auth\User|null
     * @throws \Pike\PikeException
     */
    public function getUserByResetKey(string $resetKey): ?User {
        return $this->getUser('`resetKey` = ?', [$resetKey]);
    }
    /**
     * @param string $activationKey
     * @return \Pike\Auth\User|null
     * @throws \Pike\PikeException
     */
    public function getUserByActivationKey(string $activationKey): ?User {
        return $this->getUser('`activationKey` = ?', [$activationKey]);
    }
    /**
     * @param string $username
     * @return \Pike\Auth\User|null
     * @throws \Pike\PikeException
     */
    public function getUserByUsername(string $username): ?User {
        return $this->getUser('`username` = ?', [$username]);
    }
    /**
     * @param string $username
     * @param string $email
     * @return \Pike\Auth\User|null
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
        [$placeholders, $vals] = $this->db->makeUpdateQParts($data);
        // @allow \Pike\PikeException
        return $this->db->exec('UPDATE ${p}users' .
                               ' SET ' . $placeholders .
                               ' WHERE ' . $wherePlaceholders,
                               array_merge($vals, $whereVals)) === 1;
    }
}
