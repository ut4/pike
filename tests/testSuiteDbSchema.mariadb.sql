CREATE TABLE users (
    `id` CHAR(36) NOT NULL,
    `username` VARCHAR(42) NOT NULL UNIQUE,
    `email` VARCHAR(191) NOT NULL UNIQUE, -- 191 * 4 = 767 bytes = max key length
    `passwordHash` VARCHAR(255) NOT NULL,
    `role` TINYINT(1) UNSIGNED NOT NULL DEFAULT 255,
    `activationKey` VARCHAR(512) DEFAULT NULL,
    `accountCreatedAt` INT(10) UNSIGNED DEFAULT 0,
    `resetKey` VARCHAR(512) DEFAULT NULL,
    `resetRequestedAt` INT(10) UNSIGNED DEFAULT 0,
    `accountStatus` TINYINT(1) UNSIGNED DEFAULT 1, -- 0=activated, 1=unactivated, 2=banned
    PRIMARY KEY (`id`)
) DEFAULT CHARSET = utf8mb4;
