<?php

namespace Pike\Auth;

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
     * @param string $username
     * @return object|null {id: string, username: string, email: string, passwordHash: string}
     */
    public function getUser($username) {
        try {
            $row = $this->db->fetchOne('SELECT `id`,`username`,`email`,`passwordHash`' .
                                       ' FROM ${p}users WHERE `username` = ?',
                                       [$username]);
            return $row ? makeUser($row) : null;
        } catch (\PDOException $e) {
            return null;
        }
    }
    /**
     * @param object {id: string, username: string, email: string, asswordHash: string}
     * @return bool
     */
    public function putUser($user) {
        return true;
    }
}

function makeUser($row) {
    return (object)[
        'id' => $row['id'],
        'username' => $row['username'],
        'email' => $row['email'],
        'passwordHash' => $row['passwordHash']
    ];
}