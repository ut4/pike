CREATE TABLE users (
    `id` CHAR(36) NOT NULL,
    `username` VARCHAR(42) NOT NULL UNIQUE,
    `email` VARCHAR(191) NOT NULL UNIQUE, -- 191 * 4 = 767 bytes = max key length
    `accountStatus` TINYINT(1) UNSIGNED DEFAULT 1, -- 0=activated, 1=unactivated, 2=banned
    `displayName` VARCHAR(64) DEFAULT NULL,
    `role` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 8388608, -- 1 << 23
    `accountCreatedAt` INT(10) UNSIGNED DEFAULT 0,
    --
    `passwordHash` VARCHAR(255) NOT NULL,
    `activationKey` VARCHAR(512) DEFAULT NULL,
    `resetKey` VARCHAR(512) DEFAULT NULL,
    `resetRequestedAt` INT(10) UNSIGNED DEFAULT 0,
    `loginId` CHAR(32) DEFAULT NULL,
    `loginIdValidatorHash` CHAR(64) DEFAULT NULL,
    `loginData` TEXT,
    PRIMARY KEY (`id`)
) DEFAULT CHARSET = utf8mb4;
