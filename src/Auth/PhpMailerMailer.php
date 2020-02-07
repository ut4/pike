<?php

namespace Pike\Auth;

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
     * @param {fromAddress: string, fromName?: string, toAddress: string, toName?: string, subject: string, body: string} $settings Olettaa että validi
     * @return bool
     */
    public function sendMail(object $settings) {
        try {
            $this->mailer->isSMTP();
            $this->mailer->CharSet = PHPMailer::CHARSET_UTF8;
            $this->mailer->setFrom($settings->fromAddress, $settings->fromName ?? '');
            $this->mailer->addAddress($settings->toAddress, $settings->toName ?? '');
            $this->mailer->Subject = $settings->subject;
            $this->mailer->Body = $settings->body;
            $this->mailer->send();
            return true;
        } catch (PhpMailerException $e) {
            $this->lastErr = $e;
            return false;
        }
    }
    /**
     * @return \PHPMailer\PHPMailer\Exception
     */
    public function getLastError() {
        return $this->lastErr;
    }
}
