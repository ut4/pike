<?php

namespace Pike\TestUtils;

use Pike\Response;

/** @deprecated */
class MutedResponse extends Response {
    public function send(): void {
        // Muted.
    }
}
