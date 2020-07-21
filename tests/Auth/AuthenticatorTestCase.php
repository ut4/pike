<?php

namespace Pike\Tests\Auth;

use Pike\AbstractMailer;
use Pike\Auth\Authenticator;
use Pike\Auth\Internal\CachingServicesFactory;
use Pike\Auth\Internal\DefaultUserRepository;
use Pike\TestUtils\DbTestCase;
use Pike\TestUtils\MockCrypto;

abstract class AuthenticatorTestCase extends DbTestCase {
    protected const TEST_USER_ID = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx';
    protected const TEST_USER_NAME = 'U ser';
    protected const TEST_USER_PASS = 'a pass';
    protected const TEST_USER_EMAIL = 'e@mail.com';
    protected const TEST_USER_ROLE = 1;
    protected const TEST_USER_CREATED_AT = 12345;
    protected function insertTestUserToDb($accountStatus = Authenticator::ACCOUNT_STATUS_ACTIVATED) {
        [$qList, $values, $columns] = self::$db->makeInsertQParts([
            'id' => self::TEST_USER_ID,
            'username' => self::TEST_USER_NAME,
            'email' => 'e@mail.com',
            'passwordHash' => MockCrypto::mockHashPass(self::TEST_USER_PASS),
            'role' => self::TEST_USER_ROLE,
            'accountCreatedAt' => self::TEST_USER_CREATED_AT,
            'accountStatus' => $accountStatus
        ]);
        if (self::$db->exec("INSERT INTO \${p}users ({$columns}) VALUES ({$qList})",
                            $values) < 1)
            throw new \Exception('Failed to insert test user to db');
    }
    /**
     * @param string $userId
     */
    protected function getTestUserFromDb($userId) {
        return self::$db->fetchOne('SELECT `username`,`email`,`passwordHash`' .
                                       ',`role`,`resetKey`,`resetRequestedAt`' .
                                       ',`activationKey`,`accountCreatedAt`' .
                                       ',`accountStatus`' .
                                   ' FROM users' .
                                   ' WHERE `id` = ?',
                                   [$userId]);
    }
    /**
     * @return \Pike\Auth\Internal\CachingServicesFactory
     */
    protected function makePartiallyMockedServicesFactory($mockSession = null,
                                                          $mailer = null) {
        $mailer = $mailer ?? $this->createMock(AbstractMailer::class);
        $out = $this->getMockBuilder(CachingServicesFactory::class)
            ->setConstructorArgs([function () {
                return new DefaultUserRepository(self::$db);
            }, $mailer])
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
