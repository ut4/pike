<?php

declare(strict_types=1);

namespace Pike\Auth\Internal;

abstract class AbstractMailer {
    /**
     * @param object $settings {fromAddress: string, fromName?: string, toAddress: string, toName?: string, subject: string, body: string, configureMailer?: fn(\Pike\Auth\Internal\AbstractMailer $mailer): void}, olettaa että validi
     * @return bool
     */
    public abstract function sendMail(\stdClass $settings): bool;
}
