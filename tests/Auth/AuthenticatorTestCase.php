<?php

namespace Pike\Tests\Auth;

use Pike\Auth\Internal\CachingServicesFactory;
use Pike\TestUtils\DbTestCase;
use Pike\TestUtils\MockCrypto;

abstract class AuthenticatorTestCase extends DbTestCase {
    protected const TEST_USER_ID = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx';
    protected const TEST_USER_NAME = 'U ser';
    protected const TEST_USER_PASS = 'a pass';
    protected const TEST_USER_EMAIL = 'e@mail.com';
    protected const TEST_USER_ROLE = 1;
    protected function insertTestUserToDb($data = null) {
        $vals = !$data
            ? [
                self::TEST_USER_ID,
                self::TEST_USER_NAME,
                'e@mail.com',
                MockCrypto::mockHashPass(self::TEST_USER_PASS),
                self::TEST_USER_ROLE
            ]
            : array_values($data);
        if (self::$db->exec('INSERT INTO ${p}users' .
                            ' (`id`,`username`,`email`,`passwordHash`,`role`)' .
                            ' VALUES (?,?,?,?,?)', $vals) < 1)
            throw new \Exception('Failed to insert test user to db');
    }
    /**
     * @return \Pike\Auth\Internal\CachingServicesFactory
     */
    protected function makePartiallyMockedServicesFactory($mockSession = null,
                                                          $mailer = null) {
        $out = $this->getMockBuilder(CachingServicesFactory::class)
            ->setConstructorArgs([self::$db, $mailer])
            ->setMethods(array_merge(['makeCrypto'],
                                     $mockSession ? ['makeSession'] : []))
            ->getMock();
        $out->method('makeCrypto')
            ->willReturn(new MockCrypto);
        if ($mockSession) {
            $out->method('makeSession')
                ->willReturn($mockSession);
        }
        return $out;
    }
}
