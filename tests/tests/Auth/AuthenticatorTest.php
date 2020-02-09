<?php

use Pike\Auth\Authenticator;
use Pike\Auth\Internal\CachingServicesFactory;
use Pike\TestUtils\DbTestCase;
use Pike\TestUtils\MockCrypto;
use Pike\Auth\Internal\PhpMailerMailer;

class AuthenticatorTest extends DbTestCase {
    private $auth;
    public function testRequestPasswordResetWritesResetKeyToDbAndSendsItViaEmail() {
        $state = $this->setupTestRequestPasswordTest();
        $this->auth = new Authenticator(new CachingServicesFactory(
            self::getDb(), new MockCrypto, $state->mockMailer
        ));
        $this->createTestUser($state);
        $this->invokeRequestPasswordResetFeature($state);
        $this->verifyInsertedResetKeyToDb($state);
        $this->verifySentEmail($state);
    }
    private function setupTestRequestPasswordTest() {
        $out = new \stdClass;
        $out->testUser = $this->makeTestUser();
        $out->mockResetKey = MockCrypto::mockGenRandomToken();
        $out->actualEmailSettings = null;
        $out->mockMailer = $this->createMock(PhpMailerMailer::class);
        $out->mockMailer->expects($this->once())
            ->method('sendMail')
            ->with($this->callback(function ($val) use ($out) {
                $out->actualEmailSettings = $val;
                return true; // assert later
            }))
            ->willReturn(true);
        return $out;
    }
    private function createTestUser($s) {
        if (self::$db->exec('INSERT INTO users (`id`,`username`,`email`,`passwordHash`)' .
                            ' VALUES (?,?,?,?)',
                            array_values($s->testUser)) !== 1)
            throw new \Exception('Failed to insert test data');
    }
    private function invokeRequestPasswordResetFeature($s) {
        $this->auth->requestPasswordReset($s->testUser['email'],
            function ($_user, $resetKey, $settingsOut) {
                $settingsOut->fromAddress = 'mysite.com';
                $settingsOut->subject = 'mysite.com | Password reset';
                $settingsOut->body = "Please visit /change-password/{$resetKey}";
            });
    }
    private function verifyInsertedResetKeyToDb($s) {
        $row = $this->getTestUserFromDb($s->testUser['id']);
        $this->assertNotNull($row);
        $this->assertEquals($s->mockResetKey, $row['resetKey']);
        $this->assertEquals(true, $row['resetRequestedAt'] !== null);
        $this->assertEquals(true, $row['resetRequestedAt'] > time() - 20);
        // verifyDidNotChangeExistingData
        $this->assertEquals($s->testUser['username'], $row['username']);
        $this->assertEquals($s->testUser['email'], $row['email']);
        $this->assertEquals($s->testUser['passwordHash'], $row['passwordHash']);
    }
    private function verifySentEmail($s) {
        $this->assertEquals(true, $s->actualEmailSettings !== null,
            'Pitäisi kutsua $mailer->sendMail()');
        $this->assertEquals($s->testUser['email'], $s->actualEmailSettings->toAddress);
        $this->assertEquals($s->testUser['username'], $s->actualEmailSettings->toName);
        $this->assertEquals('mysite.com', $s->actualEmailSettings->fromAddress);
        $this->assertEquals('mysite.com | Password reset', $s->actualEmailSettings->subject);
        $this->assertEquals("Please visit /change-password/{$s->mockResetKey}",
                            $s->actualEmailSettings->body);
    }
    private function makeTestUser() {
        return ['id' => 'a',
                'username' => 'Test User',
                'email' => 'testuser@email.com',
                'passwordHash' => MockCrypto::mockEncrypt('foo')];
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
        $this->auth = new Authenticator(new CachingServicesFactory(
            self::getDb(), new MockCrypto
        ));
        $this->createTestUser($state);
        $this->insertTestResetInfoToDb($state);
        $this->invokeFinalizePasswordResetFeature($state);
        $this->verifyWroteNewPasswordToDb($state);
        $this->verifyClearedResetPassInfoFromToDb($state);
    }
    private function setupFinalizePasswordResetTest() {
        $out = new \stdClass;
        $out->testUser = $this->makeTestUser();
        $out->actualUserFromDb = null;
        $out->testResetKey = 'fus';
        $out->newPassword = 'ro';
        return $out;
    }
    private function insertTestResetInfoToDb($s) {
        if (!self::$db->exec('UPDATE users SET `resetKey`=?,`resetRequestedAt`=?' .
                             ' WHERE `id` = ?',
                             [$s->testResetKey, time()-10, $s->testUser['id']]))
            throw new \Exception('Failed to insert test data');
    }
    private function invokeFinalizePasswordResetFeature($s) {
        $this->auth->finalizePasswordReset($s->testResetKey,
                                           $s->testUser['email'],
                                           $s->newPassword);
    }
    private function verifyWroteNewPasswordToDb($s) {
        $row = $this->getTestUserFromDb($s->testUser['id']);
        $this->assertNotNull($row);
        $this->assertEquals(MockCrypto::mockHashPass($s->newPassword),
                            $row['passwordHash']);
        // verifyDidNotChangeExistingData
        $this->assertEquals($s->testUser['username'], $row['username']);
        $this->assertEquals($s->testUser['email'], $row['email']);
        $s->actualUserFromDb = $row;
    }
    private function verifyClearedResetPassInfoFromToDb($s) {
        $row = $s->actualUserFromDb;
        $this->assertEquals(null, $row['resetKey']);
        $this->assertEquals(null, $row['resetRequestedAt']);
    }
}
