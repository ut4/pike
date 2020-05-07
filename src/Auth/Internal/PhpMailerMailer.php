<?php

declare(strict_types=1);

namespace Pike\Auth\Internal;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PhpMailerException;

class PhpMailerMailer {
    private $mailer;
    private $lastErr;
    /**
     * @param \PHPMailer\PHPMailer\PHPMailer $mailer = null
     */
    public function __construct(PHPMailer $mailer = null) {
        $this->mailer = $mailer ?? new PHPMailer(true);
    }
    /**
     * @param object $settings {fromAddress: string, fromName?: string, toAddress: string, toName?: string, subject: string, body: string, configureMailer?: fn(\Pike\Auth\Internal\PhpMailerMailer $mailer): void}, olettaa että validi
     * @return bool
     */
    public function sendMail(\stdClass $settings): bool {
        try {
            $this->mailer->CharSet = PHPMailer::CHARSET_UTF8;
            $this->mailer->setFrom($settings->fromAddress, $settings->fromName ?? '');
            $this->mailer->addAddress($settings->toAddress, $settings->toName ?? '');
            $this->mailer->Subject = $settings->subject;
            $this->mailer->Body = $settings->body;
            $userDefinedSetupFn = $settings->configureMailer ?? null;
            if (is_callable($userDefinedSetupFn))
                call_user_func($userDefinedSetupFn, $this->mailer);
            $this->mailer->send();
            return true;
        } catch (PhpMailerException $e) {
            $this->lastErr = $e;
            return false;
        }
    }
    /**
     * @return \PHPMailer\PHPMailer\Exception|null
     */
    public function getLastError(): ?PhpMailerException {
        return $this->lastErr;
    }
}
