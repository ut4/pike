<?php

namespace Pike\Auth;

use Pike\Db;
use Pike\DbUtils;

class UserRepository {
    private $db;
    /**
     * @param \Pike\Db $db
     */
    public function __construct(Db $db) {
        $this->db = $db;
    }
    /**
     * @param {username: string, email: string, passwordHash: string, resetKey: string, resetRequestedAt: int}
     * @return int $lastInsertId
     */
    public function putUser(object $user) {
        return 0;
    }
    /**
     * @param string $wherePlaceholders
     * @param array $whereVals
     * @return {id: string, username: string, email: string, passwordHash: string, resetKey: string, resetRequestedAt: int}|null 
     */
    public function getUser($wherePlaceholders, $whereVals) {
        try {
            $row = $this->db->fetchOne('SELECT `id`,`username`,`email`,`passwordHash`' .
                                       ',`resetKey`,`resetRequestedAt`' .
                                       ' FROM ${p}users' .
                                       ' WHERE ' . $wherePlaceholders,
                                       $whereVals);
            return $row ? makeUser($row) : null;
        } catch (\PDOException $_) {
            return null;
        }
    }
    /**
     * @param {username?: string, email?: string, passwordHash?: string, resetKey?: string, resetRequestedAt?: int} $data Olettaa että validi
     * @param string $wherePlaceholders
     * @param array $whereVals
     * @return int $numAffectedRows
     */
    public function updateUser(object $data, $wherePlaceholders, $whereVals) {
        try {
            [$placeholders, $vals] = DbUtils::makeUpdateBinders($data);
            return $this->db->exec('UPDATE ${p}users' .
                                   ' SET ' . $placeholders .
                                   ' WHERE ' . $wherePlaceholders,
                                   array_merge($vals, $whereVals)) === 1;
        } catch (\PDOException $_) {
            return 0;
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
        'resetKey' => $row['resetKey'] ?? null,
        'resetRequestedAt' => isset($row['resetRequestedAt'])
            ? (int)$row['resetRequestedAt']
            : null,
    ];
}
