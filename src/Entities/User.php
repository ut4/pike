<?php

declare(strict_types=1);

namespace Pike\Entities;

class User {
    /** @var string */
    public $id;
    /** @var string */
    public $username;
    /** @var string */
    public $email;
    /** @var ?int */
    public $accountStatus;
    /** @var ?string */
    public $displayName;
    /** @var ?int */
    public $role;
    /** @var ?int */
    public $accountCreatedAt;

    /** @var string */
    public $passwordHash;
    /** @var ?string */
    public $activationKey;
    /** @var ?string */
    public $resetKey;
    /** @var ?int */
    public $resetRequestedAt;
    /** @var ?string */
    public $loginId;
    /** @var ?string */
    public $loginIdValidatorHash;
    /** @var ?string */
    public $loginData;
}
