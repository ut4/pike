<?php

declare(strict_types=1);

namespace Pike\Interfaces;

interface MailerInterface {
    /**
     * @param object $settings {fromAddress: string, fromName?: string, toAddress: string, toName?: string, subject: string, body: string, configureMailer?: fn(\Pike\Interfaces\MailerInterface $mailer): void}, olettaa että validi
     * @return bool
     */
    public function sendMail(object $settings): bool;
}
