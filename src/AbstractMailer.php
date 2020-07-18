<?php

declare(strict_types=1);

namespace Pike;

abstract class AbstractMailer {
    /**
     * @param object $settings {fromAddress: string, fromName?: string, toAddress: string, toName?: string, subject: string, body: string, configureMailer?: fn(\Pike\AbstractMailer $mailer): void}, olettaa että validi
     * @return bool
     */
    public abstract function sendMail(object $settings): bool;
}
