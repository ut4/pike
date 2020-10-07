<?php declare(strict_types=1);

namespace Pike\Tests\Auth;

use Pike\Auth\Authenticator;
use Pike\TestUtils\MockCrypto;
use Pike\PikeException;

class AccountManagerRequestPassResetTest extends AuthenticatorTestCase {
    public function testRequestPasswordResetThrowsIfUserWasNotFound(): void {
        $am = $this->makeAuth()->getAccountManager();
        $this->expectException(PikeException::class);
        $this->expectExceptionCode(Authenticator::CREDENTIAL_WAS_INVALID);
        $am->requestPasswordReset('non-existing@email.com',
                                  function () {});
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testRequestPasswordResetThrowsIfAccountIsNotActivated(): void {
        $this->insertTestUserToDb(['accountStatus' => Authenticator::ACCOUNT_STATUS_UNACTIVATED]);
        $am = $this->makeAuth()->getAccountManager();
        $this->expectException(PikeException::class);
        $this->expectExceptionCode(Authenticator::ACCOUNT_STATUS_WAS_UNEXPECTED);
        $am->requestPasswordReset(self::TEST_USER['email'],
                                  function () {});
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testRequestPasswordResetThrowsIfAccountIsBanned(): void {
        $this->insertTestUserToDb(['accountStatus' => Authenticator::ACCOUNT_STATUS_BANNED]);
        $am = $this->makeAuth()->getAccountManager();
        $this->expectException(PikeException::class);
        $this->expectExceptionCode(Authenticator::ACCOUNT_STATUS_WAS_UNEXPECTED);
        $am->requestPasswordReset(self::TEST_USER['email'],
                                  function () {});
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testRequestPasswordResetThrowsIfMailConfigIsNotValid(): void {
        $this->insertTestUserToDb();
        $am = $this->makeAuth()->getAccountManager();
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
        $am->requestPasswordReset(
            self::TEST_USER['email'],
            function ($_user, $_resetKey, $mailConfig) {
                $mailConfig->toAddress = '';
                $mailConfig->fromName = ['not', 'a', 'string'];
                $mailConfig->toName = ['not', 'a', 'string'];
            }
        );
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testRequestPasswordResetWritesResetKeyToDbAndSendsItViaEmail(): void {
        $state = $this->setupTestRequestPasswordTest();
        $this->insertTestUserToDb();
        $state->originalData = $this->getTestUserFromDb(self::TEST_USER['id']);
        $this->invokeRequestPasswordResetFeature($state);
        $this->verifyInsertedResetKeyToDb($state);
        $this->verifyDidNotChangeOriginalDataFromDb($state);
        $this->verifySentEmail($state);
    }
    private function setupTestRequestPasswordTest(): \stdClass {
        $state = new \stdClass;
        $state->mockResetKey = MockCrypto::mockGenRandomToken();
        $state->actualEmailSettings = null;
        return $state;
    }
    private function invokeRequestPasswordResetFeature(\stdClass $s): void {
        $fn = $this->makeFnThatReturnsSpyingMailer($s);
        $this->makeAuth()->getAccountManager($fn)->requestPasswordReset(
            self::TEST_USER['email'],
            function ($_user, $resetKey, $settingsOut) {
                $settingsOut->fromAddress = 'mysite.com';
                $settingsOut->subject = 'mysite.com | Password reset';
                $settingsOut->body = "Please visit /change-password/{$resetKey}";
            }
        );
    }
    private function verifyInsertedResetKeyToDb(\stdClass $s): void {
        $data = $this->getTestUserFromDb();
        $this->assertNotNull($data);
        $this->assertEquals($s->mockResetKey, $data->resetKey);
        $this->assertEquals(true, $data->resetRequestedAt !== null);
        $this->assertEquals(true, $data->resetRequestedAt > time() - 20);
        $s->actualUserFromDb = $data;
    }
    private function verifyDidNotChangeOriginalDataFromDb(\stdClass $s): void {
        foreach (['resetKey', 'resetRequestedAt'] as $except) {
            unset($s->originalData->{$except});
            unset($s->actualUserFromDb->{$except});
        }
        $this->assertEquals($s->originalData, $s->actualUserFromDb);
    }
    private function verifySentEmail(\stdClass $s): void {
        $this->assertEquals(true, $s->actualEmailSettings !== null,
            'Pitäisi kutsua $mailer->sendMail()');
        $this->assertEquals(self::TEST_USER['email'], $s->actualEmailSettings->toAddress);
        $this->assertEquals(self::TEST_USER['username'], $s->actualEmailSettings->toName);
        $this->assertEquals('mysite.com', $s->actualEmailSettings->fromAddress);
        $this->assertEquals('mysite.com | Password reset', $s->actualEmailSettings->subject);
        $this->assertEquals("Please visit /change-password/{$s->mockResetKey}",
                            $s->actualEmailSettings->body);
    }
}
