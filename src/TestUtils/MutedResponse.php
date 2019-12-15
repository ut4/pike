<?php

namespace Pike\TestUtils;

use Pike\Response;

class MutedResponse extends Response {
    protected function send($body = '') {}
}
