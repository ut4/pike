<?php declare(strict_types=1);

namespace Pike\Tests\Auth;

use Pike\{AbstractMailer, AppContext, SessionInterface};
use Pike\Auth\Authenticator;
use Pike\Auth\Internal\{CachingServicesFactory, CookieManager, DefaultUserRepository};
use Pike\TestUtils\{DbTestCase, HttpTestUtils, MockCrypto};

abstract class AuthenticatorTestCase extends DbTestCase {
    use HttpTestUtils;
    protected const TEST_USER_ID = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx';
    protected const TEST_USER_NAME = 'U ser';
    protected const TEST_USER_PASS = 'a pass';
    protected const TEST_USER_EMAIL = 'e@mail.com';
    protected const TEST_USER_ROLE = 1;
    protected const TEST_USER_CREATED_AT = 12345;
    /**
     * @param int $accountStatus = Authenticator::ACCOUNT_STATUS_ACTIVATED
     * @param array $data = []
     */
    protected function insertTestUserToDb($accountStatus = Authenticator::ACCOUNT_STATUS_ACTIVATED,
                                          array $data = []): void {
        [$qList, $values, $columns] = self::$db->makeInsertQParts(array_merge([
            'id' => self::TEST_USER_ID,
            'username' => self::TEST_USER_NAME,
            'email' => 'e@mail.com',
            'passwordHash' => MockCrypto::mockHashPass(self::TEST_USER_PASS),
            'role' => self::TEST_USER_ROLE,
            'accountCreatedAt' => self::TEST_USER_CREATED_AT,
            'accountStatus' => $accountStatus
        ], $data));
        if (self::$db->exec("INSERT INTO \${p}users ({$columns}) VALUES ({$qList})",
                            $values) < 1)
            throw new \Exception('Failed to insert test user to db');
    }
    /**
     * @param string $userId
     * @return ?array<string, mixed>
     */
    protected function getTestUserFromDb($userId): ?array {
        return self::$db->fetchOne('SELECT `username`,`email`,`passwordHash`' .
                                       ',`role`,`resetKey`,`resetRequestedAt`' .
                                       ',`activationKey`,`accountCreatedAt`' .
                                       ',`accountStatus`' .
                                   ' FROM users' .
                                   ' WHERE `id` = ?',
                                   [$userId],
                                   \PDO::FETCH_ASSOC);
    }
    /**
     * @param ?\Pike\SessionInterface $mockSession = null
     * @param ?\Pike\AbstractMailer $mailer = null
     * @param ?\Pike\Auth\Internal\CookieManager $mockCookieManager = null
     * @return \Pike\Auth\Internal\CachingServicesFactory
     */
    protected function makePartiallyMockedServicesFactory($mockSession = null,
                                                          $mailer = null,
                                                          $mockCookieManager = null) {
        $mailer = $mailer ?? $this->createMock(AbstractMailer::class);
        $out = $this->getMockBuilder(CachingServicesFactory::class)
            ->setConstructorArgs([
                $this->createMock(AppContext::class),
                function () { return new DefaultUserRepository(self::$db); },
                '@auto',
                function () use ($mailer) { return $mailer; }])
            ->setMethods(array_merge(['makeCrypto'],
                                     $mockSession ? ['makeSession'] : [],
                                     $mockCookieManager ? ['makeCookieManager'] : []))
            ->getMock();
        $out->method('makeCrypto')
            ->willReturn(new MockCrypto);
        if ($mockSession)
            $out->method('makeSession')->willReturn($mockSession);
        if ($mockCookieManager)
            $out->method('makeCookieManager')->willReturn($mockCookieManager);
        return $out;
    }
    /**
     * @param \stdClass $state
     * @return \Pike\SessionInterface
     */
    protected function makeSpyingSession(\stdClass $state) {
        $mockSession = $this->createMock(SessionInterface::class);
        $mockSession->method('put')
            ->with('user', $this->callback(function ($data) use ($state) {
                $state->actualDataPutToSession = $data;
                return true;
            }));
        return $mockSession;
    }
    /**
     * @param \stdClass $state
     * @return \Pike\Auth\Internal\CookieManager
     */
    protected function makeSpyingCookieManager(\stdClass $state) {
        $out = $this->createMock(CookieManager::class);
        $out->method('putCookie')->with(
            $this->callBack(function ($name) use ($state) {
                $state->actualPutCookieCalls[] = [$name];
                return true;
            }),
            $this->callBack(function ($value) use ($state) {
                $state->actualPutCookieCalls[count($state->actualPutCookieCalls)-1][] = $value;
                return true;
            }));
        return $out;
    }
}
