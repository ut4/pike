<?php declare(strict_types=1);

namespace Pike\Tests\Auth;

use Pike\Auth\{Authenticator};
use Pike\PikeException;

class AuthenticatorLoginTest extends AuthenticatorTestCase {
    public function testLoginThrowsIfUserWasNotFound(): void {
        $this->expectException(PikeException::class);
        $this->expectExceptionCode(Authenticator::CREDENTIAL_WAS_INVALID);
        $this->expectExceptionMessage('User not found or not activated');
        $this->invokeLoginFeature('non-existing-username', 'irrelevant');
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
        $state->mySerializeUserForSession = function ($user) {
            return "{$user->id}|{$user->username}";
        };
        $state->actualDataPutToSession = null;
        return $state;
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
                     $s ? $s->mySerializeUserForSession : null);
        $auth->postProcess();
    }
    private function verifyWroteSerializedDataDataToSession($s): void {
        $this->assertEquals(call_user_func($s->mySerializeUserForSession, (object) [
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
        $this->verifyPassedConfigurationsToCookieStorage($state);
    }
    private function setupCookieStoreTest(): \stdClass {
        $state = $this->setupLoginSessionTest();
        $state->actualDataPassedToCookieStorage = [];
        return $state;
    }
    private function verifyPassedConfigurationsToCookieStorage(\stdClass $s): void {
        $this->assertCount(1, $s->actualDataPassedToCookieStorage);
        [$userRoleCookie] = $s->actualDataPassedToCookieStorage[0];
        $makeExpectedCookie = function ($name, $value) { return "{$name}={$value};path=/"; };
        $this->assertEquals($makeExpectedCookie('loggedInUserRole',
                                                self::TEST_USER['role']),
                            $userRoleCookie);
    }
}
