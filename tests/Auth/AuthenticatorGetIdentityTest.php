<?php declare(strict_types=1);

namespace Pike\Tests\Auth;

use Pike\Auth\Authenticator;
use Pike\Auth\Interfaces\CookieStorageInterface;
use Pike\Interfaces\SessionInterface;
use Pike\TestUtils\MockCrypto;

class AuthenticatorGetIdentityTest extends AuthenticatorTestCase {
    public function testGetIdentityRetrievesIdentityFromSession(): void {
        $auth = $this->makeAuth($this->makeSessionThatReturnsIdentity());
        $actual = $auth->getIdentity();
        $this->assertEquals((object) ['id' => self::TEST_USER['id']], $actual);
    }
    private function makeSessionThatReturnsIdentity() {
        $out = $this->createMock(SessionInterface::class);
        $out->method('get')
            ->with('user')
            ->willReturn((object) ['id' => self::TEST_USER['id']]);
        return $out;
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testGetIdentityReturnsNullIfUserSessionDoesNotExistAndRememberMeIsOff(): void {
        $auth = $this->makeAuth($this->makeSessionThatReturnsNothing());
        $actual = $auth->getIdentity();
        $this->assertNull($actual);
    }
    private function makeSessionThatReturnsNothing(\stdClass $state = null) {
        if (!$state) $state = new \stdClass;
        $out = $this->makeSpyingSession($state);
        $out->method('get')
            ->with('user')
            ->willReturn(null);
        return $out;
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testGetIdentityReturnsNullIfUserSessionNorRememberMeCookieExist(): void {
        $auth = $this->makeAuth($this->makeSessionThatReturnsNothing(),
                                $this->makeCookieStorageThatReturnsNothing(),
                                false,
                                true);
        $actual = $auth->getIdentity();
        $this->assertNull($actual);
    }
    private function makeCookieStorageThatReturnsNothing() {
        $out = $this->createMock(CookieStorageInterface::class);
        $out->method('getCookie')
            ->with('loginTokens')
            ->willReturn(null);
        return $out;
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testGetIdentityRetrievesIdentityUsingRememberMe(): void {
        $state = $this->setupRememberMeTest();
        $this->insertTestUserToDb($state->testUserData);
        $this->invokeGetIdentityFeature($state);
        $this->verifyReturnedMatchingLoginDataFromDb($state);
        $this->verifyStoredLoginDataToSession($state);
    }
    private function setupRememberMeTest(): \stdClass {
        $state = new \stdClass;
        $state->loginValidatorToken = str_repeat('a', 32);
        $state->sessionData = (object) ['id' => self::TEST_USER['id']];
        $state->testUserData = [
            'loginId' => str_repeat('b', 32),
            'loginIdValidatorHash' => MockCrypto::mockHash('sha256', $state->loginValidatorToken),
            'loginData' => serialize($state->sessionData),
        ];
        $state->actualDataPutToSession = null;
        $state->actualIdentity = null;
        return $state;
    }
    private function invokeGetIdentityFeature(\stdClass $state): void {
        $auth = $this->makeAuth($this->makeSessionThatReturnsNothing($state),
                                $this->makeCookieStorageThatReturnsValidRememberMeTokens($state),
                                false,
                                true);
        $state->actualIdentity = $auth->getIdentity();
    }
    private function verifyReturnedMatchingLoginDataFromDb(\stdClass $state): void {
        $this->assertEquals($state->sessionData, $state->actualIdentity);
    }
    private function verifyStoredLoginDataToSession(\stdClass $state): void {
        $this->assertEquals($state->sessionData,
                            $state->actualDataPutToSession);
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testGetIdentityWithRememberMeOnDoesNotRetrieveIdentityIfAccountIsBanned(): void {
        $state = $this->setupRememberBannedUserTest();
        $this->insertTestUserToDb($state->testUserData);
        $this->invokeGetIdentityFeature($state);
        $this->verifyDidNotReturIdentity($state);
        $this->verifyClearedLoginDataFromDb($state);
    }
    private function setupRememberBannedUserTest(): \stdClass {
        $state = $this->setupRememberMeTest();
        $state->testUserData['accountStatus'] = Authenticator::ACCOUNT_STATUS_BANNED;
        return $state;
    }
    private function verifyDidNotReturIdentity(\stdClass $state): void {
        $this->assertNull($state->actualIdentity);
    }
    private function verifyClearedLoginDataFromDb(\stdClass $state): void {
        $data = $this->getTestUserFromDb(self::TEST_USER['id']);
        $this->assertNotNull($data);
        $this->assertNull($data->loginId);
        $this->assertNull($data->loginIdValidatorHash);
        $this->assertNull($data->loginData);
    }
}
