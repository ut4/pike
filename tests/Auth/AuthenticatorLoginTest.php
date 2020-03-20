<?php

namespace Pike\Tests\Auth;

use Pike\Auth\Authenticator;
use Pike\Auth\Internal\CachingServicesFactory;
use Pike\PikeException;
use Pike\SessionInterface;

class AuthenticatorLoginTest extends AuthenticatorTestCase {
    public function testLoginThrowsIfUserWasNotFound() {
        $auth = new Authenticator(new CachingServicesFactory(self::$db));
        try {
            $auth->login('username', 'irrelevant');
            $this->assertFalse(true, 'Pitäisi heittää poikkeus');
        } catch (PikeException $e) {
            $this->assertEquals(Authenticator::INVALID_CREDENTIAL,
                                $e->getCode());
            $this->assertEquals('User not found',
                                $e->getMessage());
        }
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testLoginThrowsIfPasswordDidNotMatch() {
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


    public function testLoginPutsUserToSessionOnSuccess() {
        $this->insertTestUserToDb();
        $state = $this->setupLoginSessionTest();
        $this->invokeLoginFeature($state);
        $this->verifyWroteSerializedDataDataToSession($state);
    }
    private function setupLoginSessionTest() {
        $state = new \stdClass;
        $state->mySerializeUserForSession = function ($user) {
            return "{$user->id}|{$user->username}";
        };
        $state->actualDataPutToSession = null;
        $state->mockSession = $this->createMock(SessionInterface::class);
        $state->mockSession->method('put')
            ->with('user', $this->callback(function ($data) use ($state) {
                $state->actualDataPutToSession = $data;
                return true;
            }));
        return $state;
    }
    private function invokeLoginFeature($s) {
        $auth = new Authenticator($this->makePartiallyMockedServicesFactory($s->mockSession));
        $auth->login(self::TEST_USER_NAME,
                     self::TEST_USER_PASS,
                     $s->mySerializeUserForSession);
    }
    private function verifyWroteSerializedDataDataToSession($s) {
        $this->assertEquals($s->mySerializeUserForSession->__invoke((object) [
                                'id' => self::TEST_USER_ID,
                                'username' => self::TEST_USER_NAME
                            ]),
                            $s->actualDataPutToSession);
    }
}
