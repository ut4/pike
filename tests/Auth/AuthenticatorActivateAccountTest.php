<?php

namespace Pike\Tests\Auth;

use Pike\Auth\Authenticator;
use Pike\PikeException;
use Pike\TestUtils\MockCrypto;

class AuthenticatorActivateAccountTest extends AuthenticatorTestCase {
    public function testActivateAccountThrowsIfActivationKeyWasNotFoundOrAccountStatusIsNotUnactivated() {
        $this->insertTestUserToDb();
        try {
            $state = (object) ['testActivationKey' => 'non-existing-key'];
            $this->invokeActivateAccountFeature($state);
            $this->assertFalse(true, 'Pitäisi heittää poikkeus');
        } catch (PikeException $e) {
            $this->assertEquals(Authenticator::INVALID_CREDENTIAL,
                                $e->getCode());
        }
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testActivateAccountDeletesUserAndThrowsIfActivationKeyIsExpired() {
        $state = $this->setupExpiredKeyTest();
        $this->insertTestUserToDb();
        $this->insertTestActivationInfoToDb($state);
        try {
            $this->invokeActivateAccountFeature($state);
            $this->assertFalse(true, 'Pitäisi heittää poikkeus');
            $this->assertNull($this->getTestUserFromDb(self::TEST_USER_ID),
                              'Pitäisi poistaa käyttäjä');
        } catch (PikeException $e) {
            $this->assertEquals(Authenticator::EXPIRED_KEY,
                                $e->getCode());
        }
    }
    private function setupExpiredKeyTest() {
        $state = $this->setupActivateAccountTest();
        $state->testAccountCreatedAt = 0;
        return $state;
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testActivateAccountValidatesActivationKeyAndUpdatesAccountStatusToDb() {
        $state = $this->setupActivateAccountTest();
        $this->insertTestUserToDb();
        $this->insertTestActivationInfoToDb($state);
        $this->invokeActivateAccountFeature($state);
        $this->verifyWroteAccountStatusToDb($state);
        $this->verifyClearedActivationInfoFromToDb($state);
    }
    private function setupActivateAccountTest() {
        $state = new \stdClass;
        $state->actualUserFromDb = null;
        $state->testActivationKey = str_repeat('-', 32);
        $state->testAccountCreatedAt = time() - 10;
        return $state;
    }
    private function insertTestActivationInfoToDb($s) {
        if (!self::$db->exec('UPDATE users SET `activationKey`=?' .
                             ',`accountCreatedAt`=?,`accountStatus`=?' .
                             ' WHERE `id` = ?',
                             [$s->testActivationKey, $s->testAccountCreatedAt,
                              Authenticator::ACCOUNT_STATUS_UNACTIVATED,
                              self::TEST_USER_ID]))
            throw new \Exception('Failed to update test data');
    }
    private function invokeActivateAccountFeature($s) {
        $auth = new Authenticator($this->makePartiallyMockedServicesFactory());
        $auth->activateAccount($s->testActivationKey);
    }
    private function verifyWroteAccountStatusToDb($s) {
        $row = $this->getTestUserFromDb(self::TEST_USER_ID);
        $this->assertNotNull($row);
        $this->assertEquals(Authenticator::ACCOUNT_STATUS_ACTIVATED,
                            $row['accountStatus']);
        // verifyDidNotChangeExistingData
        $this->assertEquals(self::TEST_USER_NAME, $row['username']);
        $this->assertEquals(self::TEST_USER_EMAIL, $row['email']);
        $this->assertEquals(MockCrypto::mockHashPass(self::TEST_USER_PASS),
                            $row['passwordHash']);
        $this->assertEquals(self::TEST_USER_ROLE, $row['role']);
        $this->assertNull($row['activationKey']);
        $this->assertEquals($s->testAccountCreatedAt, $row['accountCreatedAt']);
        $this->assertNull($row['resetKey']);
        $this->assertNull($row['resetRequestedAt']);
        $s->actualUserFromDb = $row;
    }
    private function verifyClearedActivationInfoFromToDb($s) {
        $row = $s->actualUserFromDb;
        $this->assertNull($row['activationKey']);
    }
}
