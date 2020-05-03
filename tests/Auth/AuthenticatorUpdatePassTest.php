<?php

namespace Pike\Tests\Auth;

use Pike\Auth\Authenticator;
use Pike\TestUtils\MockCrypto;

class AuthenticatorUpdatePassTest extends AuthenticatorTestCase {
    public function testUpdatePasswordSavesNewHashedPasswordToDb() {
        $this->insertTestUserToDb();
        $state = $this->setupBasicUpdatePassTest();
        $this->invokeUpdatePassFeature($state);
        $this->verifyRehashedAndSavedNewPassword($state);
    }
    private function setupBasicUpdatePassTest() {
        $state = new \stdClass;
        $state->newPassword = 'Updated pass';
        return $state;
    }
    private function invokeUpdatePassFeature($s) {
        $auth = new Authenticator($this->makePartiallyMockedServicesFactory());
        $auth->updatePassword(self::TEST_USER_ID, $s->newPassword);
    }
    private function verifyRehashedAndSavedNewPassword($s) {
        $row = self::$db->fetchOne('SELECT * FROM ${p}users');
        $this->assertIsArray($row);
        $this->assertEquals(MockCrypto::mockHashPass($s->newPassword),
                            $row['passwordHash']);
    }
}
