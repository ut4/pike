<?php

namespace Pike\Tests\Auth;

use Pike\Auth\ACL;
use Pike\Auth\Authenticator;
use Pike\TestUtils\MockCrypto;
use Pike\Auth\Internal\AbstractMailer;
use Pike\PikeException;

class AuthenticatorCreateAccountTest extends AuthenticatorTestCase {
    public function testRequestNewAccountThrowsIfUserAlreadyExists() {
        $this->insertTestUserToDb();
        $auth = new Authenticator($this->makePartiallyMockedServicesFactory());
        try {
            $auth->requestNewAccount(self::TEST_USER_NAME,
                                     'ir@rele.vant',
                                     'pass',
                                     ACL::ROLE_AUTHOR,
                                     function () {});
            $this->assertFalse(true, 'Pitäisi heittää poikkeus');
        } catch (PikeException $e) {
            $this->assertEquals(Authenticator::USER_ALREADY_EXISTS,
                                $e->getCode());
        }
    }


    ////////////////////////////////////////////////////////////////////////////


    public function testRequestNewAccountThrowsIfMailConfigIsNotValid() {
        $this->insertTestUserToDb();
        $auth = new Authenticator($this->makePartiallyMockedServicesFactory());
        try {
            $auth->requestNewAccount('', '', '', ACL::ROLE_AUTHOR,
                                     function ($_user, $_activationKey, $mailConfig) {
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
        $this->assertEquals(Authenticator::ACCOUNT_STATUS_UNACTIVATED,
                            $row['accountStatus']);
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


    ////////////////////////////////////////////////////////////////////////////


    public function testActivateAccountValidatesActivationKeyAndUpdatesAccountStatusToDb() {
        $state = $this->setupActivateAccountTest();
        $this->insertTestUserToDb(Authenticator::ACCOUNT_STATUS_UNACTIVATED);
        $this->insertTestActivationInfoToDb($state);
        $this->invokeActivateAccountFeature($state);
        $this->verifyWroteAccountStatusToDb($state);
        $this->verifyClearedActivationInfoFromToDb($state);
    }
    private function setupActivateAccountTest() {
        $state = new \stdClass;
        $state->actualUserFromDb = null;
        $state->testActivationKey = str_repeat('-', 32);
        $state->testAccountCreatedAt = time() - 10;
        return $state;
    }
    private function insertTestActivationInfoToDb($s) {
        if (!self::$db->exec('UPDATE users SET `activationKey`=?' .
                             ',`accountCreatedAt`=?,`accountStatus`=?' .
                             ' WHERE `id` = ?',
                             [$s->testActivationKey, $s->testAccountCreatedAt,
                              Authenticator::ACCOUNT_STATUS_UNACTIVATED,
                              self::TEST_USER_ID]))
            throw new \Exception('Failed to insert test data');
    }
    private function invokeActivateAccountFeature($s) {
        $auth = new Authenticator($this->makePartiallyMockedServicesFactory());
        $auth->activateAccount($s->testActivationKey);
    }
    private function verifyWroteAccountStatusToDb($s) {
        $row = $this->getTestUserFromDb(self::TEST_USER_ID);
        $this->assertNotNull($row);
        $this->assertEquals(Authenticator::ACCOUNT_STATUS_ACTIVATED,
                            $row['accountStatus']);
        // verifyDidNotChangeExistingData
        $this->assertEquals(self::TEST_USER_NAME, $row['username']);
        $this->assertEquals(self::TEST_USER_EMAIL, $row['email']);
        $this->assertEquals(MockCrypto::mockHashPass(self::TEST_USER_PASS),
                            $row['passwordHash']);
        $this->assertEquals(self::TEST_USER_ROLE, $row['role']);
        $this->assertNull($row['activationKey']);
        $this->assertEquals($s->testAccountCreatedAt, $row['accountCreatedAt']);
        $this->assertNull($row['resetKey']);
        $this->assertNull($row['resetRequestedAt']);
        $s->actualUserFromDb = $row;
    }
    private function verifyClearedActivationInfoFromToDb($s) {
        $row = $s->actualUserFromDb;
        $this->assertEquals(null, $row['activationKey']);
    }
}
