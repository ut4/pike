<?php

declare(strict_types=1);

namespace Pike;

use PHPMailer\PHPMailer\PHPMailer;
use Pike\Interfaces\MailerInterface;

class PhpMailerMailer implements MailerInterface {
    /** @var \PHPMailer\PHPMailer\PHPMailer */
    private $mailer;
    /**
     * @param \PHPMailer\PHPMailer\PHPMailer $mailer = null
     */
    public function __construct(PHPMailer $mailer = null) {
        $this->mailer = $mailer ?? new PHPMailer(true);
    }
    /**
     * @param object $settings {fromAddress: string, fromName?: string, toAddress: string, toName?: string, subject: string, body: string, configureMailer?: fn(\Pike\PhpMailerMailer $mailer): void}, olettaa että validi
     * @return bool
     */
    public function sendMail(object $settings): bool {
        $this->mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $this->mailer->setFrom($settings->fromAddress, $settings->fromName ?? '');
        $this->mailer->addAddress($settings->toAddress, $settings->toName ?? '');
        $this->mailer->Subject = $settings->subject;
        $this->mailer->Body = $settings->body;
        $userDefinedSetupFn = $settings->configureMailer ?? null;
        if (is_callable($userDefinedSetupFn))
            call_user_func($userDefinedSetupFn, $this->mailer);
        // @allow \PHPMailer\PHPMailer\Exception
        $this->mailer->send();
        return true;
    }
}
