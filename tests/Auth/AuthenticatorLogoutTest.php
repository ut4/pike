<?php declare(strict_types=1);

namespace Pike\Tests\Auth;

class AuthenticatorLogoutTest extends AuthenticatorTestCase {
    public function testLogoutNukesCookies(): void {
        $state = $this->setupLogoutTest();
        $this->invokeLogoutFeature($state,
                                   $this->makeSpyingSession($state),
                                   $this->makeSpyingCookieStorage($state),
                                   true);
        $this->verifyPassedClearCookiesToCookieStorage('loggedInUserRole', $state);
    }
    private function setupLogoutTest(): \stdClass {
        $state = new \stdClass;
        $state->destroyWasActuallyCalled = false;
        return $state;
    }
    private function invokeLogoutFeature(\stdClass $s,
                                         $mockSession,
                                         $mockCookieStorage,
                                         bool $useUserRoleCookie = false,
                                         bool $useRememberMe = false) {
        $auth = $this->makeAuth($mockSession,
                                $mockCookieStorage,
                                $useUserRoleCookie,
                                $useRememberMe);
        $auth->logout();
        $auth->postProcess();
    }
    private function verifyPassedClearCookiesToCookieStorage(string $expectedCookieName,
                                                             \stdClass $s): void {
        $this->assertCount(1, $s->actualDataPassedToCookieStorage);
        [$clearUserRoleCookie] = $s->actualDataPassedToCookieStorage[0];
        $this->assertEquals("{$expectedCookieName}=-;path=/;expires=Thu, 01 Jan 1970 00:00:01 GMT",
                            $clearUserRoleCookie);
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testLogoutWithRememberMeNukesCookiesAndLoginDataFromDb(): void {
        $state = $this->setupRememberMeLogoutTest();
        $this->insertTestUserToDb($state->testUserData);
        $this->invokeLogoutFeature($state,
                                   $this->makeSpyingSession($state),
                                   $this->makeCookieStorageThatReturnsValidRememberMeTokens($state),
                                   false,
                                   true);
        $this->verifyPassedClearCookiesToCookieStorage('loginTokens', $state);
        $this->verifyClearedLoginDataFromDb();
    }
    private function setupRememberMeLogoutTest(): \stdClass {
        $state = new \stdClass;
        $state->loginValidatorToken = str_repeat('a', 32);
        $state->testUserData = [
            'loginId' => str_repeat('b', 32),
            'loginIdValidatorHash' => '<mock-sha256-hash---------------------------------------------->',
            'loginData' => '{"foo":"bar"}',
        ];
        return $state;
    }
    private function verifyClearedLoginDataFromDb(): void {
        $actual = $this->getTestUserFromDb();
        $expected = array_merge(self::TEST_USER,
                                ['loginId' => null,
                                 'loginIdValidatorHash' => null,
                                 'loginData' => null,]);
        $this->assertEquals((object) $expected, $actual);
    }
}
