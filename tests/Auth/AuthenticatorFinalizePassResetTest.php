<?php

namespace Pike\Tests\Auth;

use Pike\Auth\Authenticator;
use Pike\TestUtils\MockCrypto;
use Pike\PikeException;

class AuthenticatorFinalizePassResetTest extends AuthenticatorTestCase {
    public function testFinalizePassResetThrowsIfResetKeyWasNotFoundEmailDoNotMatch() {
        try {
            $state = $this->setupMissingKeyTest();
            $this->invokeFinalizePassResetFeature($state);
            $this->assertFalse(true, 'Pitäisi heittää poikkeus');
        } catch (PikeException $e) {
            $this->assertEquals(Authenticator::INVALID_CREDENTIAL,
                                $e->getCode());
        }
    }
    private function setupMissingKeyTest() {
        $state = $this->setupFinalizePassResetTest();
        $state->testResetKey = 'non-existing-key';
        return $state;
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testFinalizePassResetThrowsIfResetKeyIsExpired() {
        $state = $this->setupExpiredKeyTest();
        $this->insertTestUserToDb();
        $this->insertTestResetInfoToDb($state);
        try {
            $this->invokeFinalizePassResetFeature($state);
            $this->assertFalse(true, 'Pitäisi heittää poikkeus');
            $this->verifyDidNotWriteNewPasswordToDb($state);
            $this->verifyDidNotClearResetPassInfoFromToDb($state);
        } catch (PikeException $e) {
            $this->assertEquals(Authenticator::EXPIRED_KEY,
                                $e->getCode());
        }
    }
    private function setupExpiredKeyTest() {
        $state = $this->setupFinalizePassResetTest();
        $state->testResetRequestedAt = 0;
        return $state;
    }
    private function verifyDidNotWriteNewPasswordToDb($s) {
        $row = $this->getTestUserFromDb(self::TEST_USER_ID);
        $this->assertNotNull($row);
        $this->assertEquals(MockCrypto::mockHashPass(self::TEST_USER_PASS),
                            $row['passwordHash']);
        // verifyDidNotChangeExistingData
        $this->assertEquals(self::TEST_USER_NAME, $row['username']);
        $this->assertEquals(self::TEST_USER_EMAIL, $row['email']);
        $this->assertEquals(self::TEST_USER_ROLE, $row['role']);
        $this->assertNull($row['activationKey']);
        $this->assertNull($row['accountCreatedAt']);
        $this->assertEquals(Authenticator::ACCOUNT_STATUS_ACTIVATED,
                            $row['accountStatus']);
        $s->actualUserFromDb = $row;
    }
    private function verifyDidNotClearResetPassInfoFromToDb($s) {
        $row = $s->actualUserFromDb;
        $this->assertEquals($s->testResetKey, $row['resetKey']);
        $this->assertEquals($s->testResetRequestedAt, $row['resetRequestedAt']);
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testFinalizePassResetValidatesResetKeyAndWritesNewPasswordToDb() {
        $state = $this->setupFinalizePassResetTest();
        $this->insertTestUserToDb();
        $this->insertTestResetInfoToDb($state);
        $this->invokeFinalizePassResetFeature($state);
        $this->verifyWroteNewPasswordToDb($state);
        $this->verifyClearedResetPassInfoFromToDb($state);
    }
    private function setupFinalizePassResetTest() {
        $state = new \stdClass;
        $state->actualUserFromDb = null;
        $state->testResetKey = 'fus';
        $state->testResetRequestedAt = time() - 10;
        $state->newPassword = 'ro';
        return $state;
    }
    private function insertTestResetInfoToDb($s) {
        if (!self::$db->exec('UPDATE users SET `resetKey`=?,`resetRequestedAt`=?' .
                             ' WHERE `id` = ?',
                             [$s->testResetKey,
                              $s->testResetRequestedAt,
                              self::TEST_USER_ID]))
            throw new \Exception('Failed to update test data');
    }
    private function invokeFinalizePassResetFeature($s) {
        $auth = new Authenticator($this->makePartiallyMockedServicesFactory());
        $auth->finalizePasswordReset($s->testResetKey,
                                     self::TEST_USER_EMAIL,
                                     $s->newPassword);
    }
    private function verifyWroteNewPasswordToDb($s) {
        $row = $this->getTestUserFromDb(self::TEST_USER_ID);
        $this->assertNotNull($row);
        $this->assertEquals(MockCrypto::mockHashPass($s->newPassword),
                            $row['passwordHash']);
        // verifyDidNotChangeExistingData
        $this->assertEquals(self::TEST_USER_NAME, $row['username']);
        $this->assertEquals(self::TEST_USER_EMAIL, $row['email']);
        $this->assertEquals(self::TEST_USER_ROLE, $row['role']);
        $this->assertNull($row['activationKey']);
        $this->assertEquals(self::TEST_USER_CREATED_AT, $row['accountCreatedAt']);
        $this->assertEquals(Authenticator::ACCOUNT_STATUS_ACTIVATED,
                            $row['accountStatus']);
        $s->actualUserFromDb = $row;
    }
    private function verifyClearedResetPassInfoFromToDb($s) {
        $row = $s->actualUserFromDb;
        $this->assertNull($row['resetKey']);
        $this->assertEquals('0', $row['resetRequestedAt']);
    }
}
