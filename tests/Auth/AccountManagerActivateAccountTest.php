<?php declare(strict_types=1);

namespace Pike\Tests\Auth;

use Pike\Auth\Authenticator;
use Pike\PikeException;
use Pike\TestUtils\MockCrypto;

class AccountManagerActivateAccountTest extends AuthenticatorTestCase {
    public function testActivateAccountThrowsIfActivationKeyWasNotFoundOrAccountStatusIsNotUnactivated(): void {
        $this->insertTestUserToDb();
        $this->expectException(PikeException::class);
        $this->expectExceptionCode(Authenticator::CREDENTIAL_WAS_INVALID);
        $this->invokeActivateAccountFeature((object) ['testActivationKey' => 'non-existing-key']);
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testActivateAccountDeletesUserAndThrowsIfActivationKeyIsExpired(): void {
        $state = $this->setupExpiredKeyTest();
        $this->insertTestUserToDb(['accountStatus' => Authenticator::ACCOUNT_STATUS_UNACTIVATED,
                                   'activationKey' => $state->testActivationKey,
                                   'accountCreatedAt' => $state->testAccountCreatedAt]);
        try {
            $this->invokeActivateAccountFeature($state);
            $this->assertFalse(true, 'Pitäisi heittää poikkeus');
        } catch (PikeException $e) {
            $this->assertEquals(Authenticator::KEY_HAD_EXPIRED,
                                $e->getCode());
            $this->assertNull($this->getTestUserFromDb(self::TEST_USER['id']),
                              'Pitäisi poistaa käyttäjä');
        }
    }
    private function setupExpiredKeyTest(): \stdClass {
        $state = $this->setupActivateAccountTest();
        $state->testAccountCreatedAt = 0;
        return $state;
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testActivateAccountValidatesActivationKeyAndUpdatesAccountStatusToDb(): void {
        $state = $this->setupActivateAccountTest();
        $this->insertTestUserToDb();
        $this->insertTestActivationInfoToDb($state);
        $this->invokeActivateAccountFeature($state);
        $this->verifyWroteAccountStatusToDb($state);
        $this->verifyClearedActivationInfoFromToDb($state);
    }
    private function setupActivateAccountTest(): \stdClass {
        $state = new \stdClass;
        $state->actualUserFromDb = null;
        $state->testActivationKey = str_repeat('-', 32);
        $state->testAccountCreatedAt = time() - 10;
        return $state;
    }
    private function insertTestActivationInfoToDb(\stdClass $s): void {
        // @allow \Pike\PikeException
        self::$db->exec('UPDATE users SET `activationKey`=?' .
                        ',`accountCreatedAt`=?,`accountStatus`=?' .
                        ' WHERE `id` = ?',
                        [$s->testActivationKey, $s->testAccountCreatedAt,
                         Authenticator::ACCOUNT_STATUS_UNACTIVATED,
                         self::TEST_USER['id']]);
    }
    private function invokeActivateAccountFeature(\stdClass $s): void {
        $this->makeAuth()->getAccountManager()->activateAccount($s->testActivationKey);
    }
    private function verifyWroteAccountStatusToDb(\stdClass $s): void {
        $data = $this->getTestUserFromDb(self::TEST_USER['id']);
        $this->assertNotNull($data);
        $this->assertEquals(Authenticator::ACCOUNT_STATUS_ACTIVATED,
                            $data->accountStatus);
        $this->assertEquals(self::TEST_USER['username'], $data->username);
        $this->assertEquals(self::TEST_USER['email'], $data->email);
        $this->assertEquals(MockCrypto::mockHashPass(self::TEST_USER_PASS),
                            $data->passwordHash);
        $this->assertEquals(self::TEST_USER['role'], $data->role);
        $this->assertEquals($s->testAccountCreatedAt, $data->accountCreatedAt);
        $this->assertNull($data->resetKey);
        $this->assertEquals('0', $data->resetRequestedAt);
        $s->actualUserFromDb = $data;
    }
    private function verifyClearedActivationInfoFromToDb(\stdClass $s): void {
        $data = $s->actualUserFromDb;
        $this->assertNull($data->activationKey);
    }
}
