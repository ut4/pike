<?php declare(strict_types=1);

namespace Pike\Tests\Auth;

use Pike\Auth\Authenticator;
use Pike\TestUtils\MockCrypto;
use Pike\PikeException;

class AccountManagerFinalizePassResetTest extends AuthenticatorTestCase {
    public function testFinalizePassResetThrowsIfResetKeyWasNotFoundEmailDoNotMatch(): void {
        $state = $this->setupMissingKeyTest();
        $this->expectException(PikeException::class);
        $this->expectExceptionCode(Authenticator::CREDENTIAL_WAS_INVALID);
        $this->invokeFinalizePassResetFeature($state);
    }
    private function setupMissingKeyTest(): \stdClass {
        $state = $this->setupFinalizePassResetTest();
        $state->testResetKey = 'non-existing-key';
        return $state;
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testFinalizePassResetThrowsIfResetKeyIsExpired(): void {
        $state = $this->setupExpiredKeyTest();
        $this->insertTestUserToDb(['resetKey' => $state->testResetKey,
                                   'resetRequestedAt' => $state->testResetRequestedAt]);
        try {
            $this->invokeFinalizePassResetFeature($state);
            $this->assertFalse(true, 'Pitäisi heittää poikkeus');
        } catch (PikeException $e) {
            $this->assertEquals(Authenticator::KEY_HAD_EXPIRED, $e->getCode());
            $this->verifyDidNotWriteNewPasswordToDb($state);
            $this->verifyDidNotClearResetPassInfoFromToDb($state);
        }
    }
    private function setupExpiredKeyTest(): \stdClass {
        $state = $this->setupFinalizePassResetTest();
        $state->testResetRequestedAt = 0;
        return $state;
    }
    private function verifyDidNotWriteNewPasswordToDb(\stdClass $s): void {
        $data = $this->getTestUserFromDb();
        $this->assertNotNull($data);
        $this->assertEquals(self::TEST_USER['username'], $data->username);
        $this->assertEquals(self::TEST_USER['email'], $data->email);
        $this->assertEquals(Authenticator::ACCOUNT_STATUS_ACTIVATED,
                            $data->accountStatus);
        $this->assertEquals(self::TEST_USER['role'], $data->role);
        $this->assertEquals(self::TEST_USER['accountCreatedAt'], $data->accountCreatedAt);
        $this->assertEquals(MockCrypto::mockHashPass(self::TEST_USER_PASS),
                            $data->passwordHash);
        $this->assertNull($data->activationKey);
        $this->assertNull($data->loginId);
        $this->assertNull($data->loginIdValidatorHash);
        $this->assertNull($data->loginData);
        $s->actualUserFromDb = $data;
    }
    private function verifyDidNotClearResetPassInfoFromToDb(\stdClass $s): void {
        $data = $s->actualUserFromDb;
        $this->assertEquals($s->testResetKey, $data->resetKey);
        $this->assertEquals($s->testResetRequestedAt, $data->resetRequestedAt);
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testFinalizePassResetValidatesResetKeyAndWritesNewPasswordToDb(): void {
        $state = $this->setupFinalizePassResetTest();
        $this->insertTestUserToDb(['resetKey' => $state->testResetKey,
                                   'resetRequestedAt' => $state->testResetRequestedAt]);
        $this->invokeFinalizePassResetFeature($state);
        $this->verifyWroteNewPasswordToDb($state);
        $this->verifyClearedResetPassInfoFromToDb($state);
    }
    private function setupFinalizePassResetTest(): \stdClass {
        $state = new \stdClass;
        $state->actualUserFromDb = null;
        $state->testResetKey = 'fus';
        $state->testResetRequestedAt = time() - 10;
        $state->newPassword = 'ro';
        return $state;
    }
    private function invokeFinalizePassResetFeature(\stdClass $s): void {
        $this->makeAuth()->getAccountManager()->finalizePasswordReset(
            $s->testResetKey,
            self::TEST_USER['email'],
            $s->newPassword
        );
    }
    private function verifyWroteNewPasswordToDb(\stdClass $s): void {
        $data = $this->getTestUserFromDb();
        $this->assertNotNull($data);
        $this->assertEquals(self::TEST_USER['username'], $data->username);
        $this->assertEquals(self::TEST_USER['email'], $data->email);
        $this->assertEquals(Authenticator::ACCOUNT_STATUS_ACTIVATED,
                            $data->accountStatus);
        $this->assertEquals(self::TEST_USER['role'], $data->role);
        $this->assertEquals(self::TEST_USER['accountCreatedAt'], $data->accountCreatedAt);
        $this->assertEquals(MockCrypto::mockHashPass($s->newPassword),
                            $data->passwordHash);
        $this->assertNull($data->activationKey);
        $this->assertNull($data->loginId);
        $this->assertNull($data->loginIdValidatorHash);
        $this->assertNull($data->loginData);
        $s->actualUserFromDb = $data;
    }
    private function verifyClearedResetPassInfoFromToDb(\stdClass $s): void {
        $data = $s->actualUserFromDb;
        $this->assertNull($data->resetKey);
        $this->assertEquals('0', $data->resetRequestedAt);
    }
}
