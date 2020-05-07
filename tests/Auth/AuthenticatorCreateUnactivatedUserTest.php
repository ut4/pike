<?php

namespace Pike\Tests\Auth;

use Pike\Auth\ACL;
use Pike\Auth\Authenticator;
use Pike\TestUtils\MockCrypto;
use Pike\Auth\Internal\PhpMailerMailer;

class AuthenticatorCreateUnactivatedUserTest extends AuthenticatorTestCase {
    public function testRequestNewAccountInsertsUserToDbAndSendsActivationKeyViaEmail() {
        $state = $this->setupTestRequestNewAccountTest();
        $this->invokeRequestNewAccountRequest($state);
        $this->verifyInsertedUnactivatedUserToDb($state);
        $this->verifySentEmail($state);
    }
    private function setupTestRequestNewAccountTest() {
        $state = new \stdClass;
        $state->inputUsername = 'new_user_username';
        $state->inputEmail = 'new@user.mail';
        $state->inputPassword = 'new user pass';
        $state->inputRole = ACL::ROLE_EDITOR;
        $state->mockActivationKey = MockCrypto::mockGenRandomToken();
        $state->actualEmailSettings = null;
        $state->mockMailer = $this->createMock(PhpMailerMailer::class);
        $state->mockMailer->expects($this->once())
            ->method('sendMail')
            ->with($this->callback(function ($val) use ($state) {
                $state->actualEmailSettings = $val;
                return true;
            }))
            ->willReturn(true);
        return $state;
    }
    private function invokeRequestNewAccountRequest($s) {
        $auth = new Authenticator($this->makePartiallyMockedServicesFactory(
            null, $s->mockMailer
        ));
        $auth->requestNewAccount($s->inputUsername, $s->inputEmail, $s->inputPassword,
            $s->inputRole, function ($_user, $activationKey, $settingsOut) {
                $settingsOut->fromAddress = 'mysite.com';
                $settingsOut->subject = 'mysite.com | Account activation';
                $settingsOut->body = "Please activate your account /activate/{$activationKey}";
            });
    }
    private function verifyInsertedUnactivatedUserToDb($s) {
        $row = $this->getTestUserFromDb(MockCrypto::mockGuidv4());
        $this->assertNotNull($row);
        $this->assertEquals($s->inputUsername, $row['username']);
        $this->assertEquals($s->inputEmail, $row['email']);
        $this->assertEquals(MockCrypto::mockHashPass($s->inputPassword),
                            $row['passwordHash']);
        $this->assertEquals($s->inputRole, (int) $row['role']);
        $this->assertEquals($s->mockActivationKey, $row['activationKey']);
        $this->assertEquals(true, $row['accountCreatedAt'] !== null);
        $this->assertEquals(true, $row['accountCreatedAt'] > time() - 20);
        $this->assertNull($row['resetKey']);
        $this->assertNull($row['resetRequestedAt']);
    }
    private function verifySentEmail($s) {
        $this->assertEquals(true, $s->actualEmailSettings !== null,
            'Pitäisi kutsua $mailer->sendMail()');
        $this->assertEquals($s->inputEmail, $s->actualEmailSettings->toAddress);
        $this->assertEquals($s->inputUsername, $s->actualEmailSettings->toName);
        $this->assertEquals('mysite.com', $s->actualEmailSettings->fromAddress);
        $this->assertEquals('mysite.com | Account activation', $s->actualEmailSettings->subject);
        $this->assertEquals("Please activate your account /activate/{$s->mockActivationKey}",
                            $s->actualEmailSettings->body);
    }
}
