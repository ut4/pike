<?php declare(strict_types=1);

namespace Pike\Tests\Auth;

use Pike\Auth\{ACL, Authenticator};
use Pike\Auth\Interfaces\CookieStorageInterface;
use Pike\Defaults\DefaultUserRepository;
use Pike\Interfaces\SessionInterface;
use Pike\TestUtils\{DbTestCase, HttpTestUtils, MockCrypto};

abstract class AuthenticatorTestCase extends DbTestCase {
    protected const TEST_USER_PASS = '1234';
    use HttpTestUtils;
    protected const TEST_USER = [
        'id' => '1',
        'username' => 'Pike',
        'email' => 'pike@offshore.com',
        'accountStatus' => Authenticator::ACCOUNT_STATUS_ACTIVATED,
        'displayName' => 'Pike Bikerton',
        'role' => ACL::ROLE_SUPER_ADMIN,
        'accountCreatedAt' => 315532800,

        'passwordHash' => 'hashed: ' . self::TEST_USER_PASS, // see. MockCrypto::mockHashPass()
        'activationKey' => null,
        'resetKey' => null,
        'resetRequestedAt' => null,
        'loginId' => null,
        'loginIdValidatorHash' => null,
        'loginData' => null,
    ];
    /**
     * @param ?\Pike\Interfaces\SessionInterface $mockSession = null
     * @param ?\Pike\Auth\Interfaces\CookieStorageInterface $mockCookieStorage = null
     * @param ?bool $useUserRoleCookie = false
     * @param ?bool $useRememberMe = false
     */
    protected function makeAuth($mockSession = null,
                                $mockCookieStorage = null,
                                ?bool $useUserRoleCookie = false,
                                ?bool $useRememberMe = false): Authenticator {
        return new Authenticator(
            function ($_factory) {
                return new DefaultUserRepository(self::$db);
            },
            function ($_factory) use ($mockSession) {
                return $mockSession ?? $this->createMock(SessionInterface::class);
            },
            function ($_factory) use ($mockCookieStorage) {
                return $mockCookieStorage ?? $this->createMock(CookieStorageInterface::class);
            },
            $useUserRoleCookie ? 'loggedInUserRole' : '',
            $useRememberMe,
            new MockCrypto
        );
    }
    /**
     * @param array $data = []
     */
    protected function insertTestUserToDb(array $data = []): void {
        [$qList, $values, $columns] = self::$db->makeInsertQParts(array_merge(self::TEST_USER, $data));
        if (self::$db->exec("INSERT INTO `\${p}users` ({$columns}) VALUES ({$qList})",
                            $values) < 1)
            throw new \Exception('Failed to insert test user');
    }
    /**
     * @param \stdClass $state
     * @return \Pike\Interfaces\SessionInterface
     */
    protected function makeSpyingSession(\stdClass $state) {
        $out = $this->createMock(SessionInterface::class);
        $out->method('put')
            ->with('user', $this->callback(function ($data) use ($state) {
                $state->actualDataPutToSession = $data;
                return true;
            }));
        return $out;
    }
    /**
     * @param \stdClass $state
     * @return \Pike\Auth\Interfaces\CookieStorageInterface
     */
    protected function makeSpyingCookieStorage(\stdClass $state) {
        $out = $this->createMock(CookieStorageInterface::class);
        $out->method('storeCookie')->with(
            $this->callBack(function ($cookieConfigurations) use ($state) {
                $state->actualDataPassedToCookieStorage[] = [$cookieConfigurations];
                return true;
            }));
        return $out;
    }
}
