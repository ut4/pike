<?php

namespace Pike\Tests\Auth;

use Pike\AbstractMailer;
use Pike\Auth\Authenticator;
use Pike\TestUtils\MockCrypto;
use Pike\PikeException;

class AuthenticatorRequestPassResetTest extends AuthenticatorTestCase {
    public function testRequestPasswordResetThrowsIfUserWasNotFound() {
        $auth = new Authenticator($this->makePartiallyMockedServicesFactory());
        try {
            $auth->requestPasswordReset('non-existing-user',
                                        function () {});
            $this->assertFalse(true, 'Pitäisi heittää poikkeus');
        } catch (PikeException $e) {
            $this->assertEquals(Authenticator::INVALID_CREDENTIAL,
                                $e->getCode());
        }
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testRequestPasswordResetThrowsIfAccountIsNotActivated() {
        $auth = new Authenticator($this->makePartiallyMockedServicesFactory());
        $this->insertTestUserToDb(Authenticator::ACCOUNT_STATUS_BANNED);
        try {
            $auth->requestPasswordReset(self::TEST_USER_NAME,
                                        function () {});
            $this->assertFalse(true, 'Pitäisi heittää poikkeus');
        } catch (PikeException $e) {
            $this->assertEquals(Authenticator::UNEXPECTED_ACCOUNT_STATUS,
                                $e->getCode());
        }
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testRequestPasswordResetThrowsIfMailConfigIsNotValid() {
        $this->insertTestUserToDb();
        $auth = new Authenticator($this->makePartiallyMockedServicesFactory());
        try {
            $auth->requestPasswordReset(self::TEST_USER_NAME,
                function ($_user, $_resetKey, $mailConfig) {
                    $mailConfig->toAddress = '';
                    $mailConfig->fromName = ['not', 'a', 'string'];
                    $mailConfig->toName = ['not', 'a', 'string'];
                });
            $this->assertFalse(true, 'Pitäisi heittää poikkeus');
        } catch (PikeException $e) {
            $this->assertEquals(Authenticator::FAILED_TO_FORMAT_MAIL,
                                $e->getCode());
            $this->assertEquals(implode(', ', [
                'The length of fromAddress must be at least 3',
                'The length of toAddress must be at least 3',
                'The length of subject must be at least 1',
                'The length of body must be at least 1',
                'fromName must be string',
                'toName must be string',
            ]), $e->getMessage());
        }
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testRequestPasswordResetWritesResetKeyToDbAndSendsItViaEmail() {
        $state = $this->setupTestRequestPasswordTest();
        $this->insertTestUserToDb();
        $this->invokeRequestPasswordResetFeature($state);
        $this->verifyInsertedResetKeyToDb($state);
        $this->verifySentEmail($state);
    }
    private function setupTestRequestPasswordTest() {
        $state = new \stdClass;
        $state->mockResetKey = MockCrypto::mockGenRandomToken();
        $state->actualEmailSettings = null;
        $state->mockMailer = $this->createMock(AbstractMailer::class);
        $state->mockMailer->expects($this->once())
            ->method('sendMail')
            ->with($this->callback(function ($val) use ($state) {
                $state->actualEmailSettings = $val;
                return true;
            }))
            ->willReturn(true);
        return $state;
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
        $this->assertEquals(self::TEST_USER_ROLE, $row['role']);
        $this->assertNull($row['activationKey']);
        $this->assertEquals(self::TEST_USER_CREATED_AT, $row['accountCreatedAt']);
        $this->assertEquals(Authenticator::ACCOUNT_STATUS_ACTIVATED,
                            $row['accountStatus']);
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
}
