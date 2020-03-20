<?php

namespace Pike\Tests\Auth;

use Pike\Auth\Authenticator;
use Pike\TestUtils\MockCrypto;
use Pike\Auth\Internal\PhpMailerMailer;

class AuthenticatorResetPassTest extends AuthenticatorTestCase {
    public function testRequestPasswordResetWritesResetKeyToDbAndSendsItViaEmail() {
        $state = $this->setupTestRequestPasswordTest();
        $this->insertTestUserToDb();
        $this->invokeRequestPasswordResetFeature($state);
        $this->verifyInsertedResetKeyToDb($state);
        $this->verifySentEmail($state);
    }
    private function setupTestRequestPasswordTest() {
        $out = new \stdClass;
        $out->mockResetKey = MockCrypto::mockGenRandomToken();
        $out->actualEmailSettings = null;
        $out->mockMailer = $this->createMock(PhpMailerMailer::class);
        $out->mockMailer->expects($this->once())
            ->method('sendMail')
            ->with($this->callback(function ($val) use ($out) {
                $out->actualEmailSettings = $val;
                return true;
            }))
            ->willReturn(true);
        return $out;
    }
    private function invokeRequestPasswordResetFeature($s) {
        $auth = new Authenticator($this->makePartiallyMockedServicesFactory(
            null, $s->mockMailer
        ));
        $auth->requestPasswordReset(self::TEST_USER_EMAIL,
            function ($_user, $resetKey, $settingsOut) {
                $settingsOut->fromAddress = 'mysite.com';
                $settingsOut->subject = 'mysite.com | Password reset';
                $settingsOut->body = "Please visit /change-password/{$resetKey}";
            });
    }
    private function verifyInsertedResetKeyToDb($s) {
        $row = $this->getTestUserFromDb(self::TEST_USER_ID);
        $this->assertNotNull($row);
        $this->assertEquals($s->mockResetKey, $row['resetKey']);
        $this->assertEquals(true, $row['resetRequestedAt'] !== null);
        $this->assertEquals(true, $row['resetRequestedAt'] > time() - 20);
        // verifyDidNotChangeExistingData
        $this->assertEquals(self::TEST_USER_NAME, $row['username']);
        $this->assertEquals(self::TEST_USER_EMAIL, $row['email']);
        $this->assertEquals(MockCrypto::mockHashPass(self::TEST_USER_PASS),
                            $row['passwordHash']);
    }
    private function verifySentEmail($s) {
        $this->assertEquals(true, $s->actualEmailSettings !== null,
            'Pitäisi kutsua $mailer->sendMail()');
        $this->assertEquals(self::TEST_USER_EMAIL, $s->actualEmailSettings->toAddress);
        $this->assertEquals(self::TEST_USER_NAME, $s->actualEmailSettings->toName);
        $this->assertEquals('mysite.com', $s->actualEmailSettings->fromAddress);
        $this->assertEquals('mysite.com | Password reset', $s->actualEmailSettings->subject);
        $this->assertEquals("Please visit /change-password/{$s->mockResetKey}",
                            $s->actualEmailSettings->body);
    }
    private function makeTestUser() {
        return ['id' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
                'username' => 'Test User',
                'email' => 'testuser@email.com',
                'passwordHash' => MockCrypto::mockEncrypt('foo'),
                'role' => 1];
    }
    private function getTestUserFromDb($userId) {
        return self::$db->fetchOne('SELECT `username`,`email`,`passwordHash`' .
                                       ',`resetKey`,`resetRequestedAt`' .
                                   ' FROM users' .
                                   ' WHERE `id` = ?',
                                   [$userId]);
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testFinalizePasswordResetValidatesResetKeyAndWritesNewPasswordToDb() {
        $state = $this->setupFinalizePasswordResetTest();
        $this->insertTestUserToDb();
        $this->insertTestResetInfoToDb($state);
        $this->invokeFinalizePasswordResetFeature($state);
        $this->verifyWroteNewPasswordToDb($state);
        $this->verifyClearedResetPassInfoFromToDb($state);
    }
    private function setupFinalizePasswordResetTest() {
        $out = new \stdClass;
        $out->actualUserFromDb = null;
        $out->testResetKey = 'fus';
        $out->newPassword = 'ro';
        return $out;
    }
    private function insertTestResetInfoToDb($s) {
        if (!self::$db->exec('UPDATE users SET `resetKey`=?,`resetRequestedAt`=?' .
                             ' WHERE `id` = ?',
                             [$s->testResetKey, time()-10, self::TEST_USER_ID]))
            throw new \Exception('Failed to insert test data');
    }
    private function invokeFinalizePasswordResetFeature($s) {
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
        $s->actualUserFromDb = $row;
    }
    private function verifyClearedResetPassInfoFromToDb($s) {
        $row = $s->actualUserFromDb;
        $this->assertEquals(null, $row['resetKey']);
        $this->assertEquals(null, $row['resetRequestedAt']);
    }
}
