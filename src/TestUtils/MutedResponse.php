<?php

namespace Pike\TestUtils;

use Pike\Response;

class MutedResponse extends Response {
    public function send() {
        // Muted.
    }
}
