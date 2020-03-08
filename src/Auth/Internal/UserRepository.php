<?php

namespace Pike\Auth\Internal;

use Pike\Db;
use Pike\DbUtils;
use Pike\PikeException;

class UserRepository {
    private $db;
    /**
     * @param \Pike\Db $db
     */
    public function __construct(Db $db) {
        $this->db = $db;
    }
    /**
     * @param \stdClass $user {username: string, email: string, passwordHash: string, resetKey: string, resetRequestedAt: int}
     * @return int $lastInsertId
     */
    public function putUser(\stdClass $user) {
        return 0;
    }
    /**
     * @param string $wherePlaceholders
     * @param array $whereVals
     * @return \stdClass|null {id: string, username: string, email: string, passwordHash: string, role: string, resetKey: string, resetRequestedAt: int}
     * @throws \Pike\PikeException
     */
    public function getUser($wherePlaceholders, $whereVals) {
        try {
            $row = $this->db->fetchOne('SELECT `id`,`username`,`email`,`passwordHash`' .
                                       ',`role`,`resetKey`,`resetRequestedAt`' .
                                       ' FROM ${p}users' .
                                       ' WHERE ' . $wherePlaceholders,
                                       $whereVals);
            return $row ? makeUser($row) : null;
        } catch (\PDOException $e) {
            throw new PikeException("Unexpected database error: {$e->getMessage()}",
                                    PikeException::FAILED_DB_OP);
        }
    }
    /**
     * @param \stdClass $data {username?: string, email?: string, passwordHash?: string, role?: string, resetKey?: string, resetRequestedAt?: int}, olettaa että validi
     * @param string $wherePlaceholders
     * @param array $whereVals
     * @return int $numAffectedRows
     * @throws \Pike\PikeException
     */
    public function updateUser(\stdClass $data, $wherePlaceholders, $whereVals) {
        try {
            [$placeholders, $vals] = DbUtils::makeUpdateBinders($data);
            return $this->db->exec('UPDATE ${p}users' .
                                   ' SET ' . $placeholders .
                                   ' WHERE ' . $wherePlaceholders,
                                   array_merge($vals, $whereVals)) === 1;
        } catch (\PDOException $e) {
            throw new PikeException("Unexpected database error: {$e->getMessage()}",
                                    PikeException::FAILED_DB_OP);
        }
    }
    /**
     * @param \Closure $fn
     */
    public function runInTransaction($fn) {
        // @allow \Pike\PikeException
        $this->db->runInTransaction($fn);
    }
}

function makeUser($row) {
    return (object)[
        'id' => $row['id'],
        'username' => $row['username'],
        'email' => $row['email'],
        'passwordHash' => $row['passwordHash'],
        'role' => (int)$row['role'],
        'resetKey' => $row['resetKey'] ?? null,
        'resetRequestedAt' => isset($row['resetRequestedAt'])
            ? (int)$row['resetRequestedAt']
            : null,
    ];
}
