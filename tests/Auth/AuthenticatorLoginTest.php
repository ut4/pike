<?php declare(strict_types=1);

namespace Pike\Tests\Auth;

use Pike\Auth\{Authenticator};
use Pike\PikeException;
use Pike\TestUtils\MockCrypto;

class AuthenticatorLoginTest extends AuthenticatorTestCase {
    public function testLoginThrowsIfUserWasNotFound(): void {
        $this->expectException(PikeException::class);
        $this->expectExceptionCode(Authenticator::CREDENTIAL_WAS_INVALID);
        $this->expectExceptionMessage('User not found or not activated');
        $this->invokeLoginFeature('non-existing-username', 'irrelevant');
    }
    private function invokeLoginFeature(string $username,
                                        string $password,
                                        \stdClass $s = null,
                                        $mockSession = null,
                                        $mockCookieStorage = null,
                                        bool $useUserRoleCookie = false,
                                        bool $useRememberMe = false): void {
        $auth = $this->makeAuth($mockSession, $mockCookieStorage, $useUserRoleCookie, $useRememberMe);
        $auth->login($username,
                     $password,
                     $s ? $s->myUserToMakeSessionDataFn : null);
        $auth->postProcess();
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testLoginThrowsIfPasswordDoesNotMatch(): void {
        $this->insertTestUserToDb();
        $this->expectException(PikeException::class);
        $this->expectExceptionCode(Authenticator::CREDENTIAL_WAS_INVALID);
        $this->expectExceptionMessage('Invalid password');
        $this->invokeLoginFeature(self::TEST_USER['username'], 'wrongPass');
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testLoginThrowsIfAccountIsBanned(): void {
        $this->insertTestUserToDb(['accountStatus' => Authenticator::ACCOUNT_STATUS_BANNED]);
        $this->expectException(PikeException::class);
        $this->expectExceptionCode(Authenticator::ACCOUNT_STATUS_WAS_UNEXPECTED);
        $this->invokeLoginFeature(self::TEST_USER['username'], self::TEST_USER_PASS);
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testLoginThrowsIfAccountIsUnactivated(): void {
        $this->insertTestUserToDb(['accountStatus' => Authenticator::ACCOUNT_STATUS_UNACTIVATED]);
        $this->expectException(PikeException::class);
        $this->expectExceptionCode(Authenticator::ACCOUNT_STATUS_WAS_UNEXPECTED);
        $this->invokeLoginFeature(self::TEST_USER['username'], self::TEST_USER_PASS);
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testLoginPutsUserToSessionOnSuccess(): void {
        $this->insertTestUserToDb();
        $state = $this->setupLoginSessionTest();
        $this->invokeLoginFeature(self::TEST_USER['username'],
                                  self::TEST_USER_PASS,
                                  $state,
                                  $this->makeSpyingSession($state));
        $this->verifyWroteSerializedDataDataToSession($state);
    }
    private function setupLoginSessionTest(): \stdClass {
        $state = new \stdClass;
        $state->myUserToMakeSessionDataFn = function ($user) {
            return "{$user->id}|{$user->username}";
        };
        $state->actualDataPutToSession = null;
        return $state;
    }
    private function verifyWroteSerializedDataDataToSession($s): void {
        $this->assertEquals(call_user_func($s->myUserToMakeSessionDataFn, (object) [
                                'id' => self::TEST_USER['id'],
                                'username' => self::TEST_USER['username']
                            ]),
                            $s->actualDataPutToSession);
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testLoginStoresUserToCookiesOnSuccess(): void {
        $this->insertTestUserToDb();
        $state = $this->setupCookieStoreTest();
        $this->invokeLoginFeature(self::TEST_USER['username'],
                                  self::TEST_USER_PASS,
                                  $state,
                                  $this->makeSpyingSession($state),
                                  $this->makeSpyingCookieStorage($state),
                                  true);
        $this->assertCount(1, $state->actualDataPassedToCookieStorage);
        $this->verifyPassedUserRoleConfigurationsToCookieStorage($state);
    }
    private function setupCookieStoreTest(): \stdClass {
        $state = $this->setupLoginSessionTest();
        $state->actualDataPassedToCookieStorage = [];
        return $state;
    }
    private function verifyPassedUserRoleConfigurationsToCookieStorage(\stdClass $s): void {
        [$userRoleCookie] = $s->actualDataPassedToCookieStorage[0];
        $this->assertEquals('loggedInUserRole=' . self::TEST_USER['role'] . ';path=/',
                            $userRoleCookie);
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testLoginWithRememberMePutsLoginDataToCookiesAndDb(): void {
        $this->insertTestUserToDb();
        $state = $this->setupCookieStoreTest();
        $this->invokeLoginFeature(self::TEST_USER['username'],
                                  self::TEST_USER_PASS,
                                  $state,
                                  $this->makeSpyingSession($state),
                                  $this->makeSpyingCookieStorage($state),
                                  true,
                                  true);
        $this->verifyPassedLoginTokenConfigurationsToCookieStorage($state);
        $this->verifyInsertedLoginDataToDb($state);
    }
    private function verifyPassedLoginTokenConfigurationsToCookieStorage(\stdClass $s): void {
        $expectedLoginId = MockCrypto::mockGenRandomToken();
        $expectedLoginValidator = MockCrypto::mockGenRandomToken();
        $expectedTokens = "{$expectedLoginId}:{$expectedLoginValidator}";
        //
        [$loginTokensCookie] = $s->actualDataPassedToCookieStorage[1];
        $noExpires = explode(';expires=', $loginTokensCookie)[0];
        $this->assertEquals("loginTokens={$expectedTokens};path=/",
                            $noExpires);
    }
    private function verifyInsertedLoginDataToDb(\stdClass $s): void {
        $actual = $this->getTestUserFromDb();
        $expected = array_merge(self::TEST_USER,
                                ['loginId' => MockCrypto::mockGenRandomToken(),
                                 'loginIdValidatorHash' => MockCrypto::mockHash('sha256', MockCrypto::mockGenRandomToken()),
                                 'loginData' => serialize(call_user_func($s->myUserToMakeSessionDataFn, (object) [
                                     'id' => self::TEST_USER['id'],
                                     'username' => self::TEST_USER['username']
                                 ]))]);
        $this->assertEquals((object) $expected, $actual);
    }
}
