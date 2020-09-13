<?php declare(strict_types=1);

namespace Pike\Tests\Auth;

use Pike\Auth\{Authenticator};
use Pike\PikeException;
use Pike\TestUtils\MockCrypto;

class AuthenticatorLoginTest extends AuthenticatorTestCase {
    public function testLoginThrowsIfUserWasNotFound(): void {
        $auth = new Authenticator($this->makePartiallyMockedServicesFactory());
        try {
            $auth->login('username', 'irrelevant');
            $this->assertFalse(true, 'Pitäisi heittää poikkeus');
        } catch (PikeException $e) {
            $this->assertEquals(Authenticator::INVALID_CREDENTIAL,
                                $e->getCode());
            $this->assertEquals('User not found or not activated',
                                $e->getMessage());
        }
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testLoginThrowsIfPasswordDoesNotMatch(): void {
        $this->insertTestUserToDb();
        $auth = new Authenticator($this->makePartiallyMockedServicesFactory());
        try {
            $auth->login(self::TEST_USER_NAME, 'wrongPass');
            $this->assertFalse(true, 'Pitäisi heittää poikkeus');
        } catch (PikeException $e) {
            $this->assertEquals(Authenticator::INVALID_CREDENTIAL,
                                $e->getCode());
            $this->assertEquals('Invalid password',
                                $e->getMessage());
        }
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testLoginThrowsIfAccountIsBanned(): void {
        $this->insertTestUserToDb(Authenticator::ACCOUNT_STATUS_BANNED);
        $auth = new Authenticator($this->makePartiallyMockedServicesFactory());
        try {
            $auth->login(self::TEST_USER_NAME, self::TEST_USER_PASS);
            $this->assertFalse(true, 'Pitäisi heittää poikkeus');
        } catch (PikeException $e) {
            $this->assertEquals(Authenticator::UNEXPECTED_ACCOUNT_STATUS,
                                $e->getCode());
        }
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testLoginThrowsIfAccountIsUnactivated(): void {
        $this->insertTestUserToDb(Authenticator::ACCOUNT_STATUS_UNACTIVATED);
        $auth = new Authenticator($this->makePartiallyMockedServicesFactory());
        try {
            $auth->login(self::TEST_USER_NAME, self::TEST_USER_PASS);
            $this->assertFalse(true, 'Pitäisi heittää poikkeus');
        } catch (PikeException $e) {
            $this->assertEquals(Authenticator::UNEXPECTED_ACCOUNT_STATUS,
                                $e->getCode());
        }
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testLoginPutsUserToSessionOnSuccess(): void {
        $this->insertTestUserToDb();
        $state = $this->setupLoginSessionTest();
        $this->invokeLoginFeature($state, $this->makeSpyingSession($state));
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
    private function invokeLoginFeature($s,
                                        $mockSession = null,
                                        $mockCookieManager = null): void {
        $auth = new Authenticator($this->makePartiallyMockedServicesFactory($mockSession,
            null, $mockCookieManager), 'maybeLoggedInUserRole');
        $auth->login(self::TEST_USER_NAME,
                     self::TEST_USER_PASS,
                     $s->mySerializeUserForSession);
    }
    private function verifyWroteSerializedDataDataToSession($s): void {
        $this->assertEquals(call_user_func($s->mySerializeUserForSession, (object) [
                                'id' => self::TEST_USER_ID,
                                'username' => self::TEST_USER_NAME
                            ]),
                            $s->actualDataPutToSession);
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testLoginStoresUserToCookiesOnSuccess(): void {
        $this->insertTestUserToDb();
        $state = $this->setupStoreCookieTest();
        $this->invokeLoginFeature($state,
            $this->makeSpyingSession($state),
            $this->makeSpyingCookieManager($state));
        $this->verifyWroteCookies($state);
    }
    private function setupStoreCookieTest(): \stdClass {
        $state = $this->setupLoginSessionTest();
        $state->actualPutCookieCalls = [];
        return $state;
    }
    private function verifyWroteCookies(\stdClass $s): void {
        $this->assertCount(2, $s->actualPutCookieCalls);
        [$userRoleCookieName, $userRoleCookieValue] = $s->actualPutCookieCalls[0];
        $this->assertEquals('maybeLoggedInUserRole', $userRoleCookieName);
        $this->assertEquals(self::TEST_USER_ROLE, $userRoleCookieValue);
        //
        [$cookieNameSetByRememberMe, $cookieValueSetByRememberMe] = $s->actualPutCookieCalls[1];
        $expectedLoginId = MockCrypto::mockGenRandomToken();
        $expectedLoginIdToken = MockCrypto::mockGenRandomToken();
        $this->assertEquals('loginTokens', $cookieNameSetByRememberMe);
        $this->assertEquals("{$expectedLoginId}:{$expectedLoginIdToken}",
                            $cookieValueSetByRememberMe);
    }
}
