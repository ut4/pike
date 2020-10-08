<?php declare(strict_types=1);

namespace Pike\Tests\Auth;

use Pike\Auth\Authenticator;
use Pike\PikeException;

class AuthenticatorLoginByUserIdTest extends AuthenticatorTestCase {
    public function testLoginByUserIdThrowsIfUserWasNotFound(): void {
        $this->expectException(PikeException::class);
        $this->expectExceptionCode(Authenticator::CREDENTIAL_WAS_INVALID);
        $this->expectExceptionMessage('User not found or not activated');
        $this->invokeLoginByUserIdFeature('non-existing-userid');
    }
    private function invokeLoginByUserIdFeature(string $userId,
                                        \stdClass $s = null,
                                        $mockSession = null,
                                        $mockCookieStorage = null,
                                        bool $useUserRoleCookie = false,
                                        bool $useRememberMe = false): void {
        $auth = $this->makeAuth($mockSession, $mockCookieStorage, $useUserRoleCookie, $useRememberMe);
        $auth->loginByUserId($userId,
                             $s ? $s->myUserToMakeSessionDataFn : null);
        $auth->postProcess();
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testLoginByUserIdThrowsIfAccountIsBanned(): void {
        $this->insertTestUserToDb(['accountStatus' => Authenticator::ACCOUNT_STATUS_BANNED]);
        $this->expectException(PikeException::class);
        $this->expectExceptionCode(Authenticator::ACCOUNT_STATUS_WAS_UNEXPECTED);
        $this->invokeLoginByUserIdFeature(self::TEST_USER['id']);
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testLoginByUserIdThrowsIfAccountIsUnactivated(): void {
        $this->insertTestUserToDb(['accountStatus' => Authenticator::ACCOUNT_STATUS_UNACTIVATED]);
        $this->expectException(PikeException::class);
        $this->expectExceptionCode(Authenticator::ACCOUNT_STATUS_WAS_UNEXPECTED);
        $this->invokeLoginByUserIdFeature(self::TEST_USER['id']);
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testLoginByUserIdPutsUserToSessionOnSuccess(): void {
        $this->insertTestUserToDb();
        $state = $this->setupLoginSessionTest();
        $this->invokeLoginByUserIdFeature(self::TEST_USER['id'],
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


    public function testLoginByUserIdStoresUserToCookiesOnSuccess(): void {
        $this->insertTestUserToDb();
        $state = $this->setupCookieStoreTest();
        $this->invokeLoginByUserIdFeature(self::TEST_USER['id'],
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
