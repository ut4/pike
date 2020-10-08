<?php declare(strict_types=1);

namespace Pike\Tests\Auth;

use Pike\Auth\{ACL, Authenticator};
use Pike\TestUtils\MockCrypto;
use Pike\PikeException;

class AccountManagerRequestNewAccountTest extends AuthenticatorTestCase {
    public function testRequestNewAccountThrowsIfUserAlreadyExists(): void {
        $this->insertTestUserToDb();
        $auth = $this->makeAuth();
        $this->expectException(PikeException::class);
        $this->expectExceptionCode(Authenticator::USER_ALREADY_EXISTED);
        $auth->getAccountManager()->requestNewAccount(
            self::TEST_USER['username'],
            'ir@rele.vant',
            'pass',
            function () {},
            ACL::ROLE_AUTHOR);
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testRequestNewAccountThrowsIfMailConfigIsNotValid(): void {
        $this->insertTestUserToDb();
        $auth = $this->makeAuth();
        $this->expectException(PikeException::class);
        $this->expectExceptionCode(Authenticator::FAILED_TO_FORMAT_MAIL);
        $this->expectExceptionMessage(implode(', ', [
            'The length of fromAddress must be at least 3',
            'The length of toAddress must be at least 3',
            'The length of subject must be at least 1',
            'The length of body must be at least 1',
            'fromName must be string',
            'toName must be string',
        ]));
        $auth->getAccountManager()->requestNewAccount(
            '',
            '',
            '',
            function ($_user, $_activationKey, $mailConfig) {
                $mailConfig->fromName = ['not', 'a', 'string'];
                $mailConfig->toName = ['not', 'a', 'string'];
            },
            ACL::ROLE_AUTHOR);
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testRequestNewAccountInsertsUserToDbAndSendsActivationKeyViaEmail(): void {
        $state = $this->setupTestRequestNewAccountTest();
        $this->invokeRequestNewAccountRequest($state);
        $this->verifyInsertedUnactivatedUserToDb($state);
        $this->verifySentEmail($state);
    }
    private function setupTestRequestNewAccountTest(): \stdClass {
        $state = new \stdClass;
        $state->inputUsername = 'new_user_username';
        $state->inputEmail = 'new@user.mail';
        $state->inputPassword = 'new user pass';
        $state->inputRole = ACL::ROLE_EDITOR;
        $state->mockActivationKey = MockCrypto::mockGenRandomToken(32);
        $state->actualEmailSettings = null;
        return $state;
    }
    private function invokeRequestNewAccountRequest(\stdClass $s): void {
        $auth = $this->makeAuth();
        $fn = $this->makeFnThatReturnsSpyingMailer($s);
        $auth->getAccountManager($fn)->requestNewAccount(
            $s->inputUsername,
            $s->inputEmail,
            $s->inputPassword,
            function ($_user, $activationKey, $settingsOut) {
                $settingsOut->fromAddress = 'mysite.com';
                $settingsOut->subject = 'mysite.com | Account activation';
                $settingsOut->body = "Please activate your account /activate/{$activationKey}";
            },
            $s->inputRole);
    }
    private function verifyInsertedUnactivatedUserToDb(\stdClass $s): void {
        $data = $this->getTestUserFromDb(MockCrypto::mockGuidv4());
        $this->assertNotNull($data);
        $this->assertEquals($s->inputUsername, $data->username);
        $this->assertEquals($s->inputEmail, $data->email);
        $this->assertEquals(Authenticator::ACCOUNT_STATUS_UNACTIVATED,
                            $data->accountStatus);
        $this->assertEquals($s->inputRole, (int) $data->role);
        $this->assertEquals(true, $data->accountCreatedAt !== null);
        $this->assertEquals(true, $data->accountCreatedAt > time() - 20);
        $this->assertEquals(MockCrypto::mockHashPass($s->inputPassword),
                            $data->passwordHash);
        $this->assertEquals($s->mockActivationKey, $data->activationKey);
        $this->assertNull($data->resetKey);
        $this->assertEquals('0', $data->resetRequestedAt);
        $this->assertNull($data->loginId);
        $this->assertNull($data->loginIdValidatorHash);
        $this->assertNull($data->loginData);
    }
    private function verifySentEmail(\stdClass $s): void {
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
