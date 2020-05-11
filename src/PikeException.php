<?php

declare(strict_types=1);

namespace Pike;

class PikeException extends \RuntimeException {
    public const FAILED_DB_OP      = 101010;
    public const FAILED_FS_OP      = 101011;
    public const BAD_INPUT         = 101012;
    public const INEFFECTUAL_DB_OP = 101013;
    public const ERROR_EXCEPTION   = 101014;
}
