<?php declare(strict_types=1);

namespace Pike\Tests\Auth;

use Pike\Auth\Authenticator;
use Pike\PikeException;

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
        $this->insertTestUserToDb(['activationKey' => $state->testActivationKey,
                                   'accountCreatedAt' => $state->testAccountCreatedAt,
                                   'accountStatus' => Authenticator::ACCOUNT_STATUS_UNACTIVATED]);
        $state->originalData = $this->getTestUserFromDb(self::TEST_USER['id']);
        $this->invokeActivateAccountFeature($state);
        $this->verifyClearedActivationKeyAndUpdatedAccountStatusToDb($state);
        $this->verifyDidNotChangeOriginalDataFromDb($state);
    }
    private function setupActivateAccountTest(): \stdClass {
        $state = new \stdClass;
        $state->actualUserFromDb = null;
        $state->testActivationKey = str_repeat('-', 32);
        $state->testAccountCreatedAt = time() - 10;
        return $state;
    }
    private function invokeActivateAccountFeature(\stdClass $s): void {
        $this->makeAuth()->getAccountManager()->activateAccount($s->testActivationKey);
    }
    private function verifyClearedActivationKeyAndUpdatedAccountStatusToDb(\stdClass $s): void {
        $data = $this->getTestUserFromDb(self::TEST_USER['id']);
        $this->assertNotNull($data);
        $this->assertNull($data->activationKey);
        $this->assertEquals(Authenticator::ACCOUNT_STATUS_ACTIVATED,
                            $data->accountStatus);
        $s->actualUserFromDb = $data;
    }
    private function verifyDidNotChangeOriginalDataFromDb(\stdClass $s): void {
        foreach (['activationKey', 'accountStatus'] as $except) {
            unset($s->originalData->{$except});
            unset($s->actualUserFromDb->{$except});
        }
        $this->assertEquals($s->originalData, $s->actualUserFromDb);
    }
}
